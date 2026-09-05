<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/site.php';

$user = require_role(['admin']);
$pdo  = db();

$site = site_settings();


/* =====================================================
   ACTIONS
   ===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf($_POST['csrf'] ?? '');

    $action = $_POST['action'] ?? '';


    /* =================================================
       CREATE USER
       ================================================= */

    if ($action === 'create_user') {

        $name  = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');

        $role = in_array(
            $_POST['role'] ?? '',
            ['admin', 'employer', 'supervisor', 'worker'],
            true
        )
            ? $_POST['role']
            : 'worker';

        $pass = $_POST['password'] ?? '';

        if (strlen($pass) < 10) {
            exit('Password must be at least 10 characters.');
        }

        $s = $pdo->prepare("
            INSERT INTO users
            (full_name, email, phone, password_hash, role)
            VALUES (?, ?, ?, ?, ?)
        ");

        $s->execute([
            $name,
            $email,
            $phone,
            password_hash($pass, PASSWORD_DEFAULT),
            $role
        ]);

        $id = (int)$pdo->lastInsertId();

        audit(
            (int)$user['id'],
            'user_created',
            'user',
            $id,
            ['role' => $role]
        );

        header('Location: admin.php');
        exit;
    }


    /* =================================================
       TOGGLE USER
       ================================================= */

    if ($action === 'toggle_user') {

        $id = (int)($_POST['user_id'] ?? 0);

        if ($id !== (int)$user['id']) {

            $s = $pdo->prepare("
                UPDATE users
                SET status = IF(status='active','inactive','active')
                WHERE id = ?
            ");

            $s->execute([$id]);

            audit(
                (int)$user['id'],
                'user_status_changed',
                'user',
                $id
            );
        }

        header('Location: admin.php');
        exit;
    }


    /* =================================================
       UPDATE SITE BRANDING
       ================================================= */

    if ($action === 'update_branding') {

        $siteName = trim($_POST['site_name'] ?? '');

        if ($siteName === '') {
            exit('Site name cannot be empty.');
        }

        if (mb_strlen($siteName) > 100) {
            exit('Site name is too long.');
        }


        $oldLogo = site_logo();
        $newLogo = $oldLogo;


        /* ---------------------------------------------
           REMOVE CURRENT LOGO
           --------------------------------------------- */

        if (
            isset($_POST['remove_logo']) &&
            $_POST['remove_logo'] === '1'
        ) {

            $newLogo = '';

            if (
                $oldLogo !== '' &&
                str_starts_with($oldLogo, 'assets/uploads/')
            ) {

                $oldFile = __DIR__ . '/' . $oldLogo;

                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }


        /* ---------------------------------------------
           UPLOAD NEW LOGO
           --------------------------------------------- */

        if (
            isset($_FILES['site_logo']) &&
            $_FILES['site_logo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            $file = $_FILES['site_logo'];


            if ($file['error'] !== UPLOAD_ERR_OK) {
                exit('Logo upload failed.');
            }


            /* Maximum 2 MB */

            if ($file['size'] > 2 * 1024 * 1024) {
                exit('Logo must be smaller than 2 MB.');
            }


            $tmp = $file['tmp_name'];


            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mime = $finfo->file($tmp);


            $allowed = [
                'image/png'  => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp'
            ];


            if (!isset($allowed[$mime])) {
                exit('Only PNG, JPG or WebP logos are allowed.');
            }


            if (@getimagesize($tmp) === false) {
                exit('Uploaded file is not a valid image.');
            }


            $uploadDir = __DIR__ . '/assets/uploads';


            if (!is_dir($uploadDir)) {

                if (!mkdir($uploadDir, 0755, true)) {
                    exit('Could not create upload folder.');
                }
            }


            $filename =
                'site-logo-' .
                bin2hex(random_bytes(12)) .
                '.' .
                $allowed[$mime];


            $destination = $uploadDir . '/' . $filename;


            if (!move_uploaded_file($tmp, $destination)) {
                exit('Could not save uploaded logo.');
            }


            $newLogo = 'assets/uploads/' . $filename;


            /* Delete previous logo */

            if (
                $oldLogo !== '' &&
                $oldLogo !== $newLogo &&
                str_starts_with($oldLogo, 'assets/uploads/')
            ) {

                $oldFile = __DIR__ . '/' . $oldLogo;

                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }


        /* ---------------------------------------------
           SAVE SITE NAME
           --------------------------------------------- */

        $s = $pdo->prepare("
            INSERT INTO site_settings
                (setting_key, setting_value)
            VALUES
                ('site_name', ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value)
        ");

        $s->execute([$siteName]);


        /* ---------------------------------------------
           SAVE LOGO
           --------------------------------------------- */

        $s = $pdo->prepare("
            INSERT INTO site_settings
                (setting_key, setting_value)
            VALUES
                ('site_logo', ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value)
        ");

        $s->execute([$newLogo]);


        /* ---------------------------------------------
           AUDIT
           --------------------------------------------- */

        audit(
            (int)$user['id'],
            'site_branding_updated',
            'site_settings',
            null,
            [
                'site_name' => $siteName,
                'logo_changed' => ($newLogo !== $oldLogo)
            ]
        );


        header('Location: admin.php');
        exit;
    }
}


/* =====================================================
   REFRESH BRANDING
   ===================================================== */

$site = site_settings();


/* =====================================================
   USERS
   ===================================================== */

$users = $pdo->query("
    SELECT
        id,
        full_name,
        email,
        phone,
        role,
        status,
        created_at
    FROM users
    ORDER BY created_at DESC
")->fetchAll();


/* =====================================================
   RECORDS
   ===================================================== */

$records = $pdo->query("
    SELECT
        r.id,
        r.task,
        r.work_date,
        r.record_status,
        r.attendance_status,
        w.full_name worker_name,
        e.full_name employer_name
    FROM work_records r
    JOIN users w ON w.id = r.worker_id
    JOIN users e ON e.id = r.employer_id
    ORDER BY r.created_at DESC
    LIMIT 100
")->fetchAll();


/* =====================================================
   AUDIT LOGS
   ===================================================== */

$logs = $pdo->query("
    SELECT
        a.*,
        u.full_name
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.actor_id
    ORDER BY a.id DESC
    LIMIT 100
")->fetchAll();


/* =====================================================
   SUMMARY COUNTS
   ===================================================== */

$totalUsers   = count($users);
$totalRecords = count($records);

$activeUsers      = 0;
$pendingRecords   = 0;
$disputedRecords  = 0;
$confirmedRecords = 0;


foreach ($users as $u) {

    if ($u['status'] === 'active') {
        $activeUsers++;
    }
}


foreach ($records as $r) {

    if ($r['record_status'] === 'pending') {
        $pendingRecords++;
    }

    if ($r['record_status'] === 'disputed') {
        $disputedRecords++;
    }

    if ($r['record_status'] === 'confirmed') {
        $confirmedRecords++;
    }
}


/* =====================================================
   AUDIT LABELS
   ===================================================== */

$auditLabels = [

    'record_created' =>
        'Record created',

    'record_edited' =>
        'Record updated',

    'attendance_marked' =>
        'Attendance marked',

    'attendance_confirmed' =>
        'Attendance confirmed',

    'attendance_disputed' =>
        'Attendance disputed',

    'record_confirmed_locked' =>
        'Record confirmed & locked',

    'record_deleted' =>
        'Record deleted',

    'user_created' =>
        'User created',

    'user_status_changed' =>
        'User status changed',

    'site_branding_updated' =>
        'Site branding updated'
];

?>

<!doctype html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title>
    <?= htmlspecialchars($site['site_name']) ?> — Admin
</title>


<link
    rel="stylesheet"
    href="assets/style.css?v=2.0"
>


<style>

/* =====================================================
   HEADER BRANDING
   ===================================================== */

/*
   Uploaded logo:
   - wide
   - no white box
   - no padding
   - no border
   - natural proportions
*/

.site-logo-image{
    display:block;
    width:310px;
    height:54px;
    max-width:42vw;
    object-fit:contain;
    object-position:left center;
    background:transparent;
    padding:0;
    border:0;
    border-radius:0;
}


/*
   If there is no logo,
   show only the site name.
*/

.brand-name{
    display:block;
    color:#fff;
    font-size:22px;
    line-height:1;
    font-weight:850;
    letter-spacing:-.4px;
}


/* =====================================================
   BRANDING ADMIN PREVIEW
   ===================================================== */

.branding-preview{
    display:flex;
    align-items:center;
    gap:16px;
    margin-bottom:20px;
}

.branding-logo-preview{
    width:220px;
    height:72px;
    object-fit:contain;
    object-position:left center;
    border-radius:16px;
    border:1px solid var(--line);
    background:#fff;
    padding:8px;
}

.branding-default-logo{
    min-width:72px;
    width:auto;
    height:72px;
    padding:0 18px;
    border-radius:16px;
    background:var(--teal);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
    font-size:20px;
}

.logo-help{
    color:var(--muted);
    font-size:13px;
    margin-top:6px;
}


/* =====================================================
   PRINT LOGO
   ===================================================== */

.print-logo-image{
    display:block;
    width:auto;
    max-width:180px;
    height:auto;
    max-height:45px;
    object-fit:contain;
    object-position:left center;
    background:transparent;
    padding:0;
    border:0;
    border-radius:0;
    margin:0 0 6px 0;
}


/* =====================================================
   MOBILE HEADER LOGO
   ===================================================== */

@media(max-width:650px){

    .site-logo-image{
        width:220px;
        height:42px;
        max-width:65vw;
    }

    .brand-name{
        font-size:19px;
    }

    .branding-logo-preview{
        width:180px;
        height:60px;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     PRINT-ONLY ADMIN SUMMARY
     ===================================================== -->

<div class="print-summary">

    <div class="print-header">

        <div>

            <?php if (!empty($site['site_logo'])): ?>

                <!-- LOGO EXISTS → ONLY LOGO -->

                <img
                    class="print-logo-image"
                    src="<?= htmlspecialchars($site['site_logo']) ?>"
                    alt="<?= htmlspecialchars($site['site_name']) ?>"
                >

            <?php else: ?>

                <!-- NO LOGO → SITE NAME -->

                <div class="print-brand">

                    <?= htmlspecialchars($site['site_name']) ?>

                </div>

            <?php endif; ?>


            <div class="print-subtitle">
                Administrator Control Report
            </div>

        </div>


        <span class="print-status">
            ADMIN REPORT
        </span>

    </div>


    <h1>
        Administration Summary
    </h1>


    <p class="print-muted">
        User management · Record control · Audit visibility
    </p>


    <div class="print-grid">

        <div>

            <span>
                TOTAL USERS
            </span>

            <b>
                <?= $totalUsers ?>
            </b>

        </div>


        <div>

            <span>
                ACTIVE USERS
            </span>

            <b>
                <?= $activeUsers ?>
            </b>

        </div>


        <div>

            <span>
                TOTAL RECORDS
            </span>

            <b>
                <?= $totalRecords ?>
            </b>

        </div>

    </div>


    <div class="print-payment">

        <h2>
            Record Status
        </h2>


        <div class="print-row">

            <span>
                Confirmed records
            </span>

            <b>
                <?= $confirmedRecords ?>
            </b>

        </div>


        <div class="print-row">

            <span>
                Pending records
            </span>

            <b>
                <?= $pendingRecords ?>
            </b>

        </div>


        <div class="print-row">

            <span>
                Disputed records
            </span>

            <b>
                <?= $disputedRecords ?>
            </b>

        </div>

    </div>


    <div class="print-history">

        <h2>
            Recent Records
        </h2>


        <?php foreach (array_slice($records, 0, 5) as $r): ?>

            <div class="print-history-row">

                <b>
                    <?= htmlspecialchars($r['task']) ?>
                </b>

                <span>

                    <?= htmlspecialchars(
                        $r['worker_name']
                    ) ?>

                    ·

                    <?= htmlspecialchars(
                        $r['work_date']
                    ) ?>

                    ·

                    <?= htmlspecialchars(
                        ucfirst($r['record_status'])
                    ) ?>

                </span>

            </div>

        <?php endforeach; ?>


        <?php if (!$records): ?>

            <div class="print-history-row">

                <span>
                    No records available.
                </span>

            </div>

        <?php endif; ?>

    </div>


    <div class="print-history">

        <h2>
            Recent Audit Activity
        </h2>


        <?php foreach (array_slice($logs, 0, 5) as $l): ?>

            <div class="print-history-row">

                <b>

                    <?= htmlspecialchars(
                        $auditLabels[$l['action']]
                        ??
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $l['action']
                            )
                        )
                    ) ?>

                </b>


                <span>

                    <?= htmlspecialchars(
                        $l['full_name'] ?? 'System'
                    ) ?>

                    ·

                    <?= htmlspecialchars(
                        $l['created_at']
                    ) ?>

                </span>

            </div>

        <?php endforeach; ?>


        <?php if (!$logs): ?>

            <div class="print-history-row">

                <span>
                    No audit activity available.
                </span>

            </div>

        <?php endif; ?>

    </div>


    <div class="print-footer">

        <strong>
            <?= htmlspecialchars($site['site_name']) ?>
        </strong>

        <span>
            Administration · Shared history · Record control
        </span>

    </div>

</div>


<!-- =====================================================
     NORMAL ADMIN PAGE
     ===================================================== -->

<header class="topbar">


    <div class="brand">

        <?php if (!empty($site['site_logo'])): ?>

            <!-- =========================================
                 LOGO UPLOADED
                 SHOW ONLY LOGO
                 ========================================= -->

            <img
                class="site-logo-image"
                src="<?= htmlspecialchars($site['site_logo']) ?>"
                alt="<?= htmlspecialchars($site['site_name']) ?>"
            >

        <?php else: ?>

            <!-- =========================================
                 NO LOGO
                 SHOW ONLY SITE NAME
                 ========================================= -->

            <strong class="brand-name">

                <?= htmlspecialchars(
                    $site['site_name']
                ) ?>

            </strong>

        <?php endif; ?>

    </div>


    <button
        class="menu-toggle"
        type="button"
        aria-label="Open menu"
        aria-expanded="false"
        onclick="
            document
                .querySelector('.top-actions')
                .classList.toggle('open');

            this.setAttribute(
                'aria-expanded',
                document
                    .querySelector('.top-actions')
                    .classList
                    .contains('open')
            )
        "
    >
        ☰
    </button>


    <nav
        class="top-actions"
        aria-label="Main navigation"
    >

        <a
            class="nav-link"
            href="dashboard.php"
        >
            Dashboard
        </a>


        <a
            class="nav-link active"
            href="admin.php"
        >
            Settings
        </a>


        <span class="role-pill">
            Administrator
        </span>


        <a
            class="nav-link signout"
            href="logout.php"
        >
            Sign out
        </a>

    </nav>

</header>


<main class="container">


    <!-- =================================================
         HERO
         ================================================= -->

    <section class="hero">

        <div>

            <p class="eyebrow">
                ADMIN • CONTROL CENTER
            </p>


            <h1>
                Administration
            </h1>


            <p class="muted">
                Manage users, records and audit history.
            </p>

        </div>


        <button
            class="primary"
            onclick="printAdminSummary()"
        >
            Print summary
        </button>

    </section>


    <!-- =================================================
         SITE BRANDING
         ================================================= -->

    <section class="panel">

        <h2>
            Site branding
        </h2>


        <p class="muted">
            Change the name and logo shown across the portal.
        </p>


        <div class="branding-preview">

            <?php if (!empty($site['site_logo'])): ?>

                <img
                    class="branding-logo-preview"
                    src="<?= htmlspecialchars($site['site_logo']) ?>"
                    alt="Current site logo"
                >

            <?php else: ?>

                <div class="branding-default-logo">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $site['site_name'],
                                0,
                                2
                            )
                        )
                    ) ?>

                </div>

            <?php endif; ?>


            <div>

                <strong>
                    Current branding
                </strong>


                <div class="logo-help">
                    PNG, JPG or WebP · Maximum 2 MB
                </div>

            </div>

        </div>


        <form
            method="post"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="csrf"
                value="<?= htmlspecialchars(
                    csrf_token()
                ) ?>"
            >


            <input
                type="hidden"
                name="action"
                value="update_branding"
            >


            <div class="form-grid">


                <label>

                    Site name

                    <input
                        name="site_name"
                        value="<?= htmlspecialchars(
                            $site['site_name']
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </label>


                <label>

                    Upload new logo

                    <input
                        type="file"
                        name="site_logo"
                        accept="image/png,image/jpeg,image/webp"
                    >

                    <div class="logo-help">
                        Recommended: wide logo with transparent background.
                    </div>

                </label>

            </div>


            <?php if (!empty($site['site_logo'])): ?>

                <label
                    style="
                        display:flex;
                        align-items:center;
                        gap:8px;
                        margin:18px 0;
                    "
                >

                    <input
                        type="checkbox"
                        name="remove_logo"
                        value="1"
                    >

                    Remove current logo

                </label>

            <?php endif; ?>


            <button
                class="primary"
                type="submit"
            >
                Save branding
            </button>

        </form>

    </section>


    <!-- =================================================
         CREATE USER
         ================================================= -->

    <section class="panel">

        <h2>
            Create user
        </h2>


        <form method="post">

            <input
                type="hidden"
                name="csrf"
                value="<?= htmlspecialchars(
                    csrf_token()
                ) ?>"
            >


            <input
                type="hidden"
                name="action"
                value="create_user"
            >


            <div class="form-grid">

                <label>

                    Name

                    <input
                        name="full_name"
                        required
                    >

                </label>


                <label>

                    Email

                    <input
                        name="email"
                        type="email"
                        required
                    >

                </label>


                <label>

                    Phone

                    <input
                        name="phone"
                    >

                </label>


                <label>

                    Role

                    <select name="role">

                        <option value="worker">
                            Worker
                        </option>

                        <option value="employer">
                            Employer
                        </option>

                        <option value="supervisor">
                            Supervisor
                        </option>

                        <option value="admin">
                            Administrator
                        </option>

                    </select>

                </label>


                <label>

                    Temporary password

                    <input
                        name="password"
                        minlength="10"
                        required
                    >

                </label>

            </div>


            <button class="primary">
                Create user
            </button>

        </form>

    </section>


    <!-- =================================================
         USERS
         ================================================= -->

    <section class="panel">

        <h2>
            Users
        </h2>


        <div class="tablewrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            Name
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach ($users as $u): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $u['full_name']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $u['email']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $u['role']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $u['status']
                                ) ?>
                            </td>


                            <td>

                                <?php if (
                                    $u['id'] != $user['id']
                                ): ?>

                                    <form method="post">

                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= htmlspecialchars(
                                                csrf_token()
                                            ) ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="toggle_user"
                                        >


                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= $u['id'] ?>"
                                        >


                                        <button
                                            class="secondary smallbtn"
                                        >

                                            <?= $u['status'] === 'active'
                                                ? 'Deactivate'
                                                : 'Activate'
                                            ?>

                                        </button>

                                    </form>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </section>


    <!-- =================================================
         RECENT RECORDS
         ================================================= -->

    <section class="panel">

        <h2>
            Recent records
        </h2>


        <div class="tablewrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            Task
                        </th>

                        <th>
                            Worker
                        </th>

                        <th>
                            Employer
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Attendance
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach ($records as $r): ?>

                        <tr>

                            <td>

                                <a
                                    href="record.php?id=<?= $r['id'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $r['task']
                                    ) ?>

                                </a>

                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['worker_name']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['employer_name']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['work_date']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['attendance_status']
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['record_status']
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </section>


    <!-- =================================================
         AUDIT LOG
         ================================================= -->

    <section class="panel">

        <h2>
            Audit log
        </h2>


        <?php foreach ($logs as $l): ?>

            <p class="history">

                <b>

                    <?= htmlspecialchars(
                        $auditLabels[$l['action']]
                        ??
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $l['action']
                            )
                        )
                    ) ?>

                </b>


                —

                <?= htmlspecialchars(
                    $l['full_name'] ?? 'System'
                ) ?>


                —

                <?= htmlspecialchars(
                    $l['created_at']
                ) ?>


                —

                <?= htmlspecialchars(
                    $l['entity_type']
                ) ?>


                #

                <?= (int)$l['entity_id'] ?>

            </p>

        <?php endforeach; ?>

    </section>


</main>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer class="site-footer">

    <div>

        <strong>
            <?= htmlspecialchars(
                $site['site_name']
            ) ?>
        </strong>


        <span>
            Administration & record control.
        </span>

    </div>


    <div>

        <span>

            © <?= date('Y') ?>

            <?= htmlspecialchars(
                $site['site_name']
            ) ?>

        </span>


        <span>
            Not a payment service or legal authority.
        </span>

    </div>

</footer>


<script>

function printAdminSummary() {
    window.print();
}

</script>


</body>
</html>