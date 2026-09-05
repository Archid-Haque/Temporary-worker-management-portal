<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/site.php';

$user = require_login();
$pdo  = db();

$site = site_settings();

$siteName = trim($site['site_name'] ?? 'WorkerLedger');
$siteLogo = trim($site['site_logo'] ?? '');

if ($siteName === '') {
    $siteName = 'WorkerLedger';
}

$id = (int)($_GET['id'] ?? 0);


/* -------------------------------------------------------
   LOAD RECORD
------------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT 
        r.*,
        w.full_name worker_name,
        w.email worker_email,
        e.full_name employer_name,
        e.email employer_email
    FROM work_records r
    JOIN users w ON w.id = r.worker_id
    JOIN users e ON e.id = r.employer_id
    WHERE r.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$r = $stmt->fetch();

if (!$r) {
    http_response_code(404);
    exit('Record not found.');
}


/* -------------------------------------------------------
   ACCESS CONTROL
------------------------------------------------------- */

$allowed =
    $user['role'] === 'admin' ||
    $user['role'] === 'supervisor' ||
    in_array(
        $user['id'],
        [
            (int)$r['worker_id'],
            (int)$r['employer_id']
        ],
        true
    );

if (!$allowed) {
    http_response_code(403);
    exit('Access denied.');
}


$canManage =
    $user['role'] === 'admin' ||
    $user['role'] === 'supervisor' ||
    (
        $user['role'] === 'employer' &&
        (int)$user['id'] === (int)$r['employer_id']
    );


