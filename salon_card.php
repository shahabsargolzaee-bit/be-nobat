<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json'); 
error_reporting(0);

$json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $counts = $data['counts'] ?? null;
$type = $data['type'] ?? null;

if($counts=='')
$counts='5';


if($type=='')
$type='0';




 $db = "ctonfugw_main";
    $pass = "kwN?Cx#v77,u";
    $user1 = "ctonfugw_shahab";
    $server = "localhost";
    $con = mysqli_connect($server, $user1, $pass, $db);
 
  $counts=mysqli_real_escape_string($con, $counts);
    $type=mysqli_real_escape_string($con, $type);
   

function get_salons($counts,$type) {
  global $con;

if($type=='0'){
    $result = mysqli_query($con, "SELECT id,title,city,score,province,address,about,ccount FROM salon order by score desc limit $counts");
}elseif($type=='1'){
$result = mysqli_query($con, "SELECT id,title,city,score,province,address,about,ccount FROM salon order by id desc limit $counts");
}
    
    if (!$result) {
        mysqli_close($con);
        return ['error' => 'Failed to fetch cities'];
    }

    $salons = [];
    while ($row = mysqli_fetch_assoc($result)) {
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
        ];
    }
    
    
    return $salons;
}  





$salons = get_salons($counts,$type);


mysqli_close($con);

$response = [
    'salon' => $salons, 
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>