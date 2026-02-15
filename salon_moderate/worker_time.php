<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user, $pass, $db);
if (!$con) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function sanitize_array($con, $array) {
    $sanitized = [];
    foreach ($array as $key => $value) {
        $sanitized[$key] = is_array($value)
            ? sanitize_array($con, $value)
            : mysqli_real_escape_string($con, trim($value));
    }
    return $sanitized;
}

$input = sanitize_array($con, json_decode(file_get_contents("php://input"), true));
if (!$input || !isset($input['type']) || !isset($input['salon']) || !isset($input['worker'])) {
    echo json_encode(['error' => 'Missing required fields: type, salon, worker']);
    exit;
}

$type = intval($input['type']);
$salon_id = intval($input['salon']);
$worker_id = intval($input['worker']);
$days = ['sat', 'sun', 'mon', 'tues', 'wed', 'thurs', 'fri'];
$valid_shifts = ['1_s', '1_e', '2_s', '2_e'];

$check_salon = mysqli_query($con, "SELECT 1 FROM salon WHERE id = '$salon_id' LIMIT 1");
if (mysqli_num_rows($check_salon) === 0) {
    echo json_encode(['error' => 'Salon not found']);
    exit;
}

$check_worker = mysqli_query($con, "SELECT 1 FROM users WHERE lst = '$worker_id' LIMIT 1");
if (mysqli_num_rows($check_worker) === 0) {
    echo json_encode(['error' => 'Worker not found']);
    exit;
}

$check_existing = mysqli_query($con, "SELECT 1 FROM worker_time WHERE salon = '$salon_id' AND worker = '$worker_id' LIMIT 1");
$has_existing = mysqli_num_rows($check_existing) > 0;

if ($type === 2) {
    mysqli_query($con, "DELETE FROM worker_time WHERE salon = '$salon_id' AND worker = '$worker_id'");
    echo json_encode(['success' => true, 'message' => 'Worker time records deleted']);
    mysqli_close($con);
    exit;
}

if (!isset($input['shifts']) || !is_array($input['shifts'])) {
    echo json_encode(['error' => 'Missing or invalid shifts array']);
    exit;
}

$shifts = $input['shifts'];

if ($type === 0 && $has_existing) {
    echo json_encode(['error' => 'Working hours already exist. Use type=1 to update']);
    exit;
}

if ($type === 1 && !$has_existing) {
    echo json_encode(['error' => 'No working hours found to update. Use type=0 to insert']);
    exit;
}

if ($type === 1) {
    mysqli_query($con, "DELETE FROM worker_time WHERE salon = '$salon_id' AND worker = '$worker_id'");
}

$inserted = [];

foreach ($valid_shifts as $shift_code) {
    if (!isset($shifts[$shift_code]) || !is_array($shifts[$shift_code])) {
        echo json_encode(['error' => "Missing or invalid shift: $shift_code"]);
        exit;
    }

    $times = $shifts[$shift_code];
    foreach ($days as $day) {
        if (!isset($times[$day])) {
            echo json_encode(['error' => "Missing $day time for shift $shift_code"]);
            exit;
        }

        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $times[$day])) {
            echo json_encode(['error' => "Invalid time format for $day in shift $shift_code. Expected HH:MM:SS"]);
            exit;
        }
    }

    $sat   = $times['sat'];
    $sun   = $times['sun'];
    $mon   = $times['mon'];
    $tues  = $times['tues'];
    $wed   = $times['wed'];
    $thurs = $times['thurs'];
    $fri   = $times['fri'];

    $query = "INSERT INTO worker_time (
        shift, sat, sun, mon, tues, wed, thurs, fri, worker, salon
    ) VALUES (
        '$shift_code', '$sat', '$sun', '$mon', '$tues', '$wed', '$thurs', '$fri', '$worker_id', '$salon_id'
    )";

    if (mysqli_query($con, $query)) {
        $inserted[] = $shift_code;
    } else {
        echo json_encode(['error' => "Failed to insert shift $shift_code", 'details' => mysqli_error($con)]);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'mode' => $type === 0 ? 'inserted' : 'updated',
    'shifts' => $inserted
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

mysqli_close($con);
?>