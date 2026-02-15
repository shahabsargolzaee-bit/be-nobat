<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');

require_once '/home3/ctonfugw/public_html/api/jdate.php';

$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');

function respond($code, $data) {
    global $con;
    if (isset($con) && $con) @mysqli_close($con);
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

if (!$con) respond(500, ['error'=>'Database connection failed']);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) respond(400, ['error'=>'Invalid JSON input']);

$required = ['salon_id','worker_id','customer_id','service_id','date','start_time','end_time'];
foreach ($required as $k) {
    if (!isset($in[$k]) || $in[$k] === '') respond(422, ['error'=>"Field {$k} is required"]);
}

$salon_id    = mysqli_real_escape_string($con, (string)$in['salon_id']);
$worker_id   = mysqli_real_escape_string($con, (string)$in['worker_id']);
$customer_id = mysqli_real_escape_string($con, (string)$in['customer_id']);
$service_id  = mysqli_real_escape_string($con, (string)$in['service_id']);
$date_in     = mysqli_real_escape_string($con, (string)$in['date']);
$start_time_in = mysqli_real_escape_string($con, (string)$in['start_time']);
$end_time_in   = mysqli_real_escape_string($con, (string)$in['end_time']);

if (!preg_match('/^\d+$/', $salon_id) || !preg_match('/^\d+$/', $worker_id) || !preg_match('/^\d+$/', $customer_id) || !preg_match('/^\d+$/', $service_id)) {
    respond(422, ['error'=>'salon_id, worker_id, customer_id and service_id must be numeric']);
}

if (!preg_match('/^\d{4}\.\d{2}\.\d{2}$/', $date_in)) {
    respond(422, ['error'=>'Date must be Jalali in format YYYY.MM.DD (example: 1404.09.25)']);
}

$today_j = jdate('Y.m.d');
if (strcmp($date_in, $today_j) < 0) respond(422, ['error'=>'Date must not be before today']);

if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time_in)) {
    $start_time = $start_time_in;
} elseif (preg_match('/^\d{2}:\d{2}$/', $start_time_in)) {
    $start_time = $start_time_in.':00';
} else {
    respond(422, ['error'=>'Invalid start_time format. Example: 13:00 or 13:00:00']);
}

if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time_in)) {
    $end_time = $end_time_in;
} elseif (preg_match('/^\d{2}:\d{2}$/', $end_time_in)) {
    $end_time = $end_time_in.':00';
} else {
    respond(422, ['error'=>'Invalid end_time format. Example: 14:00 or 14:00:00']);
}

try {
    $dt_start = new DateTime(substr($start_time, 0, 5) . ":00");
    $dt_end   = new DateTime(substr($end_time, 0, 5) . ":00");
} catch (Exception $e) {
    respond(422, ['error'=>'Provided times are not parsable']);
}
if ($dt_end <= $dt_start) respond(422, ['error'=>'end_time must be after start_time']);

if ($date_in === $today_j) {
    $now_time = date('H:i:s');
    try {
        $dt_now = new DateTime(substr($now_time, 0, 5) . ":00");
    } catch (Exception $e) {
        respond(500, ['error'=>'Server time error']);
    }
    if ($dt_start <= $dt_now) respond(422, ['error'=>'start_time must be after current time for today']);
}

list($sh, $sm) = explode(':', substr($start_time, 0, 5));
list($eh, $em) = explode(':', substr($end_time, 0, 5));
$service_duration = (intval($eh) * 60 + intval($em)) - (intval($sh) * 60 + intval($sm));
if ($service_duration <= 0) respond(422, ['error'=>'service_duration must be positive']);

$qSalon = mysqli_query($con, "SELECT ID, calendar_holidays FROM salon WHERE ID='".mysqli_real_escape_string($con, $salon_id)."' LIMIT 1");
if (!$qSalon) respond(500, ['error'=>'Failed to query salon', 'mysql_error'=>mysqli_error($con)]);
if (mysqli_num_rows($qSalon) === 0) respond(404, ['error'=>'Salon not found']);
$salonRow = mysqli_fetch_assoc($qSalon);
$calendar_holidays_flag = isset($salonRow['calendar_holidays']) ? intval($salonRow['calendar_holidays']) : 1;
if ($calendar_holidays_flag === 0) {
    $chk = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM calendar_holidays WHERE date='".mysqli_real_escape_string($con, $date_in)."' LIMIT 1");
    if (!$chk) respond(500, ['error'=>'Failed to check calendar holidays', 'mysql_error'=>mysqli_error($con)]);
    $cnt = intval(mysqli_fetch_assoc($chk)['cnt']);
    if ($cnt > 0) respond(422, ['error'=>'Requested date is a holiday and bookings are not allowed for this salon']);
}

