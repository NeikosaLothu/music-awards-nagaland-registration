<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/recaptcha.php';

start_secure_session();


$formData = $_SESSION['form_data'] ?? [];
$formError = $_SESSION['form_error'] ?? '';

unset($_SESSION['form_error']);

$categories = [
    'Rock',
    'Hip-Hop/R&B',
    'Gospel',
    'Regional/ Folk Fusion',
    'Pop',
    'Best Cover Song',
    'Song of the Year'
];

function old_value(string $key, array $data): string
{
    return e((string)($data[$key] ?? ''));
}

function selected_category(string $category, array $data): string
{
    return (($data['category'] ?? '') === $category) ? 'selected' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="description"
        content="Artist submission portal for the 18th Music Awards of Nagaland."
    >

    <title>
        Artist Submissions | 18th Music Awards of Nagaland
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

</head>

<body>

<!-- =========================================================
     HEADER
========================================================= -->

<header class="site-header">

    <div class="header-inner">

        <a
            href="https://musicawardsofnagaland.in/"
            class="site-brand"
        >

            <span class="brand-mark"><img src="assets/img/music-awards-logo.png" alt="Music Awards of Nagaland"></span>

            <span class="brand-text">
                <strong>MUSIC AWARDS</strong>
                <span>OF NAGALAND</span>
                <small>18TH EDITION</small>
            </span>

        </a>

        <div class="header-actions">

            <a
                href="https://musicawardsofnagaland.in/"
                class="back-link"
            >
                BACK TO WEBSITE
                <span>↗</span>
            </a>

        </div>

    </div>

</header>


<!-- =========================================================
     HERO
========================================================= -->

<main>

<section class="hero-section">

    <div class="hero-inner">

        <div class="hero-copy">
            <div class="hero-label">
                18TH MUSIC AWARDS OF NAGALAND
            </div>

            <div class="hero-title-row">

                <span class="hero-tafma-badge">
                    <img src="assets/img/tafma-logo.png" alt="TaFMA — Task Force for Music &amp; Arts">
                </span>

                <h1>
                    Submit Your<br>
                    <span>Song</span>
                </h1>

            </div>

            <div class="hero-bottom">

                <p>
                    18th Music Awards of Nagaland
                </p>

                <p class="hero-description">
                    Complete the application below. All required fields are marked with an orange * and your details can be reviewed before submission.
                </p>

            </div>

            <div class="hero-line"></div>
        </div>

        <div class="hero-logo">
            <img src="assets/img/music-awards-logo.png" alt="18th Music Awards of Nagaland">
        </div>

    </div>

</section>


<!-- =========================================================
     FORM NAVIGATION
========================================================= -->

<section class="submission-nav">

    <div class="content-width">

        <div class="section-intro-small">
            YOUR SUBMISSION
        </div>

        <div class="submission-steps">

            <a href="#category">
                <span>01</span>
                CATEGORY
            </a>

            <a href="#artist">
                <span>02</span>
                ARTIST
            </a>

            <a href="#contact">
                <span>03</span>
                CONTACT
            </a>

            <a href="#song">
                <span>04</span>
                SONG
            </a>

            <a href="#links">
                <span>05</span>
                LINKS
            </a>

            <a href="#lyrics">
                <span>06</span>
                LYRICS
            </a>

            <a href="#verification">
                <span>07</span>
                REVIEW
            </a>

            <a href="#payment">
                <span>08</span>
                PAYMENT
            </a>

        </div>

    </div>

</section>


<!-- =========================================================
     FORM
========================================================= -->

<section class="form-area">

<div class="content-width">

<?php if ($formError): ?>

    <div class="form-alert">
        <?= e($formError) ?>
    </div>

<?php endif; ?>


<form
    action="payment.php"
    method="post"
    id="submissionForm"
    novalidate
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= e(csrf_token()) ?>"
    >


    <!-- =====================================================
         HONEYPOT
    ====================================================== -->

    <div class="honeypot-field" aria-hidden="true">
        <label>
            Website
            <input
                type="text"
                name="website"
                tabindex="-1"
                autocomplete="off"
            >
        </label>
    </div>


    <!-- =====================================================
         01 CATEGORY
    ====================================================== -->

    <section
        class="form-section"
        id="category"
    >

        <div class="section-number">
            01
        </div>

        <div class="section-content">

            <div class="section-heading">

                <div class="section-kicker">
                    SUBMISSION CATEGORY
                </div>

                <h2>
                    Choose your category
                </h2>

                <p>
                    Select the award category for this entry.
                </p>

            </div>


            <div class="field">

                <label for="category">
                    Category (Genre)
                    <span>*</span>
                </label>

                <select
                    id="category"
                    name="category"
                    required
                >

                    <option value="">
                        Select a category
                    </option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= e($category) ?>"
                            <?= selected_category($category, $formData) ?>
                        >
                            <?= e($category) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>

    </section>


    <!-- =====================================================
         02 ARTIST
    ====================================================== -->

    <section
        class="form-section"
        id="artist"
    >

        <div class="section-number">
            02
        </div>

        <div class="section-content">

            <div class="section-heading">

                <div class="section-kicker">
                    ARTIST / BAND
                </div>

                <h2>
                    Tell us about the artist
                </h2>

                <p>
                    Enter the official details of the artist
                    or band submitting this entry.
                </p>

            </div>


            <div class="field">

                <label for="artist_name">
                    Official Artist Name / Band Name
                    <span>*</span>
                </label>

                <input
                    type="text"
                    id="artist_name"
                    name="artist_name"
                    value="<?= old_value('artist_name', $formData) ?>"
                    placeholder="Enter artist or band name"
                    maxlength="150"
                    required
                >

            </div>


            <div class="field">

                <label for="address">
                    Address
                    <span>*</span>
                </label>

                <textarea
                    id="address"
                    name="address"
                    rows="4"
                    maxlength="500"
                    placeholder="Enter your address"
                    required
                ><?= old_value('address', $formData) ?></textarea>

            </div>

        </div>

    </section>


    <!-- =====================================================
         03 CONTACT
    ====================================================== -->

    <section
        class="form-section"
        id="contact"
    >

        <div class="section-number">
            03
        </div>

        <div class="section-content">

            <div class="section-heading">

                <div class="section-kicker">
                    CONTACT INFORMATION
                </div>

                <h2>
                    How can we reach you?
                </h2>

                <p>
                    Provide the primary contact details for
                    this submission.
                </p>

            </div>


            <div class="field-grid">

                <div class="field">

                    <label for="contact_name">
                        Contact Name
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="contact_name"
                        name="contact_name"
                        value="<?= old_value('contact_name', $formData) ?>"
                        placeholder="Full name"
                        maxlength="150"
                        required
                    >

                </div>


                <div class="field">

                    <label for="contact_number">
                        Contact Number
                        <span>*</span>
                    </label>

                    <input
                        type="tel"
                        id="contact_number"
                        name="contact_number"
                        value="<?= old_value('contact_number', $formData) ?>"
                        placeholder="+91 XXXXX XXXXX"
                        maxlength="20"
                        required
                    >

                </div>

            </div>


            <div class="field">

                <label for="band_page">
                    Band Page / Website / Social Media Site
                    <span>*</span>
                </label>

                <input
                    type="url"
                    id="band_page"
                    name="band_page"
                    value="<?= old_value('band_page', $formData) ?>"
                    placeholder="https://..."
                    maxlength="500"
                    required
                >

            </div>

        </div>

    </section>


    <!-- =====================================================
         04 SONG
    ====================================================== -->

    <section
        class="form-section"
        id="song"
    >

        <div class="section-number">
            04
        </div>

        <div class="section-content">

            <div class="section-heading">

                <div class="section-kicker">
                    SONG INFORMATION
                </div>

                <h2>
                    Tell us about the song
                </h2>

                <p>
                    Enter the official songwriting and
                    technical credits.
                </p>

            </div>


            <div class="field-grid">

                <div class="field">

                    <label for="song_title">
                        Song Title
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="song_title"
                        name="song_title"
                        value="<?= old_value('song_title', $formData) ?>"
                        placeholder="Enter song title"
                        maxlength="200"
                        required
                    >

                </div>


                <div class="field">

                    <label for="songwriter">
                        Songwriter
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="songwriter"
                        name="songwriter"
                        value="<?= old_value('songwriter', $formData) ?>"
                        placeholder="Enter songwriter name"
                        maxlength="200"
                        required
                    >

                </div>


                <div class="field">

                    <label for="sound_engineer">
                        Sound Engineer
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="sound_engineer"
                        name="sound_engineer"
                        value="<?= old_value('sound_engineer', $formData) ?>"
                        placeholder="Enter sound engineer"
                        maxlength="200"
                        required
                    >

                </div>


                <div class="field">

                    <label for="producer">
                        Producer
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="producer"
                        name="producer"
                        value="<?= old_value('producer', $formData) ?>"
                        placeholder="Enter producer"
                        maxlength="200"
                        required
                    >

                </div>


                <div class="field">

                    <label for="video_director">
                        Video Director
                        <small>(Optional)</small>
                    </label>

                    <input
                        type="text"
                        id="video_director"
                        name="video_director"
                        value="<?= old_value('video_director', $formData) ?>"
                        placeholder="Enter video director"
                        maxlength="200"
                    >

                </div>


                <div class="field">

                    <label for="recorded_at">
                        Recorded At
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="recorded_at"
                        name="recorded_at"
                        value="<?= old_value('recorded_at', $formData) ?>"
                        placeholder="Studio / recording location"
                        maxlength="200"
                        required
                    >

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         05 LINKS
    ====================================================== -->

    <section
        class="form-section"
        id="links"
    >

        <div class="section-number">
            05
        </div>

        <div class="section-content">

            <div class="section-heading">

                <div class="section-kicker">
                    LINKS
                </div>

                <h2>
                    Where can we listen and watch?
                </h2>

                <p>
                    Provide the streaming and music video
                    links associated with this song.
                </p>

            </div>


            <div class="field">

                <label for="platform_audio_link">
                    Audio Link
                    <span>*</span>
                </label>

                <div class="field-help">
                    Spotify, Apple Music or YouTube Music.
                </div>

                <input
                    type="url"
                    id="platform_audio_link"
                    name="platform_audio_link"
                    value="<?= old_value('platform_audio_link', $formData) ?>"
                    placeholder="https://..."
                    maxlength="500"
                    required
                >

            </div>


            <div class="field">

                <label for="music_video_link">
                    Music Video Link
                    <span>*</span>
                </label>

                <div class="field-help">
                    YouTube link to the official music video.
                </div>

                <input
                    type="url"
                    id="music_video_link"
                    name="music_video_link"
                    value="<?= old_value('music_video_link', $formData) ?>"
                    placeholder="https://youtube.com/..."
                    maxlength="500"
                    required
                >

            </div>

        </div>

    </section>


    <!-- =====================================================
         07 LYRICS
    ====================================================== -->

    <section
        class="form-section"
        id="lyrics"
    >

        <div class="section-number">
            06
        </div>

        <div class="section-content">

            <div class="section-heading">

                <div class="section-kicker">
                    LYRICS
                </div>

                <h2>
                    Tell us the story
                </h2>

                <p>
                    Paste the complete lyrics of the submitted song.
                </p>

            </div>


            <div class="field">

                <label for="lyrics">
                    Lyrics
                    <span>*</span>
                </label>

                <textarea
                    id="lyrics"
                    name="lyrics"
                    rows="12"
                    maxlength="10000"
                    placeholder="Paste the complete lyrics here..."
                    required
                ><?= old_value('lyrics', $formData) ?></textarea>

                <div class="character-count">
                    <span id="lyricsCount">0</span> /
                    10,000 characters
                </div>

            </div>

        </div>

    </section>


<!-- =====================================================
     07 VERIFICATION
====================================================== -->

<section
    class="form-section verification-section"
    id="verification"
>

    <div class="section-number">
        07
    </div>

    <div class="section-content">

        <div class="section-heading">

            <div class="section-kicker">
                VERIFICATION & REVIEW
            </div>

            <h2>
                Almost there.
            </h2>

            <p>
                Complete the verification below before
                moving on to payment.
            </p>

        </div>

        <div class="captcha-box">

            <div class="captcha-question">

                <span class="captcha-label">
                    VERIFICATION
                </span>

                <strong>
                    Confirm you are not a robot.
                </strong>

            </div>

            <div class="recaptcha-wrapper">

                <div
                    class="g-recaptcha"
                    data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>">
                </div>

            </div>

        </div>

        <div class="review-note">

            <strong>
                Before continuing
            </strong>

            <p>
                Your information will be shown to you
                on a preview page before the official
                submission is created.
            </p>

        </div>

    </div>

</section>


<!-- =====================================================
     08 PAYMENT
====================================================== -->

<section
    class="form-section verification-section"
    id="payment"
>

    <div class="section-number">
        08
    </div>

    <div class="section-content">

        <div class="section-heading">

            <div class="section-kicker">
                PAYMENT
            </div>

            <h2>
                Registration fee
            </h2>

            <p>
                A one-time registration fee secures your entry. Pay to unlock
                the final review and submission step.
            </p>

        </div>

        <div class="registration-fee-box">
            <div class="registration-fee-copy">
                <div class="final-label">REGISTRATION FEE</div>
                <strong>₹250</strong>
                <p>Payment is required before you can review and finally submit your entry.</p>
            </div>
            <div class="registration-fee-meta">
                <span>SECURE PAYMENT</span>
                <strong>Razorpay</strong>
            </div>
        </div>

        <div class="final-action">
            <div>
                <span class="final-label">READY FOR PAYMENT</span>
                <p>First pay the ₹250 registration fee. Your official application is created only after final submission.</p>
            </div>

            <button type="submit" class="submit-button" id="previewButton">
                <span>PAY ₹250 &amp; CONTINUE</span>
                <span class="submit-arrow">→</span>
            </button>
        </div>

    </div>

</section>

</form>


</div>

</section>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="site-footer">

    <div class="footer-inner">

        <div class="footer-brand">

            <span class="brand-mark"><img src="assets/img/music-awards-logo.png" alt="Music Awards of Nagaland"></span>

            <div>
                <strong>
                    MUSIC AWARDS OF NAGALAND
                </strong>

                <span>
                    Artist Submission Portal · 18th Edition
                </span>
            </div>

        </div>

        <div class="footer-tech-partner">
            <img src="assets/img/vi-tech-partner.png" alt="Vi — Tech Partner">
        </div>


        <a
            href="https://musicawardsofnagaland.in/"
            class="footer-link"
        >
            BACK TO WEBSITE ↗
        </a>

    </div>


    <div class="footer-bottom">

        <span>
            © 2026 · 18th Music Awards of Nagaland
        </span>

        <span>
            Artist Submissions
        </span>

    </div>

</footer>


<script>

document.addEventListener('DOMContentLoaded', function () {

    /* -------------------------------------------------------
       Lyrics counter
    ------------------------------------------------------- */

    const lyrics = document.getElementById('lyrics');
    const lyricsCount = document.getElementById('lyricsCount');

    function updateLyricsCount() {

        if (!lyrics || !lyricsCount) {
            return;
        }

        lyricsCount.textContent = lyrics.value.length;
    }

    if (lyrics) {

        lyrics.addEventListener(
            'input',
            updateLyricsCount
        );

        updateLyricsCount();
    }


    /* -------------------------------------------------------
       Smooth navigation
    ------------------------------------------------------- */

    document.querySelectorAll(
        '.submission-steps a'
    ).forEach(function (link) {

        link.addEventListener('click', function (event) {

            const targetId = this.getAttribute('href');

            if (!targetId || targetId === '#') {
                return;
            }

            const target = document.querySelector(targetId);

            if (!target) {
                return;
            }

            event.preventDefault();

            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        });

    });


    /* -------------------------------------------------------
       Submit button
    ------------------------------------------------------- */

    const form = document.getElementById('submissionForm');
    const button = document.getElementById('previewButton');

    if (form && button) {

        form.addEventListener('submit', function () {

            if (!form.checkValidity()) {
                return;
            }

            button.disabled = true;

            button.querySelector('span:first-child').textContent =
                'STARTING PAYMENT...';

        });

    }


    /* -------------------------------------------------------
       Reveal sections
    ------------------------------------------------------- */

    const sections = document.querySelectorAll(
        '.form-section'
    );

    if ('IntersectionObserver' in window) {

        const observer = new IntersectionObserver(
            function (entries) {

                entries.forEach(function (entry) {

                    if (entry.isIntersecting) {

                        entry.target.classList.add(
                            'section-visible'
                        );

                    }

                });

            },
            {
                threshold: 0.08
            }
        );

        sections.forEach(function (section) {
            observer.observe(section);
        });

    } else {

        sections.forEach(function (section) {
            section.classList.add('section-visible');
        });

    }

});

</script>

<script src="assets/js/app.js"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

</body>
</html>