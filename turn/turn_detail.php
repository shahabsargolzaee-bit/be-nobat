<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$reservation_id = $data['reservation_id'] ?? null;
$customer_id = $data['customer_id'] ?? null;

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);

$reservation_id = mysqli_real_escape_string($con, $reservation_id);
$customer_id = mysqli_real_escape_string($con, $customer_id);


$query = "
SELECT 
    t.LST AS reservation_id,
    s.title AS salon_name,
    s.address AS salon_address,
    sd.service AS service_name,
    sd.sub_service,
    wu.fname AS worker_fname,
    wu.lname AS worker_lname,
    cu.fname AS customer_fname,
    cu.lname AS customer_lname,
    t.start_time,
    t.end_time,
    t.date,
    t.price,
    t.status,
    t.pay_state,
    t.created_time
FROM turn t
INNER JOIN salon s ON t.salon = s.ID
INNER JOIN service_detail sd ON t.service = sd.service_id
INNER JOIN users wu ON t.worker = wu.lst
INNER JOIN users cu ON t.customer = cu.lst
WHERE t.LST = '$reservation_id' AND t.customer = '$customer_id'
LIMIT 1
";

$result = mysqli_query($con, $query);

if ($row = mysqli_fetch_assoc($result)) {
    $response = [
        'reservation_id' => $row['reservation_id'],
        'salon_name'     => $row['salon_name'],
          'salon_address'     => $row['salon_address'],
        'service_name'   => $row['service_name'],
        'sub_service'    => $row['sub_service'],
        'worker_name'    => $row['worker_fname'] . ' ' . $row['worker_lname'],
        'customer_name'  => $row['customer_fname'] . ' ' . $row['customer_lname'],
        'start_time'     => $row['start_time'],
        'end_time'       => $row['end_time'],
        'date'           => $row['date'],
        'price'          => $row['price'],
        'status'         => $row['status'],
        'pay_state'      => $row['pay_state'],
        'created_time'   => $row['created_time']
    ];
} else {
    $response = [
        'error' => 'No reservation found for this customer with the given ID'
    ];
}

mysqli_close($con);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
