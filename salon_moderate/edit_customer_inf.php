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
$type = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'])) $type = (int)$_POST['type'];
if ($raw !== false && trim($raw) !== '' && $type === null) {
    $tmpj = json_decode($raw, true);
    if (is_array($tmpj) && isset($tmpj['type'])) $type = (int)$tmpj['type'];
}
if ($type === null) respond(400, ['error' => 'missing type']);
$json = null;
$user = null;
$user_id = null;
$salon_id = null;
if ($type === 0) {
    $user = isset($_POST['user']) && $_POST['user'] !== '' ? (string)$_POST['user'] : null;
    $user_id = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int)$_POST['user_id'] : null;
    $salon_id = isset($_POST['salon_id']) && $_POST['salon_id'] !== '' ? (int)$_POST['salon_id'] : null;
} else {
    $json = $json ?? json_decode($raw, true);
    if (!is_array($json)) respond(400, ['error' => 'invalid json']);
    $user = isset($json['user']) && $json['user'] !== '' ? (string)$json['user'] : null;
    $user_id = isset($json['user_id']) && $json['user_id'] !== '' ? (int)$json['user_id'] : null;
    $salon_id = isset($json['salon_id']) && $json['salon_id'] !== '' ? (int)$json['salon_id'] : null;
}
if ($salon_id === null) respond(400, ['error' => 'salon_id is required']);
if (($user === null || $user === '') && (!$user_id)) respond(400, ['error' => 'at least one of user or user_id must be provided']);
$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error' => 'db_connect_failed']);
mysqli_set_charset($con, 'utf8mb4');
$user_esc = $user !== null ? mysqli_real_escape_string($con, $user) : null;
$user_id_val = $user_id ? intval($user_id) : null;
if ($user !== null && $user_id) {
    $q = "SELECT lst,username FROM users WHERE username='$user_esc' LIMIT 1";
    $r = mysqli_query($con, $q);
    if (!$r) { mysqli_close($con); respond(500, ['error' => 'query_failed']); }
    $row = mysqli_fetch_assoc($r);
    if (!$row) { mysqli_close($con); respond(404, ['error' => 'user_username_not_found']); }
    $lst_from_username = (int)$row['lst'];
    if ($lst_from_username !== $user_id_val) { mysqli_close($con); respond(400, ['error' => 'user_and_user_id_do_not_match']); }
    $lst = $lst_from_username;
    $username = $row['username'];
} else if ($user !== null) {
    $q = "SELECT lst,username,avatar,fname,lname,province,city,gender,birthday,email,attract,job FROM users WHERE username='$user_esc' LIMIT 1";
    $r = mysqli_query($con, $q);
    if (!$r) { mysqli_close($con); respond(500, ['error' => 'query_failed']); }
    $row = mysqli_fetch_assoc($r);
    if (!$row) { mysqli_close($con); respond(404, ['error' => 'user_username_not_found']); }
    $lst = (int)$row['lst'];
    $username = $row['username'];
} else {
    $q = "SELECT lst,username,avatar,fname,lname,province,city,gender,birthday,email,attract,job FROM users WHERE lst=" . intval($user_id_val) . " LIMIT 1";
    $r = mysqli_query($con, $q);
    if (!$r) { mysqli_close($con); respond(500, ['error' => 'query_failed']); }
    $row = mysqli_fetch_assoc($r);
    if (!$row) { mysqli_close($con); respond(404, ['error' => 'user_id_not_found']); }
    $lst = (int)$row['lst'];
    $username = $row['username'];
}
if ($type === 0) {
    $updates_users = [];
    $phone2_update = null;
    if (isset($_POST['fname']) && $_POST['fname'] !== '') $updates_users['fname'] = mysqli_real_escape_string($con, $_POST['fname']);
    if (isset($_POST['lname']) && $_POST['lname'] !== '') $updates_users['lname'] = mysqli_real_escape_string($con, $_POST['lname']);
    if (isset($_POST['province']) && $_POST['province'] !== '') $updates_users['province'] = mysqli_real_escape_string($con, $_POST['province']);
    if (isset($_POST['city']) && $_POST['city'] !== '') $updates_users['city'] = mysqli_real_escape_string($con, $_POST['city']);
    if (isset($_POST['gender']) && $_POST['gender'] !== '') $updates_users['gender'] = (int)$_POST['gender'];
    if (isset($_POST['birthday']) && $_POST['birthday'] !== '') $updates_users['birthday'] = mysqli_real_escape_string($con, $_POST['birthday']);
    if (isset($_POST['email']) && $_POST['email'] !== '') $updates_users['email'] = mysqli_real_escape_string($con, $_POST['email']);
    if (isset($_POST['job']) && $_POST['job'] !== '') $updates_users['job'] = mysqli_real_escape_string($con, $_POST['job']);
    if (isset($_POST['attract']) && $_POST['attract'] !== '') $updates_users['attract'] = mysqli_real_escape_string($con, $_POST['attract']);
    if (isset($_POST['extra_phone']) && $_POST['extra_phone'] !== '') $phone2_update = mysqli_real_escape_string($con, $_POST['extra_phone']);
    $avatar_delete_requested = isset($_POST['avatar']) && (string)$_POST['avatar'] === '0';
    $file_uploaded = isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($avatar_delete_requested) {
        $file = '/home3/ctonfugw/public_html/images/users/' . intval($lst) . '.jpg';
        if (is_file($file)) @unlink($file);
        $upd = "UPDATE users SET avatar=0 WHERE lst=" . intval($lst) . " LIMIT 1";
        if (!mysqli_query($con, $upd)) { mysqli_close($con); respond(500, ['error' => 'avatar_delete_failed']); }
    } else {
        if ($file_uploaded) {
            $f = $_FILES['avatar_file'];
            if ($f['error'] !== UPLOAD_ERR_OK) { mysqli_close($con); respond(400, ['error' => 'avatar_upload_error']); }
            if ($f['size'] > 1048576) { mysqli_close($con); respond(400, ['error' => 'avatar_too_large']); }
            $info = @getimagesize($f['tmp_name']);
            if ($info === false) { mysqli_close($con); respond(400, ['error' => 'avatar_not_image']); }
            if ($info[2] !== IMAGETYPE_JPEG) { mysqli_close($con); respond(400, ['error' => 'avatar_must_be_jpg']); }
            $dst = '/home3/ctonfugw/public_html/images/users/' . intval($lst) . '.jpg';
            if (!move_uploaded_file($f['tmp_name'], $dst)) { mysqli_close($con); respond(500, ['error' => 'avatar_move_failed']); }
            $upd = "UPDATE users SET avatar=1 WHERE lst=" . intval($lst) . " LIMIT 1";
            if (!mysqli_query($con, $upd)) { mysqli_close($con); respond(500, ['error' => 'avatar_flag_update_failed']); }
        }
    }
    if (count($updates_users) > 0) {
        $sets = [];
        foreach ($updates_users as $k => $v) {
            if (is_int($v) || is_numeric($v)) $sets[] = "$k=" . intval($v); else $sets[] = "$k='" . $v . "'";
        }
        $sqlu = "UPDATE users SET " . implode(',', $sets) . " WHERE lst=" . intval($lst) . " LIMIT 1";
        if (!mysqli_query($con, $sqlu)) { mysqli_close($con); respond(500, ['error' => 'users_update_failed']); }
    }
    if ($phone2_update !== null) {
        $q = "SELECT LST FROM phones WHERE user_id=" . intval($lst) . " ORDER BY LST ASC LIMIT 1";
        $r = mysqli_query($con, $q);
        if ($r && ($rowp = mysqli_fetch_assoc($r))) {
            $pid = (int)$rowp['LST'];
            $upd = "UPDATE phones SET phone='" . $phone2_update . "' WHERE LST=" . $pid . " LIMIT 1";
            if (!mysqli_query($con, $upd)) { mysqli_close($con); respond(500, ['error' => 'phone_update_failed']); }
        } else {
            $ins = "INSERT INTO phones (user_id,phone) VALUES (" . intval($lst) . ",'" . $phone2_update . "')";
            if (!mysqli_query($con, $ins)) { mysqli_close($con); respond(500, ['error' => 'phone_insert_failed']); }
        }
    }
    respond(200, ['message' => 'updated']);
    mysqli_close($con);
    exit;
} elseif ($type === 2) {
    $json = $json ?? json_decode($raw, true);
    if (!is_array($json)) { mysqli_close($con); respond(400, ['error' => 'invalid json']); }
    $updates_users = [];
    $phone2_update = null;
    if (isset($json['fname']) && $json['fname'] !== '') $updates_users['fname'] = mysqli_real_escape_string($con, $json['fname']);
    if (isset($json['lname']) && $json['lname'] !== '') $updates_users['lname'] = mysqli_real_escape_string($con, $json['lname']);
    if (isset($json['province']) && $json['province'] !== '') $updates_users['province'] = mysqli_real_escape_string($con, $json['province']);
    if (isset($json['city']) && $json['city'] !== '') $updates_users['city'] = mysqli_real_escape_string($con, $json['city']);
    if (isset($json['gender']) && $json['gender'] !== '') $updates_users['gender'] = (int)$json['gender'];
    if (isset($json['birthday']) && $json['birthday'] !== '') $updates_users['birthday'] = mysqli_real_escape_string($con, $json['birthday']);
    if (isset($json['email']) && $json['email'] !== '') $updates_users['email'] = mysqli_real_escape_string($con, $json['email']);
    if (isset($json['job']) && $json['job'] !== '') $updates_users['job'] = mysqli_real_escape_string($con, $json['job']);
    if (isset($json['attract']) && $json['attract'] !== '') $updates_users['attract'] = mysqli_real_escape_string($con, $json['attract']);
    if (isset($json['extra_phone']) && $json['extra_phone'] !== '') $phone2_update = mysqli_real_escape_string($con, $json['extra_phone']);
    $avatar_delete_requested = isset($json['avatar']) && (string)$json['avatar'] === '0';
    if ($avatar_delete_requested) {
        $file = '/home3/ctonfugw/public_html/images/users/' . intval($lst) . '.jpg';
        if (is_file($file)) @unlink($file);
        $upd = "UPDATE users SET avatar=0 WHERE lst=" . intval($lst) . " LIMIT 1";
        if (!mysqli_query($con, $upd)) { mysqli_close($con); respond(500, ['error' => 'avatar_delete_failed']); }
    }
    if (count($updates_users) > 0) {
        $sets = [];
        foreach ($updates_users as $k => $v) {
            if (is_int($v) || is_numeric($v)) $sets[] = "$k=" . intval($v); else $sets[] = "$k='" . $v . "'";
        }
        $sqlu = "UPDATE users SET " . implode(',', $sets) . " WHERE lst=" . intval($lst) . " LIMIT 1";
        if (!mysqli_query($con, $sqlu)) { mysqli_close($con); respond(500, ['error' => 'users_update_failed']); }
    }
    if ($phone2_update !== null) {
        $q = "SELECT LST FROM phones WHERE user_id=" . intval($lst) . " ORDER BY LST ASC LIMIT 1";
        $r = mysqli_query($con, $q);
        if ($r && ($rowp = mysqli_fetch_assoc($r))) {
            $pid = (int)$rowp['LST'];
            $upd = "UPDATE phones SET phone='" . $phone2_update . "' WHERE LST=" . $pid . " LIMIT 1";
            if (!mysqli_query($con, $upd)) { mysqli_close($con); respond(500, ['error' => 'phone_update_failed']); }
        } else {
            $ins = "INSERT INTO phones (user_id,phone) VALUES (" . intval($lst) . ",'" . $phone2_update . "')";
            if (!mysqli_query($con, $ins)) { mysqli_close($con); respond(500, ['error' => 'phone_insert_failed']); }
        }
    }
    respond(200, ['message' => 'updated']);
    mysqli_close($con);
    exit;
} elseif ($type === 3) {
    $json = $json ?? json_decode($raw, true);
    if (!is_array($json)) { mysqli_close($con); respond(400, ['error' => 'invalid json']); }
    if (!isset($json['phone']) || $json['phone'] === '') { mysqli_close($con); respond(400, ['error' => 'phone_required']); }
    $phone = mysqli_real_escape_string($con, $json['phone']);
    $ins = "INSERT INTO phones (user_id,phone) VALUES (" . intval($lst) . ",'" . $phone . "')";
    if (!mysqli_query($con, $ins)) { mysqli_close($con); respond(500, ['error' => 'phone_insert_failed']); }
    respond(200, ['message' => 'phone_added']);
    mysqli_close($con);
    exit;
} else {
    mysqli_close($con);
    respond(400, ['error' => 'invalid_type']);
}
?>