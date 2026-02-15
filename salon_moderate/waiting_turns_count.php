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
$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') respond(400, ['error' => 'empty request body']);
$data = json_decode($raw, true);
if (!is_array($data)) respond(400, ['error' => 'invalid json']);
$salon_id = isset($data['salon_id']) && $data['salon_id'] !== '' ? (int)$data['salon_id'] : null;
$worker_id = isset($data['worker_id']) && $data['worker_id'] !== '' ? (int)$data['worker_id'] : null;
if ($salon_id === null) respond(400, ['error' => 'salon_id is required']);
$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error' => 'db_connect_failed']);
mysqli_set_charset($con, 'utf8mb4');
$salon_val = intval($salon_id);
$sql = "SELECT COUNT(*) AS cnt FROM turn WHERE salon=" . $salon_val . " AND status=0";
if ($worker_id !== null) $sql .= " AND worker=" . intval($worker_id);
$res = mysqli_query($con, $sql);
if (!$res) {
    mysqli_close($con);
    respond(500, ['error' => 'query_failed']);
}
$row = mysqli_fetch_assoc($res);
$count = isset($row['cnt']) ? (int)$row['cnt'] : 0;
mysqli_free_result($res);
mysqli_close($con);
respond(200, ['pending_turns' => $count]);
?>