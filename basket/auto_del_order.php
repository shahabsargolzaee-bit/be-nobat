<?php
header('Content-Type: application/json');
error_reporting(0);

$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);

$now = time();
$threshold = $now - 600; 


$delete_query = "DELETE FROM basket WHERE created_at < $threshold";
mysqli_query($con, $delete_query);


$deleted_count = mysqli_affected_rows($con);

mysqli_close($con);


echo json_encode([
    'message' => 'Expired basket items cleaned successfully',
    'deleted_count' => $deleted_count
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
