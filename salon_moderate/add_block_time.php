<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');

require_once '/home3/ctonfugw/public_html/api/jdate.php';

function respond(int $code, $data) {
    global $con;
    if (isset($con) && $con) @mysqli_close($con);
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function is_valid_jdate(string $d): bool {
    return (bool) preg_match('/^\d{4}\.(0[1-9]|1[0-2])\.(0[1-9]|[12]\d|3[01])$/', $d);
}

function jalali_range(string $start, string $end): array {
    $out = [];
    list($sy,$sm,$sd) = explode('.', $start);
    list($ey,$em,$ed) = explode('.', $end);
    list($gsy,$gsm,$gsd) = jalali_to_gregorian((int)$sy,(int)$sm,(int)$sd);
    list($gey,$gem,$ged) = jalali_to_gregorian((int)$ey,(int)$em,(int)$ed);
    $ts = strtotime("$gsy-$gsm-$gsd");
    $end_ts = strtotime("$gey-$gem-$ged");
    if ($ts === false || $end_ts === false) return $out;
    while ($ts <= $end_ts) {
        $out[] = jdate('Y.m.d', $ts);
        $ts = strtotime('+1 day', $ts);
    }
    return $out;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) respond(400, ['error' => 'Invalid JSON input']);

$required = ['salon_id','worker_id','date_start','start_time','end_time','repeat','title'];
foreach ($required as $k) {
    if (!isset($input[$k]) || $input[$k] === '') respond(422, ['error'=>"Field {$k} is required"]);
}

$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error'=>'Database connection failed']);

$salon_id_raw   = $input['salon_id'];
$worker_id_raw  = $input['worker_id'];
$date_start_raw = $input['date_start'];
$start_time_in  = $input['start_time'];
$end_time_in    = $input['end_time'];
$repeat_raw     = $input['repeat'];
$date_end_raw   = $input['date_end'] ?? null;
$memo_raw       = $input['memo'] ?? null;
$title_raw      = $input['title'];

$salon_id   = mysqli_real_escape_string($con, (string)$salon_id_raw);
$worker_id  = mysqli_real_escape_string($con, (string)$worker_id_raw);
$date_start = mysqli_real_escape_string($con, (string)$date_start_raw);
$start_time = mysqli_real_escape_string($con, (string)$start_time_in);
$end_time   = mysqli_real_escape_string($con, (string)$end_time_in);
$repeat     = intval($repeat_raw);
$date_end   = $date_end_raw !== null ? mysqli_real_escape_string($con, (string)$date_end_raw) : null;
$memo       = $memo_raw === null ? null : mysqli_real_escape_string($con, (string)$memo_raw);
     
$title   = mysqli_real_escape_string($con, (string)$title_raw);

if (!preg_match('/^\d+$/', $salon_id) || !preg_match('/^\d+$/', $worker_id)) respond(422, ['error'=>'salon_id and worker_id must be numeric']);

if ($repeat !== 0 && $repeat !== 1) respond(422, ['error'=>'repeat must be 0 or 1']);

if (!is_valid_jdate($date_start)) respond(422, ['error'=>'date_start must be Jalali YYYY.MM.DD']);
if ($repeat === 1) {
    if ($date_end === null || $date_end === '') respond(422, ['error'=>'date_end is required when repeat is 1']);
    if (!is_valid_jdate($date_end)) respond(422, ['error'=>'date_end must be Jalali YYYY.MM.DD']);
    if (strcmp($date_end, $date_start) < 0) respond(422, ['error'=>'date_end must not be before date_start']);
}

if (!is_string($title) || $title === '') respond(422, ['error'=>'title is required']);
if (mb_strlen($title) > 50) respond(422, ['error'=>'title must be at most 50 characters']);

if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) respond(422, ['error'=>'Invalid start_time format. Example: 13:00 or 13:00:00']);
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) respond(422, ['error'=>'Invalid end_time format. Example: 14:00 or 14:00:00']);

