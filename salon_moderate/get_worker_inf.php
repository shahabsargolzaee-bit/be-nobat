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
$worker = isset($data['worker']) && $data['worker'] !== '' ? (string)$data['worker'] : null;
$worker_id = isset($data['worker_id']) && $data['worker_id'] !== '' ? (int)$data['worker_id'] : null;
$salon_id = isset($data['salon_id']) && $data['salon_id'] !== '' ? (int)$data['salon_id'] : null;
if ($type === null) respond(400, ['error' => 'missing type']);
if ($salon_id === null) respond(400, ['error' => 'salon_id is required']);
if (($worker === null || $worker === '') && (!$worker_id)) respond(400, ['error' => 'at least one of worker or worker_id must be provided']);
$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error' => 'db_connect_failed']);
mysqli_set_charset($con, 'utf8mb4');
$worker_esc = $worker !== null ? mysqli_real_escape_string($con, $worker) : null;
$worker_id_val = $worker_id ? intval($worker_id) : null;
if ($worker !== null && $worker_id) {
    $sql = "SELECT lst,username FROM users WHERE username='$worker_esc' LIMIT 1";
    $r = mysqli_query($con, $sql);
    if (!$r) respond(500, ['error' => 'query_failed']);
    $row = mysqli_fetch_assoc($r);
    if (!$row) respond(404, ['error' => 'worker_username_not_found']);
    $lst_from_username = (int)$row['lst'];
    if ($lst_from_username !== $worker_id_val) respond(400, ['error' => 'worker_and_worker_id_do_not_match']);
    $lst = $lst_from_username;
    $username = $row['username'];
} else if ($worker !== null) {
    $sql = "SELECT lst,username,avatar,fname,lname,province,city,gender,birthday FROM users WHERE username='$worker_esc' LIMIT 1";
    $r = mysqli_query($con, $sql);
    if (!$r) respond(500, ['error' => 'query_failed']);
    $row = mysqli_fetch_assoc($r);
    if (!$row) respond(404, ['error' => 'worker_username_not_found']);
    $lst = (int)$row['lst'];
    $username = $row['username'];
} else {
    $r = mysqli_query($con, "SELECT lst,username,avatar,fname,lname,province,city,gender,birthday FROM users WHERE lst=" . intval($worker_id_val) . " LIMIT 1");
    if (!$r) respond(500, ['error' => 'query_failed']);
    $row = mysqli_fetch_assoc($r);
    if (!$row) respond(404, ['error' => 'worker_id_not_found']);
    $lst = (int)$row['lst'];
    $username = $row['username'];
}
if ($type === 0) {
    $ru = mysqli_query($con, "SELECT lst,username,avatar,fname,lname,province,city,gender,birthday FROM users WHERE lst=" . intval($lst) . " LIMIT 1");
    if (!$ru) respond(500, ['error' => 'query_failed_users']);
    $rowu = mysqli_fetch_assoc($ru);
    if (!$rowu) respond(404, ['error' => 'user_not_found']);
    $sql_team = "SELECT salon,worker_user,email,color,job_title,employment_type,start_work,end_work,note FROM teams WHERE worker_user='" . mysqli_real_escape_string($con, $rowu['username']) . "' AND salon=" . intval($salon_id) . " LIMIT 1";
    $rt = mysqli_query($con, $sql_team);
    $team = $rt ? mysqli_fetch_assoc($rt) : [];
    $rp = mysqli_query($con, "SELECT LST,phone FROM phones WHERE user_id='" . intval($lst) . "' ORDER BY LST ASC");
    $phone1 = $rowu['username'] ?? '';
    $phone2 = '';
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
        'email' => $team['email'] ?? '',
        'phone1' => $phone1,
        'phone2' => $phone2,
        'job_title' => $team['job_title'] ?? '',
        'birthday' => $rowu['birthday'] ?? '',
        'color' => $team['color'] ?? '',
        'start_work' => $team['start_work'] ?? '',
        'end_work' => $team['end_work'] ?? '',
        'specialist_id' => $rowu['username'] ?? '',
        'employment_type' => isset($team['employment_type']) ? (int)$team['employment_type'] : null,
        'note' => $team['note'] ?? '',
        'avatar' => isset($rowu['avatar']) ? (int)$rowu['avatar'] : null,
        'province' => $rowu['province'] ?? '',
        'city' => $rowu['city'] ?? '',
        'gender' => isset($rowu['gender']) && $rowu['gender'] !== null ? (int)$rowu['gender'] : null
    ];
    respond(200, ['type' => 0, 'data' => $response]);
} elseif ($type === 1) {
    $sql_addr = "SELECT LST,worker,salon,title,address FROM address WHERE worker='" . intval($lst) . "' AND salon=" . intval($salon_id) . " ORDER BY LST DESC";
    $ra = mysqli_query($con, $sql_addr);
    if (!$ra) respond(500, ['error' => 'query_failed_address']);
    $addrs = [];
    while ($r = mysqli_fetch_assoc($ra)) {
        $addrs[] = [
            'id' => isset($r['LST']) ? (int)$r['LST'] : null,
            'worker' => isset($r['worker']) ? (int)$r['worker'] : null,
            'salon' => isset($r['salon']) ? (int)$r['salon'] : null,
            'title' => $r['title'] ?? '',
            'address' => $r['address'] ?? ''
        ];
    }
    if (count($addrs) === 0) respond(404, ['error' => 'no_addresses_found']);
    respond(200, ['type' => 1, 'data' => $addrs]);
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