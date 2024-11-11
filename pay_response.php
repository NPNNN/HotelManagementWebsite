<?php

require('admin/inc/db_config.php');
require('admin/inc/essentials.php');
require('razorpay-php/Razorpay.php');

use Razorpay\Api\Api;

date_default_timezone_set("Asia/Kolkata");

session_start();

function regenrate_session($uid)
{
    $user_q = select("SELECT * FROM `user_cred` WHERE `id`=? LIMIT 1", [$uid], 'i');
    $user_fetch = mysqli_fetch_assoc($user_q);

    $_SESSION['login'] = true;
    $_SESSION['uId'] = $user_fetch['id'];
    $_SESSION['uName'] = $user_fetch['name'];
    $_SESSION['uPic'] = $user_fetch['profile'];
    $_SESSION['uPhone'] = $user_fetch['phonenum'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the payment details from the Razorpay response
    $razorpay_payment_id = $_POST['razorpay_payment_id'] ?? null;
    $razorpay_order_id = $_POST['razorpay_order_id'] ?? null;
    $razorpay_signature = $_POST['razorpay_signature'] ?? null;

    // Ensure all required POST parameters are received
    if (!$razorpay_payment_id || !$razorpay_order_id || !$razorpay_signature) {
        // redirect('index.php');
    }

    // Initialize Razorpay with your key and secret
    $api_key = 'rzp_test_UUO4CRYrk6tLCW';
    $api_secret = 'PcfSXazSaILfcxG8h6QxvDdT';
    $api = new Api($api_key, $api_secret);

    // Fetch the order details from the database
    $slct_query = "SELECT `booking_id`, `user_id` FROM `booking_order` WHERE `order_id`=?";
    $stmt = $con->prepare($slct_query);
    $stmt->bind_param('s', $razorpay_order_id);
    $stmt->execute();
    $slct_res = $stmt->get_result();

    if ($slct_res->num_rows == 0) {
        // redirect('index.php');
    }

    $slct_fetch = $slct_res->fetch_assoc();

    if (!(isset($_SESSION['login']) && $_SESSION['login'] == true)) {
        regenrate_session($slct_fetch['user_id']);
    }

    // Verify the signature to ensure the response is from Razorpay
    $attributes = array(
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature' => $razorpay_signature
    );

    try {
        $api->utility->verifyPaymentSignature($attributes);
echo $razorpay_order_id;
        // If the verification is successful, update the booking status
        $upd_query = "UPDATE `booking_order` SET `booking_status`='booked', `trans_id`=?, `trans_status`='TXN_SUCCESS', `trans_resp_msg`='Payment successful' WHERE `order_id`=?";
        $stmt = $con->prepare($upd_query);
        $stmt->bind_param('si', $razorpay_payment_id, $razorpay_order_id);
        $stmt->execute();

    } catch (\Exception $e) {
        // If verification fails, update the booking status as payment failed
        $upd_query = "UPDATE `booking_order` SET `booking_status`='payment failed', `trans_id`=?, `trans_amt`=?, `trans_status`='TXN_FAILED', `trans_resp_msg`=? WHERE `order_id`=?";
        $stmt = $con->prepare($upd_query);
        $stmt->bind_param('sisi', $razorpay_payment_id, $_POST['amount'], $e->getMessage(), $razorpay_order_id);
        $stmt->execute();
    }

    redirect('pay_status.php?order=' . $razorpay_order_id);
} else {
    redirect('index.php');
}
?>
