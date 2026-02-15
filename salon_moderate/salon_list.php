<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');

$in = json_decode(file_get_contents('php://input'), true);
$userId = isset($in['user_id']) ? intval($in['user_id']) : 0;

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'user_id is required and must be integer']);
    exit;
}

$con = mysqli_connect(
    'localhost',
    'ctonfugw_shahab',
    'kwN?Cx#v77,u',
    'ctonfugw_main'
);
if (!$con) {
    http_response_code(500);
    echo json_encode(['error' => 'database connection failed']);
    exit;
}

$r = mysqli_query($con, "SELECT role FROM users WHERE lst = {$userId} LIMIT 1");
if (!$row = mysqli_fetch_assoc($r)) {
    http_response_code(404);
    echo json_encode(['error' => 'user not found']);
    exit;
}
$role = intval($row['role']);
$data = [];

if ($role === 1) {
    $qr = mysqli_query($con, "
        SELECT ID AS salon_id, title, province, city, state
          FROM salon
         WHERE owner = {$userId}
    ");
    $myRoleLabel = 'سالن‌دار';
}
else if ($role === 2) {
    $qr = mysqli_query($con, "
        SELECT DISTINCT s.ID AS salon_id,
                        s.title,
                        s.province,
                        s.city,
                        s.state
          FROM salon s
          JOIN service srv ON srv.salon = s.ID
         WHERE srv.worker = {$userId}
    ");
    $myRoleLabel = 'ورکر';
}
else {
    http_response_code(403);
    echo json_encode(['error' => 'insufficient permissions']);
    exit;
}

while ($row = mysqli_fetch_assoc($qr)) {
    $data[] = [
        'salon_id'  => intval($row['salon_id']),
        'title'     => $row['title'],
        'province'  => $row['province'],
        'city'      => $row['city'],
        'state'     => intval($row['state']),
        'my_role'   => $myRoleLabel
    ];
}

mysqli_close($con);

echo json_encode([
    'total' => count($data),
    'data'  => $data
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
?>