if (preg_match('/^\d{2}:\d{2}$/', $start_time)) $start_time .= ':00';
if (preg_match('/^\d{2}:\d{2}$/', $end_time)) $end_time .= ':00';

try {
    $dt_start_time = new DateTime(substr($start_time,0,5) . ":00");
    $dt_end_time   = new DateTime(substr($end_time,0,5) . ":00");
} catch (Exception $e) {
    respond(422, ['error'=>'Provided times are not parsable']);
}
if ($dt_end_time <= $dt_start_time) respond(422, ['error'=>'end_time must be after start_time']);

$today_j = jdate('Y.m.d');
$now_time = date('H:i:s');

if (strcmp($date_start, $today_j) < 0) respond(422, ['error'=>'date_start must not be before today']);
if ($date_start === $today_j) {
    try {
        $dt_now = new DateTime(substr($now_time,0,5) . ":00");
    } catch (Exception $e) {
        respond(500, ['error'=>'Server time error']);
    }
    if ($dt_start_time <= $dt_now) respond(422, ['error'=>'start_time must be after current time for today']);
}

$qSalon = mysqli_query($con, "SELECT ID FROM salon WHERE ID='".mysqli_real_escape_string($con,$salon_id)."' LIMIT 1");
if (!$qSalon) respond(500, ['error'=>'Failed to query salon', 'mysql_error'=>mysqli_error($con)]);
if (mysqli_num_rows($qSalon) === 0) respond(404, ['error'=>'Salon not found']);

$qWorker = mysqli_query($con, "SELECT lst, username FROM users WHERE lst='".mysqli_real_escape_string($con,$worker_id)."' LIMIT 1");
if (!$qWorker) respond(500, ['error'=>'Failed to query worker', 'mysql_error'=>mysqli_error($con)]);
if (mysqli_num_rows($qWorker) === 0) respond(404, ['error'=>'Worker not found']);
$workerRow = mysqli_fetch_assoc($qWorker);
$worker_username = $workerRow['username'];

$qTeam = mysqli_query($con, "SELECT LST FROM teams WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker_user='".mysqli_real_escape_string($con,$worker_username)."' LIMIT 1");
if (!$qTeam) respond(500, ['error'=>'Failed to check team membership', 'mysql_error'=>mysqli_error($con)]);
if (mysqli_num_rows($qTeam) === 0) respond(403, ['error'=>'Worker is not a member of this salon (teams check)']);

if ($repeat === 1) {
    $dates = jalali_range($date_start, $date_end);
} else {
    $dates = [$date_start];
}
if (empty($dates)) respond(422, ['error'=>'No dates to process']);

$conflicts = [];
$existing_block_conflicts = [];
$insert_ids = [];

$new_start_time_obj = $dt_start_time;
$new_end_time_obj   = $dt_end_time;

