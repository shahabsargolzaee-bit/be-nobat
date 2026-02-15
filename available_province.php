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

function get_provinces_with_active_cities() {
    global $con;

    $query = "SELECT DISTINCT province_id, province FROM cities WHERE status = 1";
    $result = mysqli_query($con, $query);

    if (!$result) {
        mysqli_close($con);
        return ['error' => 'Failed to fetch provinces'];
    }

    $provinces = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $provinces[] = [
            'province_id' => $row['province_id'],
            'province' => $row['province']
        ];
    }

    return $provinces;
}

$provinces = get_provinces_with_active_cities();

mysqli_close($con);

$response = [
    'provinces' => $provinces
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
