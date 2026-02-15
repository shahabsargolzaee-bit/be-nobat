<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json'); 
error_reporting(0);

$json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $type = $data['type'] ?? null;
   $user = $data['phone'] ?? null;
    $passw = $data['passw'] ?? null;


if($type=='' || strlen($passw)<4)
die();


 $db = "ctonfugw_main";
    $pass = "kwN?Cx#v77,u";
    $user1 = "ctonfugw_shahab";
    $server = "localhost";
    $con = mysqli_connect($server, $user1, $pass, $db);
    
    $type=mysqli_real_escape_string($con, $type);
    $user=mysqli_real_escape_string($con, $user);
    $passw=mysqli_real_escape_string($con, $passw);
    
    $ip1=GetRealIp();
   function GetRealIp()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else
        $ip = $_SERVER['REMOTE_ADDR'];
    return $ip;
}

function check_pass($type,$user,$passw) {
  global $con;

if($type=='0'){
    $result = mysqli_query($con, "SELECT username FROM users where username='$user' and token='$passw'");
}
if($type=='1'){
    $result = mysqli_query($con, "SELECT username FROM users where username='$user' and password='$passw'");
}
   
$row = mysqli_fetch_array($result);
  if($row[0]==$user){
      $jb='1';
  }else{
      $jb='0';
  }
  
    return $jb;
}  


function join1($user,$ip1) {
  global $con;
  
require_once("/home3/ctonfugw/public_html/api/jdate.php");

    $tt=time();
$last_seen = jdate("Y.m.d - H:i", $tt);
    
 mysqli_query($con,"Update users set ip='$ip1',last_seen='$last_seen',token='0',gen_time='0' where username='$user'");
 $result = mysqli_query($con, "SELECT lst,username,role,fname,lname FROM users where username='$user'");

   while ($row = mysqli_fetch_assoc($result)) {
        $inf[] = [
            'id' => $row['lst'],    
            'username' => $row['username'], 
            'role' => $row['role'],
            'fname' => $row['fname'],    
            'lname' => $row['lname'], 
        ];
    
    
    return $inf;
} 
}

$jb = check_pass($type,$user,$passw);
if($jb=='1'){
$jb1=join1($user,$ip1);


echo json_encode($jb1, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}else{
    echo "0";
}

mysqli_close($con);


?>