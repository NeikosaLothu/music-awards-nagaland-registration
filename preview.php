<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/recaptcha.php';
require_once __DIR__ . '/config/razorpay.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    redirect('index.php');
}
if (empty($_SESSION['form_data'])) {
    $_SESSION['form_error'] = 'Your form session has expired. Please complete the form again.';
    redirect('index.php');
}
if (empty($_SESSION['payment_verified'])) {
    $_SESSION['form_error'] = 'Please complete the ₹250 registration payment before viewing the final preview.';
    redirect('index.php');
}
if ((int)($_SESSION['payment_amount'] ?? 0) !== RAZORPAY_AMOUNT_PAISE) {
    $_SESSION['form_error'] = 'Payment verification could not be confirmed. Please try again.';
    redirect('index.php');
}
$data = $_SESSION['form_data'];
$_SESSION['confirm_token'] = bin2hex(random_bytes(32));
$_SESSION['previewed_at'] = time();

/*
|--------------------------------------------------------------------------
| Field groups — mirrors the 8-step form so the review page reads the
| same way the form was filled in, but as compact two-column rows
| instead of one long vertical list.
|--------------------------------------------------------------------------
*/

$groups = [
    [
        'title' => 'Category & Artist',
        'icon'  => '01',
        'items' => [
            ['key' => 'category', 'label' => 'Category (Genre)'],
            ['key' => 'artist_name', 'label' => 'Official Artist Name / Band Name'],
            ['key' => 'address', 'label' => 'Address', 'wide' => true],
        ],
    ],
    [
        'title' => 'Contact Information',
        'icon'  => '02',
        'items' => [
            ['key' => 'contact_name', 'label' => 'Contact Name'],
            ['key' => 'contact_number', 'label' => 'Contact Number'],
            ['key' => 'band_page', 'label' => 'Band Page / Website / Social Media', 'link' => true, 'wide' => true],
        ],
    ],
    [
        'title' => 'Song & Production Credits',
        'icon'  => '03',
        'items' => [
            ['key' => 'song_title', 'label' => 'Song Title'],
            ['key' => 'songwriter', 'label' => 'Songwriter'],
            ['key' => 'sound_engineer', 'label' => 'Sound Engineer'],
            ['key' => 'producer', 'label' => 'Producer'],
            ['key' => 'video_director', 'label' => 'Video Director'],
            ['key' => 'recorded_at', 'label' => 'Recorded At'],
        ],
    ],
    [
        'title' => 'Links',
        'icon'  => '04',
        'items' => [
            ['key' => 'platform_audio_link', 'label' => 'Audio Link (Spotify/Apple/YouTube Music)', 'link' => true],
            ['key' => 'music_video_link', 'label' => 'Music Video Link (YouTube)', 'link' => true],
        ],
    ],
    [
        'title' => 'Lyrics',
        'icon'  => '05',
        'items' => [
            ['key' => 'lyrics', 'label' => 'Lyrics', 'wide' => true, 'lyrics' => true],
        ],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Preview Submission | 18th Music Awards of Nagaland</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="site-brand">
            <span class="brand-mark"><img src="assets/img/music-awards-logo.png" alt="Music Awards of Nagaland"></span>
            <span class="brand-text">
                <strong>MUSIC AWARDS</strong>
                <span>OF NAGALAND</span>
                <small>18TH EDITION</small>
            </span>
        </a>

        <div class="header-actions">
            <a href="index.php" class="back-link">
                ← BACK TO FORM
            </a>
        </div>
    </div>
</header>

<main>

<section class="page-hero">
    <div class="content-width">
        <div class="page-eyebrow">FINAL REVIEW</div>
        <h1>Preview your submission</h1>
        <p>Please check every detail below before confirming — you can still go back and edit anything.</p>
    </div>
</section>

<section class="review-area">
<div class="content-width">

    <div class="review-card">

        <div class="review-card-top">
            <div>
                <div class="page-eyebrow">18TH MUSIC AWARDS OF NAGALAND</div>
                <h2>Submission Details</h2>
            </div>
            <span class="status-badge paid">₹250 PAID · READY TO SUBMIT</span>
        </div>

        <?php foreach ($groups as $group): ?>

            <div class="review-group">

                <div class="review-group-title">
                    <span><?= e($group['icon']) ?></span>
                    <?= e($group['title']) ?>
                </div>

                <div class="review-grid">

                    <?php foreach ($group['items'] as $item):
                        $value = $data[$item['key']] ?? '';
                        $wideClass = !empty($item['wide']) ? ' review-wide' : '';
                    ?>

                        <div class="review-item<?= $wideClass ?>">

                            <span class="review-label"><?= e($item['label']) ?></span>

                            <?php if (!empty($item['link']) && $value !== ''): ?>

                                <a
                                    class="review-value"
                                    href="<?= e($value) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                ><?= e($value) ?></a>

                            <?php elseif (!empty($item['lyrics'])): ?>

                                <div class="lyrics-preview"><?= nl2br(e($value)) ?></div>

                            <?php else: ?>

                                <span class="review-value"><?= nl2br(e($value)) ?></span>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endforeach; ?>

        <div class="review-payment-box">
            <div><span>REGISTRATION FEE</span><strong>₹250 · PAID</strong></div>
            <div><span>RAZORPAY PAYMENT ID</span><strong><?= e((string)($_SESSION['payment_id'] ?? '')) ?></strong></div>
        </div>

        <div class="review-note">
            <strong>Payment verified — final submission is still required</strong>
            <p>Your ₹250 registration payment has been verified. Clicking "Final Submit" will now create the official application record and generate your submission ID.</p>
        </div>

        <form action="submit.php" method="post" id="confirmForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="confirm_token" value="<?= e($_SESSION['confirm_token']) ?>">

            <div class="review-actions">
                <a href="index.php" class="btn-outline">← Go Back &amp; Edit</a>
                <button type="submit" id="confirmButton" class="submit-button">
                    <span>FINAL SUBMIT</span>
                    <span class="submit-arrow">→</span>
                </button>
            </div>
        </form>

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
        <span>Submission Preview</span>
    </div>
</footer>

<script src="assets/js/app.js"></script>
</body>
</html>