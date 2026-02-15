<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
date_default_timezone_set('Asia/Tehran');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
header('Content-Type: application/json; charset=UTF-8');
require_once '/home3/ctonfugw/public_html/api/jdate.php';

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$raw = json_decode(file_get_contents('php://input'), true);

$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error'=>'database connection failed']);
mysqli_set_charset($con, 'utf8mb4');

$salon_id   = intval(mysqli_real_escape_string($con, $raw['salon_id']   ?? '0'));
$start_date = mysqli_real_escape_string($con, $raw['start_date'] ?? '');
$end_date   = mysqli_real_escape_string($con, $raw['end_date']   ?? '');

if ($salon_id <= 0)                                   respond(400, ['error'=>'salon_id is required']);
if (!preg_match('/^\d{4}\.\d{2}\.\d{2}$/', $start_date)) respond(400, ['error'=>'start_date invalid']);
if (!preg_match('/^\d{4}\.\d{2}\.\d{2}$/', $end_date))   respond(400, ['error'=>'end_date invalid']);
if ($start_date > $end_date)                           respond(400, ['error'=>'start_date must be <= end_date']);

$stmt = mysqli_prepare($con, "
    SELECT
      p.`date`       AS trans_date,
      p.`time`       AS trans_time,
      p.`price`      AS price,
      uc.username    AS customer_phone,
      CONCAT(uc.fname,' ',uc.lname) AS customer_name,
      uw.username    AS worker_phone,
      CONCAT(uw.fname,' ',uw.lname) AS worker_name,
      s.title        AS salon_name,
      p.refid,
      p.pay_method,
      p.type
    FROM pays p
    JOIN users uc ON uc.lst   = p.customer
    JOIN users uw ON uw.lst   = p.worker
    JOIN salon s  ON s.ID      = p.salon
    WHERE p.salon = ? AND p.`date` BETWEEN ? AND ?
    ORDER BY p.`date`, p.`time`
");
mysqli_stmt_bind_param($stmt, 'iss', $salon_id, $start_date, $end_date);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$monthNames = [
    '01'=>'فروردین','02'=>'اردیبهشت','03'=>'خرداد','04'=>'تیر',
    '05'=>'مرداد','06'=>'شهریور','07'=>'مهر','08'=>'آبان',
    '09'=>'آذر','10'=>'دی','11'=>'بهمن','12'=>'اسفند'
];

$transactions = [];
while ($row = mysqli_fetch_assoc($res)) {
    list($jy, $jm, $jd) = explode('.', $row['trans_date']);
    $monthName = $monthNames[$jm] ?? '';
    $transactions[] = [
        'date'           => $row['trans_date'],
        'time'           => $row['trans_time'],
        'month_name'     => $monthName,
        'price'          => intval($row['price']),
        'customer_phone' => $row['customer_phone'],
        'customer_name'  => $row['customer_name'],
        'worker_phone'   => $row['worker_phone'],
        'worker_name'    => $row['worker_name'],
        'salon_name'     => $row['salon_name'],
        'refid'          => $row['refid'],
        'pay_method'     => $row['pay_method'] === '0' ? 'آنلاین' : 'حضوری',
        'type'           => $row['type'] === '0'       ? 'سایر'   : 'فروش'
    ];
}

mysqli_close($con);
respond(200, ['count'=>count($transactions), 'transactions'=>$transactions]);
?>