<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

$raw = json_decode(file_get_contents('php://input'), true);
$owner_id = isset($raw['owner_user_id']) ? intval($raw['owner_user_id']) : 0;
$salon_id = isset($raw['salon_id'])       ? intval($raw['salon_id'])       : 0;

if ($owner_id <= 0)    respond(400, ['error'=>'owner_user_id is required and must be integer']);
if ($salon_id <= 0)    respond(400, ['error'=>'salon_id is required and must be integer']);

$con = mysqli_connect(
    'localhost',
    'ctonfugw_shahab',
    'kwN?Cx#v77,u',
    'ctonfugw_main'
);
if (!$con) respond(500, ['error'=>'database connection failed']);

mysqli_set_charset($con, 'utf8mb4');

$stmt = mysqli_prepare(
    $con,
    "SELECT 1
       FROM salon
      WHERE ID = ? AND owner = ?
      LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $salon_id, $owner_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) === 0) {
    mysqli_close($con);
    respond(403, ['error'=>'not authorized: user is not owner of this salon']);
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(
    $con,
    "SELECT
        u.lst,
        u.fname,
        u.lname,
        u.username,
        u.score,
        u.avatar,
        t.email
      FROM teams t
      JOIN users u
        ON u.username = t.worker_user
     WHERE t.salon = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $salon_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$addr_stmt = mysqli_prepare($con, "SELECT LST, title, address FROM address WHERE worker = ? AND salon = ? ORDER BY LST");
if (!$addr_stmt) {
    mysqli_stmt_close($stmt);
    mysqli_close($con);
    respond(500, ['error'=>'prepare_failed_address_query']);
}

$data = [];
while ($row = mysqli_fetch_assoc($res)) {
    $firstLetter = mb_substr($row['fname'], 0, 1, 'UTF-8');
    $worker_id = intval($row['lst']);

    mysqli_stmt_bind_param($addr_stmt, 'ii', $worker_id, $salon_id);
    mysqli_stmt_execute($addr_stmt);
    $addr_res = mysqli_stmt_get_result($addr_stmt);

    $addresses = [];
    while ($ar = mysqli_fetch_assoc($addr_res)) {
        $addresses[] = [
            'id' => intval($ar['LST']),
            'title' => $ar['title'] !== null ? $ar['title'] : null,
            'address' => $ar['address'] !== null ? $ar['address'] : null
        ];
    }

    $data[] = [
        'ID'            => $worker_id,
        'fname'         => $row['fname'],
        'lname'         => $row['lname'],
        'first_letter'  => $firstLetter,
        'phone'         => $row['username'],
        'email'         => $row['email'],
        'addresses'     => $addresses,
        'score'         => round((float)$row['score'], 2),
        'avatar'        => intval($row['avatar'])
    ];
}

mysqli_stmt_close($addr_stmt);
mysqli_stmt_close($stmt);
mysqli_close($con);

respond(200, [
    'total' => count($data),
    'data'  => $data
]);
?>