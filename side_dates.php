<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);
require_once("jdf.php");

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$date = $data['date'] ?? null;
$fd_type = $data['fd_type'] ?? '0';
$after = isset($data['after']) ? (int)$data['after'] : 5;
$before = isset($data['before']) ? (int)$data['before'] : 5;
$salon_id = $data['salon_id'] ?? null;
$service_id = $data['service_id'] ?? null;
$worker_id = $data['worker_id'] ?? null;

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);

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

$today_ts = strtotime(date('Y-m-d'));

if ($fd_type == '0' || !$date) {
    $base_ts = $today_ts;
} else {
    list($jy, $jm, $jd) = explode('.', $date);
    $jy = intval(fa_to_en($jy));
    $jm = intval(fa_to_en($jm));
    $jd = intval(fa_to_en($jd));
    list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
    $base_ts = strtotime("$gy-$gm-$gd");
}

$valid_service_q = mysqli_query($con, "SELECT duration FROM service WHERE service='$service_id' AND salon='$salon_id' AND worker='$worker_id' LIMIT 1");
if (!mysqli_num_rows($valid_service_q)) {
    mysqli_close($con);
    echo json_encode([
        'error' => 'سرویس معتبر برای این پرسنل و سالن یافت نشد.',
        'side_dates' => []
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
$duration = intval(mysqli_fetch_assoc($valid_service_q)['duration']);

$calendar_setting = 0;
$salon_q = mysqli_query($con, "SELECT calendar_holidays FROM salon WHERE ID='$salon_id' LIMIT 1");
if ($row = mysqli_fetch_assoc($salon_q)) {
    $calendar_setting = intval($row['calendar_holidays']);
}

$dates_range = [];
for ($i = -$before; $i <= $after; $i++) {
    $ts = strtotime("+$i day", $base_ts);
    if ($ts >= $today_ts) {
        $dates_range[] = $ts;
    }
}

$side_dates = [];
foreach ($dates_range as $ts) {
    $jalali_date = fa_to_en(jdate('Y.m.d', $ts));
    list($jy, $jm, $jd) = explode('.', $jalali_date);
    $weekday_num = (intval(date('w', $ts)) + 1) % 7;
    $weekday_name = getPersianWeekday($weekday_num);
    $month_name = jdate('F', $ts);
    $year = fa_to_en(jdate('Y', $ts));
    $day = fa_to_en(jdate('d', $ts));
    $is_today = ($ts === $today_ts);
    $is_selected = ($ts === $base_ts);
    $is_available = false;

    $holiday_check = mysqli_query($con, "SELECT 1 FROM calendar_holidays WHERE date='$jalali_date' LIMIT 1");
    $is_holiday = mysqli_num_rows($holiday_check) > 0;

    if ($is_holiday && $calendar_setting == 0) {
        $is_available = false;
    } else {
        $weekday_column_map = [
            0 => 'sat',
            1 => 'sun',
            2 => 'mon',
            3 => 'tues',
            4 => 'wed',
            5 => 'thurs',
            6 => 'fri'
        ];
        $column = $weekday_column_map[$weekday_num] ?? null;

        $shift_q = mysqli_query($con, "SELECT shift, `$column` AS time FROM worker_time WHERE salon='$salon_id' AND worker='$worker_id'");
        $shifts = [];
        while ($row = mysqli_fetch_assoc($shift_q)) {
            $shifts[$row['shift']] = $row['time'];
        }

        $gregorian_date = implode('-', jalali_to_gregorian($jy, $jm, $jd));
        $now = time();
        foreach (['1', '2'] as $shift_num) {
            $start = $shifts["{$shift_num}_s"] ?? null;
            $end = $shifts["{$shift_num}_e"] ?? null;
            if ($start && $end) {
                $start_ts = strtotime("$gregorian_date $start");
                $end_ts = strtotime("$gregorian_date $end");
                if (date('Y-m-d') === $gregorian_date) {
                    $start_ts = max($start_ts, $now);
                }
                while ($start_ts + ($duration * 60) <= $end_ts) {
                    $slot_start = date('H:i', $start_ts);
                    $slot_end = date('H:i', $start_ts + ($duration * 60));

                    $conflict1 = mysqli_num_rows(mysqli_query($con, "SELECT 1 FROM turn WHERE salon='$salon_id' AND worker='$worker_id' AND date='$jalali_date' AND status IN (0,1) AND NOT ('$slot_end' <= start_time OR '$slot_start' >= end_time) LIMIT 1")) > 0;
                    $conflict2 = mysqli_num_rows(mysqli_query($con, "SELECT 1 FROM basket WHERE salon='$salon_id' AND worker='$worker_id' AND date='$jalali_date' AND created_at >= " . ($now - 600) . " AND NOT ('$slot_end' <= start_time OR '$slot_start' >= end_time) LIMIT 1")) > 0;

                    if (!($conflict1 || $conflict2)) {
                        $is_available = true;
                        break 2;
                    }
                    $start_ts += ($duration * 60);
                }
            }
        }
    }

    $side_dates[] = [
        'date' => $jalali_date,
        'year' => $year,
        'day' => $day,
        'weekday' => $weekday_name,
        'month' => $month_name,
        'is_today' => $is_today,
        'is_selected' => $is_selected,
        'is_available' => $is_available
    ];
}

mysqli_close($con);
echo json_encode([
    'side_dates' => $side_dates
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>