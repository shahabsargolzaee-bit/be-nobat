<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
error_reporting(0);

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user, $pass, $db);
if (!$con) {
    echo json_encode(['error' => 'Database connection failed'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input['id']) || !is_numeric($input['id']) || !isset($input['user_id']) || !is_numeric($input['user_id'])) {
    echo json_encode(['error' => 'Missing or invalid salon ID or user ID'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$salon_id = intval($input['id']);
$user_id = intval($input['user_id']);

$check_salon = mysqli_query($con, "SELECT * FROM salon WHERE id = '$salon_id' LIMIT 1");
if (mysqli_num_rows($check_salon) === 0) {
    echo json_encode(['error' => 'Salon not found'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
$salon = mysqli_fetch_assoc($check_salon);
$owner_id = intval($salon['owner']);

if ($user_id !== $owner_id) {
    echo json_encode(['error' => 'Access denied. Only the salon owner can edit this record.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$fields = [
    'title', 'address', 'location', 'tel', 'province', 'city', 'type',
    'calendar_holidays', 'area', 'line', 'moving', 'floor', 'elevator',
    'child_state', 'wheel', 'cooling', 'heating', 'book', 'toy',
    'h_water', 'tv', 'music', 'park_space', 'drink', 'bisexual',
    'animal', 'dirt', 'dull', 'tolerance', 'social_network', 'about'
];

$updates = [];

foreach ($fields as $field) {
    if (isset($input[$field])) {
        $value = $input[$field];
        if (is_numeric($value)) {
            $updates[] = "$field = '" . intval($value) . "'";
        } else {
            $safe = mysqli_real_escape_string($con, trim($value));
            $updates[] = "$field = '$safe'";
        }
    }
}

if (isset($input['tel'])) {
    $tel = mysqli_real_escape_string($con, trim($input['tel']));
    $tel_check = mysqli_query($con, "SELECT 1 FROM salon WHERE tel = '$tel' AND id != '$salon_id' LIMIT 1");
    if (mysqli_num_rows($tel_check) > 0) {
        echo json_encode(['error' => 'Phone number already exists'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

if (isset($input['title'], $input['province'], $input['city'], $input['type'])) {
    $title = mysqli_real_escape_string($con, trim($input['title']));
    $province = mysqli_real_escape_string($con, trim($input['province']));
    $city = mysqli_real_escape_string($con, trim($input['city']));
    $type = intval($input['type']);
    $title_check = mysqli_query($con, "SELECT 1 FROM salon WHERE title = '$title' AND province = '$province' AND city = '$city' AND type = '$type' AND id != '$salon_id' LIMIT 1");
    if (mysqli_num_rows($title_check) > 0) {
        echo json_encode(['error' => 'Salon title already exists in this city, province, and type'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

if (empty($updates)) {
    echo json_encode(['error' => 'No editable fields provided'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$query = "UPDATE salon SET " . implode(', ', $updates) . " WHERE id = '$salon_id'";

if (mysqli_query($con, $query)) {
    echo json_encode(['success' => true, 'message' => 'Salon updated successfully'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'Update failed', 'details' => mysqli_error($con)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

mysqli_close($con);
?>