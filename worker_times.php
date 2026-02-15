<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);
require_once("jdf.php");

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$salon_id = $data['salon_id'] ?? null;
$service_id = $data['service_id'] ?? null;
$worker_id = $data['worker_id'] ?? null;
$date = $data['date'] ?? null;
$fd_type = $data['fd_type'] ?? null;

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);

$salon_id = mysqli_real_escape_string($con, $salon_id);
$service_id = mysqli_real_escape_string($con, $service_id);
$worker_id = mysqli_real_escape_string($con, $worker_id);
$date = mysqli_real_escape_string($con, $date);
$fd_type = mysqli_real_escape_string($con, $fd_type);

$valid_check = "SELECT 1 FROM service WHERE service='$service_id' AND salon='$salon_id' AND worker='$worker_id' LIMIT 1";
$valid_result = mysqli_query($con, $valid_check);
if (mysqli_num_rows($valid_result) === 0) {
    mysqli_close($con);
    echo json_encode([
        'error' => 'این سرویس توسط این پرسنل در این سالن ارائه نمی‌شود.',
        'salon_id' => $salon_id,
        'service_id' => $service_id,
        'worker_id' => $worker_id,
        'date' => $date
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function fa_to_en($str) {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($fa, $en, $str);
}

function getPersianWeekday($num) {
    $map = [
        0 => 'شنبه',
        1 => 'یک‌شنبه',
        2 => 'دوشنبه',
        3 => 'سه‌شنبه',
        4 => 'چهارشنبه',
        5 => 'پنج‌شنبه',
        6 => 'جمعه'
    ];
    return $map[$num] ?? '';
}

list($year, $month, $day) = explode('.', $date);
$weekday_number = fa_to_en(jdate('w', jmktime(0, 0, 0, $month, $day, $year)));
$weekday_number = intval($weekday_number);

$weekday_column_map = [
    0 => 'sat',
    1 => 'sun',
    2 => 'mon',
    3 => 'tues',
    4 => 'wed',
    5 => 'thurs',
    6 => 'fri'
];
$column = $weekday_column_map[$weekday_number] ?? null;
$weekday_farsi = getPersianWeekday($weekday_number);

list($gy, $gm, $gd) = jalali_to_gregorian($year, $month, $day);
$gregorian_date = "$gy-$gm-$gd";

$today_ts = strtotime(date('Y-m-d'));
$input_ts = strtotime($gregorian_date);
if ($input_ts < $today_ts) {
    mysqli_close($con);
    echo json_encode([
        'error' => 'امکان انتخاب تاریخ گذشته وجود ندارد.',
        'date' => $date
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$shifts = [];
$query = "SELECT shift, `$column` AS time FROM worker_time WHERE salon='$salon_id' AND worker='$worker_id'";
$result = mysqli_query($con, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $shifts[$row['shift']] = $row['time'];
}

$duration = 30;
$q = "SELECT duration, price FROM service WHERE service='$service_id' AND salon='$salon_id' AND worker='$worker_id'";
$res = mysqli_query($con, $q);
if ($row = mysqli_fetch_assoc($res)) {
    $duration = intval($row['duration']);
    $price = intval($row['price']);
}

function build_slots($start, $end, $duration, $gregorian_date, $date, $salon_id, $worker_id, $con) {
    $slots = [];
    $start_ts = strtotime("$gregorian_date $start");
    $end_ts = strtotime("$gregorian_date $end");
    $now = time();
    if (date('Y-m-d') === $gregorian_date) {
        $start_ts = max($start_ts, $now);
    }

    while ($start_ts + ($duration * 60) <= $end_ts) {
        $slot_start = date('H:i', $start_ts);
        $slot_end = date('H:i', $start_ts + ($duration * 60));

        $check_turn = "SELECT 1 FROM turn WHERE salon='$salon_id' AND worker='$worker_id' AND date='$date' AND status IN (0,1)
                       AND NOT (
                           '$slot_end' <= start_time OR
                           '$slot_start' >= end_time
                       ) LIMIT 1";
        $conflict_turn = mysqli_query($con, $check_turn);
        $conflict1 = mysqli_num_rows($conflict_turn) > 0;

        $check_basket = "SELECT 1 FROM basket WHERE salon='$salon_id' AND worker='$worker_id' AND date='$date'
                         AND created_at >= " . ($now - 600) . "
                         AND NOT (
                             '$slot_end' <= start_time OR
                             '$slot_start' >= end_time
                         ) LIMIT 1";
        $conflict_basket = mysqli_query($con, $check_basket);
        $conflict2 = mysqli_num_rows($conflict_basket) > 0;

        $is_available = !($conflict1 || $conflict2);

        $slots[] = [
            'start_time' => $slot_start,
            'end_time' => $slot_end,
            'is_available' => $is_available
        ];

        $start_ts += ($duration * 60);
    }

    return $slots;
}




foreach ($dates_range as $ts) {
    $jalali_date = fa_to_en(jdate('Y.m.d', $ts));
    $weekday_num = (intval(date('w', $ts)) + 1) % 7;
    $weekday_name = getPersianWeekday($weekday_num);
    $month_name = jdate('F', $ts);
    $year = fa_to_en(jdate('Y', $ts));
    $day = fa_to_en(jdate('d', $ts));
    $is_today = ($ts === $today_ts);
    $is_selected = ($ts === $input_ts);

    $future_dates[] = [
        'date' => $jalali_date,
        'year' => $year,
        'day' => $day,
        'weekday' => $weekday_name,
        'month' => $month_name,
        'is_today' => $is_today,
        'is_selected' => $is_selected
    ];
}



$all_slots = [];
if (isset($shifts['1_s']) && isset($shifts['1_e'])) {
    $all_slots = array_merge($all_slots, build_slots($shifts['1_s'], $shifts['1_e'], $duration, $gregorian_date, $date, $salon_id, $worker_id, $con));
}
if (isset($shifts['2_s']) && isset($shifts['2_e'])) {
    $all_slots = array_merge($all_slots, build_slots($shifts['2_s'], $shifts['2_e'], $duration, $gregorian_date, $date, $salon_id, $worker_id, $con));
}

$response = [
    'salon_id' => $salon_id,
    'service_id' => $service_id,
    'worker_id' => $worker_id,
    'date' => $date,
    'weekday' => $weekday_farsi,
    'price' => $price,
    'slots' => $all_slots,
];
mysqli_close($con);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>