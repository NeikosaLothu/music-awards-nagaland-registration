<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/razorpay.php';
require_once __DIR__ . '/config/recaptcha.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
if (!hash_equals(csrf_token(), (string)($_POST['csrf_token'] ?? ''))) {
    $_SESSION['form_error'] = 'Your session has expired. Please reload the form and try again.'; redirect('index.php');
}
if (!empty($_POST['website'] ?? '')) { $_SESSION['form_error'] = 'Unable to process this submission.'; redirect('index.php'); }
if (!razorpay_is_configured()) { $_SESSION['form_error'] = 'Online payment is not configured yet. Please contact the organizers.'; redirect('index.php'); }

if (!empty($_SESSION['payment_verified']) && !empty($_SESSION['payment_id']) && (int)($_SESSION['payment_amount'] ?? 0) === RAZORPAY_AMOUNT_PAISE) {
    redirect('preview.php');
}

$data = collect_form_data($_POST);
$errors = validate_submission($data);
$recaptchaResponse = trim((string)($_POST['g-recaptcha-response'] ?? ''));
if ($recaptchaResponse === '') {
    $errors['captcha'] = 'Please complete the CAPTCHA verification.';
} else {
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret'=>RECAPTCHA_SECRET_KEY,'response'=>$recaptchaResponse,'remoteip'=>$_SERVER['REMOTE_ADDR'] ?? '']),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch); $curlError = curl_error($ch); curl_close($ch);
    $captchaValid = false;
    if ($response !== false && $curlError === '') { $r = json_decode($response, true); $captchaValid = is_array($r) && !empty($r['success']); }
    if (!$captchaValid) $errors['captcha'] = 'CAPTCHA verification failed. Please try again.';
}
if ($errors) {
    $_SESSION['form_data'] = $data;
    $_SESSION['form_error'] = implode(' ', array_values($errors));
    redirect('index.php');
}

$_SESSION['form_data'] = $data;
$_SESSION['captcha_verified'] = true;

// Reuse a still-fresh, not-yet-paid order for this session instead of
// asking Razorpay for a brand new one every time the form is (re)posted.
// A Razorpay order stays open for retries until it is paid or expires,
// so this also covers "retry a failed payment" for free, and keeps a
// refreshed/resubmitted form from spamming the Orders API.
$reusableOrderId = (string)($_SESSION['razorpay_order_id'] ?? '');
$reusableOrderAge = time() - (int)($_SESSION['razorpay_order_created_at'] ?? 0);
$canReuseOrder = $reusableOrderId !== ''
    && empty($_SESSION['payment_verified'])
    && $reusableOrderAge >= 0
    && $reusableOrderAge < 1200;

