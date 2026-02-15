<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$order_id = $data['order_id'] ?? null;
$customer_id = $data['customer_id'] ?? null;

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);

$order_id = mysqli_real_escape_string($con, $order_id);
$customer_id = mysqli_real_escape_string($con, $customer_id);


$query = "
SELECT 
    b.order_id,
    s.title AS salon_name,
    sd.service AS service_name,
    sd.sub_service,
    wu.fname AS worker_fname,
    wu.lname AS worker_lname,
    cu.fname AS customer_fname,
    cu.lname AS customer_lname,
    b.start_time,
    b.end_time,
    b.date,
    b.price
FROM basket b
INNER JOIN salon s ON b.salon = s.ID
INNER JOIN service_detail sd ON b.service = sd.service_id
INNER JOIN users wu ON b.worker = wu.lst
INNER JOIN users cu ON b.customer = cu.lst
WHERE b.order_id = '$order_id' AND b.customer = '$customer_id'
LIMIT 1
";

$result = mysqli_query($con, $query);

if ($row = mysqli_fetch_assoc($result)) {
    $response = [
        'salon_name'     => $row['salon_name'],
        'service_name'   => $row['service_name'],
        'sub_service'    => $row['sub_service'],
        'worker_name'    => $row['worker_fname'] . ' ' . $row['worker_lname'],
        'customer_name'  => $row['customer_fname'] . ' ' . $row['customer_lname'],
        'start_time'     => $row['start_time'],
        'end_time'       => $row['end_time'],
        'date'           => $row['date'],
        'price'          => $row['price']
    ];
} else {
    $response = [
        'error' => 'No such order found in your basket'
    ];
}

mysqli_close($con);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
