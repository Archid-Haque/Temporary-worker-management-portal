<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/site.php';

$site = site_settings();

$siteName = trim($site['site_name'] ?? 'WorkerLedger');
$siteLogo = trim($site['site_logo'] ?? '');

if ($siteName === '') {
    $siteName = 'WorkerLedger';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="UTF-8">

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  >

  <title>
    <?= htmlspecialchars($siteName) ?> — Simple Work Records
  </title>

  <link
    rel="stylesheet"
    href="home.css?v=2"
  >

  <style>

    /* =================================================
       GLOBAL BRANDING
       ================================================= */

    .site-brand-logo{
      display:block;
      width:300px;
      height:58px;
      max-width:38vw;
      object-fit:contain;
      object-position:left center;
      background:transparent;
      border:0;
      padding:0;
    }


    /* No logo = site name */

    .site-brand-name{
      font-size:21px;
      font-weight:900;
      line-height:1;
      color:inherit;
      white-space:nowrap;
    }


    /* =================================================
       HERO VISUAL LOGO
       ================================================= */

    .hero-logo-image{
      display:block;
      width:115px;
      height:42px;
      object-fit:contain;
      object-position:left center;
      background:transparent;
      border:0;
      padding:0;
    }


    .hero-logo-name{
      font-size:18px;
      font-weight:900;
    }


    /* =================================================
       MOBILE
       ================================================= */

    @media(max-width:700px){

      .site-brand-logo{
        width:210px;
        height:44px;
        max-width:62vw;
      }

      .site-brand-name{
        font-size:18px;
      }

      .hero-logo-image{
        width:100px;
        height:38px;
      }

    }

  </style>

</head>


<body>


  <!-- =================================================
       NAVBAR
       ================================================= -->

  <header class="navbar">


    <div class="brand">


      <?php if ($siteLogo !== ''): ?>

        <!-- =========================================
             LOGO EXISTS
             SHOW ONLY FULL LOGO
             ========================================= -->

        <img
          src="<?= htmlspecialchars($siteLogo) ?>"
          alt="<?= htmlspecialchars($siteName) ?>"
          class="site-brand-logo"
        >

      <?php else: ?>

        <!-- =========================================
             NO LOGO
             SHOW ONLY SITE NAME
             ========================================= -->

        <div class="site-brand-name">
          <?= htmlspecialchars($siteName) ?>
        </div>

      <?php endif; ?>


    </div>


    <a
      href="login.php"
      class="nav-button"
    >
      Open Ledger
    </a>


  </header>



  <!-- =================================================
       HERO
       ================================================= -->

  <main>


    <section class="hero">


      <div class="hero-content">


        <div class="eyebrow">
          WEB08 • HACKATHON MVP
        </div>


        <h1>

          Work deserves a
          <span>clear record.</span>

        </h1>


        <p class="hero-text">

          A simple shared platform to record work,
          confirm attendance and keep payment
          calculations transparent.

        </p>


        <div class="hero-actions">


          <a
            href="login.php"
            class="primary-btn"
          >

            Get Started

            <span>→</span>

          </a>


          <a
            href="#how-it-works"
            class="secondary-btn"
          >

            How it works

          </a>


        </div>


        <div class="hero-note">

          No complicated payroll system.
          Just a clear, shared record.

        </div>


      </div>



      <!-- =================================================
           HERO VISUAL
           ================================================= -->

      <div class="hero-visual">


        <div class="visual-card main-card">


          <div class="visual-top">


            <?php if ($siteLogo !== ''): ?>

              <!-- Uploaded logo -->

              <img
                src="<?= htmlspecialchars($siteLogo) ?>"
                alt="<?= htmlspecialchars($siteName) ?>"
                class="hero-logo-image"
              >

            <?php else: ?>

              <!-- No logo -->

              <div class="hero-logo-name">
                <?= htmlspecialchars($siteName) ?>
              </div>

            <?php endif; ?>


            <span class="status-dot">
              ●
            </span>


          </div>


          <div class="visual-line large"></div>

          <div class="visual-line"></div>


          <div class="visual-grid">


            <div>

              <small>
                WORK
              </small>

              <strong>
                Recorded
              </strong>

            </div>


            <div>

              <small>
                ATTENDANCE
              </small>

              <strong>
                Confirmed
              </strong>

            </div>


          </div>


          <div class="visual-payment">


            <div>

              <small>
                PAYMENT RECORD
              </small>

              <strong>
                Clear &amp; transparent
              </strong>

            </div>


            <div class="check">
              ✓
            </div>


          </div>


        </div>



        <div class="floating-card card-one">
          ✓ Attendance
        </div>


        <div class="floating-card card-two">
          ₹ Calculation
        </div>


        <div class="floating-card card-three">
          🔒 Record history
        </div>


      </div>


    </section>



    <!-- =================================================
         TRUST STRIP
         ================================================= -->

    <section class="trust-strip">


      <div>

        <strong>
          One shared record
        </strong>

        <span>
          Both sides see the same history
        </span>

      </div>


      <div>

        <strong>
          Clear calculations
        </strong>

        <span>
          Earned − advances − payments
        </span>

      </div>


      <div>

        <strong>
          Traceable history
        </strong>

        <span>
          Important actions stay recorded
        </span>

      </div>


    </section>



    <!-- =================================================
         HOW IT WORKS
         ================================================= -->

    <section
      class="section"
      id="how-it-works"
    >


      <div class="section-heading">


        <div class="eyebrow">
          HOW IT WORKS
        </div>


        <h2>

          From work to record,
          without the confusion.

        </h2>


        <p>

          <?= htmlspecialchars($siteName) ?>
          keeps the complete work
          journey in one simple place.

        </p>


      </div>



      <div class="steps">


        <div class="step">

          <div class="step-number">
            01
          </div>

          <div class="step-icon">
            📝
          </div>

          <h3>
            Record the work
          </h3>

          <p>

            Capture the task, date and agreed rate
            before the work begins.

          </p>

        </div>



        <div class="step">

          <div class="step-number">
            02
          </div>

          <div class="step-icon">
            ✓
          </div>

          <h3>
            Confirm attendance
          </h3>

          <p>

            Attendance can be recorded and
            confirmed instead of relying on memory.

          </p>

        </div>



        <div class="step">

          <div class="step-number">
            03
          </div>

          <div class="step-icon">
            ₹
          </div>

          <h3>
            Track the calculation
          </h3>

          <p>

            Extra work, advances and completed
            payments are reflected clearly.

          </p>

        </div>



        <div class="step">

          <div class="step-number">
            04
          </div>

          <div class="step-icon">
            🔒
          </div>

          <h3>
            Preserve the history
          </h3>

          <p>

            Important confirmations and disputes
            remain visible in the record history.

          </p>

        </div>


      </div>


    </section>



    <!-- =================================================
         FEATURES
         ================================================= -->

    <section class="feature-section">


      <div class="feature-content">


        <div class="eyebrow">
          BUILT FOR CLARITY
        </div>


        <h2>

          Everything important.
          Nothing unnecessary.

        </h2>


        <p>

          <?= htmlspecialchars($siteName) ?>
          focuses on one problem:
          making temporary work records easy to
          understand, verify and preserve.

        </p>


        <a
          href="login.php"
          class="primary-btn"
        >

          Open <?= htmlspecialchars($siteName) ?>

          <span>→</span>

        </a>


      </div>



      <div class="feature-list">


        <div class="feature-item">

          <div class="feature-icon">
            ✓
          </div>

          <div>

            <h3>
              Shared records
            </h3>

            <p>

              A single source of truth for the
              work record.

            </p>

          </div>

        </div>



        <div class="feature-item">

          <div class="feature-icon">
            ₹
          </div>

          <div>

            <h3>
              Transparent calculations
            </h3>

            <p>

              Clearly show how the pending amount
              is calculated.

            </p>

          </div>

        </div>



        <div class="feature-item">

          <div class="feature-icon">
            ⚑
          </div>

          <div>

            <h3>
              Dispute visibility
            </h3>

            <p>

              A disagreement can be flagged instead
              of silently changing the record.

            </p>

          </div>

        </div>



        <div class="feature-item">

          <div class="feature-icon">
            🖨
          </div>

          <div>

            <h3>
              Print-friendly summaries
            </h3>

            <p>

              Generate a clean record that can be
              printed or shared.

            </p>

          </div>

        </div>


      </div>


    </section>



    <!-- =================================================
         CTA
         ================================================= -->

    <section class="cta">


      <div class="eyebrow">

        <?= htmlspecialchars(
          strtoupper($siteName)
        ) ?>

      </div>


      <h2>
        Make every work record count.
      </h2>


      <p>

        Simple records. Clear calculations.
        Shared history.

      </p>


      <a
        href="login.php"
        class="primary-btn light-btn"
      >

        Enter <?= htmlspecialchars($siteName) ?>

        <span>→</span>

      </a>


    </section>


  </main>



  <!-- =================================================
       FOOTER
       ================================================= -->

  <footer>


    <div>

      <strong>
        <?= htmlspecialchars($siteName) ?>
      </strong>

      <span>
        • Temporary Worker Records
      </span>

    </div>


    <div>

      Aevix • @2026

    </div>


  </footer>


</body>

</html>