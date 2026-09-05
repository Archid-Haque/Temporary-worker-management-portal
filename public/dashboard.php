<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/site.php';

$user = require_login();
$pdo = db();

$site = site_settings();

$siteName = trim($site['site_name'] ?? 'WorkerLedger');
$siteLogo = trim($site['site_logo'] ?? '');

if ($siteName === '') {
    $siteName = 'WorkerLedger';
}


/* =====================================================
   ACTIONS
   ===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf($_POST['csrf'] ?? '');

    $action = $_POST['action'] ?? '';


    /* =================================================
       DELETE RECORD
       ================================================= */

    if (
        $action === 'delete_record' &&
        in_array(
            $user['role'],
            ['admin','employer','supervisor'],
            true
        )
    ) {

        $deleteId = (int)($_POST['record_id'] ?? 0);

        $s = $pdo->prepare("
            SELECT
                id,
                task,
                employer_id,
                record_status
            FROM work_records
            WHERE id=?
            LIMIT 1
        ");

        $s->execute([$deleteId]);

        $target = $s->fetch();

        $canDelete =
            $target &&
            $target['record_status'] === 'pending' &&
            (
                $user['role'] === 'admin' ||
                $user['role'] === 'supervisor' ||
                (int)$target['employer_id'] === (int)$user['id']
            );


        if ($canDelete) {

            audit(
                (int)$user['id'],
                'record_deleted',
                'work_record',
                $deleteId,
                [
                    'task' => $target['task']
                ]
            );


            $pdo->prepare("
                DELETE FROM work_records
                WHERE id=?
                AND record_status='pending'
            ")->execute([$deleteId]);
        }


        header('Location: dashboard.php?deleted=1');
        exit;
    }


    /* =================================================
       CREATE RECORD
       ================================================= */

    if (
        $action === 'create_record' &&
        in_array(
            $user['role'],
            ['admin','employer','supervisor'],
            true
        )
    ) {

        $workerId =
            (int)$_POST['worker_id'];

        $employerId =
            $user['role'] === 'employer'
            ? (int)$user['id']
            : (int)$_POST['employer_id'];

        $task =
            trim($_POST['task']);

        $date =
            $_POST['work_date'];

        $rateType =
            $_POST['rate_type'] === 'per_task'
            ? 'per_task'
            : 'daily';

        $rate =
            max(
                0,
                (float)$_POST['rate']
            );

        $units =
            max(
                0,
                (float)$_POST['units']
            );

        $extra =
            max(
                0,
                (float)$_POST['extra_work']
            );

        $advance =
            max(
                0,
                (float)$_POST['advance_amount']
            );

        $paid =
            max(
                0,
                (float)$_POST['paid_amount']
            );


        $stmt = $pdo->prepare("
            INSERT INTO work_records
            (
                worker_id,
                employer_id,
                supervisor_id,
                task,
                work_date,
                rate_type,
                rate,
                units,
                extra_work,
                advance_amount,
                paid_amount,
                created_by
            )
            VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?)
        ");


        $stmt->execute([
            $workerId,
            $employerId,
            null,
            $task,
            $date,
            $rateType,
            $rate,
            $units,
            $extra,
            $advance,
            $paid,
            $user['id']
        ]);


        $id =
            (int)$pdo->lastInsertId();


        audit(
            (int)$user['id'],
            'record_created',
            'work_record',
            $id,
            [
                'task' => $task
            ]
        );


        header(
            'Location: dashboard.php?created=1'
        );

        exit;
    }
}


/* =====================================================
   LOAD RECORDS
   ===================================================== */

if ($user['role'] === 'worker') {

    $stmt = $pdo->prepare("
        SELECT
            r.*,
            w.full_name worker_name,
            e.full_name employer_name
        FROM work_records r
        JOIN users w
            ON w.id=r.worker_id
        JOIN users e
            ON e.id=r.employer_id
        WHERE r.worker_id=?
        ORDER BY
            r.work_date DESC,
            r.id DESC
    ");

    $stmt->execute([
        $user['id']
    ]);

} elseif ($user['role'] === 'employer') {

    $stmt = $pdo->prepare("
        SELECT
            r.*,
            w.full_name worker_name,
            e.full_name employer_name
        FROM work_records r
        JOIN users w
            ON w.id=r.worker_id
        JOIN users e
            ON e.id=r.employer_id
        WHERE r.employer_id=?
        ORDER BY
            r.work_date DESC,
            r.id DESC
    ");

    $stmt->execute([
        $user['id']
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            r.*,
            w.full_name worker_name,
            e.full_name employer_name
        FROM work_records r
        JOIN users w
            ON w.id=r.worker_id
        JOIN users e
            ON e.id=r.employer_id
        ORDER BY
            r.work_date DESC,
            r.id DESC
    ");
}


$records =
    $stmt->fetchAll();


/* =====================================================
   WORKERS / EMPLOYERS
   ===================================================== */

$workers = $pdo->query("
    SELECT
        id,
        full_name
    FROM users
    WHERE role='worker'
    AND status='active'
    ORDER BY full_name
")->fetchAll();


$employers = $pdo->query("
    SELECT
        id,
        full_name
    FROM users
    WHERE role='employer'
    AND status='active'
    ORDER BY full_name
")->fetchAll();


/* =====================================================
   DASHBOARD CALCULATIONS
   ===================================================== */

$earned = 0.0;
$pending = 0.0;

$disputes = 0;
$confirmed = 0;


foreach ($records as $r) {

    $e =
        (float)$r['rate'] *
        (float)$r['units'];


    $p =
        max(
            0,
            $e +
            (float)$r['extra_work'] -
            (float)$r['advance_amount'] -
            (float)$r['paid_amount']
        );


    $earned += $e;
    $pending += $p;


    if (
        $r['record_status'] === 'disputed' ||
        $r['attendance_status'] === 'disputed'
    ) {
        $disputes++;
    }


    if (
        $r['record_status'] === 'confirmed'
    ) {
        $confirmed++;
    }
}

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
    <?= htmlspecialchars($siteName) ?> — Dashboard
</title>


<link
    rel="stylesheet"
    href="assets/style.css?v=2.0"
>


<style>

/* =====================================================
   GLOBAL BRANDING
   ===================================================== */

/*
   When a logo exists:
   show ONLY the logo.
   Keep the original wide proportions.
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
   When no logo exists:
   show ONLY the site name.
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
   MOBILE LOGO
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

}

</style>

</head>


<body>


<!-- =====================================================
     TOP NAVIGATION
     ===================================================== -->

<header class="topbar">


    <a
        class="brand"
        href="dashboard.php"
        aria-label="<?= htmlspecialchars($siteName) ?> dashboard"
    >


        <?php if ($siteLogo !== ''): ?>

            <!-- =========================================
                 LOGO EXISTS
                 SHOW ONLY LOGO
                 ========================================= -->

            <img
                class="site-logo-image"
                src="<?= htmlspecialchars($siteLogo) ?>"
                alt="<?= htmlspecialchars($siteName) ?>"
            >


        <?php else: ?>

            <!-- =========================================
                 NO LOGO
                 SHOW ONLY SITE NAME
                 ========================================= -->

            <strong class="brand-name">

                <?= htmlspecialchars($siteName) ?>

            </strong>

        <?php endif; ?>


    </a>


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
            class="nav-link active"
            href="dashboard.php"
        >
            Dashboard
        </a>


        <?php if ($user['role'] === 'admin'): ?>

            <a
                class="nav-link"
                href="admin.php"
            >
                Settings
            </a>

        <?php endif; ?>


        <span class="role-pill">

            <?= htmlspecialchars(
                ucfirst($user['role'])
            ) ?>

        </span>


        <a
            class="nav-link signout"
            href="logout.php"
        >
            Sign out
        </a>


    </nav>


</header>


<!-- =====================================================
     MAIN CONTENT
     ===================================================== -->

<main class="container">


    <?php if (isset($_GET['deleted'])): ?>

        <div class="notice">

            Record deleted successfully.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['created'])): ?>

        <div class="notice">

            Work record created successfully.

        </div>

    <?php endif; ?>


    <!-- =================================================
         HERO
         ================================================= -->

    <section class="hero">


        <div>


            <p class="eyebrow">
                WEB08 • COMPANY PORTAL
            </p>


            <h1>

                <?=
                    $user['role'] === 'worker'
                    ? 'My work & payment records'
                    : 'Work, attendance & payment records'
                ?>

            </h1>


            <p class="muted">

                Signed in as

                <?= htmlspecialchars(
                    $user['full_name']
                ) ?>.

            </p>


        </div>


        <?php if (
            in_array(
                $user['role'],
                ['admin','employer','supervisor'],
                true
            )
        ): ?>

            <button
                class="primary"
                onclick="
                    document
                        .getElementById('recordModal')
                        .classList
                        .remove('hidden')
                "
            >
                + New work record
            </button>

        <?php endif; ?>


    </section>


    <!-- =================================================
         STATS
         ================================================= -->

    <section class="stats">


        <article>

            <span>
                Records
            </span>

            <b>
                <?= count($records) ?>
            </b>

        </article>


        <article>

            <span>
                Pending
            </span>

            <b>
                ₹<?= number_format($pending,0) ?>
            </b>

        </article>


        <article>

            <span>
                Disputes
            </span>

            <b>
                <?= $disputes ?>
            </b>

        </article>


        <article>

            <span>
                Confirmed
            </span>

            <b>
                <?= $confirmed ?>
            </b>

        </article>


    </section>


    <!-- =================================================
         RECORDS
         ================================================= -->

    <section class="panel">


        <div class="panel-head">


            <div>

                <h2>
                    Records
                </h2>


                <p class="muted">

                    Server-stored records with
                    attendance and payment history.

                </p>

            </div>


            <input
                id="search"
                class="search"
                placeholder="Search..."
            >


        </div>


        <div id="recordList">


            <?php foreach ($records as $r): ?>


                <?php

                $p = max(
                    0,
                    (float)$r['rate'] *
                    (float)$r['units'] +
                    (float)$r['extra_work'] -
                    (float)$r['advance_amount'] -
                    (float)$r['paid_amount']
                );

                ?>


                <article
                    class="record"
                    data-search="<?= htmlspecialchars(
                        strtolower(
                            $r['task'] .
                            ' ' .
                            $r['worker_name'] .
                            ' ' .
                            $r['employer_name']
                        )
                    ) ?>"
                >


                    <div>

                        <strong>

                            <?= htmlspecialchars(
                                $r['task']
                            ) ?>

                        </strong>


                        <div class="record-meta">

                            <?= htmlspecialchars(
                                $r['worker_name']
                            ) ?>

                            •

                            <?= htmlspecialchars(
                                $r['employer_name']
                            ) ?>

                            •

                            <?= htmlspecialchars(
                                $r['work_date']
                            ) ?>

                        </div>

                    </div>


                    <div>

                        <span class="badge">

                            <?= htmlspecialchars(
                                ucfirst(
                                    $r['record_status']
                                )
                            ) ?>

                        </span>


                        <div class="record-meta">

                            <?= htmlspecialchars(
                                ucfirst(
                                    $r['attendance_status']
                                )
                            ) ?>

                            attendance

                        </div>

                    </div>


                    <div class="money">

                        ₹<?= number_format(
                            $p,
                            0
                        ) ?>


                        <small>
                            pending
                        </small>

                    </div>


                    <div class="record-actions">


                        <a
                            class="secondary smallbtn"
                            href="record.php?id=<?= (int)$r['id'] ?>"
                        >
                            View
                        </a>


                        <?php if (
                            in_array(
                                $user['role'],
                                [
                                    'admin',
                                    'supervisor',
                                    'employer'
                                ],
                                true
                            ) &&
                            $r['record_status'] === 'pending'
                        ): ?>


                            <a
                                class="secondary smallbtn"
                                href="record.php?id=<?= (int)$r['id'] ?>#edit-record"
                            >
                                Edit
                            </a>


                            <form
                                method="post"
                                onsubmit="
                                    return confirm(
                                        'Delete this pending record? This cannot be undone.'
                                    );
                                "
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
                                    value="delete_record"
                                >


                                <input
                                    type="hidden"
                                    name="record_id"
                                    value="<?= (int)$r['id'] ?>"
                                >


                                <button
                                    class="secondary smallbtn danger"
                                    type="submit"
                                >
                                    Delete
                                </button>


                            </form>


                        <?php endif; ?>


                    </div>


                </article>


            <?php endforeach; ?>


            <?php if (!$records): ?>


                <div class="empty">

                    <h3>
                        No records yet
                    </h3>


                    <p class="muted">

                        Create the first work assignment.

                    </p>

                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer class="site-footer">


    <div>


        <strong>

            <?= htmlspecialchars(
                $siteName
            ) ?>

        </strong>


        <span>

            Simple, transparent work & payment
            records.

        </span>


    </div>


    <div>


        <span>

            Record-keeping tool • ©
            <?= date('Y') ?>
            <?= htmlspecialchars(
                $siteName
            ) ?>

        </span>


        <span>

            Not a payment service or legal authority.

        </span>


    </div>


</footer>


<!-- =====================================================
     NEW RECORD MODAL
     ===================================================== -->

<?php if (
    in_array(
        $user['role'],
        ['admin','employer','supervisor'],
        true
    )
): ?>


<div
    class="modal hidden"
    id="recordModal"
>


    <div class="modal-card">


        <div class="modal-head">


            <h2>
                New work record
            </h2>


            <button
                type="button"
                onclick="
                    document
                        .getElementById('recordModal')
                        .classList
                        .add('hidden')
                "
            >
                ×
            </button>


        </div>


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
                value="create_record"
            >


            <div class="form-grid">


                <!-- WORKER -->

                <label>

                    Worker


                    <select
                        name="worker_id"
                        required
                    >


                        <?php foreach (
                            $workers as $w
                        ): ?>

                            <option
                                value="<?= $w['id'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $w['full_name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </label>


                <!-- EMPLOYER -->

                <?php if (
                    $user['role'] !== 'employer'
                ): ?>


                    <label>

                        Employer


                        <select
                            name="employer_id"
                            required
                        >


                            <?php foreach (
                                $employers as $e
                            ): ?>

                                <option
                                    value="<?= $e['id'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $e['full_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </label>


                <?php endif; ?>


                <!-- TASK -->

                <label>

                    Task


                    <input
                        name="task"
                        required
                    >

                </label>


                <!-- DATE -->

                <label>

                    Date


                    <input
                        name="work_date"
                        type="date"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </label>


                <!-- RATE TYPE -->

                <label>

                    Rate type


                    <select
                        name="rate_type"
                    >

                        <option value="daily">
                            Daily
                        </option>

                        <option value="per_task">
                            Per task
                        </option>

                    </select>

                </label>


                <!-- RATE -->

                <label>

                    Rate (₹)


                    <input
                        name="rate"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    >

                </label>


                <!-- UNITS -->

                <label>

                    Units / days


                    <input
                        name="units"
                        type="number"
                        min="0"
                        step="0.5"
                        value="1"
                        required
                    >

                </label>


                <!-- EXTRA -->

                <label>

                    Extra work (₹)


                    <input
                        name="extra_work"
                        type="number"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                </label>


                <!-- ADVANCE -->

                <label>

                    Advance (₹)


                    <input
                        name="advance_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                </label>


                <!-- PAID -->

                <label>

                    Paid (₹)


                    <input
                        name="paid_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                </label>


            </div>


            <div class="modal-actions">


                <button
                    class="primary"
                    type="submit"
                >
                    Save record
                </button>


            </div>


        </form>


    </div>


</div>


<?php endif; ?>


<!-- =====================================================
     SEARCH
     ===================================================== -->

<script>

document
    .getElementById('search')
    ?.addEventListener(
        'input',
        function(){

            const q =
                this.value.toLowerCase();


            document
                .querySelectorAll(
                    '#recordList .record'
                )
                .forEach(
                    function(x){

                        x.style.display =
                            x.dataset.search.includes(q)
                            ? 'grid'
                            : 'none';

                    }
                );

        }
    );

</script>


</body>

</html>