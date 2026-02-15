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

if (!isset($body['salon']) || !isset($body['worker']) || !isset($body['title']) || !isset($body['address'])) {
    respond(400, ['error' => 'missing_parameters', 'message' => 'salon, worker, title and address are required']);
}

$con = mysqli_connect(
    'localhost',
    'ctonfugw_shahab',
    'kwN?Cx#v77,u',
    'ctonfugw_main'
);
if (!$con) respond(500, ['error' => 'database connection failed']);
mysqli_set_charset($con, 'utf8mb4');

$salon_raw = $body['salon'];
$worker_raw = $body['worker'];
$title_raw = $body['title'];
$address_raw = $body['address'];

$salon_s = mysqli_real_escape_string($con, trim((string)$salon_raw));
$worker_s = mysqli_real_escape_string($con, trim((string)$worker_raw));
$title_s = mysqli_real_escape_string($con, trim((string)$title_raw));
$address_s = mysqli_real_escape_string($con, trim((string)$address_raw));

if (!ctype_digit($salon_s)) {
    mysqli_close($con);
    respond(400, ['error' => 'invalid_parameters', 'message' => 'salon must be an integer id']);
}

$salon = (int)$salon_s;

$find_user_sql = "SELECT lst FROM users WHERE username = ? LIMIT 1";
$find_user_stmt = mysqli_prepare($con, $find_user_sql);
if (!$find_user_stmt) {
    mysqli_close($con);
    respond(500, ['error' => 'prepare_failed', 'message' => mysqli_error($con)]);
}
mysqli_stmt_bind_param($find_user_stmt, 's', $worker_s);
$exec = mysqli_stmt_execute($find_user_stmt);
if ($exec === false) {
    mysqli_stmt_close($find_user_stmt);
    mysqli_close($con);
    respond(500, ['error' => 'query_failed', 'message' => mysqli_stmt_error($find_user_stmt)]);
}
$user_res = mysqli_stmt_get_result($find_user_stmt);
if ($user_res === false) {
    mysqli_stmt_close($find_user_stmt);
    mysqli_close($con);
    respond(500, ['error' => 'get_result_failed', 'message' => mysqli_error($con)]);
}
$user_row = mysqli_fetch_assoc($user_res);
mysqli_stmt_close($find_user_stmt);
if (!$user_row || !isset($user_row['lst'])) {
    mysqli_close($con);
    respond(404, ['error' => 'worker_not_found', 'message' => 'worker username not found']);
}
$worker_id = (int)$user_row['lst'];

$insert_sql = "INSERT INTO address (worker, salon, title, address) VALUES (?, ?, ?, ?)";
$insert_stmt = mysqli_prepare($con, $insert_sql);
if (!$insert_stmt) {
    mysqli_close($con);
    respond(500, ['error' => 'prepare_failed', 'message' => mysqli_error($con)]);
}
mysqli_stmt_bind_param($insert_stmt, 'iiss', $worker_id, $salon, $title_s, $address_s);
$exec2 = mysqli_stmt_execute($insert_stmt);
if ($exec2 === false) {
    mysqli_stmt_close($insert_stmt);
    mysqli_close($con);
    respond(500, ['error' => 'insert_failed', 'message' => mysqli_stmt_error($insert_stmt)]);
}
$insert_id = mysqli_insert_id($con);
mysqli_stmt_close($insert_stmt);
mysqli_close($con);

respond(201, ['success' => true, 'address_id' => (int)$insert_id, 'message' => 'address_added']);
?>