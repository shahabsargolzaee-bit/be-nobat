<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}



$raw = json_decode(file_get_contents('php://input'), true);
$salon_id = isset($raw['salon_id']) ? $raw['salon_id'] : null;
if ($salon_id === null) respond(400, ['error' => 'salon_id is required']);
if (!is_int($salon_id) && !ctype_digit((string)$salon_id)) respond(400, ['error' => 'salon_id must be integer']);
$salon_id = (int)$salon_id;

$con = mysqli_connect(
    'localhost',
    'ctonfugw_shahab',
    'kwN?Cx#v77,u',
    'ctonfugw_main'
);
if (!$con) respond(500, ['error' => 'database connection failed']);
mysqli_set_charset($con, 'utf8mb4');

$sql = "
SELECT
  s.LST AS service_entry_id,
  s.service AS service_id,
  sd.service AS service_title,
  sd.sub_service AS sub_service_title,
  s.worker AS worker_id,
  s.duration AS duration,
  s.price AS price
FROM service s
LEFT JOIN service_detail sd ON s.service = sd.service_id
WHERE s.salon = ?
ORDER BY sd.service, sd.sub_service, s.LST
";

$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    mysqli_close($con);
    respond(500, ['error' => 'prepare_failed']);
}
mysqli_stmt_bind_param($stmt, 'i', $salon_id);
$exec = mysqli_stmt_execute($stmt);
if ($exec === false) {
    mysqli_stmt_close($stmt);
    mysqli_close($con);
    respond(500, ['error' => 'query_failed', 'message' => mysqli_stmt_error($stmt)]);
}
$res = mysqli_stmt_get_result($stmt);
if ($res === false) {
    mysqli_stmt_close($stmt);
    mysqli_close($con);
    respond(500, ['error' => 'get_result_failed']);
}

$data = [];
while ($row = mysqli_fetch_assoc($res)) {
    $data[] = [
        'service_entry_id' => isset($row['service_entry_id']) ? (int)$row['service_entry_id'] : null,
        'service_id' => isset($row['service_id']) ? (int)$row['service_id'] : null,
        'service_title' => $row['service_title'] !== null ? $row['service_title'] : null,
        'sub_service_title' => $row['sub_service_title'] !== null ? $row['sub_service_title'] : null,
        'worker_id' => isset($row['worker_id']) ? (int)$row['worker_id'] : null,
        'duration' => $row['duration'] !== null ? $row['duration'] : null,
        'price' => $row['price'] !== null ? $row['price'] : null
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($con);

respond(200, ['total' => count($data), 'data' => $data]);
?>