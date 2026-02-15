<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$customer_id = $data['customer_id'] ?? null;
$order_id = $data['order_id'] ?? null;

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);

$customer_id = mysqli_real_escape_string($con, $customer_id);
$order_id = mysqli_real_escape_string($con, $order_id);


$check = "SELECT 1 FROM basket WHERE order_id='$order_id' AND customer='$customer_id' LIMIT 1";
$result = mysqli_query($con, $check);

if (mysqli_num_rows($result) > 0) {
   
    $delete = "DELETE FROM basket WHERE order_id='$order_id' AND customer='$customer_id'";
    if (mysqli_query($con, $delete)) {
        $response = [
            'message' => "Order #$order_id successfully removed from basket"
        ];
    } else {
        $response = [
            'error' => "Failed to remove order #$order_id from basket"
        ];
    }
} else {
    $response = [
        'error' => "No such order found in your basket"
    ];
}

mysqli_close($con);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
