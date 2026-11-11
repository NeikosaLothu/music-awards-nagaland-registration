<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

start_secure_session();

$submissionId = $_SESSION['success_submission_id'] ?? null;
if (!$submissionId) {
    redirect('index.php');
}
unset($_SESSION['success_submission_id']);

$pdo = db();
$submission = find_submission($pdo, (string)$submissionId);
$paymentStatus = $submission['payment_status'] ?? 'pending';

// Note: in the current flow, a submission row is only ever created in
// submit.php AFTER payment is verified, so payment_status is always 'paid'
// here in normal operation. The badge/fallback below only covers the
// unlikely case of a data anomaly (e.g. a manually edited database row).

$paymentBadge = [
    'paid'    => ['label' => 'PAID', 'class' => 'paid'],
    'failed'  => ['label' => 'PAYMENT NOT COMPLETED', 'class' => 'failed'],
    'pending' => ['label' => 'PAYMENT PENDING', 'class' => 'pending'],
][$paymentStatus] ?? ['label' => 'PAYMENT PENDING', 'class' => 'pending'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Submission Successful | 18th Music Awards of Nagaland</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a href="https://musicawardsofnagaland.in/" class="site-brand">
            <span class="brand-mark"><img src="assets/img/music-awards-logo.png" alt="Music Awards of Nagaland"></span>
            <span class="brand-text">
                <strong>MUSIC AWARDS</strong>
                <span>OF NAGALAND</span>
                <small>18TH EDITION</small>
            </span>
        </a>
    </div>
</header>

<main>
<section class="success-area">
<div class="content-width">

    <div class="success-card">

        <div class="success-icon">✓</div>

        <div class="page-eyebrow">18TH MUSIC AWARDS OF NAGALAND</div>

        <h1>Submission successful</h1>

        <p class="success-message">
            Thank you for submitting your entry to the 18th Music Awards of Nagaland.
        </p>

        <div class="submission-id-box">
            <span>Submission ID</span>
            <strong><?= e($submissionId) ?></strong>
        </div>

        <p style="margin: 0 0 10px;">
            <span class="status-badge <?= e($paymentBadge['class']) ?>"><?= e($paymentBadge['label']) ?></span>
        </p>

        <p class="success-note">Please save this submission ID for your records.</p>

        <?php if ($paymentStatus !== 'paid'): ?>
            <div class="form-alert" style="text-align: left;">
                We could not confirm payment for this application. Please contact the
                organizers with your Submission ID above before submitting again.
            </div>
        <?php endif; ?>

        <div class="success-actions">
            <?php if ($paymentStatus === 'paid'): ?>
                <button type="button" class="submit-button" style="justify-content: center;" onclick="window.print()">
                    <span>Print / Save Confirmation</span>
                </button>
            <?php endif; ?>
            <a class="btn-outline" href="https://musicawardsofnagaland.in/">Return to Homepage</a>
        </div>

    </div>

</div>
</section>
</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <span class="brand-mark"><img src="assets/img/music-awards-logo.png" alt="Music Awards of Nagaland"></span>
            <div>
                <strong>MUSIC AWARDS OF NAGALAND</strong>
                <span>Artist Submission Portal · 18th Edition</span>
            </div>
        </div>

        <a href="https://musicawardsofnagaland.in/" class="footer-link">BACK TO WEBSITE ↗</a>
    </div>

    <div class="footer-bottom">
        <span>© 2026 · 18th Music Awards of Nagaland</span>
        <span>Submission Confirmation</span>
    </div>
</footer>

<script src="assets/js/app.js"></script>
</body>
</html>