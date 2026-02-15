<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);


    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $type = $data['gender'] ?? null;


if($type!='0' && $type!='1'){
    echo 'Please send a valid Gender with POST method !';
    die();
}
 

function get_service($type) {
    $db = "ctonfugw_main";
    $pass = "kwN?Cx#v77,u";
    $user1 = "ctonfugw_shahab";
    $server = "localhost";

    $con = mysqli_connect($server, $user1, $pass, $db);
    
     $type=mysqli_real_escape_string($con, $type);
  
    
  

    $result = mysqli_query($con, "SELECT service, service_id 
                             FROM service_detail 
                             WHERE gender='$type' 
                             GROUP BY service");
    


    $services = []; 
    while ($row = mysqli_fetch_array($result)) {
       $services[] = [
            'ID' => $row['service_id'],  
            'service' => $row['service'],
              
                  
        ]; 
    }
    
    mysqli_close($con);
    return $services;
}  

$jb = get_service($type);
echo json_encode($jb, JSON_UNESCAPED_UNICODE);
?>