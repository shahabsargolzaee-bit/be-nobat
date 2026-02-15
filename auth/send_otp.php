<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json'); 
error_reporting(0);
 ini_set("soap.wsdl_cache_enabled", "0");

$json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $phone= $data['phone'] ?? null;
    

if($phone=='')
die();


 $db = "ctonfugw_main";
    $pass = "kwN?Cx#v77,u";
    $user1 = "ctonfugw_shahab";
    $server = "localhost";
    $con = mysqli_connect($server, $user1, $pass, $db);
    
         $phone=mysqli_real_escape_string($con, $phone);
    
    
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



function send($phone,$ip1) {
     
  global $con;


    $result = mysqli_query($con, "SELECT username FROM users where username='$phone'");
   
 $row = mysqli_fetch_array($result);
 
 if($row[0]!=$phone){
 
  $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = substr(str_shuffle($chars), 0, 7);
     $pss = substr(str_shuffle($chars), 0, 6);
 mysqli_query($con,"INSERT INTO users(username,password,role,referral_code,ip) VALUES('$phone', '$pss', '1','$randomString','$ip1')");
 }
    $token = rand(1000, 9999);
    $tt=time();
     mysqli_query($con,"Update users set token='$token',gen_time='$tt' where username='$phone'");
     
     
    
  try {
$client = new SoapClient('http://api.payamak-panel.com/post/send.asmx?wsdl', array('encoding'=>'UTF-8'));
 $parameters['username'] = "moeinmashayekhi";
    $parameters['password'] = "@Pp134460796";
    $parameters['from'] = "50002002801749";
    $parameters['to'] = array($phone);
    $parameters['text'] ="کد تایید یکبار مصرف: ".$token."\n"."be-nobat";
    $parameters['isflash'] = true;
    $parameters['udh'] = "";
    $parameters['recId'] = array(0);
    $parameters['status'] = 0x0;
echo $client->GetCredit(array("username"=>"moeinmashayekhi","password"=>"@Pp134460796"))->GetCreditResult;
echo $client->SendSms($parameters)->SendSmsResult;

 } catch (SoapFault $ex) {
    
}


    
    return $status;
}  





$ans = send($phone,$ip1);


mysqli_close($con);

echo $ans;
?>