$qWorker = mysqli_query($con, "SELECT lst, username FROM users WHERE lst='".mysqli_real_escape_string($con, $worker_id)."' LIMIT 1");
if (!$qWorker) respond(500, ['error'=>'Failed to query worker', 'mysql_error'=>mysqli_error($con)]);
if (mysqli_num_rows($qWorker) === 0) respond(404, ['error'=>'Worker not found']);
$workerRow = mysqli_fetch_assoc($qWorker);
$worker_username = $workerRow['username'];

$qTeam = mysqli_query($con, "SELECT LST FROM teams WHERE salon='".mysqli_real_escape_string($con, $salon_id)."' AND worker_user='".mysqli_real_escape_string($con, $worker_username)."' LIMIT 1");
if (!$qTeam) respond(500, ['error'=>'Failed to check team membership', 'mysql_error'=>mysqli_error($con)]);
if (mysqli_num_rows($qTeam) === 0) respond(403, ['error'=>'Worker is not a member of this salon']);

$qCustomer = mysqli_query($con, "SELECT lst FROM users WHERE lst='".mysqli_real_escape_string($con, $customer_id)."' LIMIT 1");
if (!$qCustomer) respond(500, ['error'=>'Failed to query customer', 'mysql_error'=>mysqli_error($con)]);
if (mysqli_num_rows($qCustomer) === 0) respond(404, ['error'=>'Customer not found']);

$qService = mysqli_query($con,
    "SELECT LST, duration, price FROM service
     WHERE service='".mysqli_real_escape_string($con, $service_id)."'
       AND salon='".mysqli_real_escape_string($con, $salon_id)."'
       AND worker='".mysqli_real_escape_string($con, $worker_id)."'
     LIMIT 1"
);
if (!$qService) respond(500, ['error'=>'Failed to check service availability', 'mysql_error'=>mysqli_error($con)]);
if (mysqli_num_rows($qService) === 0) respond(422, ['error'=>'This service is not offered by the specified worker in this salon']);
$serviceRow = mysqli_fetch_assoc($qService);
$service_price = isset($serviceRow['price']) ? intval($serviceRow['price']) : 0;
$service_duration_from_table = isset($serviceRow['duration']) ? intval($serviceRow['duration']) : $service_duration;

$day_col_map = [
    0 => 'sun',
    1 => 'mon',
    2 => 'tues',
    3 => 'wed',
    4 => 'thurs',
    5 => 'fri',
    6 => 'sat'
];
list($jy,$jm,$jd) = explode('.', $date_in);
list($gy,$gm,$gd) = jalali_to_gregorian((int)$jy,(int)$jm,(int)$jd);
$w = date('w', strtotime("$gy-$gm-$gd"));
$day_col = $day_col_map[intval($w)];

$wt_sql = "SELECT shift, sat, sun, mon, tues, wed, thurs, fri FROM worker_time WHERE worker='".mysqli_real_escape_string($con, $worker_id)."' AND salon='".mysqli_real_escape_string($con, $salon_id)."'";
$wt_res = mysqli_query($con, $wt_sql);
if (!$wt_res) respond(500, ['error'=>'Failed to fetch worker_time', 'mysql_error'=>mysqli_error($con)]);
$shifts = [];
while ($r = mysqli_fetch_assoc($wt_res)) {
    $shifts[$r['shift']] = $r[$day_col] ?? null;
}
$allowed_intervals = [];
if (isset($shifts['1_s']) && isset($shifts['1_e']) && $shifts['1_s'] !== '00:00:00' && $shifts['1_e'] !== '00:00:00') {
    $allowed_intervals[] = ['start'=>$shifts['1_s'],'end'=>$shifts['1_e']];
}
if (isset($shifts['2_s']) && isset($shifts['2_e']) && $shifts['2_s'] !== '00:00:00' && $shifts['2_e'] !== '00:00:00') {
    $allowed_intervals[] = ['start'=>$shifts['2_s'],'end'=>$shifts['2_e']];
}
if (empty($allowed_intervals)) respond(422, ['error'=>'Worker has no working hours for this day in this salon']);