foreach ($dates as $d) {
    if (strcmp($d, $today_j) < 0) {
        $conflicts[] = ['date'=>$d, 'error'=>'date is before today'];
        continue;
    }

    $q1 = "
      SELECT LST, start_time, end_time, date, reg_by, status
      FROM turn
      WHERE salon='".mysqli_real_escape_string($con,$salon_id)."'
        AND worker='".mysqli_real_escape_string($con,$worker_id)."'
        AND date='".mysqli_real_escape_string($con,$d)."'
        AND reg_by = 1
        AND status NOT IN (3,4)
    ";
    $r1 = mysqli_query($con, $q1);
    if (!$r1) respond(500, ['error'=>'Failed to query existing reservations', 'mysql_error'=>mysqli_error($con)]);
    while ($ex = mysqli_fetch_assoc($r1)) {
        $ex_start = substr($ex['start_time'],0,5) . ":00";
        $ex_end   = substr($ex['end_time'],0,5) . ":00";
        try {
            $ex_dt_start = new DateTime($ex_start);
            $ex_dt_end   = new DateTime($ex_end);
        } catch (Exception $e) {
            continue;
        }
        if (!($new_end_time_obj <= $ex_dt_start || $new_start_time_obj >= $ex_dt_end)) {
            $conflicts[] = [
                'date' => $d,
                'conflict_type' => 'reservation',
                'turn_id' => intval($ex['LST']),
                'start_time' => $ex['start_time'],
                'end_time' => $ex['end_time'],
                'status' => intval($ex['status'])
            ];
        }
    }

    $q2 = "
      SELECT LST, start_time, end_time, date, reg_by
      FROM turn
      WHERE salon='".mysqli_real_escape_string($con,$salon_id)."'
        AND worker='".mysqli_real_escape_string($con,$worker_id)."'
        AND date='".mysqli_real_escape_string($con,$d)."'
        AND reg_by = 0
    ";
    $r2 = mysqli_query($con, $q2);
    if (!$r2) respond(500, ['error'=>'Failed to query existing blocks', 'mysql_error'=>mysqli_error($con)]);
    while ($ex = mysqli_fetch_assoc($r2)) {
        $ex_start = substr($ex['start_time'],0,5) . ":00";
        $ex_end   = substr($ex['end_time'],0,5) . ":00";
        try {
            $ex_dt_start = new DateTime($ex_start);
            $ex_dt_end   = new DateTime($ex_end);
        } catch (Exception $e) {
            continue;
        }
        if (!($new_end_time_obj <= $ex_dt_start || $new_start_time_obj >= $ex_dt_end)) {
            $existing_block_conflicts[] = [
                'date' => $d,
                'conflict_type' => 'existing_block',
                'turn_id' => intval($ex['LST']),
                'start_time' => $ex['start_time'],
                'end_time' => $ex['end_time']
            ];
        }
    }
}

if (!empty($conflicts) || !empty($existing_block_conflicts)) {
    $payload = [
        'error' => 'Conflicts detected, no blocks were created',
        'conflicts' => $conflicts,
        'existing_blocks' => $existing_block_conflicts
    ];
    respond(409, $payload);
}

$fields = "customer, service, salon, worker, status, date, start_time, end_time, service_duration, tolerance, pay_state, price, reg_by, created_time, memo, title";
$created_time = jdate('Y.m.d - H:i');

foreach ($dates as $d) {
    $val_customer = 1;
    $val_service  = 1;
    $val_salon    = intval($salon_id);
    $val_worker   = intval($worker_id);
    $val_status   = 0;
    $val_date     = "'".mysqli_real_escape_string($con, $d)."'";
    $val_start    = "'".mysqli_real_escape_string($con, $start_time)."'";
    $val_end      = "'".mysqli_real_escape_string($con, $end_time)."'";
    $val_duration = intval((intval(substr($end_time,0,2))*60 + intval(substr($end_time,3,2))) - (intval(substr($start_time,0,2))*60 + intval(substr($start_time,3,2))));
    $val_tolerance = 0;
    $val_pay = 0;
    $val_price = 0;
    $val_reg = 0;
    $val_created = "'".mysqli_real_escape_string($con, $created_time)."'";
    $val_memo = $memo === null ? "NULL" : "'".$memo."'";
    $val_title = "'".mysqli_real_escape_string($con, mb_substr($title, 0, 50))."'";

    $sql = "INSERT INTO turn ($fields) VALUES (
        {$val_customer},
        {$val_service},
        {$val_salon},
        {$val_worker},
        {$val_status},
        {$val_date},
        {$val_start},
        {$val_end},
        {$val_duration},
        {$val_tolerance},
        {$val_pay},
        {$val_price},
        {$val_reg},
        {$val_created},
        {$val_memo},
        {$val_title}
    )";

    $ins = mysqli_query($con, $sql);
    if (!$ins) {
        respond(500, ['error'=>'Insert failed', 'mysql_error' => mysqli_error($con)]);
    }
    $insert_ids[] = mysqli_insert_id($con);
}

respond(201, [
    'message' => 'Block time(s) created successfully',
    'repeat' => $repeat,
    'date_start' => $date_start,
    'date_end' => $repeat === 1 ? $date_end : null,
    'title' => $title,
    'created_ids' => $insert_ids
]);
?>
