<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json'); 
error_reporting(0);

$json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
$salon_id = $data['salon_id'] ?? null;
$service_id = $data['service_id'] ?? null;






 $db = "ctonfugw_main";
    $pass = "kwN?Cx#v77,u";
    $user1 = "ctonfugw_shahab";
    $server = "localhost";
    $con = mysqli_connect($server, $user1, $pass, $db);
 
  $salon_id=mysqli_real_escape_string($con, $salon_id);
    $service_id=mysqli_real_escape_string($con, $service_id);
   
   
   

function get_salons($service_id,$salon_id) {
  global $con;


    $result = mysqli_query($con, "SELECT 
    s.salon,
    s.service,
    s.worker,
    s.price,
    s.duration,
    u.fname,
    u.lname,
    u.score,
    u.avatar,
    u.lst as user_id,
    wa.about as worker_about
FROM 
    service s
INNER JOIN 
    users u ON s.worker = u.lst
LEFT JOIN 
    worker_about wa ON s.worker = wa.worker AND s.salon = wa.salon
WHERE 
    s.salon = '$salon_id' 
    AND s.service = '$service_id'
ORDER BY s.price ASC");

    


 $workers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $workers[] = [
        'worker_id' => $row['worker'],    
        'worker_score' => $row['score'],  
        'worker_name' => $row['fname'] . " " . $row['lname'], 
        'worker_about' => $row['worker_about'],
        'picture' => $row['avatar'],
        'price' => $row['price'],         
        'duration' => $row['duration']   
    ];
}
    
    
    return $workers;
}  





$workers = get_salons($service_id,$salon_id);


mysqli_close($con);

$response = [
'salon_id'=> $salon_id, 
'service_id'=> $service_id,
    'service_workers' => $workers, 
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>