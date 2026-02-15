<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);
if (!$con) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function sanitize_array($con, $array) {
    $sanitized = [];
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = sanitize_array($con, $value);
        } else {
            $sanitized[$key] = mysqli_real_escape_string($con, trim($value));
        }
    }
    return $sanitized;
}


$input = json_decode(file_get_contents("php://input"), true);
if (
    !$input ||
    !isset($input['salon']) ||
    !isset($input['shifts']) ||
    !isset($input['type']) ||
    !is_array($input['shifts'])
) {
    echo json_encode(['error' => 'Invalid input. Expecting salon, shifts, and type.']);
    exit;
}

$salon_id = intval($input['salon']);
$shifts = $input['shifts'];
$type = intval($input['type']);
$days = ['sat', 'sun', 'mon', 'tues', 'wed', 'thurs', 'fri'];
$valid_shifts = ['1_s', '1_e', '2_s', '2_e'];

$data = sanitize_array($con, $_POST); 
$input = sanitize_array($con, json_decode(file_get_contents("php://input"), true)); 


$check_salon = mysqli_query($con, "SELECT 1 FROM salon WHERE id = '$salon_id' LIMIT 1");
if (mysqli_num_rows($check_salon) === 0) {
    echo json_encode(['error' => 'Salon not found. Invalid salon ID.']);
    exit;
}


$check_time = mysqli_query($con, "SELECT 1 FROM salon_time WHERE salon = '$salon_id' LIMIT 1");
$has_existing = mysqli_num_rows($check_time) > 0;

if ($type === 0 && $has_existing) {
    echo json_encode(['error' => 'Working hours already exist. Use update mode (type=1).']);
    exit;
}

if ($type === 1 && !$has_existing) {
    echo json_encode(['error' => 'No working hours found to update. Use insert mode (type=0).']);
    exit;
}


if ($type === 1) {
    mysqli_query($con, "DELETE FROM salon_time WHERE salon = '$salon_id'");
}

$inserted = [];

foreach ($valid_shifts as $index => $shift_code) {
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

    $sat   = mysqli_real_escape_string($con, $times['sat']);
    $sun   = mysqli_real_escape_string($con, $times['sun']);
    $mon   = mysqli_real_escape_string($con, $times['mon']);
    $tues  = mysqli_real_escape_string($con, $times['tues']);
    $wed   = mysqli_real_escape_string($con, $times['wed']);
    $thurs = mysqli_real_escape_string($con, $times['thurs']);
    $fri   = mysqli_real_escape_string($con, $times['fri']);

    $query = "INSERT INTO salon_time (
        shift, sat, sun, mon, tues, wed, thurs, fri, salon
    ) VALUES (
        '$shift_code', '$sat', '$sun', '$mon', '$tues', '$wed', '$thurs', '$fri', '$salon_id'
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
