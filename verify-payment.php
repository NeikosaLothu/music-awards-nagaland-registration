<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/razorpay.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit; }

// An order older than this must be re-created rather than verified — keeps
// a very stale order_id lingering in an old session from being paid and
// accepted much later.
const RAZORPAY_ORDER_MAX_AGE_SECONDS = 3600;

try {
    $input=json_decode((string)file_get_contents('php://input'),true);
    if(!is_array($input)) throw new RuntimeException('Invalid payment response.');
    if(!hash_equals(csrf_token(),(string)($input['csrf_token']??''))) throw new RuntimeException('Your payment session has expired. Please start again.');
    if(!razorpay_is_configured()) throw new RuntimeException('Razorpay is not configured.');
    $paymentId=trim((string)($input['razorpay_payment_id']??''));
    $returnedOrderId=trim((string)($input['razorpay_order_id']??''));
    $signature=trim((string)($input['razorpay_signature']??''));
    $sessionOrderId=trim((string)($_SESSION['razorpay_order_id']??''));
    $orderCreatedAt=(int)($_SESSION['razorpay_order_created_at']??0);
    if($paymentId===''||$returnedOrderId===''||$signature===''||$sessionOrderId==='') throw new RuntimeException('Incomplete payment details were received.');
    if(!hash_equals($sessionOrderId,$returnedOrderId)) throw new RuntimeException('Payment order mismatch.');
    if($orderCreatedAt===0||(time()-$orderCreatedAt)>RAZORPAY_ORDER_MAX_AGE_SECONDS) throw new RuntimeException('This payment session has expired. Please start again.');
    if(!razorpay_verify_checkout_signature($sessionOrderId,$paymentId,$signature)) throw new RuntimeException('Payment signature verification failed.');

    try {
        $payment=razorpay_fetch_payment($paymentId);
    } catch (Throwable $e) {
        error_log('[Music Awards Registration / Payment Verification] Razorpay fetch failed: '.$e->getMessage());
        throw new RuntimeException('We could not confirm your payment with Razorpay right now. Please wait a moment and try again, or contact the organizers with payment ID '.$paymentId.'.');
    }

    if((string)($payment['order_id']??'')!==$sessionOrderId) throw new RuntimeException('Payment does not belong to this registration order.');
    if((int)($payment['amount']??0)!==RAZORPAY_AMOUNT_PAISE||(string)($payment['currency']??'')!==RAZORPAY_CURRENCY) throw new RuntimeException('Payment amount or currency does not match the registration fee.');
    if(strtolower((string)($payment['status']??''))!=='captured') throw new RuntimeException('Payment has not been captured yet. Please wait a moment and try again.');

    try {
        $alreadyUsed = is_payment_id_already_used(db(), $paymentId);
    } catch (Throwable $e) {
        error_log('[Music Awards Registration / Payment Verification] DB check failed: '.$e->getMessage());
        throw new RuntimeException('We could not confirm your payment right now. Please wait a moment and try again, or contact the organizers with payment ID '.$paymentId.'.');
    }
    if($alreadyUsed) throw new RuntimeException('This payment has already been used for another submission. Please contact the organizers with your payment ID.');

    // Sensitive state transition — rotate the session ID to reduce session
    // fixation risk now that this session is tied to a real payment.
    session_regenerate_id(true);

    $_SESSION['payment_verified']=true;
    $_SESSION['payment_id']=$paymentId;
    $_SESSION['payment_order_id']=$sessionOrderId;
    $_SESSION['payment_signature']=$signature;
    $_SESSION['payment_amount']=RAZORPAY_AMOUNT_PAISE;
    $_SESSION['payment_verified_at']=time();
    echo json_encode(['success'=>true,'message'=>'Payment verified successfully.']);
} catch(Throwable $e) {
    error_log('[Music Awards Registration / Payment Verification] '.$e->getMessage());
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
