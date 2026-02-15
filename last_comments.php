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
        
        
        
function get_comments($countc) {
  global $con;

$result = mysqli_query($con, "SELECT 
    c.lst,
    c.customer,
    c.title,
    c.detail,
    c.date,
    c.score,
    u.lst as user_id,
    u.avatar,
    u.fname,
    u.lname
FROM site_comments c
LEFT JOIN users u ON c.customer = u.lst
ORDER BY c.score DESC limit $countc");

    
 

  while ($row = mysqli_fetch_assoc($result)) {
      $u=$row['customer'];
      $result1 = mysqli_query($con, "SELECT lst,province,city,avatar FROM users where lst='$u'");
      $row1 = mysqli_fetch_assoc($result1);
      
     
      
        $comments[] = [
            'UserID' => $row['lst'],
            'name' => $row['fname']." ".$row['lname'],  
             'title' => $row['title'], 
            'comment' => $row['detail'],    
            'score' => $row['score'],
            'date' => $row['date'], 
            'avatar' => $row1['avatar']
        ];
    }
    
    
    return $comments;
} 


$comments=get_comments($countc);

mysqli_close($con);

$response = [
    'comments' => $comments, 
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        ?>