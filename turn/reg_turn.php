<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);

require_once("/home3/ctonfugw/public_html/api/jdate.php");

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$customer_id = $data['customer_id'] ?? null;
$pay_state = $data['pay_method'] ?? null;

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);

$customer_id = mysqli_real_escape_string($con, $customer_id);
$pay_state = mysqli_real_escape_string($con, $pay_state);

$pay_state = intval($pay_state);
$created_time = jdate("Y.m.d - H:i", time());

$finalized = [];


$q = "SELECT * FROM basket WHERE customer='$customer_id'";
$res = mysqli_query($con, $q);

while ($row = mysqli_fetch_assoc($res)) {
    $order_id = $row['order_id'];
    $salon    = $row['salon'];
    $service  = $row['service'];
    $worker   = $row['worker'];
    $date     = $row['date'];
    $start    = $row['start_time'];
    $end      = $row['end_time'];
    $price    = $row['price'];

    if ($pay_state === 0) {
        $insert = "INSERT INTO turn (customer, service, salon, worker, status, date, start_time, end_time, service_duration, tolerance, pay_state, price, reg_by, created_time)
                   VALUES ('$customer_id', '$service', '$salon', '$worker', 0, '$date', '$start', '$end', 0, 0, 0, '$price', 1, '$created_time')";
                 
        if (mysqli_query($con, $insert)) {
            $lst = mysqli_insert_id($con);
            $finalized[] = [
                'order_id' => $order_id,
                'reservation_id' => $lst
            ];
        }
    } elseif ($pay_state === 1) {
    
        $finalized[] = [
            'order_id' => $order_id,
            'reservation_id' => null,
            'status' => 'Pending online payment'
        ];
    }
}


mysqli_query($con, "DELETE FROM basket WHERE customer='$customer_id'");

mysqli_close($con);


echo json_encode([
    'customer_id' => $customer_id,
    'pay_method' => $pay_state,
    'finalized_orders' => $finalized
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
