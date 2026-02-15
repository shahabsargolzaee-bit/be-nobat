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
    echo json_encode(['error' => 'Database connection failed'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$data = $_POST;

$required_fields = [
    'owner', 'title', 'address', 'location', 'tel',
    'province', 'city', 'type', 'calendar_holidays', 'area', 'line',
    'moving', 'floor', 'elevator', 'child_state', 'wheel', 'cooling',
    'heating', 'book', 'toy', 'h_water', 'tv', 'music', 'park_space',
    'drink', 'bisexual', 'animal', 'dirt', 'dull', 'tolerance',
    'about'
];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        echo json_encode(['error' => "Missing required field: $field"], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

$state = 0;
$score = 0;
$ccount = 0;
$esteemuntil = '0';
$social_network = mysqli_real_escape_string($con, $data['social_network'] ?? '');

$owner = mysqli_real_escape_string($con, $data['owner']);
$title = mysqli_real_escape_string($con, $data['title']);
$address = mysqli_real_escape_string($con, $data['address']);
$location = mysqli_real_escape_string($con, $data['location']);
$tel = mysqli_real_escape_string($con, $data['tel']);
$province = mysqli_real_escape_string($con, $data['province']);
$city = mysqli_real_escape_string($con, $data['city']);
$type = intval($data['type']);
$calendar_holidays = intval($data['calendar_holidays']);
$area = intval($data['area']);
$line = intval($data['line']);
$moving = intval($data['moving']);
$floor = intval($data['floor']);
$elevator = intval($data['elevator']);
$child_state = intval($data['child_state']);
$wheel = intval($data['wheel']);
$cooling = intval($data['cooling']);
$heating = intval($data['heating']);
$book = intval($data['book']);
$toy = intval($data['toy']);
$h_water = intval($data['h_water']);
$tv = intval($data['tv']);
$music = intval($data['music']);
$park_space = intval($data['park_space']);
$drink = intval($data['drink']);
$bisexual = intval($data['bisexual']);
$animal = intval($data['animal']);
$dirt = intval($data['dirt']);
$dull = intval($data['dull']);
$tolerance = intval($data['tolerance']);
$about = mysqli_real_escape_string($con, $data['about']);

$tel_check = mysqli_query($con, "SELECT 1 FROM salon WHERE tel='$tel' LIMIT 1");
if (mysqli_num_rows($tel_check) > 0) {
    mysqli_close($con);
    echo json_encode(['error' => 'Phone number already exists.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$title_check = mysqli_query($con, "SELECT 1 FROM salon WHERE title='$title' AND province='$province' AND city='$city' AND type='$type' LIMIT 1");
if (mysqli_num_rows($title_check) > 0) {
    mysqli_close($con);
    echo json_encode(['error' => 'Salon title already exists in this city, province, and type.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$query = "INSERT INTO salon (
    title, owner, state, esteemuntil, address, location, tel, province, city, type,
    calendar_holidays, area, line, moving, floor, elevator, score, child_state, wheel,
    cooling, heating, book, toy, h_water, tv, music, park_space, drink, bisexual,
    animal, dirt, dull, tolerance, social_network, about, ccount
) VALUES (
    '$title', '$owner', '$state', '$esteemuntil', '$address', '$location', '$tel', '$province', '$city', '$type',
    '$calendar_holidays', '$area', '$line', '$moving', '$floor', '$elevator', '$score', '$child_state', '$wheel',
    '$cooling', '$heating', '$book', '$toy', '$h_water', '$tv', '$music', '$park_space', '$drink', '$bisexual',
    '$animal', '$dirt', '$dull', '$tolerance', '$social_network', '$about', '$ccount'
)";

if (mysqli_query($con, $query)) {
    $salon_id = mysqli_insert_id($con);

    $valid_images = [];
    if (isset($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
        $valid_images = array_filter($_FILES['images']['name'], function($name) {
            return !empty($name);
        });
    }

    if (count($valid_images) > 10) {
        echo json_encode(['error' => 'Maximum 10 images allowed.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $upload_path = "/home3/ctonfugw/public_html/images/salons/$salon_id/";
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }

    foreach ($_FILES['images']['name'] as $index => $name) {
        $tmp_name = $_FILES['images']['tmp_name'][$index];
        $size = $_FILES['images']['size'][$index];
        $type = mime_content_type($tmp_name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $allowed_exts = ['jpg', 'jpeg', 'png'];

        if (!in_array($type, $allowed_types) || !in_array($ext, $allowed_exts)) {
            continue;
        }

        if ($size > 5 * 1024 * 1024) {
            continue;
        }

        $image_number = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
        $target_file = $upload_path . "$image_number.jpg";

        if ($index === 0 && $ext !== 'jpg') {
            echo json_encode(['error' => 'First image must be in JPG format.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        move_uploaded_file($tmp_name, $target_file);
    }

    echo json_encode(['success' => true, 'salon_id' => $salon_id], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'Salon registration failed.', 'details' => mysqli_error($con)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

mysqli_close($con);
?>