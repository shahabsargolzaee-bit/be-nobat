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

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$raw          = json_decode(file_get_contents('php://input'), true);
$con          = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error'=>'database connection failed']);
mysqli_set_charset($con, 'utf8mb4');

$salon_id     = intval(mysqli_real_escape_string($con, $raw['salon_id']     ?? '0'));
$search_name  = mysqli_real_escape_string($con, $raw['search_name']  ?? '');
$search_phone = mysqli_real_escape_string($con, $raw['search_phone'] ?? '');

if ($salon_id <= 0) respond(400, ['error'=>'salon_id is required']);

$sql = "
SELECT
  p.customer,
  SUM(CASE WHEN p.type = 0 THEN p.price ELSE 0 END) AS total_purchase,
  MIN(p.date) AS created_date,
  uc.username AS customer_phone,
  CONCAT(uc.fname,' ',uc.lname) AS customer_name
FROM pays p
JOIN users uc ON uc.lst = p.customer
WHERE p.salon = ?
";
$params = [$salon_id];
$types  = "i";

if ($search_name !== '') {
    $sql   .= " AND CONCAT(uc.fname,' ',uc.lname) LIKE ? ";
    $types .= "s";
    $params[] = "%{$search_name}%";
}
if ($search_phone !== '') {
    $sql   .= " AND uc.username LIKE ? ";
    $types .= "s";
    $params[] = "%{$search_phone}%";
}

$sql .= " GROUP BY p.customer";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$monthNames = [
  '01'=>'فروردین','02'=>'اردیبهشت','03'=>'خرداد','04'=>'تیر',
  '05'=>'مرداد','06'=>'شهریور','07'=>'مهر','08'=>'آبان',
  '09'=>'آذر','10'=>'دی','11'=>'بهمن','12'=>'اسفند'
];

$output = [];
while ($row = mysqli_fetch_assoc($res)) {
    list(, $cM,) = explode('.', $row['created_date']);
    $created_month_name = $monthNames[$cM] ?? '';

    $custId = intval($row['customer']);
    $cStmt  = mysqli_prepare($con, "
      SELECT full_name, detail, date, average_score
      FROM comments
      WHERE salon = ? AND customer = ?
    ");
    mysqli_stmt_bind_param($cStmt, 'ii', $salon_id, $custId);
    mysqli_stmt_execute($cStmt);
    $cRes = mysqli_stmt_get_result($cStmt);

    $comments = [];
    while ($c = mysqli_fetch_assoc($cRes)) {
        $comments[] = [
            'full_name'     => $c['full_name'],
            'detail'        => $c['detail'],
            'date'          => $c['date'],
            'average_score' => round((float)$c['average_score'], 2)
        ];
    }
    mysqli_stmt_close($cStmt);
    if (empty($comments)) {
        $comments = '-';
    }

    $output[] = [
        'customer_id'         => $custId,
        'customer_phone'      => $row['customer_phone'],
        'customer_name'       => $row['customer_name'],
        'total_purchase'      => intval($row['total_purchase']),
        'created_date'        => $row['created_date'],
        'created_month_name'  => $created_month_name,
        'comments'            => $comments
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($con);
respond(200, ['count'=>count($output), 'customers'=>$output]);
?>