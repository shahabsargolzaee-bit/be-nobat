<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');

require_once '/home3/ctonfugw/public_html/api/jdate.php';

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$con = mysqli_connect(
    'localhost',
    'ctonfugw_shahab',
    'kwN?Cx#v77,u',
    'ctonfugw_main'
);

if (!$con) {
    respond(500, ['error' => 'Database connection failed']);
}

$in = json_decode(file_get_contents('php://input'), true);

function int_from_input(mysqli $con, array $in, string $key, bool $required = false): ?int {
    if (!is_array($in) || !array_key_exists($key, $in)) {
        if ($required) return null;
        return null;
    }
    $raw = $in[$key];
    if ($raw === null || $raw === '') {
        if ($required) return null;
        return null;
    }
    $escaped = mysqli_real_escape_string($con, (string)$raw);
    if (!preg_match('/^-?\d+$/', $escaped)) return null;
    return (int)$escaped;
}

function status_text(int $s): string {
    if ($s === 0) return 'منتظر تأیید';
    if ($s === 1) return 'تأیید شده';
    if ($s === 4) return 'لغو توسط مشتری';
    if ($s === 3) return 'لغو توسط سالن/ورکر';
    if ($s === 2) return 'انجام شده';
    return 'نامشخص';
}

function pay_type_text(int $t): string {
    if ($t === 0) return 'فروش';
    if ($t === 1) return 'سایر';
    return 'نامشخص';
}

$type = int_from_input($con, $in, 'type', true);
$salon_id = int_from_input($con, $in, 'salon_id', true);
$count_days = int_from_input($con, $in, 'count_days');
$statusFilter = int_from_input($con, $in, 'status');

if ($type === null) {
    respond(400, ['error' => 'Parameter type is required']);
}

if ($salon_id === null) {
    respond(400, ['error' => 'Parameter salon_id is required']);
}

if (in_array($type, [0,1,2], true)) {
    if ($count_days === null) respond(400, ['error' => 'Parameter count_days is required for this type', 'range' => '1..30']);
    if ($count_days < 1 || $count_days > 30) respond(400, ['error' => 'count_days must be between 1 and 30']);
}

$allowedStatuses = [0,1,2,3,4];
if ($statusFilter !== null && !in_array($statusFilter, $allowedStatuses, true)) {
    respond(400, ['error' => 'Invalid status value', 'allowed' => $allowedStatuses]);
}

$salon_id_esc = mysqli_real_escape_string($con, (string)$salon_id);
$checkSalonSql = "SELECT ID FROM salon WHERE ID = '{$salon_id_esc}' LIMIT 1";
$salonRes = mysqli_query($con, $checkSalonSql);
if (!$salonRes || mysqli_num_rows($salonRes) === 0) {
    respond(404, ['error' => 'salon_id not found', 'salon_id' => $salon_id]);
}

function jalali_today(): string { return jdate('Y.m.d'); }
function jalali_time_now(): string { return date('H:i:s'); }
function jalali_dates_list(int $days, bool $future = false, bool $include_today = true): array {
    $out = [];
    $startOffset = $future ? 1 : 0;
    $base = time();
    for ($i = 0; $i < $days; $i++) {
        $offset = $future ? ($startOffset + $i) : ($include_today ? $i : ($i + 1));
        $ts = strtotime(($future ? "+{$offset} day" : "-{$offset} day"), $base);
        $out[] = jdate('Y.m.d', $ts);
    }
    return array_values(array_unique($out));
}
function in_list_escaped(mysqli $con, array $vals): string {
    $valsEsc = array_map(function ($v) use ($con) { return "'" . mysqli_real_escape_string($con, (string)$v) . "'"; }, $vals);
    return implode(',', $valsEsc);
}
function fetch_all(mysqli $con, string $sql): array {
    $res = mysqli_query($con, $sql);
    if (!$res) return [];
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) $out[] = $row;
    return $out;
}

$salonCond = "turn.salon = '{$salon_id_esc}'";
$paySalonCond = "p.salon = '{$salon_id_esc}'";
$regCond = "turn.reg_by = 1";
$statusSqlCond = (in_array($type, [0,1,3], true) && $statusFilter !== null) ? "AND turn.status = " . mysqli_real_escape_string($con, (string)$statusFilter) : '';
$timeNow = jalali_time_now();
$todayJ = jalali_today();

$response = ['type' => $type];

