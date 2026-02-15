<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json'); 
error_reporting(0);

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$gender = $data['gender'] ?? null;
$date = $data['date'] ?? null;
$province = $data['province'] ?? null;
$city = $data['city'] ?? null;
$service = $data['service'] ?? null;
$from = isset($data['from']) ? (int)$data['from'] : 0;
$to = isset($data['to']) ? (int)$data['to'] : 20;

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);

$gender = mysqli_real_escape_string($con, $gender);
$date = mysqli_real_escape_string($con, $date);
$province = mysqli_real_escape_string($con, $province);
$city = mysqli_real_escape_string($con, $city);
$service = mysqli_real_escape_string($con, $service);

$service_ids = [];
if ($service !== null && $service !== '*') {
    $service_lookup = mysqli_query($con, "SELECT service_id FROM service_detail WHERE service = '$service'");
    while ($row = mysqli_fetch_assoc($service_lookup)) {
        $service_ids[] = (int)$row['service_id'];
    }
}

function get_service_tags($salon_id, $service_name_input) {
    global $con;

    $query = "
        SELECT 
            s.service as service_id,
            sd.service as service_name,
            MIN(s.price) as min_price,
            MIN(s.duration) as duration
        FROM service s
        INNER JOIN service_detail sd ON s.service = sd.service_id
        WHERE s.salon = '$salon_id'
        GROUP BY s.service
    ";

    $result = mysqli_query($con, $query);
    $all_services = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $sid = (int)$row['service_id'];
        $all_services[$sid] = [
            'service_id' => $sid,
            'service_name' => $row['service_name'],
            'min_price' => (int)$row['min_price'],
            'duration' => (int)$row['duration']
        ];
    }


    $main_ids = [];
    $main_lookup = mysqli_query($con, "SELECT service_id FROM service_detail WHERE service = '$service_name_input'");
    while ($row = mysqli_fetch_assoc($main_lookup)) {
        $main_ids[] = (int)$row['service_id'];
    }

    $tags = [];

    foreach ($main_ids as $mid) {
        if (isset($all_services[$mid])) {
            $tags[] = $all_services[$mid];
            unset($all_services[$mid]);
            break;
        }
    }

    $remaining = array_values($all_services);
    shuffle($remaining);

    for ($i = 0; $i < 4 && $i < count($remaining); $i++) {
        $tags[] = $remaining[$i];
    }

 
    $unique_tags = [];
    $seen_names = [];

    foreach ($tags as $tag) {
        if (!in_array($tag['service_name'], $seen_names)) {
            $unique_tags[] = $tag;
            $seen_names[] = $tag['service_name'];
        }
    }

    return $unique_tags;
}

function get_salons($gender, $date, $province, $city, $service_ids, $from, $to, $service_name_input) {
    global $con;

    $conditions = ["s.state = '1'"];
    $joins = [
        "INNER JOIN service sv ON s.id = sv.salon",
        "INNER JOIN service_detail sd ON sv.service = sd.service_id"
    ];

    if ($gender !== null && $gender !== '*') {
        $conditions[] = "s.type = '$gender'";
    }

    if ($province !== null && $province !== '*') {
        $conditions[] = "s.province = '$province'";
    }

    if ($city !== null && $city !== '*') {
        $conditions[] = "s.city = '$city'";
    }

    if (!empty($service_ids)) {
        $id_list = implode(',', $service_ids);
        $conditions[] = "sd.service_id IN ($id_list)";
    }

    $whereClause = implode(" AND ", $conditions);
    $joinClause = implode(" ", $joins);

    $count_query = "
        SELECT COUNT(DISTINCT s.id) AS total
        FROM salon s
        $joinClause
        WHERE $whereClause
    ";
    $count_result = mysqli_query($con, $count_query);
    $total_count = 0;
    if ($count_result && $row = mysqli_fetch_assoc($count_result)) {
        $total_count = (int)$row['total'];
    }

    $limit = $to - $from;
    $query = "
        SELECT DISTINCT
            s.id,
            s.title,
            s.city,
            s.score,
            s.province,
            s.address,
            s.about,
            s.ccount
        FROM salon s
        $joinClause
        WHERE $whereClause
        ORDER BY s.score DESC
        LIMIT $from, $limit
    ";

    $result = mysqli_query($con, $query);

    if (!$result) {
        return ['salons' => [], 'total_count' => 0];
    }

    $salons = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tags = get_service_tags($row['id'], $service_name_input);

        $salons[] = [
            'ID' => $row['id'],
            'title' => $row['title'],
            'score' => $row['score'],
            'pic' => 'salons/'.$row['id'].'/01.jpg',
            'province' => $row['province'],
            'city' => $row['city'],
            'address' => $row['address'],
            'about' => $row['about'],
            'CommentCount' => $row['ccount'],
            'tags' => $tags
        ];
    }

    return ['salons' => $salons, 'total_count' => $total_count];
}

$data = get_salons($gender, $date, $province, $city, $service_ids, $from, $to, $service);
$actual_to = $from + count($data['salons']);

mysqli_close($con);

$response = [
    'salon' => $data['salons'],
    'from' => $from,
    'to' => $actual_to,
    'total_count' => $data['total_count']
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
