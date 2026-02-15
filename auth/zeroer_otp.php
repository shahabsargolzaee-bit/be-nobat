<?php
error_reporting(0);
 
 



 $db = "ctonfugw_main";
    $pass = "kwN?Cx#v77,u";
    $user1 = "ctonfugw_shahab";
    $server = "localhost";
    $con = mysqli_connect($server, $user1, $pass, $db);
    




function exp1() {
     
  global $con;


  
 
   $result = mysqli_query($con, "SELECT gen_time FROM users where gen_time<>'0'");
   
 
    $tt=time()-180;
     
     
      while ($row = mysqli_fetch_array($result)) {
        mysqli_query($con,"Update users set token='',gen_time='0' where gen_time<'$tt'");
    }
    

}  





 exp1();


mysqli_close($con);

echo "ok";
?>