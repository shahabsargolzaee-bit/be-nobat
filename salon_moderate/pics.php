<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);

$domain = "https://be-nobat.ir";
$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user, $pass, $db);
if (!$con) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

function sanitize($con, $value) {
    return mysqli_real_escape_string($con, trim($value));
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$is_json = stripos($contentType, 'application/json') !== false;

$input = $is_json ? json_decode(file_get_contents("php://input"), true) : $_POST;
$input = is_array($input) ? array_map(function($v) use ($con) {
    return is_string($v) ? sanitize($con, $v) : $v;
}, $input) : [];

$type = isset($input['type']) ? intval($input['type']) : null;
$salon_id = isset($input['salon']) ? intval($input['salon']) : null;

if ($type === null || $salon_id === null) {
    echo json_encode(['error' => 'Missing required fields: type, salon']);
    exit;
}

$check_salon = mysqli_query($con, "SELECT 1 FROM salon WHERE id = '$salon_id' LIMIT 1");
if (mysqli_num_rows($check_salon) === 0) {
    echo json_encode(['error' => 'Salon not found']);
    exit;
}

$upload_dir = "/home3/ctonfugw/public_html/images/salons/$salon_id/";
$base_url = "$domain/images/salons/$salon_id/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0775, true);
}

if ($type === 0) {
    $files = $_FILES['images'] ?? null;
    if (!$files || !is_array($files['name'])) {
        echo json_encode(['error' => 'No images uploaded']);
        exit;
    }

    $existing = glob($upload_dir . "*.jpg");
    if (count($existing) + count($files['name']) > 10) {
        echo json_encode(['error' => 'Maximum 10 images allowed per salon']);
        exit;
    }

    $max_size = 2048000;
    $uploaded = [];

    for ($i = 0; $i < count($files['name']); $i++) {
        $tmp = $files['tmp_name'][$i];
        $name = basename($files['name'][$i]);
        $type = mime_content_type($tmp);
        $size = $files['size'][$i];

        if ($size > $max_size) {
            echo json_encode(['error' => "File too large: $name"]);
            exit;
        }

        if ($type !== 'image/jpeg') {
            echo json_encode(['error' => "Only JPG images allowed: $name"]);
            exit;
        }

        if (preg_match('/\.(php|exe|sh|bat)$/i', $name)) {
            echo json_encode(['error' => "Executable files not allowed: $name"]);
            exit;
        }

        $unique = uniqid("salon_", true) . ".jpg";
        $target = $upload_dir . $unique;

        if (move_uploaded_file($tmp, $target)) {
            $uploaded[] = $unique;
        } else {
            echo json_encode(['error' => "Failed to upload: $name"]);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'uploaded' => $uploaded,
        'total' => count(glob($upload_dir . "*.jpg"))
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($type === 1) {
    $files = glob($upload_dir . "*.jpg");
    $list = [];

    foreach ($files as $file) {
        $name = basename($file);
        $filename = pathinfo($name, PATHINFO_FILENAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        $list[] = [
            'url' => $base_url . $name,
            'filename' => $filename,
            'extension' => $extension,
            'is_main' => $name === "01.jpg"
        ];
    }

    echo json_encode([
        'success' => true,
        'images' => $list
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}


$filename = isset($input['filename']) ? sanitize($con, $input['filename']) : '';

if ($type === 2) {
    if (!$filename || !preg_match('/\.jpg$/i', $filename)) {
        echo json_encode(['error' => 'Invalid filename']);
        exit;
    }

    $target = $upload_dir . $filename;
    if (!file_exists($target)) {
        echo json_encode(['error' => 'File not found']);
        exit;
    }

    if (unlink($target)) {
        echo json_encode(['success' => true, 'message' => 'File deleted']);
    } else {
        echo json_encode(['error' => 'Failed to delete file']);
    }
    exit;
}

if ($type === 3) {
    if (!$filename || !preg_match('/\.jpg$/i', $filename)) {
        echo json_encode(['error' => 'Invalid filename']);
        exit;
    }

    $target = $upload_dir . $filename;
    $main = $upload_dir . "01.jpg";

    if (!file_exists($target)) {
        echo json_encode(['error' => 'Requested file not found']);
        exit;
    }

    if (file_exists($main)) {
        $backup = $upload_dir . "backup_" . uniqid() . ".jpg";
        rename($main, $backup);
    }

    if (rename($target, $main)) {
        echo json_encode(['success' => true, 'message' => 'Main image updated']);
    } else {
        echo json_encode(['error' => 'Failed to update main image']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid type']);