$ok_within_shift = false;
foreach ($allowed_intervals as $int) {
    try {
        $int_start = new DateTime(substr($int['start'],0,5).":00");
        $int_end = new DateTime(substr($int['end'],0,5).":00");
    } catch (Exception $e) {
        continue;
    }
    if ($dt_start >= $int_start && $dt_end <= $int_end) {
        $ok_within_shift = true;
        break;
    }
}
if (!$ok_within_shift) respond(422, ['error'=>'Requested time is outside worker shifts']);

$turns_sql = "
    SELECT start_time, end_time
    FROM turn
    WHERE salon='".mysqli_real_escape_string($con, $salon_id)."' AND worker='".mysqli_real_escape_string($con, $worker_id)."' AND date='".mysqli_real_escape_string($con, $date_in)."' AND status NOT IN (3,4)
";
$turns_res = mysqli_query($con, $turns_sql);
if (!$turns_res) respond(500, ['error'=>'Failed to fetch existing turns for worker', 'mysql_error'=>mysqli_error($con)]);
while ($ex = mysqli_fetch_assoc($turns_res)) {
    $ex_start = $ex['start_time'];
    $ex_end = $ex['end_time'];
    try {
        $ex_dt_start = new DateTime(substr($ex_start,0,5).":00");
        $ex_dt_end = new DateTime(substr($ex_end,0,5).":00");
    } catch (Exception $e) {
        continue;
    }
    if (!($dt_end <= $ex_dt_start || $dt_start >= $ex_dt_end)) {
        respond(409, ['error'=>'Requested time overlaps an existing reservation for this worker']);
    }
}

$customer_turns_sql = "
    SELECT start_time, end_time, salon, worker
    FROM turn
    WHERE customer='".mysqli_real_escape_string($con, $customer_id)."' AND date='".mysqli_real_escape_string($con, $date_in)."' AND status NOT IN (3,4)
";
$customer_turns_res = mysqli_query($con, $customer_turns_sql);
if (!$customer_turns_res) respond(500, ['error'=>'Failed to fetch existing turns for customer', 'mysql_error'=>mysqli_error($con)]);
while ($ex = mysqli_fetch_assoc($customer_turns_res)) {
    $ex_start = $ex['start_time'];
    $ex_end = $ex['end_time'];
    try {
        $ex_dt_start = new DateTime(substr($ex_start,0,5).":00");
        $ex_dt_end = new DateTime(substr($ex_end,0,5).":00");
    } catch (Exception $e) {
        continue;
    }
    if (!($dt_end <= $ex_dt_start || $dt_start >= $ex_dt_end)) {
        respond(409, ['error'=>'Customer has another reservation at the same time']);
    }
}

$customer_val = intval($customer_id);
$service_val  = intval($service_id);
$salon_val    = intval($salon_id);
$worker_val   = intval($worker_id);
$status_val   = 1;
$date_val     = "'".$date_in."'";
$start_val    = "'".$start_time."'";
$end_val      = "'".$end_time."'";
$duration_val = intval($service_duration_from_table);
$tolerance_val = 0;
$pay_state_val = 0;
$price_val     = intval($service_price);
$reg_by_val    = 1;
$created_time  = jdate('Y.m.d - H:i');
$created_val   = "'".$created_time."'";
$memo_val      = "NULL";

$fields = "customer, service, salon, worker, status, date, start_time, end_time, service_duration, tolerance, pay_state, price, reg_by, created_time, memo";
$sql = "INSERT INTO turn ($fields) VALUES (
    {$customer_val},
    {$service_val},
    {$salon_val},
    {$worker_val},
    {$status_val},
    {$date_val},
    {$start_val},
    {$end_val},
    {$duration_val},
    {$tolerance_val},
    {$pay_state_val},
    {$price_val},
    {$reg_by_val},
    {$created_val},
    {$memo_val}
)";

$ins = mysqli_query($con, $sql);
if (!$ins) respond(500, ['error'=>'Insert failed', 'mysql_error' => mysqli_error($con)]);

$last_id = mysqli_insert_id($con);

respond(201, [
    'message' => 'Turn created successfully',
    'turn' => [
        'LST' => $last_id,
        'customer' => $customer_val,
        'service' => $service_val,
        'salon' => $salon_val,
        'worker' => $worker_val,
        'status' => $status_val,
        'date' => $date_in,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'service_duration' => $duration_val,
        'tolerance' => $tolerance_val,
        'pay_state' => $pay_state_val,
        'price' => $price_val,
        'reg_by' => $reg_by_val,
        'created_time' => $created_time,
        'memo' => null
    ]
]);
?>
