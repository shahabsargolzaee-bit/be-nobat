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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['error' => 'method_not_allowed']);
if (!isset($_POST['salon_id']) || trim($_POST['salon_id']) === '') respond(400, ['error' => 'salon_id_required']);
if (!isset($_POST['fname']) || trim($_POST['fname']) === '') respond(400, ['error' => 'fname_required']);
if (!isset($_POST['lname']) || trim($_POST['lname']) === '') respond(400, ['error' => 'lname_required']);
if (!isset($_POST['phone']) || trim($_POST['phone']) === '') respond(400, ['error' => 'phone_required']);
$salon_id = (int)$_POST['salon_id'];
$fname = trim($_POST['fname']);
$lname = trim($_POST['lname']);
$phone = trim($_POST['phone']);
if (!preg_match('/^09\d{9}$/', $phone)) respond(400, ['error' => 'phone_format_invalid']);
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$gender = isset($_POST['gender']) && $_POST['gender'] !== '' ? (int)$_POST['gender'] : null;
$birthday = isset($_POST['birthday']) ? trim($_POST['birthday']) : '';
$extra_phone = isset($_POST['extra_phone']) ? trim($_POST['extra_phone']) : '';
$job = isset($_POST['job']) ? trim($_POST['job']) : '';
$attract = isset($_POST['attract']) && $_POST['attract'] !== '' ? (int)$_POST['attract'] : null;
$avatar_file_provided = isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE;
if ($avatar_file_provided) {
    $fsize = $_FILES['avatar_file']['size'];
    if ($fsize > 1048576) respond(400, ['error' => 'avatar_too_large']);
    if ($_FILES['avatar_file']['error'] !== UPLOAD_ERR_OK) respond(400, ['error' => 'avatar_upload_error']);
    $tmp = $_FILES['avatar_file']['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) respond(400, ['error' => 'avatar_not_image']);
    if ($info[2] !== IMAGETYPE_JPEG) respond(400, ['error' => 'avatar_must_be_jpg']);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmp) : '';
    if ($finfo) finfo_close($finfo);
    if ($mime !== 'image/jpeg' && $mime !== 'image/pjpeg') respond(400, ['error' => 'avatar_must_be_jpg']);
    $ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'jpg' && $ext !== 'jpeg') respond(400, ['error' => 'avatar_extension_must_be_jpg']);
}
$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error' => 'db_connect_failed']);
mysqli_set_charset($con, 'utf8mb4');
$phone_esc = mysqli_real_escape_string($con, $phone);
$q = "SELECT lst,username FROM users WHERE username='$phone_esc' LIMIT 1";
$r = mysqli_query($con, $q);
if (!$r) { mysqli_close($con); respond(500, ['error' => 'query_failed']); }
$user_row = mysqli_fetch_assoc($r);
$new_user_created = false;
if ($user_row) {
    $user_id = (int)$user_row['lst'];
} else {
    $chars='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $pass='';
    for ($i=0;$i<6;$i++) $pass .= $chars[random_int(0,strlen($chars)-1)];
    $refchars='ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $ref='';
    for ($i=0;$i<4;$i++) $ref .= $refchars[random_int(0,strlen($refchars)-1)];
    $username = $phone_esc;
    $password = mysqli_real_escape_string($con, $pass);
    $referral_code = mysqli_real_escape_string($con, $ref);
    $gender_val = $gender === null ? "NULL" : intval($gender);
    $fname_esc = "'" . mysqli_real_escape_string($con, $fname) . "'";
    $lname_esc = "'" . mysqli_real_escape_string($con, $lname) . "'";
    $email_esc = $email !== '' ? "'" . mysqli_real_escape_string($con, $email) . "'" : "NULL";
    $birthday_esc = $birthday !== '' ? "'" . mysqli_real_escape_string($con, $birthday) . "'" : "NULL";
    $attract_val = $attract === null ? "NULL" : intval($attract);
    $job_esc = $job !== '' ? "'" . mysqli_real_escape_string($con, $job) . "'" : "NULL";
    $ins = "INSERT INTO users (username,password,avatar,role,gender,fname,lname,birthday,referral_code,attract,email,job) VALUES ('" . mysqli_real_escape_string($con,$username) . "','" . $password . "',0,0," . $gender_val . "," . $fname_esc . "," . $lname_esc . "," . $birthday_esc . ",'" . $referral_code . "'," . $attract_val . "," . $email_esc . "," . $job_esc . ")";
    if (!mysqli_query($con, $ins)) { mysqli_close($con); respond(500, ['error' => 'user_insert_failed']); }
    $user_id = (int)mysqli_insert_id($con);
    $new_user_created = true;
    if ($avatar_file_provided) {
        $f = $_FILES['avatar_file'];
        $tmp = $f['tmp_name'];
        $dst = '/home3/ctonfugw/public_html/images/users/' . intval($user_id) . '.jpg';
        if (!move_uploaded_file($tmp, $dst)) { mysqli_close($con); respond(500, ['error' => 'avatar_move_failed']); }
        $upd = "UPDATE users SET avatar=1 WHERE lst=" . intval($user_id) . " LIMIT 1";
        if (!mysqli_query($con, $upd)) { mysqli_close($con); respond(500, ['error' => 'avatar_flag_update_failed']); }
    }
}
if ($extra_phone !== '') {
    if (!preg_match('/^09\d{9}$/', $extra_phone)) { mysqli_close($con); respond(400, ['error' => 'extra_phone_format_invalid']); }
    $extra_esc = mysqli_real_escape_string($con, $extra_phone);
    $q2 = "SELECT LST FROM phones WHERE phone='$extra_esc' AND user_id=" . intval($user_id) . " LIMIT 1";
    $r2 = mysqli_query($con, $q2);
    if (!$r2) { mysqli_close($con); respond(500, ['error' => 'query_failed']); }
    $exist_phone_row = mysqli_fetch_assoc($r2);
    if ($exist_phone_row) { mysqli_close($con); respond(409, ['error' => 'phone_already_exists_for_user']); }
    $ins2 = "INSERT INTO phones (user_id,phone) VALUES (" . intval($user_id) . ",'" . $extra_esc . "')";
    if (!mysqli_query($con, $ins2)) { mysqli_close($con); respond(500, ['error' => 'phone_insert_failed']); }
}
$q_final = "SELECT lst FROM users WHERE username='" . mysqli_real_escape_string($con, $phone) . "' LIMIT 1";
$r_final = mysqli_query($con, $q_final);
if (!$r_final) { mysqli_close($con); respond(500, ['error' => 'query_failed']); }
$row_final = mysqli_fetch_assoc($r_final);
$user_lst = $row_final ? (int)$row_final['lst'] : $user_id;
mysqli_close($con);
respond(200, ['message'=>'customer_added_or_linked','user_id'=> $user_lst, 'created' => $new_user_created]);
?>