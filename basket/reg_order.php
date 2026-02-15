<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);

require_once("/home3/ctonfugw/public_html/api/jdate.php");
date_default_timezone_set("Asia/Tehran");

function jdate_diff_days($from_jdate, $to_jdate) {
    list($fy, $fm, $fd) = explode('.', $from_jdate);
    list($ty, $tm, $td) = explode('.', $to_jdate);
    list($gy1, $gm1, $gd1) = jalali_to_gregorian($fy, $fm, $fd);
    list($gy2, $gm2, $gd2) = jalali_to_gregorian($ty, $tm, $td);
    $d1 = new DateTime("$gy1-$gm1-$gd1");
    $d2 = new DateTime("$gy2-$gm2-$gd2");
    return $d1->diff($d2)->days;
}

function get_weekday_key($jalali_date) {
    list($jy, $jm, $jd) = explode('.', $jalali_date);
    list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
    $w = date('w', strtotime("$gy-$gm-$gd"));
    $map = ['sun','mon','tues','wed','thurs','fri','sat'];
    return $map[$w];
}

$input = json_decode(file_get_contents('php://input'), true);

$customer_id = intval($input['customer_id'] ?? 0);
$salon_id    = intval($input['salon_id'] ?? 0);
$service_id  = intval($input['service_id'] ?? 0);
$worker_id   = intval($input['worker_id'] ?? 0);
$date        = trim($input['date'] ?? '');
$start_time  = trim($input['start_time'] ?? '');
$end_time    = trim($input['end_time'] ?? '');

if (!$customer_id || !$salon_id || !$service_id || !$worker_id || !$date || !$start_time || !$end_time) {
    echo json_encode(['error' => 'Missing required fields'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$con = mysqli_connect('localhost', 'ctonfugw_shahab', 'kwN?Cx#v77,u', 'ctonfugw_main');
if (!$con) {
    echo json_encode(['error' => 'Database connection failed'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$customer_id = mysqli_real_escape_string($con, $customer_id);
$salon_id    = mysqli_real_escape_string($con, $salon_id);
$service_id  = mysqli_real_escape_string($con, $service_id);
$worker_id   = mysqli_real_escape_string($con, $worker_id);
$date        = mysqli_real_escape_string($con, $date);
$start_time  = mysqli_real_escape_string($con, $start_time);
$end_time    = mysqli_real_escape_string($con, $end_time);

$today_jdate = jdate("Y.m.d", time());

if ($date < $today_jdate) {
    echo json_encode(['error' => 'You cannot reserve for a past date'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    mysqli_close($con);
    exit;
}

if (jdate_diff_days($today_jdate, $date) > 45) {
    echo json_encode(['error' => 'You cannot reserve more than 45 days in advance'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    mysqli_close($con);
    exit;
}

if ($date === $today_jdate) {
    $now_time = date("H:i");
    if ($start_time < $now_time) {
        echo json_encode(['error' => 'Start time must be later than current time'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        mysqli_close($con);
        exit;
    }
}

if ($end_time <= $start_time) {
    echo json_encode(['error' => 'End time must be later than start time'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    mysqli_close($con);
    exit;
}

$wk = get_weekday_key($date);
$sql = "SELECT shift, `$wk` AS t FROM salon_time WHERE salon='$salon_id'";
$res = mysqli_query($con, $sql);

$shifts = [];
while ($r = mysqli_fetch_assoc($res)) {
    if ($r['t'] !== '00:00:00') {
        $shifts[$r['shift']] = $r['t'];
    }
}

$ok = false;
if (isset($shifts['1_s'], $shifts['1_e']) && $start_time >= $shifts['1_s'] && $end_time <= $shifts['1_e']) {
    $ok = true;
}
if (isset($shifts['2_s'], $shifts['2_e']) && $start_time >= $shifts['2_s'] && $end_time <= $shifts['2_e']) {
    $ok = true;
}
if (!$ok) {
    echo json_encode(['error' => 'Reservation time is outside salon working hours'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    mysqli_close($con);
    exit;
}

$sql = "SELECT price FROM service WHERE service='$service_id' AND salon='$salon_id' AND worker='$worker_id' LIMIT 1";
$res = mysqli_query($con, $sql);
if (! $row = mysqli_fetch_assoc($res)) {
    echo json_encode(['error' => 'Service not found'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    mysqli_close($con);
    exit;
}
$price = intval($row['price']);

$now = time();
$rs1 = mysqli_query($con, "SELECT COUNT(*) cnt FROM basket WHERE customer='$customer_id' AND created_at>=" . ($now - 600));
$c1  = intval(mysqli_fetch_assoc($rs1)['cnt']);
$rs2 = mysqli_query($con, "SELECT COUNT(*) cnt FROM turn WHERE customer='$customer_id' AND status=1");
$c2  = intval(mysqli_fetch_assoc($rs2)['cnt']);

if ($c1 + $c2 >= 8) {
    echo json_encode(['error' => 'You cannot add more than 8 active orders'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    mysqli_close($con);
    exit;
}

$sql = "
  SELECT 1 FROM (
    SELECT start_time, end_time FROM basket 
      WHERE salon='$salon_id' AND worker='$worker_id' AND date='$date'
    UNION ALL
    SELECT start_time, end_time FROM turn 
      WHERE salon='$salon_id' AND worker='$worker_id' AND date='$date' AND status=1
  ) x
  WHERE NOT('$end_time'<=start_time OR '$start_time'>=end_time)
  LIMIT 1
";
if (mysqli_num_rows(mysqli_query($con, $sql)) > 0) {
    echo json_encode(['error' => 'Time conflict with another reservation'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    mysqli_close($con);
    exit;
}

$created_at = time();
$sql = "INSERT INTO basket 
    (salon,worker,customer,service,start_time,end_time,date,price,created_at)
  VALUES
    ('$salon_id','$worker_id','$customer_id','$service_id','$start_time','$end_time','$date','$price','$created_at')";
if (mysqli_query($con, $sql)) {
    $order_id = mysqli_insert_id($con);
    $out = ['message'=>'Your order successfully added to basket','order_id'=>$order_id];
} else {
    $out = ['error'=>'Failed to add order to basket'];
}

mysqli_close($con);
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>