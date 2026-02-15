<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');

function respond($status = 200, $data = []) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}


$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) respond(400, ['error' => 'invalid_json', 'message' => 'request body must be valid JSON']);


if (!isset($body['salon']) || !isset($body['worker'])) {
    respond(400, ['error' => 'missing_parameters', 'message' => 'salon and worker are required']);
}


$con = mysqli_connect(
    'localhost',
    'ctonfugw_shahab',
    'kwN?Cx#v77,u',
    'ctonfugw_main'
);
if (!$con) respond(500, ['error' => 'database connection failed']);
mysqli_set_charset($con, 'utf8mb4');

 
$salon_raw  = $body['salon'];
$worker_raw = $body['worker'];

$salon_esc  = mysqli_real_escape_string($con, trim((string)$salon_raw));
$worker_esc = mysqli_real_escape_string($con, trim((string)$worker_raw));

if (!ctype_digit($salon_esc) || !ctype_digit($worker_esc)) {
    respond(400, ['error' => 'invalid_parameters', 'message' => 'salon and worker must be integer ids']);
}
$salon = (int)$salon_esc;
$worker = (int)$worker_esc;


$hasFilter = false;
$filter = null;
if (isset($body['service_name']) && $body['service_name'] !== '') {
    $filter_raw = (string)$body['service_name'];
    $filter_esc = mysqli_real_escape_string($con, trim($filter_raw));
    if ($filter_esc !== '') {
        $hasFilter = true;
        $filter = '%' . $filter_esc . '%';
    }
}


$sql = "
SELECT
  s.LST AS service_entry_id,
  s.service AS service_id,
  sd.service AS service_name,
  sd.sub_service AS sub_service_name,
  sd.gender AS sub_service_gender,
  s.duration AS duration,
  s.price AS price
FROM service s
LEFT JOIN service_detail sd ON s.service = sd.service_id
WHERE s.salon = ? AND s.worker = ?
";

if ($hasFilter) {
    $sql .= " AND (sd.service LIKE ? OR sd.sub_service LIKE ?)";
}

$sql .= " ORDER BY sd.service, sd.sub_service, s.LST";


$stmt = mysqli_prepare($con, $sql);
if (!$stmt) respond(500, ['error' => 'prepare_failed', 'message' => mysqli_error($con)]);

if ($hasFilter) {
    mysqli_stmt_bind_param($stmt, 'iiss', $salon, $worker, $filter, $filter);
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $salon, $worker);
}

$exec = mysqli_stmt_execute($stmt);
if ($exec === false) respond(500, ['error' => 'query_failed', 'message' => mysqli_stmt_error($stmt)]);
$res = mysqli_stmt_get_result($stmt);
if ($res === false) respond(500, ['error' => 'get_result_failed', 'message' => mysqli_error($con)]);


$output = [];
while ($row = mysqli_fetch_assoc($res)) {
    $sid = $row['service_id'] !== null ? (string)$row['service_id'] : 'unknown';
    $sname = $row['service_name'] !== null ? $row['service_name'] : null;
    $sub = [
        'service_entry_id' => isset($row['service_entry_id']) ? (int)$row['service_entry_id'] : null,
        'sub_service_name' => $row['sub_service_name'] !== null ? $row['sub_service_name'] : null,
        'sub_service_gender' => $row['sub_service_gender'] !== null ? (int)$row['sub_service_gender'] : null,
        'duration' => $row['duration'] !== null ? $row['duration'] : null,
        'price' => $row['price'] !== null ? $row['price'] : null
    ];

    if (!isset($output[$sid])) {
        $output[$sid] = [
            'service_id' => $sid === 'unknown' ? null : (int)$sid,
            'service_name' => $sname,
            'sub_services' => []
        ];
    }
    $output[$sid]['sub_services'][] = $sub;
}

mysqli_stmt_close($stmt);
mysqli_close($con);

$final = array_values($output);
respond(200, ['services' => $final]);
?>
