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
if ($raw === false || $raw === '') respond(400, ['error' => 'empty request body']);
$data = json_decode($raw, true);
if (!is_array($data)) respond(400, ['error' => 'invalid json']);
$type = isset($data['type']) ? (int)$data['type'] : null;
$user = isset($data['user']) && $data['user'] !== '' ? (string)$data['user'] : null;
$user_id = isset($data['user_id']) && $data['user_id'] !== '' ? (int)$data['user_id'] : null;
$salon_id = isset($data['salon_id']) && $data['salon_id'] !== '' ? (int)$data['salon_id'] : null;
if ($type === null) respond(400, ['error' => 'missing type']);
if ($salon_id === null) respond(400, ['error' => 'salon_id is required']);
if (($user === null || $user === '') && (!$user_id)) respond(400, ['error' => 'at least one of user or user_id must be provided']);
$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error' => 'db_connect_failed']);
mysqli_set_charset($con, 'utf8mb4');
$user_esc = $user !== null ? mysqli_real_escape_string($con, $user) : null;
$user_id_val = $user_id ? intval($user_id) : null;
if ($user !== null && $user_id) {
    $sql = "SELECT lst,username FROM users WHERE username='$user_esc' LIMIT 1";
    $r = mysqli_query($con, $sql);
    if (!$r) respond(500, ['error' => 'query_failed']);
    $row = mysqli_fetch_assoc($r);
    if (!$row) respond(404, ['error' => 'user_username_not_found']);
    $lst_from_username = (int)$row['lst'];
    if ($lst_from_username !== $user_id_val) respond(400, ['error' => 'user_and_user_id_do_not_match']);
    $lst = $lst_from_username;
    $username = $row['username'];
} else if ($user !== null) {
    $sql = "SELECT lst,username,avatar,fname,lname,province,city,gender,birthday,address,attract FROM users WHERE username='$user_esc' LIMIT 1";
    $r = mysqli_query($con, $sql);
    if (!$r) respond(500, ['error' => 'query_failed']);
    $row = mysqli_fetch_assoc($r);
    if (!$row) respond(404, ['error' => 'user_username_not_found']);
    $lst = (int)$row['lst'];
    $username = $row['username'];
} else {
    $r = mysqli_query($con, "SELECT lst,username,avatar,fname,lname,province,city,gender,birthday,address,attract FROM users WHERE lst=" . intval($user_id_val) . " LIMIT 1");
    if (!$r) respond(500, ['error' => 'query_failed']);
    $row = mysqli_fetch_assoc($r);
    if (!$row) respond(404, ['error' => 'user_id_not_found']);
    $lst = (int)$row['lst'];
    $username = $row['username'];
}
if ($type === 0) {
    $ru = mysqli_query($con, "SELECT lst,username,avatar,fname,lname,gender,birthday,email,attract,job FROM users WHERE lst=" . intval($lst) . " LIMIT 1");
    if (!$ru) respond(500, ['error' => 'query_failed_users']);
    $rowu = mysqli_fetch_assoc($ru);
    if (!$rowu) respond(404, ['error' => 'user_not_found']);
    $phone1 = $rowu['username'] ?? '';
    $phone2 = '';
    $rp = mysqli_query($con, "SELECT phone FROM phones WHERE user_id='" . intval($lst) . "' ORDER BY LST ASC");
    if ($rp) {
        $phones_list = [];
        while ($p = mysqli_fetch_assoc($rp)) {
            if (isset($p['phone']) && $p['phone'] !== '') $phones_list[] = $p['phone'];
        }
        if (isset($phones_list[0])) $phone2 = $phones_list[0];
    }
    $response = [
        'fname' => $rowu['fname'] ?? '',
        'lname' => $rowu['lname'] ?? '',
        'email' => $rowu['email'] ?? '',
        'job' => $rowu['job'] ?? '',
        'phone1' => $phone1,
        'extra_phone' => $phone2,
        'avatar' => isset($rowu['avatar']) ? (int)$rowu['avatar'] : null,
        'gender' => isset($rowu['gender']) && $rowu['gender'] !== null ? (int)$rowu['gender'] : null,
        'birthday' => $rowu['birthday'] ?? '',
        'attract' => $rowu['attract'] ?? ''
    ];
    respond(200, ['type' => 0, 'data' => $response]);
} elseif ($type === 1) {
    $ru = mysqli_query($con, "SELECT address FROM users WHERE lst=" . intval($lst) . " LIMIT 1");
    if (!$ru) respond(500, ['error' => 'query_failed_users']);
    $rowu = mysqli_fetch_assoc($ru);
    if (!$rowu) respond(404, ['error' => 'user_not_found']);
    respond(200, ['type' => 1, 'data' => ['address' => $rowu['address'] ?? '']]);
} elseif ($type === 2) {
    $rp = mysqli_query($con, "SELECT LST,user_id,phone FROM phones WHERE user_id='" . intval($lst) . "' ORDER BY LST DESC");
    if (!$rp) respond(500, ['error' => 'query_failed_phones']);
    $phones = [];
    while ($r = mysqli_fetch_assoc($rp)) {
        $phones[] = ['id' => isset($r['LST']) ? (int)$r['LST'] : null, 'user_id' => isset($r['user_id']) ? (int)$r['user_id'] : null, 'phone' => $r['phone'] ?? ''];
    }
    if (count($phones) === 0) respond(404, ['error' => 'no_phones_found']);
    respond(200, ['type' => 2, 'data' => $phones]);
} else {
    respond(400, ['error' => 'invalid_type']);
}
mysqli_close($con);
?>