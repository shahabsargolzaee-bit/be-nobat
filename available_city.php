<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json'); 
error_reporting(0);

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$province_id = $data['province_id'] ?? null;

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);

$province_id = mysqli_real_escape_string($con, $province_id);

function get_available_cities($province_id) {
    global $con;

    $query = "SELECT city_id, city FROM cities WHERE status = 1 AND province_id = '$province_id'";
    $result = mysqli_query($con, $query);

    if (!$result) {
        mysqli_close($con);
        return ['error' => 'Failed to fetch cities'];
    }

    $cities = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $cities[] = [
            'city_id' => $row['city_id'],
            'city' => $row['city']
        ];
    }

    return $cities;
}

$cities = get_available_cities($province_id);

mysqli_close($con);

$response = [
    'cities' => $cities
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