switch ($type) {
    case 0:
        $dates = jalali_dates_list($count_days, false, true);
        $in = in_list_escaped($con, $dates);
        $sql = "
            SELECT turn.date AS jdate, COUNT(*) AS turns_count, COALESCE(SUM(price),0) AS sales_sum
            FROM turn
            WHERE {$salonCond} AND {$regCond} AND turn.date IN ({$in}) {$statusSqlCond}
            GROUP BY turn.date
        ";
        $rows = fetch_all($con, $sql);
        $map = [];
        $totalTurns = 0;
        $totalSales = 0;
        foreach ($rows as $r) {
            $map[$r['jdate']] = ['date' => $r['jdate'], 'turns_count' => (int)$r['turns_count'], 'sales_sum' => (int)$r['sales_sum']];
            $totalTurns += (int)$r['turns_count'];
            $totalSales += (int)$r['sales_sum'];
        }
        $ordered = [];
        foreach ($dates as $d) $ordered[] = $map[$d] ?? ['date' => $d, 'turns_count' => 0, 'sales_sum' => 0];
        $response['data'] = [
            'past_N_days_daily' => $ordered,
            'summary' => ['total_days' => count($dates), 'total_turns' => $totalTurns, 'total_sales' => $totalSales],
            'count_days' => $count_days
        ];
        break;

    case 1:
        $dates = jalali_dates_list($count_days, true, false);
        $in = in_list_escaped($con, $dates);
        $sql = "
            SELECT
                turn.LST,
                turn.date,
                turn.start_time,
                turn.end_time,
                turn.price,
                turn.service AS service_id,
                s.service AS service_type_id,
                c.lst AS customer_id,
                c.username AS customer_phone,
                c.fname AS customer_fname,
                c.lname AS customer_lname,
                w.lst AS worker_id,
                w.fname AS worker_fname,
                w.lname AS worker_lname,
                sd.service AS service_name,
                sd.sub_service AS sub_service_name,
                sd.gender AS service_gender,
                turn.status AS status
            FROM turn
            LEFT JOIN users c ON c.lst = turn.customer
            LEFT JOIN users w ON w.lst = turn.worker
            LEFT JOIN service s ON s.LST = turn.service
            LEFT JOIN service_detail sd ON sd.service_id = s.service
            WHERE {$salonCond} AND {$regCond} AND turn.date IN ({$in}) {$statusSqlCond}
            ORDER BY turn.date ASC, turn.start_time ASC
        ";
        $rows = fetch_all($con, $sql);
        $grouped = []; foreach ($dates as $d) $grouped[$d] = [];
        $total = 0;
        foreach ($rows as $r) {
            $st = isset($r['status']) ? (int)$r['status'] : -1;
            $grouped[$r['date']][] = [
                'turn_id' => (int)$r['LST'],
                'date' => $r['date'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time'],
                'price' => (int)$r['price'],
                'status_text' => status_text($st),
                'service' => [
                    'id' => (int)$r['service_id'],
                    'type_id' => isset($r['service_type_id']) ? (int)$r['service_type_id'] : null,
                    'name' => $r['service_name'] ?? null,
                    'sub_service' => $r['sub_service_name'] ?? null,
                    'gender' => isset($r['service_gender']) ? (int)$r['service_gender'] : null,
                ],
                'customer' => [
                    'id' => isset($r['customer_id']) ? (int)$r['customer_id'] : null,
                    'phone' => $r['customer_phone'] ?? null,
                    'fname' => $r['customer_fname'] ?? null,
                    'lname' => $r['customer_lname'] ?? null,
                ],
                'worker' => [
                    'id' => isset($r['worker_id']) ? (int)$r['worker_id'] : null,
                    'fname' => $r['worker_fname'] ?? null,
                    'lname' => $r['worker_lname'] ?? null,
                ],
            ];
            $total++;
        }
        $out = [];
        foreach ($dates as $d) $out[] = ['date' => $d, 'reservations_count' => count($grouped[$d]), 'reservations' => $grouped[$d]];
        $response['data'] = ['next_N_days_details' => $out, 'summary' => ['total_days' => count($dates), 'total_reservations' => $total], 'count_days' => $count_days];
        break;

    case 2:
        $days = $count_days ?? 3;
        $dates = jalali_dates_list($days, false, true);
        $in = in_list_escaped($con, $dates);
        $turnSql = "
            SELECT status, COUNT(*) AS cnt
            FROM turn
            WHERE {$salonCond} AND {$regCond} AND turn.date IN ({$in})
            GROUP BY status
            ORDER BY cnt DESC
        ";
        $turnRows = fetch_all($con, $turnSql);
        $turn_mapped = []; $turn_total = 0;
        foreach ($turnRows as $r) { $s = (int)$r['status']; $turn_mapped[] = ['status_text' => status_text($s), 'count' => (int)$r['cnt']]; $turn_total += (int)$r['cnt']; }
        $paySql = "
            SELECT p.date AS pdate,
                   COUNT(*) AS payments_count,
                   COALESCE(SUM(p.price),0) AS payments_sum,
                   SUM(CASE WHEN p.type = 0 THEN 1 ELSE 0 END) AS sales_count,
                   COALESCE(SUM(CASE WHEN p.type = 0 THEN p.price ELSE 0 END),0) AS sales_sum
            FROM pays p
            WHERE {$paySalonCond} AND p.date IN ({$in})
            GROUP BY p.date
            ORDER BY p.date DESC
        ";
        $payRows = fetch_all($con, $paySql);
        $pay_map = [];
        $totalPayments = 0;
        $totalPaymentsSum = 0;
        $totalSalesCount = 0;
        $totalSalesSum = 0;
        foreach ($payRows as $p) {
            $pay_map[$p['pdate']] = [
                'date' => $p['pdate'],
                'payments_count' => (int)$p['payments_count'],
                'payments_sum' => (int)$p['payments_sum'],
                'sales_count' => (int)$p['sales_count'],
                'sales_sum' => (int)$p['sales_sum'],
            ];
            $totalPayments += (int)$p['payments_count'];
            $totalPaymentsSum += (int)$p['payments_sum'];
            $totalSalesCount += (int)$p['sales_count'];
            $totalSalesSum += (int)$p['sales_sum'];
        }
        $ordered_pays = [];
        foreach ($dates as $d) $ordered_pays[] = $pay_map[$d] ?? ['date' => $d, 'payments_count' => 0, 'payments_sum' => 0, 'sales_count' => 0, 'sales_sum' => 0];
        $response['data'] = [
            'turns_by_status' => $turn_mapped,
            'turns_summary' => ['total_turns' => $turn_total, 'days' => count($dates)],
            'payments_by_day' => $ordered_pays,
            'payments_summary' => [
                'total_payments_count' => $totalPayments,
                'total_payments_sum' => $totalPaymentsSum,
                'total_sales_count' => $totalSalesCount,
                'total_sales_sum' => $totalSalesSum,
            ],
            'count_days' => $days
        ];
        break;

    case 3:
        $todayEsc = mysqli_real_escape_string($con, $todayJ);
        $timeEsc = mysqli_real_escape_string($con, $timeNow);
        $sql = "
            SELECT
                turn.LST,
                turn.date,
                turn.start_time,
                turn.end_time,
                turn.price,
                turn.service AS service_id,
                s.service AS service_type_id,
                c.lst AS customer_id,
                c.username AS customer_phone,
                c.fname AS customer_fname,
                c.lname AS customer_lname,
                w.lst AS worker_id,
                w.fname AS worker_fname,
                w.lname AS worker_lname,
                sd.service AS service_name,
                sd.sub_service AS sub_service_name,
                sd.gender AS service_gender,
                turn.status AS status
            FROM turn
            LEFT JOIN users c ON c.lst = turn.customer
            LEFT JOIN users w ON w.lst = turn.worker
            LEFT JOIN service s ON s.LST = turn.service
            LEFT JOIN service_detail sd ON sd.service_id = s.service
            WHERE {$salonCond} AND {$regCond} AND turn.date = '{$todayEsc}' AND turn.start_time > '{$timeEsc}' {$statusSqlCond}
            ORDER BY turn.start_time ASC
        ";
        $rows = fetch_all($con, $sql);
        $mapped = [];
        foreach ($rows as $r) {
            $st = isset($r['status']) ? (int)$r['status'] : -1;
            $mapped[] = [
                'turn_id' => (int)$r['LST'],
                'date' => $r['date'],
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time'],
                'price' => (int)$r['price'],
                'status_text' => status_text($st),
                'service' => [
                    'id' => (int)$r['service_id'],
                    'type_id' => isset($r['service_type_id']) ? (int)$r['service_type_id'] : null,
                    'name' => $r['service_name'] ?? null,
                    'sub_service' => $r['sub_service_name'] ?? null,
                    'gender' => isset($r['service_gender']) ? (int)$r['service_gender'] : null,
                ],
                'customer' => [
                    'id' => isset($r['customer_id']) ? (int)$r['customer_id'] : null,
                    'phone' => $r['customer_phone'] ?? null,
                    'fname' => $r['customer_fname'] ?? null,
                    'lname' => $r['customer_lname'] ?? null,
                ],
                'worker' => [
                    'id' => isset($r['worker_id']) ? (int)$r['worker_id'] : null,
                    'fname' => $r['worker_fname'] ?? null,
                    'lname' => $r['worker_lname'] ?? null,
                ],
            ];
        }
        $response['data'] = ['today_upcoming' => $mapped, 'summary' => ['total_upcoming' => count($mapped)]];
        break;

    case 4:
        $y = (int)jdate('Y');
        $m = (int)jdate('m');
        $thisPrefix = sprintf('%04d.%02d.', $y, $m);
        if ($m === 1) { $lastY = $y - 1; $lastM = 12; } else { $lastY = $y; $lastM = $m - 1; }
        $lastPrefix = sprintf('%04d.%02d.', $lastY, $lastM);
        $thisPrefixEsc = mysqli_real_escape_string($con, $thisPrefix);
        $lastPrefixEsc = mysqli_real_escape_string($con, $lastPrefix);
        $topThisSql = "
            SELECT turn.service AS service_id, sd.service AS service_name, sd.sub_service AS sub_service_name, COUNT(*) AS reservations
            FROM turn
            LEFT JOIN service s ON s.LST = turn.service
            LEFT JOIN service_detail sd ON sd.service_id = s.service
            WHERE {$salonCond} AND {$regCond} AND turn.date LIKE '{$thisPrefixEsc}%'
            GROUP BY turn.service
            ORDER BY reservations DESC
        ";
        $topLastSql = "
            SELECT turn.service AS service_id, sd.service AS service_name, sd.sub_service AS sub_service_name, COUNT(*) AS reservations
            FROM turn
            LEFT JOIN service s ON s.LST = turn.service
            LEFT JOIN service_detail sd ON sd.service_id = s.service
            WHERE {$salonCond} AND {$regCond} AND turn.date LIKE '{$lastPrefixEsc}%'
            GROUP BY turn.service
            ORDER BY reservations DESC
        ";
        $thisList = fetch_all($con, $topThisSql);
        $lastList = fetch_all($con, $topLastSql);
        $totalThis = 0; foreach ($thisList as $r) $totalThis += (int)$r['reservations'];
        $totalLast = 0; foreach ($lastList as $r) $totalLast += (int)$r['reservations'];
        $response['data'] = [
            'top_services_current_month' => $thisList,
            'top_services_last_month' => $lastList,
            'summary' => ['total_reservations_current_month' => $totalThis, 'total_reservations_last_month' => $totalLast],
        ];
        break;

    case 5:
        $y = (int)jdate('Y');
        $m = (int)jdate('m');
        $thisPrefix = sprintf('%04d.%02d.', $y, $m);
        if ($m === 1) { $lastY = $y - 1; $lastM = 12; } else { $lastY = $y; $lastM = $m - 1; }
        $lastPrefix = sprintf('%04d.%02d.', $lastY, $lastM);
        $thisPrefixEsc = mysqli_real_escape_string($con, $thisPrefix);
        $lastPrefixEsc = mysqli_real_escape_string($con, $lastPrefix);
        $lastMonthSql = "
            SELECT worker AS worker_id, COUNT(*) AS reservations
            FROM turn
            WHERE {$salonCond} AND {$regCond} AND turn.date LIKE '{$lastPrefixEsc}%'
            GROUP BY worker
            ORDER BY reservations DESC
        ";
        $thisMonthSql = "
            SELECT worker AS worker_id, COUNT(*) AS reservations
            FROM turn
            WHERE {$salonCond} AND {$regCond} AND turn.date LIKE '{$thisPrefixEsc}%'
            GROUP BY worker
            ORDER BY reservations DESC
        ";
        $lastList = fetch_all($con, $lastMonthSql);
        $thisList = fetch_all($con, $thisMonthSql);
        $totalLast = 0; foreach ($lastList as $r) $totalLast += (int)$r['reservations'];
        $totalThis = 0; foreach ($thisList as $r) $totalThis += (int)$r['reservations'];
        $response['data'] = [
            'last_month' => $lastList,
            'this_month' => $thisList,
            'summary' => ['total_reservations_last_month' => $totalLast, 'total_reservations_this_month' => $totalThis],
        ];
        break;

    default:
        mysqli_close($con);
        respond(400, ['error' => 'Invalid type value', 'valid_types' => [0,1,2,3,4,5]]);
}

mysqli_close($con);
respond(200, $response);
?>