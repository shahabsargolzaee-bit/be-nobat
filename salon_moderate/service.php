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
        $sanitized[$key] = is_array($value) ? sanitize_array($con, $value) : mysqli_real_escape_string($con, trim($value));
    }
    return $sanitized;
}

$input = sanitize_array($con, json_decode(file_get_contents("php://input"), true));
if (!$input || !isset($input['type'])) {
    echo json_encode(['error' => 'Missing required field: type']);
    exit;
}

$type = intval($input['type']);


if ($type === 3) {
    if (!isset($input['salon']) || !isset($input['service']) || !isset($input['sub_service'])) {
        echo json_encode(['error' => 'Missing fields: salon, service, sub_service']);
        exit;
    }

    $salon_id = intval($input['salon']);
    $service_name = $input['service'];
    $sub_service_name = $input['sub_service'];

    $check_salon = mysqli_query($con, "SELECT 1 FROM salon WHERE id = '$salon_id' LIMIT 1");
    if (mysqli_num_rows($check_salon) === 0) {
        echo json_encode(['error' => 'Salon not found.']);
        exit;
    }

    $get_service = mysqli_query($con, "SELECT service_id FROM service_detail WHERE service = '$service_name' AND sub_service = '$sub_service_name' LIMIT 1");
    if (mysqli_num_rows($get_service) === 0) {
        echo json_encode(['error' => 'Service/sub-service not found in service_detail.']);
        exit;
    }
    $service_detail = mysqli_fetch_assoc($get_service);
    $service_id = intval($service_detail['service_id']);

    $delete = mysqli_query($con, "DELETE FROM service WHERE salon = '$salon_id' AND service = '$service_id'");
    if (!$delete) {
        echo json_encode(['error' => 'Failed to delete service records.', 'details' => mysqli_error($con)]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'All records of this service removed from salon.']);
    mysqli_close($con);
    exit;
}


if (!isset($input['salon']) || !isset($input['worker_phone'])) {
    echo json_encode(['error' => 'Missing fields: salon, worker_phone']);
    exit;
}

$salon_id = intval($input['salon']);
$worker_phone = $input['worker_phone'];


$get_salon = mysqli_query($con, "SELECT type FROM salon WHERE id = '$salon_id' LIMIT 1");
if (mysqli_num_rows($get_salon) === 0) {
    echo json_encode(['error' => 'Salon not found.']);
    exit;
}
$salon = mysqli_fetch_assoc($get_salon);
$salon_type = intval($salon['type']);


$get_user = mysqli_query($con, "SELECT lst, role FROM users WHERE username = '$worker_phone' LIMIT 1");
if (mysqli_num_rows($get_user) === 0) {
    echo json_encode(['error' => 'User not found with this phone number.']);
    exit;
}
$user = mysqli_fetch_assoc($get_user);
$worker_id = intval($user['lst']);

if ($type === 0 || $type === 1) {
    $required = ['service', 'sub_service', 'duration', 'price'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            echo json_encode(['error' => "Missing field: $field"]);
            exit;
        }
    }

    $service_name = $input['service'];
    $sub_service_name = $input['sub_service'];
    $duration = intval($input['duration']);
    $price = intval($input['price']);

    $get_service = mysqli_query($con, "SELECT service_id, gender FROM service_detail WHERE service = '$service_name' AND sub_service = '$sub_service_name' LIMIT 1");
    if (mysqli_num_rows($get_service) === 0) {
        echo json_encode(['error' => 'Service/sub-service not found in service_detail.']);
        exit;
    }
    $service_detail = mysqli_fetch_assoc($get_service);
    $service_id = intval($service_detail['service_id']);
    $service_gender = intval($service_detail['gender']);

    if ($service_gender !== $salon_type) {
        echo json_encode(['error' => 'Service gender does not match salon type.']);
        exit;
    }

    if ($type === 0) {
        $check_duplicate = mysqli_query($con, "SELECT 1 FROM service WHERE salon = '$salon_id' AND worker = '$worker_id' AND service = '$service_id' LIMIT 1");
        if (mysqli_num_rows($check_duplicate) > 0) {
            echo json_encode(['error' => 'This service is already assigned to this worker in this salon.']);
            exit;
        }

        $insert = mysqli_query($con, "INSERT INTO service (service, salon, worker, duration, price) VALUES ('$service_id', '$salon_id', '$worker_id', '$duration', '$price')");
        if (!$insert) {
            echo json_encode(['error' => 'Failed to insert service.', 'details' => mysqli_error($con)]);
            exit;
        }

        if (intval($user['role']) !== 2) {
            mysqli_query($con, "UPDATE users SET role = 2 WHERE lst = '$worker_id'");
        }

        echo json_encode(['success' => true, 'message' => 'Service assigned to worker.']);

    } elseif ($type === 1) {
        if (!isset($input['lst']) || !is_numeric($input['lst'])) {
            echo json_encode(['error' => 'Missing or invalid service ID (lst) for update.']);
            exit;
        }

        $service_row_id = intval($input['lst']);
        $check_exists = mysqli_query($con, "SELECT 1 FROM service WHERE lst = '$service_row_id' AND salon = '$salon_id' AND worker = '$worker_id' LIMIT 1");
        if (mysqli_num_rows($check_exists) === 0) {
            echo json_encode(['error' => 'Service record not found for this worker in this salon.']);
            exit;
        }

        $update = mysqli_query($con, "UPDATE service SET service = '$service_id', duration = '$duration', price = '$price' WHERE lst = '$service_row_id'");
        if (!$update) {
            echo json_encode(['error' => 'Failed to update service.', 'details' => mysqli_error($con)]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Service updated successfully.']);
    }

} elseif ($type === 2) {
    mysqli_query($con, "DELETE FROM service WHERE salon = '$salon_id' AND worker = '$worker_id'");

    $check_other_services = mysqli_query($con, "SELECT 1 FROM service WHERE worker = '$worker_id' LIMIT 1");
    if (mysqli_num_rows($check_other_services) === 0) {
        mysqli_query($con, "UPDATE users SET role = 3 WHERE lst = '$worker_id'");
    }

    echo json_encode(['success' => true, 'message' => 'Worker services removed from salon.']);
} else {
    echo json_encode(['error' => 'Invalid type. Use 0 for add, 1 for edit, 2 for remove, 3 for bulk delete.']);
}

mysqli_close($con);
?>
