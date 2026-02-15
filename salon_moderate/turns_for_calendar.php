<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');

require_once '/home3/ctonfugw/public_html/api/jdate.php';

function is_valid_jdate(string $d): bool {
    return (bool) preg_match('/^\d{4}\.(0[1-9]|1[0-2])\.(0[1-9]|[12]\d|3[01])$/', $d);
}

function is_valid_time(string $t): bool {
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $t);
}

function get_weekday(string $jalali_date): string {
    list($jy,$jm,$jd) = explode('.', $jalali_date);
    list($gy,$gm,$gd) = jalali_to_gregorian((int)$jy,(int)$jm,(int)$jd);
    $w = date('w', strtotime("$gy-$gm-$gd"));
    $map = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه','شنبه'];
    return $map[$w];
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

$in = json_decode(file_get_contents('php://input'), true);

$salon_id        = $in['salon_id']        ?? null;
$type            = $in['type']            ?? null;
$worker_user     = trim($in['worker_user']   ?? '');
$worker_name     = trim($in['worker_name']   ?? '');
$worker_family   = trim($in['worker_family'] ?? '');
$customer_user   = trim($in['customer_user']   ?? '');
$customer_name   = trim($in['customer_name']   ?? '');
$customer_family = trim($in['customer_family'] ?? '');
$service_name    = trim($in['service_name']    ?? '');
$date_start      = trim($in['date_start']      ?? '');
$date_end        = trim($in['date_end']        ?? '');
$start_time      = trim($in['start_time']      ?? '');
$end_time        = trim($in['end_time']        ?? '');
$price_min       = isset($in['price_min']) ? intval($in['price_min']) : null;
$price_max       = isset($in['price_max']) ? intval($in['price_max']) : null;
$pay_state       = isset($in['pay_state'])   ? intval($in['pay_state'])   : null;
$from            = max(0, intval($in['from']  ?? 0));
$to_request      = max(0, intval($in['to']    ?? 0));

if (!is_numeric($salon_id)) {
    echo json_encode(['error'=>'پارامتر salon_id اجباری است و باید عدد باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
if (!is_numeric($type) || $type < 0 || $type > 6) {
    echo json_encode(['error'=>'پارامتر type باید عدد بین 0 تا 6 باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
if ($date_start && !is_valid_jdate($date_start)) {
    echo json_encode(['error'=>'فرمت date_start باید YYYY.MM.DD باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
if ($date_end && !is_valid_jdate($date_end)) {
    echo json_encode(['error'=>'فرمت date_end باید YYYY.MM.DD باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
if ($date_start && $date_end && $date_end < $date_start) {
    echo json_encode(['error'=>'date_end نباید کمتر از date_start باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
if ($start_time && !is_valid_time($start_time)) {
    echo json_encode(['error'=>'فرمت start_time باید HH:MM یا HH:MM:SS باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
if ($end_time && !is_valid_time($end_time)) {
    echo json_encode(['error'=>'فرمت end_time باید HH:MM یا HH:MM:SS باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
if ($start_time && $end_time && $end_time < $start_time) {
    echo json_encode(['error'=>'end_time نباید کمتر از start_time باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
if ($price_min !== null && $price_max !== null && $price_max < $price_min) {
    echo json_encode(['error'=>'price_max نباید کمتر از price_min باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}
if ($pay_state !== null && ($pay_state < 0 || $pay_state > 1)) {
    echo json_encode(['error'=>'pay_state باید 0 یا 1 باشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) {
    echo json_encode(['error'=>'اتصال به پایگاه داده برقرار نشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$where = ["t.salon = '".mysqli_real_escape_string($con, $salon_id)."'"];

switch ((int)$type) {
    case 1:
        $where[] = "t.status = 0";
        break;
    case 2:
        $where[] = "t.status = 1";
        break;
    case 3:
        $where[] = "t.status = 4";
        break;
    case 4:
        $where[] = "t.status = 3";
        break;
    case 5:
        $where[] = "t.status = 2";
        break;
    case 6:
        $today_j = jdate('Y.m.d');
        $where[] = "t.date = '".mysqli_real_escape_string($con, $today_j)."'";
        break;
}

if ($type !== 6) {
    if ($date_start) $where[] = "t.date >= '".mysqli_real_escape_string($con, $date_start)."'";
    if ($date_end)   $where[] = "t.date <= '".mysqli_real_escape_string($con, $date_end)."'";
}

if ($start_time) $where[] = "t.start_time >= '".mysqli_real_escape_string($con, $start_time)."'";
if ($end_time)   $where[] = "t.end_time <= '".mysqli_real_escape_string($con, $end_time)."'";
if ($price_min !== null) $where[] = "t.price >= ".intval($price_min);
if ($price_max !== null) $where[] = "t.price <= ".intval($price_max);
if ($pay_state !== null) $where[] = "t.pay_state = ".intval($pay_state);

if ($worker_user) {
    $wu = mysqli_real_escape_string($con, $worker_user);
    $wq = mysqli_query($con, "SELECT lst FROM users WHERE username = '$wu' LIMIT 1");
    if ($wr = mysqli_fetch_assoc($wq)) {
        $where[] = "t.worker = ".intval($wr['lst']);
    } else {
        echo json_encode(['error'=>'ورکر یافت نشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        mysqli_close($con);
        exit;
    }
}

if ($worker_name)   $where[] = "w.fname LIKE '%".mysqli_real_escape_string($con, $worker_name)."%'";
if ($worker_family) $where[] = "w.lname LIKE '%".mysqli_real_escape_string($con, $worker_family)."%'";
if ($customer_user) {
    $cu = mysqli_real_escape_string($con, $customer_user);
    $uq = mysqli_query($con, "SELECT lst FROM users WHERE username = '$cu' LIMIT 1");
    if ($ur = mysqli_fetch_assoc($uq)) {
        $where[] = "t.customer = ".intval($ur['lst']);
    } else {
        echo json_encode(['error'=>'مشتری یافت نشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        mysqli_close($con);
        exit;
    }
}
if ($customer_name)   $where[] = "u.fname LIKE '%".mysqli_real_escape_string($con, $customer_name)."%'";
if ($customer_family) $where[] = "u.lname LIKE '%".mysqli_real_escape_string($con, $customer_family)."%'";

if ($service_name) {
    $sn = mysqli_real_escape_string($con, $service_name);
    $sr = mysqli_query($con, "
        SELECT service_id
          FROM service_detail
         WHERE service    LIKE '%$sn%'
            OR sub_service LIKE '%$sn%'
    ");
    $ids = [];
    while ($r = mysqli_fetch_assoc($sr)) {
        $ids[] = intval($r['service_id']);
    }
    if (empty($ids)) {
        echo json_encode(['error'=>'سرویسی یافت نشد'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        mysqli_close($con);
        exit;
    }
    $where[] = "t.service IN (".implode(',',$ids).")";
}

$where_sql = implode(' AND ', $where);

$count_q = "
  SELECT COUNT(*) AS cnt
    FROM turn            t
    JOIN users           u  ON u.lst = t.customer
    JOIN users           w  ON w.lst = t.worker
    JOIN service_detail  sd ON sd.service_id = t.service
   WHERE $where_sql
";
$rs = mysqli_query($con, $count_q);
$total = intval(mysqli_fetch_assoc($rs)['cnt']);

$to_actual = min($to_request ?: $total, $total);
$limit     = max(0, $to_actual - $from);

if ($date_start && $date_end) {
    $range_start = $date_start;
    $range_end = $date_end;
} else {
    $where_no_date = $where;
    $where_no_date = array_values(array_filter($where_no_date, function($w){ return stripos($w, "t.date") === false; }));
    $where_no_date_sql = implode(' AND ', $where_no_date);
    $minmax_q = "
      SELECT MIN(t.date) AS min_date, MAX(t.date) AS max_date
      FROM turn t
      WHERE $where_no_date_sql
    ";
    $mmr = mysqli_query($con, $minmax_q);
    $min_date = null; $max_date = null;
    if ($mmr) {
        $mm = mysqli_fetch_assoc($mmr);
        $min_date = $mm['min_date'] ?: null;
        $max_date = $mm['max_date'] ?: null;
    }
    if ($min_date && $max_date) {
        $range_start = $min_date;
        $range_end = $max_date;
    } else {
        $range_start = jdate('Y.m.d');
        $ts = strtotime('+6 day', time());
        $range_end = jdate('Y.m.d', $ts);
    }
}

$all_dates = jalali_range($range_start, $range_end);
if (empty($all_dates)) {
    echo json_encode(['error'=>'Unable to build date range'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    mysqli_close($con);
    exit;
}

$where_with_range = $where;
$has_date_constraint = false;
foreach ($where_with_range as $w) {
    if (stripos($w, "t.date") !== false) { $has_date_constraint = true; break; }
}
if (!$has_date_constraint) {
    $where_with_range[] = "t.date >= '".mysqli_real_escape_string($con, $range_start)."'";
    $where_with_range[] = "t.date <= '".mysqli_real_escape_string($con, $range_end)."'";
}
$where_with_range_sql = implode(' AND ', $where_with_range);

$list_q = "
  SELECT
    t.LST               AS turn_id,
    t.date              AS date,
    t.start_time        AS start_time,
    t.end_time          AS end_time,
    t.price             AS price,
    t.pay_state         AS pay_state,
    t.status            AS status,
    u.username          AS customer_phone,
    u.fname             AS customer_name,
    u.lname             AS customer_family,
    w.username          AS worker_phone,
    w.fname             AS worker_name,
    w.lname             AS worker_family,
    sd.service          AS service_category,
    sd.sub_service      AS service_name,
    t.service_duration  AS service_duration
  FROM turn            t
  JOIN users           u  ON u.lst = t.customer
  JOIN users           w  ON w.lst = t.worker
  JOIN service_detail  sd ON sd.service_id = t.service
  WHERE $where_with_range_sql
  ORDER BY t.date DESC, t.start_time ASC, t.LST DESC
";
$res = mysqli_query($con, $list_q);
if (!$res) {
    echo json_encode(['error'=>'Query failed', 'mysql_error'=>mysqli_error($con)], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    mysqli_close($con);
    exit;
}

$team_colors = [];
$team_q = mysqli_query($con, "SELECT worker_user, color FROM teams WHERE salon='".mysqli_real_escape_string($con, $salon_id)."'");
if ($team_q) {
    while ($tr = mysqli_fetch_assoc($team_q)) {
        $team_colors[$tr['worker_user']] = $tr['color'];
    }
}

$grouped = [];
$total_returned = 0;

while ($row = mysqli_fetch_assoc($res)) {
    $date = $row['date'];
    if (!isset($grouped[$date])) {
        $grouped[$date] = [
            'date' => $date,
            'weekday' => get_weekday($date),
            'count' => 0,
            'turns' => [],
        ];
    }
    $turn = [
        'turn_id'           => intval($row['turn_id']),
        'start_time'        => $row['start_time'],
        'end_time'          => $row['end_time'],
        'price'             => intval($row['price']),
        'payment_type'      => ($row['pay_state'] === '0' || $row['pay_state'] === 0) ? 'حضوری' : 'آنلاین',
        'status'            => $row['status'],
        'customer' => [
            'phone' => $row['customer_phone'],
            'fname' => $row['customer_name'],
            'lname' => $row['customer_family'],
        ],
        'worker' => [
            'phone' => $row['worker_phone'],
            'fname' => $row['worker_name'],
            'lname' => $row['worker_family'],
            'color' => isset($team_colors[$row['worker_phone']]) ? $team_colors[$row['worker_phone']] : null
        ],
        'service' => [
            'category' => $row['service_category'],
            'name' => $row['service_name'],
            'duration' => intval($row['service_duration']),
        ],
    ];
    $grouped[$date]['turns'][] = $turn;
    $grouped[$date]['count']++;
    $total_returned++;
}

foreach ($all_dates as $d) {
    if (!isset($grouped[$d])) {
        $grouped[$d] = [
            'date' => $d,
            'weekday' => get_weekday($d),
            'count' => 0,
            'turns' => [],
        ];
    }
}

$dates = array_keys($grouped);
usort($dates, function($a, $b){
    if ($a === $b) return 0;
    return ($a < $b) ? 1 : -1;
});
$by_date_desc = [];
foreach (array_reverse($dates) as $d) {
    $by_date_desc[$d] = $grouped[$d];
}
mysqli_close($con);

echo json_encode([
    'type'        => (int)$type,
    'total'       => $total,
    'range_start' => $range_start,
    'range_end'   => $range_end,
    'from'        => $from,
    'to'          => $to_request ?: $total,
    'returned'    => $total_returned,
    'by_date'     => $by_date_desc,
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
?>
