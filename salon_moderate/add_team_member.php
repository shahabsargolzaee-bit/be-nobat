<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');
require_once '/home3/ctonfugw/public_html/api/jdate.php';

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

function is_valid_phone($p) {
    return (bool)preg_match('/^09\d{9}$/', $p);
}

function is_valid_jdate($d) {
    return (bool)preg_match('/^\d{4}\.(0[1-9]|1[0-2])\.(0[1-9]|[12]\d|3[01])$/', $d);
}

function rand_digits($n) {
    $s = '';
    for ($i=0;$i<$n;$i++) $s .= mt_rand(0,9);
    return $s;
}

function rand_upper_letters($n) {
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $s = '';
    for ($i=0;$i<$n;$i++) $s .= $letters[random_int(0,25)];
    return $s;
}

$owner_user_id = isset($_POST['owner_user_id']) ? intval($_POST['owner_user_id']) : 0;
$salon_id = isset($_POST['salon_id']) ? intval($_POST['salon_id']) : 0;
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$birthday = isset($_POST['birthday']) ? trim($_POST['birthday']) : '';
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$color = isset($_POST['color']) ? trim($_POST['color']) : '';
$job_title = isset($_POST['job_title']) ? trim($_POST['job_title']) : '';
$start_work = isset($_POST['start_work']) ? trim($_POST['start_work']) : '';
$end_work = isset($_POST['end_work']) ? trim($_POST['end_work']) : '';
$employment_type = isset($_POST['employment_type']) ? $_POST['employment_type'] : null;
$note = isset($_POST['note']) ? trim($_POST['note']) : '';
$file = isset($_FILES['avatar']) ? $_FILES['avatar'] : null;

if ($owner_user_id <= 0) respond(400, ['error'=>'owner_user_id is required and must be integer']);
if ($salon_id <= 0) respond(400, ['error'=>'salon_id is required and must be integer']);
if ($phone_number === '') respond(400, ['error'=>'phone_number is required']);
if (!is_valid_phone($phone_number)) respond(400, ['error'=>'phone_number must be a valid Iranian mobile starting with 09 and 11 digits']);
if ($first_name === '' || mb_strlen($first_name) > 30) respond(400, ['error'=>'first_name is required and max length 30']);
if ($last_name === '' || mb_strlen($last_name) > 45) respond(400, ['error'=>'last_name is required and max length 45']);
if ($birthday !== '' && !is_valid_jdate($birthday)) respond(400, ['error'=>'birthday must be Jalali YYYY.MM.DD or empty']);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) respond(400, ['error'=>'email is invalid']);
if ($color !== '' && mb_strlen($color) > 10) respond(400, ['error'=>'color max length 10']);
if ($job_title !== '' && mb_strlen($job_title) > 30) respond(400, ['error'=>'job_title max length 30']);
if ($start_work !== '' && !is_valid_jdate($start_work)) respond(400, ['error'=>'start_work must be Jalali YYYY.MM.DD or empty']);
if ($end_work !== '' && !is_valid_jdate($end_work)) respond(400, ['error'=>'end_work must be Jalali YYYY.MM.DD or empty']);
if ($start_work !== '' && $end_work !== '' && $end_work < $start_work) respond(400, ['error'=>'end_work must be >= start_work']);
if ($employment_type !== null) {
    if (!in_array(intval($employment_type), [0,1], true)) respond(400, ['error'=>'employment_type must be 0 or 1']);
    $employment_type = intval($employment_type);
}
if ($note !== '' && mb_strlen($note) > 150) respond(400, ['error'=>'note max length 150']);

if ($file && isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
    if ($file['size'] > 1024*1024) respond(400, ['error'=>'avatar must be <= 1MB']);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime !== 'image/jpeg' && $mime !== 'image/jpg') respond(400, ['error'=>'avatar must be a JPG image']);
    $img = @getimagesize($file['tmp_name']);
    if ($img === false) respond(400, ['error'=>'avatar is not a valid image']);
    if ($img[2] !== IMAGETYPE_JPEG) respond(400, ['error'=>'avatar must be JPEG format']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'jpg' && $ext !== 'jpeg') respond(400, ['error'=>'avatar filename must have jpg/jpeg extension']);
}

$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error'=>'database connection failed']);

mysqli_report(MYSQLI_REPORT_OFF);
mysqli_begin_transaction($con);