if ($canReuseOrder) {
    $orderId = $reusableOrderId;
} else {
    $pdo = db();
    $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (rate_limit_count($pdo, 'order_create', 'ip:' . $clientIp, 600) >= 8) {
        $_SESSION['form_error'] = 'Too many payment attempts from this network. Please wait a few minutes and try again.';
        redirect('index.php');
    }

    try {
        $_SESSION['payment_verified'] = false;
        unset($_SESSION['payment_id'], $_SESSION['payment_order_id'], $_SESSION['payment_signature'], $_SESSION['payment_amount']);
        $order = razorpay_create_order('MAN2026-' . strtoupper(bin2hex(random_bytes(6))));
        $orderId = (string)($order['id'] ?? '');
        if ($orderId === '' || (int)($order['amount'] ?? 0) !== RAZORPAY_AMOUNT_PAISE) throw new RuntimeException('Razorpay returned an invalid order.');
        $_SESSION['razorpay_order_id'] = $orderId;
        $_SESSION['razorpay_order_created_at'] = time();
        rate_limit_hit($pdo, 'order_create', 'ip:' . $clientIp);
    } catch (Throwable $e) {
        error_log('[Music Awards Registration / Razorpay Order] ' . $e->getMessage());
        $_SESSION['form_error'] = 'We could not start the payment right now. Please try again in a moment.';
        redirect('index.php');
    }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment | 18th Music Awards of Nagaland</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css"></head><body>
<header class="site-header"><div class="header-inner">
<a href="index.php" class="site-brand"><span class="brand-mark"><img src="assets/img/music-awards-logo.png" alt="Music Awards of Nagaland"></span><span class="brand-text"><strong>MUSIC AWARDS</strong><span>OF NAGALAND</span><small>18TH EDITION</small></span></a>
<a href="index.php" class="back-link">← BACK TO FORM</a>
</div></header>
<main><section class="payment-area"><div class="content-width"><div class="payment-card">
<div class="payment-logo"><img src="assets/img/music-awards-logo.png" alt="Music Awards of Nagaland"></div>
<div class="page-eyebrow">STEP 2 · REGISTRATION PAYMENT</div>
<h1>Pay your registration fee</h1>
<p class="payment-intro">Your form has been checked successfully. Complete the registration payment to continue to the final preview.</p>
<div class="payment-amount-box"><div><span>REGISTRATION FEE</span><strong>₹250</strong></div><div class="payment-secure">SECURE RAZORPAY CHECKOUT</div></div>
<div class="payment-summary"><div><span>Artist / Band</span><strong><?= e($data['artist_name']) ?></strong></div><div><span>Category</span><strong><?= e($data['category']) ?></strong></div></div>
<div class="payment-note"><strong>Important</strong><p>Your application is <b>not submitted yet</b>. Payment only unlocks the final review page. Your official application record is created only after you click <b>Final Submit</b> on the review page.</p></div>
<div id="paymentError" class="payment-error" hidden></div>
<button type="button" id="payButton" class="submit-button payment-button"><span>PAY ₹250 &amp; CONTINUE</span><span class="submit-arrow">→</span></button>
<p class="payment-footnote">By continuing, you will be redirected to Razorpay's secure checkout.</p>
</div></div></section></main>
<footer class="site-footer"><div class="footer-inner"><div class="footer-brand"><span class="brand-mark"><img src="assets/img/music-awards-logo.png" alt="Music Awards of Nagaland"></span><div><strong>MUSIC AWARDS OF NAGALAND</strong><span>Artist Submission Portal · 18th Edition</span></div></div><a href="https://musicawardsofnagaland.in/" class="footer-link">BACK TO WEBSITE ↗</a></div></footer>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function(){
 const button=document.getElementById('payButton'), errorBox=document.getElementById('paymentError');
 function showError(message){errorBox.textContent=message;errorBox.hidden=false;button.disabled=false;button.querySelector('span:first-child').textContent='PAY ₹250 & CONTINUE';}
 button.addEventListener('click',function(){
  button.disabled=true; errorBox.hidden=true; button.querySelector('span:first-child').textContent='OPENING SECURE CHECKOUT...';
  const options={
   key:<?= json_encode(RAZORPAY_KEY_ID) ?>, amount:<?= RAZORPAY_AMOUNT_PAISE ?>, currency:<?= json_encode(RAZORPAY_CURRENCY) ?>,
   name:'Music Awards of Nagaland', description:<?= json_encode(RAZORPAY_DESCRIPTION) ?>,
   image:new URL('assets/img/music-awards-logo.png', window.location.href).href, order_id:<?= json_encode($orderId) ?>,
   prefill:{name:<?= json_encode($data['contact_name']) ?>,contact:<?= json_encode($data['contact_number']) ?>},
   notes:{artist_name:<?= json_encode($data['artist_name']) ?>,category:<?= json_encode($data['category']) ?>},
   theme:{color:'#f45a00'},
   modal:{ondismiss:function(){button.disabled=false;button.querySelector('span:first-child').textContent='PAY ₹250 & CONTINUE';}},
   handler:function(response){
    button.querySelector('span:first-child').textContent='VERIFYING PAYMENT...';
    fetch('verify-payment.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({csrf_token:<?= json_encode(csrf_token()) ?>,razorpay_payment_id:response.razorpay_payment_id,razorpay_order_id:response.razorpay_order_id,razorpay_signature:response.razorpay_signature})})
    .then(function(res){return res.json().then(function(data){return{ok:res.ok,data:data};});})
    .then(function(result){if(!result.ok||!result.data.success)throw new Error(result.data.message||'Payment could not be verified.');window.location.href='preview.php';})
    .catch(function(error){showError(error.message||'Payment verification failed. Please contact the organizers before paying again.');});
   }
  };
  const razorpay=new Razorpay(options);
  razorpay.on('payment.failed',function(){showError('Payment was not completed. You have not submitted the application. Please try again.');});
  razorpay.open();
 });
})();
</script></body></html>
