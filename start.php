<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json');
error_reporting(0);
 
 $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $countc = $data['countc'] ?? null;
    

if($countc=='')
$countc='5';

 $db = "ctonfugw_main";
    $pass = "kwN?Cx#v77,u";
    $user1 = "ctonfugw_shahab";
    $server = "localhost";
    $con = mysqli_connect($server, $user1, $pass, $db);
    
        $countc=mysqli_real_escape_string($con, $countc);
    
    if (!$con) {
        return ['error' => 'Failed to connect to database'];
    }

function get_cities() {
  global $con;


    $result = mysqli_query($con, "SELECT lst,city FROM cities where status=1");
    
    if (!$result) {
        mysqli_close($con);
        return ['error' => 'Failed to fetch cities'];
    }

    $cities = [];
    while ($row = mysqli_fetch_assoc($result)) {
         
         $cities[] = [
            'ID' => $row['lst'],    
            'city' => $row['city'],    
                  
        ];
    }
    
    
    return $cities;
}  


function get_comments($countc) {
  global $con;

    $result = mysqli_query($con, "SELECT full_name,detail,date,average_score FROM comments order by lst desc limit $countc");
    
    if (!$result) {
        mysqli_close($con);
        return ['error' => 'Failed to fetch comments'];
    }

  while ($row = mysqli_fetch_assoc($result)) {
        $comments[] = [
            'name' => $row['full_name'],    
            'comment' => $row['detail'],  
            'score' => $row['average_score'],
            'date' => $row['date'],       
        ];
    }
    
    
    return $comments;
} 


$cities = get_cities();
$comments=get_comments($countc);

mysqli_close($con);

$response = [
    'cities' => $cities, 
    'comments' => $comments, 
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>