$stmt = mysqli_prepare($con, "SELECT s.ID FROM salon s WHERE s.ID = ? AND s.owner = ? LIMIT 1");
if (!$stmt) { mysqli_rollback($con); mysqli_close($con); respond(500, ['error'=>'db prepare failed']); }
mysqli_stmt_bind_param($stmt, 'ii', $salon_id, $owner_user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$salonExists = mysqli_fetch_assoc($res);
if (!$salonExists) { mysqli_rollback($con); mysqli_close($con); respond(403, ['error'=>'owner not authorized for this salon']); }

$stmt = mysqli_prepare($con, "SELECT lst FROM users WHERE username = ? LIMIT 1");
if (!$stmt) { mysqli_rollback($con); mysqli_close($con); respond(500, ['error'=>'db prepare failed']); }
mysqli_stmt_bind_param($stmt, 's', $phone_number);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$isNewUser = false;
$password_plain = null;
$referral_code = null;
if ($existing = mysqli_fetch_assoc($res)) {
    $newUserId = intval($existing['lst']);
    $isNewUser = false;
} else {
    $isNewUser = true;
    $password_plain = rand_digits(5);
    $referral_code = rand_upper_letters(4);
    $avatarFlag = 0;
    $roleWorker = 2;
    $dateJalali = jdate('Y.m.d');
    $stmtIns = mysqli_prepare($con, "INSERT INTO users (username, password, avatar, role, date, fname, lname, birthday, last_seen, gen_time, referral_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmtIns) { mysqli_rollback($con); mysqli_close($con); respond(500, ['error'=>'db prepare failed']); }
    $gen_time = (string)time();
    $emptyLastSeen = '';
    mysqli_stmt_bind_param($stmtIns, 'ssiisssssss', $phone_number, $password_plain, $avatarFlag, $roleWorker, $dateJalali, $first_name, $last_name, $birthday, $emptyLastSeen, $gen_time, $referral_code);
    $ok = mysqli_stmt_execute($stmtIns);
    if (!$ok) { mysqli_rollback($con); mysqli_close($con); respond(500, ['error'=>'failed to create user']); }
    $newUserId = intval(mysqli_insert_id($con));
}

$stmtTeam = mysqli_prepare($con, "INSERT INTO teams (salon, worker_user, email, color, job_title, employment_type, start_work, end_work, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmtTeam) { mysqli_rollback($con); mysqli_close($con); respond(500, ['error'=>'db prepare failed']); }
mysqli_stmt_bind_param($stmtTeam, 'issssisss', $salon_id, $phone_number, $email, $color, $job_title, $employment_type, $start_work, $end_work, $note);
$ok = mysqli_stmt_execute($stmtTeam);
if (!$ok) { mysqli_rollback($con); mysqli_close($con); respond(500, ['error'=>'failed to insert team record']); }
$teamId = intval(mysqli_insert_id($con));

$avatarSaved = false;
if ($isNewUser && $file && isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
    $destDir = '/home3/ctonfugw/public_html/images/users';
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) { mysqli_rollback($con); mysqli_close($con); respond(500, ['error'=>'failed to create avatar directory']); }
    $destPath = $destDir . '/' . $newUserId . '.jpg';
    $tmp = $file['tmp_name'];
    $image = @imagecreatefromjpeg($tmp);
    if ($image === false) { mysqli_rollback($con); mysqli_close($con); respond(400, ['error'=>'failed processing jpeg image']); }
    if (!imagejpeg($image, $destPath, 90)) { imagedestroy($image); mysqli_rollback($con); mysqli_close($con); respond(500, ['error'=>'failed saving avatar']); }
    imagedestroy($image);
    $stmtUpd = mysqli_prepare($con, "UPDATE users SET avatar = 1 WHERE lst = ?");
    if ($stmtUpd) {
        mysqli_stmt_bind_param($stmtUpd, 'i', $newUserId);
        mysqli_stmt_execute($stmtUpd);
    }
    $avatarSaved = true;
}

mysqli_commit($con);
mysqli_close($con);

$response = [
    'team_id' => $teamId,
    'user_id' => $newUserId,
    'is_new_user' => $isNewUser,
    'avatar_saved' => $avatarSaved,
    'worker_user' => $phone_number,
    'email' => $email,
    'job_title' => $job_title,
    'employment_type' => $employment_type,
    'start_work' => $start_work,
    'end_work' => $end_work,
    'note' => $note
];

if ($isNewUser) {
 //   $response['initial_password'] = $password_plain;
   // $response['referral_code'] = $referral_code;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