/* -------------------------------------------------------
   ACTIONS
------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf($_POST['csrf'] ?? '');

    $action = $_POST['action'] ?? '';


    /* ===================================================
       DELETE RECORD
    =================================================== */

    if (
        $action === 'delete_record' &&
        $canManage
    ) {

        if ($r['record_status'] !== 'pending') {

            exit(
                'Only pending records can be deleted. ' .
                'Confirmed or disputed records are preserved.'
            );
        }


        audit(
            (int)$user['id'],
            'record_deleted',
            'work_record',
            $id,
            [
                'task' => $r['task']
            ]
        );


        $pdo
            ->prepare("
                DELETE FROM work_records
                WHERE id = ?
                AND record_status = 'pending'
            ")
            ->execute([$id]);


        header(
            'Location: dashboard.php?deleted=1'
        );

        exit;
    }


    /* ===================================================
       EDIT RECORD
    =================================================== */

    if (
        $action === 'edit_record' &&
        $canManage
    ) {

        if ($r['record_status'] !== 'pending') {

            exit(
                'This record is locked. ' .
                'Only pending records can be edited.'
            );
        }


        $task =
            trim(
                $_POST['task'] ?? ''
            );

        $date =
            $_POST['work_date'] ?? '';


        $rateType =
            ($_POST['rate_type'] ?? 'daily') === 'per_task'
            ? 'per_task'
            : 'daily';


        $rate =
            max(
                0,
                (float)(
                    $_POST['rate'] ?? 0
                )
            );


        $units =
            max(
                0,
                (float)(
                    $_POST['units'] ?? 0
                )
            );


        $extra =
            max(
                0,
                (float)(
                    $_POST['extra_work'] ?? 0
                )
            );


        $advance =
            max(
                0,
                (float)(
                    $_POST['advance_amount'] ?? 0
                )
            );


        $paid =
            max(
                0,
                (float)(
                    $_POST['paid_amount'] ?? 0
                )
            );


        if (
            $task === '' ||
            $date === ''
        ) {

            exit(
                'Task and date are required.'
            );
        }


        $s = $pdo->prepare("
            UPDATE work_records
            SET
                task = ?,
                work_date = ?,
                rate_type = ?,
                rate = ?,
                units = ?,
                extra_work = ?,
                advance_amount = ?,
                paid_amount = ?
            WHERE id = ?
            AND record_status = 'pending'
        ");


        $s->execute([
            $task,
            $date,
            $rateType,
            $rate,
            $units,
            $extra,
            $advance,
            $paid,
            $id
        ]);


        audit(
            (int)$user['id'],
            'record_edited',
            'work_record',
            $id,
            [
                'task' => $task
            ]
        );


        header(
            'Location: record.php?id=' .
            $id .
            '&updated=1'
        );

        exit;
    }


    /* ===================================================
       CONFIRM RECORD WHEN ALREADY CONFIRMED
       =================================================== */

    if (
        $r['record_status'] === 'confirmed' &&
        in_array(
            $action,
            ['confirm_record'],
            true
        )
    ) {

        /* No-op */

    }


    /* ===================================================
       MARK ATTENDANCE
       =================================================== */

    elseif (
        $action === 'mark_attendance' &&
        in_array(
            $user['role'],
            [
                'admin',
                'employer',
                'supervisor'
            ],
            true
        ) &&
        $r['attendance_status'] === 'pending'
    ) {

        $pdo->beginTransaction();


        $pdo
            ->prepare("
                UPDATE work_records
                SET attendance_status = 'marked'
                WHERE id = ?
            ")
            ->execute([$id]);


        $pdo
            ->prepare("
                INSERT INTO attendance_events
                (
                    record_id,
                    actor_id,
                    action,
                    note
                )
                VALUES (?, ?, ?, ?)
            ")
            ->execute([
                $id,
                $user['id'],
                'marked',
                'Attendance marked by employer/supervisor'
            ]);


        audit(
            (int)$user['id'],
            'attendance_marked',
            'work_record',
            $id
        );


        $pdo->commit();
    }


    /* ===================================================
       WORKER CONFIRMS ATTENDANCE
       =================================================== */

    elseif (
        $action === 'confirm_attendance' &&
        $user['role'] === 'worker' &&
        $r['attendance_status'] === 'marked'
    ) {

        $pdo->beginTransaction();


        $pdo
            ->prepare("
                UPDATE work_records
                SET attendance_status = 'confirmed'
                WHERE id = ?
            ")
            ->execute([$id]);


        $pdo
            ->prepare("
                INSERT INTO attendance_events
                (
                    record_id,
                    actor_id,
                    action
                )
                VALUES (?, ?, ?)
            ")
            ->execute([
                $id,
                $user['id'],
                'confirmed'
            ]);


        audit(
            (int)$user['id'],
            'attendance_confirmed',
            'work_record',
            $id
        );


        $pdo->commit();
    }


    /* ===================================================
       WORKER DISPUTES ATTENDANCE
    =================================================== */

    elseif (
        $action === 'dispute_attendance' &&
        $user['role'] === 'worker' &&
        $r['attendance_status'] === 'marked'
    ) {

        $note =
            trim(
                $_POST['note'] ?? ''
            );


        $pdo->beginTransaction();


        $pdo
            ->prepare("
                UPDATE work_records
                SET
                    attendance_status = 'disputed',
                    record_status = 'disputed'
                WHERE id = ?
            ")
            ->execute([$id]);


        $pdo
            ->prepare("
                INSERT INTO attendance_events
                (
                    record_id,
                    actor_id,
                    action,
                    note
                )
                VALUES (?, ?, ?, ?)
            ")
            ->execute([
                $id,
                $user['id'],
                'disputed',
                $note
            ]);


        audit(
            (int)$user['id'],
            'attendance_disputed',
            'work_record',
            $id,
            [
                'note' => $note
            ]
        );


        $pdo->commit();
    }


    /* ===================================================
       CONFIRM & LOCK RECORD
    =================================================== */

    elseif (
        $action === 'confirm_record' &&
        in_array(
            $user['role'],
            [
                'admin',
                'employer',
                'supervisor'
            ],
            true
        ) &&
        $r['record_status'] === 'pending'
    ) {

        $pdo->beginTransaction();


        $pdo
            ->prepare("
                UPDATE work_records
                SET
                    record_status = 'confirmed',
                    confirmed_by = ?,
                    confirmed_at = NOW()
                WHERE id = ?
            ")
            ->execute([
                $user['id'],
                $id
            ]);


        audit(
            (int)$user['id'],
            'record_confirmed_locked',
            'work_record',
            $id
        );


        $pdo->commit();
    }


    header(
        "Location: record.php?id=$id"
    );

    exit;
}


/* -------------------------------------------------------
   CALCULATIONS
------------------------------------------------------- */

$earned =
    (float)$r['rate'] *
    (float)$r['units'];


$pending = max(
    0,
    $earned +
    (float)$r['extra_work'] -
    (float)$r['advance_amount'] -
    (float)$r['paid_amount']
);


/* -------------------------------------------------------
   ATTENDANCE HISTORY
------------------------------------------------------- */

$ae = $pdo->prepare("
    SELECT
        a.*,
        u.full_name
    FROM attendance_events a
    JOIN users u
        ON u.id = a.actor_id
    WHERE record_id = ?
    ORDER BY a.id DESC
");

$ae->execute([$id]);

$attendance =
    $ae->fetchAll();


/* -------------------------------------------------------
   PAYMENT EVENTS
------------------------------------------------------- */

$pe = $pdo->prepare("
    SELECT
        p.*,
        u.full_name
    FROM payment_events p
    JOIN users u
        ON u.id = p.actor_id
    WHERE record_id = ?
    ORDER BY p.id DESC
");

$pe->execute([$id]);

$payments =
    $pe->fetchAll();


/* -------------------------------------------------------
   AUDIT HISTORY
------------------------------------------------------- */

$logs = $pdo->prepare("
    SELECT
        a.*,
        u.full_name
    FROM audit_logs a
    LEFT JOIN users u
        ON u.id = a.actor_id
    WHERE entity_type = 'work_record'
    AND entity_id = ?
    ORDER BY a.id DESC
");

$logs->execute([$id]);

$logs =
    $logs->fetchAll();


/* -------------------------------------------------------
   HUMAN-READABLE AUDIT LABELS
------------------------------------------------------- */

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
        'Record deleted'

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
    <?= htmlspecialchars($siteName) ?> — Record
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
   Uploaded logo:
   - wide
   - no white box
   - no padding
   - no border
   - no site name beside it
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
   No logo:
   show site name only.
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
   MOBILE
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
     PRINT-ONLY SUMMARY
===================================================== -->

<div class="print-summary">


    <div class="print-header">


        <div>


            <?php if ($siteLogo !== ''): ?>

                <!-- LOGO EXISTS → LOGO ONLY -->

                <img
                    class="print-logo-image"
                    src="<?= htmlspecialchars($siteLogo) ?>"
                    alt="<?= htmlspecialchars($siteName) ?>"
                >


            <?php else: ?>

                <!-- NO LOGO → SITE NAME ONLY -->

                <div class="print-brand">

                    <?= htmlspecialchars($siteName) ?>

                </div>


            <?php endif; ?>


            <div class="print-subtitle">

                Temporary Worker Work Record

            </div>


        </div>


        <span class="print-status">

            <?= htmlspecialchars(
                strtoupper(
                    $r['record_status']
                )
            ) ?>

        </span>


    </div>


    <h1>

        <?= htmlspecialchars(
            $r['task']
        ) ?>

    </h1>


    <p class="print-muted">


        <?= htmlspecialchars(
            $r['worker_name']
        ) ?>


        ↔


        <?= htmlspecialchars(
            $r['employer_name']
        ) ?>


        ·


        <?= htmlspecialchars(
            $r['work_date']
        ) ?>


    </p>


    <div class="print-grid">


        <div>

            <span>
                RATE
            </span>


            <b>

                ₹<?= number_format(
                    (float)$r['rate'],
                    0
                ) ?>


                /


                <?= htmlspecialchars(
                    $r['rate_type']
                ) ?>

            </b>

        </div>


        <div>

            <span>
                UNITS / DAYS
            </span>


            <b>

                <?= htmlspecialchars(
                    $r['units']
                ) ?>

            </b>

        </div>


        <div>

            <span>
                ATTENDANCE
            </span>


            <b>

                <?= htmlspecialchars(
                    ucfirst(
                        $r['attendance_status']
                    )
                ) ?>

            </b>

        </div>


    </div>


    <!-- =================================================
         PAYMENT
    ================================================= -->

    <div class="print-payment">


        <h2>
            Payment Summary
        </h2>


        <div class="print-row">

            <span>
                Earned amount
            </span>


            <b>

                ₹<?= number_format(
                    $earned,
                    0
                ) ?>

            </b>

        </div>


        <div class="print-row">

            <span>
                Extra work
            </span>


            <b>

                + ₹<?= number_format(
                    (float)$r['extra_work'],
                    0
                ) ?>

            </b>

        </div>


        <div class="print-row">

            <span>
                Advance
            </span>


            <b>

                − ₹<?= number_format(
                    (float)$r['advance_amount'],
                    0
                ) ?>

            </b>

        </div>


        <div class="print-row">

            <span>
                Paid
            </span>


            <b>

                − ₹<?= number_format(
                    (float)$r['paid_amount'],
                    0
                ) ?>

            </b>

        </div>


        <div class="print-total">

            <span>
                Pending Amount
            </span>


            <b>

                ₹<?= number_format(
                    $pending,
                    0
                ) ?>

            </b>

        </div>


    </div>


    <!-- =================================================
         HISTORY
    ================================================= -->

    <div class="print-history">


        <h2>
            Record History
        </h2>


        <?php foreach (
            array_slice(
                $logs,
                0,
                5
            ) as $x
        ): ?>


            <div class="print-history-row">


                <b>

                    <?= htmlspecialchars(
                        $auditLabels[$x['action']]
                        ??
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $x['action']
                            )
                        )
                    ) ?>

                </b>


                <span>

                    <?= htmlspecialchars(
                        $x['full_name']
                        ?? 'System'
                    ) ?>


                    ·


                    <?= htmlspecialchars(
                        $x['created_at']
                    ) ?>

                </span>


            </div>


        <?php endforeach; ?>


    </div>


    <div class="print-footer">


        <strong>

            <?= htmlspecialchars(
                $siteName
            ) ?>

        </strong>


        <span>

            Clear records · Shared history ·
            Fewer disputes

        </span>


    </div>


</div>


<!-- =====================================================
     NORMAL WEBSITE
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

                <?= htmlspecialchars(
                    $siteName
                ) ?>

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
                .classList
                .toggle('open');

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
            class="nav-link"
            href="dashboard.php"
        >
            All records
        </a>


        <span class="role-pill">

            <?= htmlspecialchars(
                ucfirst(
                    $user['role']
                )
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


<main class="container">


    <!-- =================================================
         HERO
    ================================================= -->

    <section class="hero">


        <div>


            <p class="eyebrow">

                RECORD •
                <?= strtoupper(
                    $r['record_status']
                ) ?>

            </p>


            <h1>

                <?= htmlspecialchars(
                    $r['task']
                ) ?>

            </h1>


            <p class="muted">


                <?= htmlspecialchars(
                    $r['worker_name']
                ) ?>


                ↔


                <?= htmlspecialchars(
                    $r['employer_name']
                ) ?>


                •


                <?= htmlspecialchars(
                    $r['work_date']
                ) ?>


            </p>


        </div>


        <button
            class="primary"
            onclick="printSummary()"
        >
            Print summary
        </button>


    </section>


    <!-- =================================================
         STATS
    ================================================= -->

    <section class="stats">


        <article>

            <span>
                Earned
            </span>


            <b>

                ₹<?= number_format(
                    $earned,
                    0
                ) ?>

            </b>

        </article>


        <article>

            <span>
                Extra
            </span>


            <b>

                ₹<?= number_format(
                    (float)$r['extra_work'],
                    0
                ) ?>

            </b>

        </article>


        <article>

            <span>
                Advances + paid
            </span>


            <b>

                ₹<?= number_format(
                    (float)$r['advance_amount'] +
                    (float)$r['paid_amount'],
                    0
                ) ?>

            </b>

        </article>


        <article>

            <span>
                Pending
            </span>


            <b>

                ₹<?= number_format(
                    $pending,
                    0
                ) ?>

            </b>

        </article>


    </section>


    <!-- =================================================
         WORK + ATTENDANCE
    ================================================= -->

    <section class="grid2">


        <div class="panel">


            <h2>
                Work details
            </h2>


            <p>

                <b>
                    Worker:
                </b>


                <?= htmlspecialchars(
                    $r['worker_name']
                ) ?>

            </p>


            <p>

                <b>
                    Employer:
                </b>


                <?= htmlspecialchars(
                    $r['employer_name']
                ) ?>

            </p>


            <p>

                <b>
                    Rate:
                </b>


                ₹<?= number_format(
                    (float)$r['rate'],
                    0
                ) ?>


                /


                <?= htmlspecialchars(
                    $r['rate_type']
                ) ?>


                ×


                <?= htmlspecialchars(
                    $r['units']
                ) ?>

            </p>


        </div>


        <div class="panel">


            <h2>
                Attendance
            </h2>


            <p class="status">

                <?= htmlspecialchars(
                    ucfirst(
                        $r['attendance_status']
                    )
                ) ?>

            </p>


            <?php

            if (
                in_array(
                    $user['role'],
                    [
                        'admin',
                        'employer',
                        'supervisor'
                    ],
                    true
                ) &&
                $r['attendance_status'] === 'pending'
            ):

            ?>


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
                        value="mark_attendance"
                    >


                    <button class="primary">

                        Mark attendance

                    </button>


                </form>


            <?php endif; ?>


            <?php

            if (
                $user['role'] === 'worker' &&
                $r['attendance_status'] === 'marked'
            ):

            ?>


                <div class="detail-actions">


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
                            value="confirm_attendance"
                        >


                        <button class="primary">

                            Confirm attendance

                        </button>


                    </form>


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
                            value="dispute_attendance"
                        >


                        <input
                            name="note"
                            placeholder="Optional dispute note"
                        >


                        <button class="secondary">

                            Dispute

                        </button>


                    </form>


                </div>


            <?php endif; ?>


        </div>


    </section>


    <!-- =================================================
         PAYMENT SUMMARY
    ================================================= -->

    <section class="panel">


        <h2>
            Payment summary
        </h2>


        <div class="payment-row">


            <span>
                Earned amount
            </span>


            <b>

                ₹<?= number_format(
                    $earned,
                    0
                ) ?>

            </b>


        </div>


        <div class="payment-row">


            <span>
                Extra work
            </span>


            <b>

                + ₹<?= number_format(
                    (float)$r['extra_work'],
                    0
                ) ?>

            </b>


        </div>


        <div class="payment-row">


            <span>
                Advance
            </span>


            <b>

                − ₹<?= number_format(
                    (float)$r['advance_amount'],
                    0
                ) ?>

            </b>


        </div>


        <div class="payment-row">


            <span>
                Completed payments
            </span>


            <b>

                − ₹<?= number_format(
                    (float)$r['paid_amount'],
                    0
                ) ?>

            </b>


        </div>


        <div class="pending">


            <span>
                Pending amount
            </span>


            <b>

                ₹<?= number_format(
                    $pending,
                    0
                ) ?>

            </b>


        </div>


    </section>


    <!-- =================================================
         RECORD CONTROL
    ================================================= -->

    <section class="panel">


        <h2>
            Record control
        </h2>


        <p>


            <b>
                Status:
            </b>


            <?= htmlspecialchars(
                ucfirst(
                    $r['record_status']
                )
            ) ?>


            <?=

                $r['record_status'] === 'confirmed'
                ? ' — locked'
                : ''

            ?>


        </p>


        <?php

        if (
            in_array(
                $user['role'],
                [
                    'admin',
                    'employer',
                    'supervisor'
                ],
                true
            ) &&
            $r['record_status'] === 'pending'
        ):

        ?>


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
                    value="confirm_record"
                >


                <button class="primary">

                    Confirm & lock record

                </button>


            </form>


        <?php endif; ?>


    </section>


    <!-- =================================================
         EDIT RECORD
    ================================================= -->

    <?php

    if (
        $canManage &&
        $r['record_status'] === 'pending'
    ):

    ?>


        <section
            class="panel"
            id="edit-record"
        >


            <h2>
                Edit record
            </h2>


            <p class="muted">

                Pending records can be corrected
                before confirmation.
                Confirmed or disputed records
                cannot be silently changed.

            </p>


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
                    value="edit_record"
                >


                <div class="form-grid">


                    <label>

                        Task


                        <input
                            name="task"
                            value="<?= htmlspecialchars(
                                $r['task']
                            ) ?>"
                            required
                        >

                    </label>


                    <label>

                        Date


                        <input
                            name="work_date"
                            type="date"
                            value="<?= htmlspecialchars(
                                $r['work_date']
                            ) ?>"
                            required
                        >

                    </label>


                    <label>

                        Rate type


                        <select name="rate_type">


                            <option
                                value="daily"
                                <?= $r['rate_type'] === 'daily'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Daily
                            </option>


                            <option
                                value="per_task"
                                <?= $r['rate_type'] === 'per_task'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Per task
                            </option>


                        </select>


                    </label>


                    <label>

                        Rate (₹)


                        <input
                            name="rate"
                            type="number"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                $r['rate']
                            ) ?>"
                            required
                        >

                    </label>


                    <label>

                        Units / days


                        <input
                            name="units"
                            type="number"
                            min="0"
                            step="0.5"
                            value="<?= htmlspecialchars(
                                $r['units']
                            ) ?>"
                            required
                        >

                    </label>


                    <label>

                        Extra work (₹)


                        <input
                            name="extra_work"
                            type="number"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                $r['extra_work']
                            ) ?>"
                        >

                    </label>


                    <label>

                        Advance (₹)


                        <input
                            name="advance_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                $r['advance_amount']
                            ) ?>"
                        >

                    </label>


                    <label>

                        Paid (₹)


                        <input
                            name="paid_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                $r['paid_amount']
                            ) ?>"
                        >

                    </label>


                </div>


                <div class="modal-actions">


                    <button class="primary">

                        Save changes

                    </button>


                </div>


            </form>


            <hr>


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


                <button
                    class="secondary danger"
                    type="submit"
                >

                    Delete record

                </button>


            </form>


        </section>


    <?php endif; ?>


    <!-- =================================================
         ATTENDANCE HISTORY
    ================================================= -->

    <section class="panel">


        <h2>
            Attendance history
        </h2>


        <?php foreach (
            $attendance as $x
        ): ?>


            <p class="history">


                <b>

                    <?= htmlspecialchars(
                        $x['action']
                    ) ?>

                </b>


                —


                <?= htmlspecialchars(
                    $x['full_name']
                ) ?>


                —


                <?= htmlspecialchars(
                    $x['created_at']
                ) ?>


                <?php if ($x['note']): ?>

                    —

                    <?= htmlspecialchars(
                        $x['note']
                    ) ?>

                <?php endif; ?>


            </p>


        <?php endforeach; ?>


        <?php if (!$attendance): ?>

            <p class="muted">

                No attendance events yet.

            </p>

        <?php endif; ?>


    </section>


    <!-- =================================================
         AUDIT HISTORY
    ================================================= -->

    <section class="panel">


        <h2>
            Audit history
        </h2>


        <?php foreach (
            $logs as $x
        ): ?>


            <p class="history">


                <b>

                    <?= htmlspecialchars(
                        $auditLabels[$x['action']]
                        ??
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $x['action']
                            )
                        )
                    ) ?>

                </b>


                —


                <?= htmlspecialchars(
                    $x['full_name']
                    ?? 'System'
                ) ?>


                —


                <?= htmlspecialchars(
                    $x['created_at']
                ) ?>


            </p>


        <?php endforeach; ?>


    </section>


    <p class="tiny">

        <?= htmlspecialchars(
            $siteName
        ) ?>

        is a record-keeping tool,
        not a payment service,
        payroll/legal authority.

    </p>


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

            Clear records.
            Shared history.
            Fewer disputes.

        </span>


    </div>


    <div>


        <span>

            © <?= date('Y') ?>

            <?= htmlspecialchars(
                $siteName
            ) ?>

        </span>


        <span>

            Record-keeping tool •
            Not a payment service or legal authority.

        </span>


    </div>


</footer>


<script>

function printSummary() {

    window.print();

}

</script>


</body>

</html>