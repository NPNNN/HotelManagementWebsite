<?php 

require('admin/inc/db_config.php');
require('admin/inc/essentials.php');
require('razorpay-php/Razorpay.php');

use Razorpay\Api\Api;

date_default_timezone_set("Asia/Kolkata");

session_start();

if(!(isset($_SESSION['login']) && $_SESSION['login'] == true)){
    redirect('index.php');
}

if(isset($_POST['pay_now'])) {
    header("Pragma: no-cache");
    header("Cache-Control: no-cache");
    header("Expires: 0");

    $CUST_ID = $_SESSION['uId'];
    $TXN_AMOUNT = $_SESSION['room']['payment'] * 100; // Convert to paise
echo $TXN_AMOUNT;
    // Initialize Razorpay with your key and secret
    $api_key = 'rzp_test_UUO4CRYrk6tLCW';
    $api_secret = 'PcfSXazSaILfcxG8h6QxvDdT';
    $api = new Api($api_key, $api_secret);

    // Create an order
    $order = $api->order->create([
        'amount' => $TXN_AMOUNT, // amount in paise (100 paise = 1 rupee)
        'currency' => 'INR',
        'receipt' => 'order_rcptid_' . random_int(11111, 9999999)
    ]);

    // Get the Razorpay order ID
    $razorpay_order_id = $order->id;

    // Set your callback URL
    $callback_url = "http://localhost/houseton/pay_response.php";

    // Insert payment data into the database
    $frm_data = filteration($_POST);

    $query1 = "INSERT INTO `booking_order`(`user_id`, `room_id`, `check_in`,`trans_amt`,`check_out`, `order_id`) VALUES (?,?,?,?,?,?)";
    insert($query1, [$CUST_ID, $_SESSION['room']['id'], $frm_data['checkin'],$_SESSION['room']['payment'], $frm_data['checkout'], $razorpay_order_id], 'isssss');
    
    $booking_id = mysqli_insert_id($con);

    $query2 = "INSERT INTO `booking_details`(`booking_id`, `room_name`, `price`, `total_pay`, `user_name`, `phonenum`, `address`) VALUES (?,?,?,?,?,?,?)";
    insert($query2, [$booking_id, $_SESSION['room']['name'], $_SESSION['room']['price'], $TXN_AMOUNT / 100, $frm_data['name'], $frm_data['phonenum'], $frm_data['address']], 'issssss');

    // Include Razorpay Checkout.js library and handle the payment
    echo '
    <html>
      <head>
        <title>Processing</title>
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            function startPayment() {
                var options = {
                    key: "' . $api_key . '",
                    amount: ' . $order->amount . ',
                    currency: "' . $order->currency . '",
                    name: "Your Company Name",
                    description: "Payment for your order",
                    image: "https://cdn.razorpay.com/logos/GhRQcyean79PqE_medium.png",
                    order_id: "' . $razorpay_order_id . '",
                    theme: {
                        color: "#738276"
                    },
                    callback_url: "' . $callback_url . '"
                };
                var rzp = new Razorpay(options);
                rzp.open();
            }
            window.onload = startPayment;
        </script>
      </head>
      <body>
        <button onclick="startPayment()" style="display:none;">Pay with Razorpay</button>
      </body>
    </html>';
}
?>
