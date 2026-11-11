<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/recaptcha.php';
require_once __DIR__ . '/config/razorpay.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

if (!hash_equals(csrf_token(), (string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['form_error'] = 'Your session has expired. Please start again.';
    redirect('index.php');
}

if (empty($_SESSION['form_data']) || empty($_SESSION['confirm_token'])) {
    $_SESSION['form_error'] = 'Your preview session has expired. Please complete the form again.';
    redirect('index.php');
}

$confirmToken = (string)($_POST['confirm_token'] ?? '');
if (!hash_equals((string)$_SESSION['confirm_token'], $confirmToken)) {
    $_SESSION['form_error'] = 'Invalid submission confirmation. Please preview the form again.';
    redirect('index.php');
}

if (empty($_SESSION['payment_verified']) || empty($_SESSION['payment_id']) || empty($_SESSION['payment_order_id'])) {
    $_SESSION['form_error'] = 'Payment must be completed and verified before final submission.';
    redirect('index.php');
}
if ((int)($_SESSION['payment_amount'] ?? 0) !== RAZORPAY_AMOUNT_PAISE) {
    $_SESSION['form_error'] = 'The verified payment amount does not match the ₹250 registration fee.';
    redirect('index.php');
}

$paymentVerifiedAt = (int)($_SESSION['payment_verified_at'] ?? 0);
if ($paymentVerifiedAt === 0 || (time() - $paymentVerifiedAt) > 3600) {
    unset(
        $_SESSION['payment_verified'], $_SESSION['payment_id'], $_SESSION['payment_order_id'],
        $_SESSION['payment_signature'], $_SESSION['payment_amount'], $_SESSION['payment_verified_at']
    );
    $_SESSION['form_error'] = 'Your payment verification has expired. Please complete payment again.';
    redirect('index.php');
}

if (!empty($_SESSION['submission_processing'])) {
    $_SESSION['form_error'] = 'This submission is already being processed. Please wait.';
    redirect('index.php');
}

$_SESSION['submission_processing'] = true;

try {
    $data = $_SESSION['form_data'];

    $errors = validate_submission($data);

    if (empty($_SESSION['captcha_verified'])) {
    $errors['captcha'] = 'CAPTCHA verification is missing. Please complete the CAPTCHA again.';
}

    if ($errors) {
        $_SESSION['form_error'] = implode(' ', array_values($errors));
        unset($_SESSION['submission_processing']);
        redirect('index.php');
    }

    $payment = razorpay_fetch_payment((string)$_SESSION['payment_id']);
    if (
        (string)($payment['order_id'] ?? '') !== (string)$_SESSION['payment_order_id']
        || (int)($payment['amount'] ?? 0) !== RAZORPAY_AMOUNT_PAISE
        || (string)($payment['currency'] ?? '') !== RAZORPAY_CURRENCY
        || strtolower((string)($payment['status'] ?? '')) !== 'captured'
    ) {
        throw new RuntimeException('Razorpay could not confirm a captured ₹250 payment for this application.');
    }

    $pdo = db();

    if (is_payment_id_already_used($pdo, (string)$_SESSION['payment_id'])) {
        throw new RuntimeException('Payment ID ' . $_SESSION['payment_id'] . ' has already been used for a submission.');
    }

    $attempts = 0;
    do {
        $submissionId = generate_submission_id();
        $stmt = $pdo->prepare('SELECT id FROM submissions WHERE submission_id = :submission_id LIMIT 1');
        $stmt->execute(['submission_id' => $submissionId]);
        $exists = (bool)$stmt->fetchColumn();
        $attempts++;
    } while ($exists && $attempts < 5);

    if ($exists) {
        throw new RuntimeException('Could not generate a unique submission reference.');
    }

    $sql = 'INSERT INTO submissions (
        submission_id, category, artist_name, address, band_page,
        contact_name, contact_number, song_title, songwriter, sound_engineer,
        platform_audio_link, music_video_link, lyrics, producer, video_director,
        recorded_at, created_at, payment_status, razorpay_order_id, razorpay_payment_id, payment_updated_at
    ) VALUES (
        :submission_id, :category, :artist_name, :address, :band_page,
        :contact_name, :contact_number, :song_title, :songwriter, :sound_engineer,
        :platform_audio_link, :music_video_link, :lyrics, :producer, :video_director,
        :recorded_at, NOW(), \'paid\', :razorpay_order_id, :razorpay_payment_id, NOW()
    )';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'submission_id' => $submissionId,
        'category' => $data['category'],
        'artist_name' => $data['artist_name'],
        'address' => $data['address'],
        'band_page' => $data['band_page'],
        'contact_name' => $data['contact_name'],
        'contact_number' => $data['contact_number'],
        'song_title' => $data['song_title'],
        'songwriter' => $data['songwriter'],
        'sound_engineer' => $data['sound_engineer'],
        'platform_audio_link' => $data['platform_audio_link'],
        'music_video_link' => $data['music_video_link'],
        'lyrics' => $data['lyrics'],
        'producer' => $data['producer'],
        'video_director' => $data['video_director'],
        'recorded_at' => $data['recorded_at'],
        'razorpay_order_id' => (string)$_SESSION['payment_order_id'],
        'razorpay_payment_id' => (string)$_SESSION['payment_id'],
    ]);

    $_SESSION['success_submission_id'] = $submissionId;
    unset(
    $_SESSION['form_data'],
    $_SESSION['confirm_token'],
    $_SESSION['captcha_verified'],
    $_SESSION['previewed_at'],
    $_SESSION['submission_processing'],
    $_SESSION['csrf_token'],
    $_SESSION['payment_verified'],
    $_SESSION['payment_id'],
    $_SESSION['payment_order_id'],
    $_SESSION['payment_signature'],
    $_SESSION['payment_amount'],
    $_SESSION['payment_verified_at'],
    $_SESSION['razorpay_order_id'],
    $_SESSION['razorpay_order_created_at']
);

    redirect('success.php');
} catch (Throwable $e) {
    error_log('[Music Awards Registration] ' . $e->getMessage());
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        // Unique-constraint violation on razorpay_payment_id: this exact
        // payment has already been used to create a submission. The
        // is_payment_id_already_used() check above catches this in the
        // normal case; this catches the rare race between two
        // near-simultaneous requests for the same payment.
        $_SESSION['form_error'] = 'This payment has already been used for a submission. If you believe this is an error, please contact the organizers with your payment reference.';
    } else {
        $_SESSION['form_error'] = 'We could not complete your submission right now. Please try again. If the problem continues, contact the organizers.';
    }
    unset($_SESSION['submission_processing']);
    redirect('index.php');
}
