<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json'); 
error_reporting(0);
require_once("jdf.php");





$json = file_get_contents('php://input');
$data = json_decode($json, true);
    
$id = $data['id'] ?? null;








$db = "ctonfugw_main";
$pass = "kwN?Cx#v77,u";
$user1 = "ctonfugw_shahab";
$server = "localhost";
$con = mysqli_connect($server, $user1, $pass, $db);
 
$id = mysqli_real_escape_string($con, $id);
  
  


  
  
function get_salon($id) {
    global $con;

    $result = mysqli_query($con, "SELECT id,owner,title,address,location,province,city,type,area,line,moving,floor,elevator,score,child_state,wheel,cooling,heating,book,toy,h_water,tv,music,park_space,drink,bisexual,animal,dirt,dull,tolerance,social_network,about,ccount,calendar_holidays from salon where id='$id'");
    
    $result1 = mysqli_query($con, "SELECT 
        s.service as service_id,
        sd.service as service_name,
        sd.sub_service,
        sd.service_id as sub_service_id,
        s.price,
        s.duration,
        s.worker
    FROM service s
    INNER JOIN service_detail sd ON s.service = sd.service_id
    WHERE s.salon = '$id'
    ORDER BY s.service, sd.sub_service, s.price");

$result2 = mysqli_query($con, "SELECT 
    c.lst,
    c.customer,
    c.detail,
    c.date,
    c.worker,
    c.average_score,
    c.full_name,
    u.lst as user_id,
    u.avatar
FROM comments c
LEFT JOIN users u ON c.worker = u.lst
ORDER BY c.lst DESC");

$result3 = mysqli_query($con, "SELECT
w.lst,w.worker,w.about,u.fname,u.lname,u.avatar,u.score
from worker_about w
LEFT JOIN users u ON w.worker=u.lst
WHERE w.salon='$id'");

$result4 = mysqli_query($con, "SELECT lst,service,worker,date,start_time,end_time,service_duration,tolerance from turn WHERE salon='$id' and status='0'");
    
$result5 = mysqli_query($con, "SELECT shift,sat,sun,mon,tues,wed,thurs,fri from salon_time WHERE salon='$id'");

$schedule = [
    'saturday' => ['shift1_start' => null, 'shift1_end' => null, 'shift2_start' => null, 'shift2_end' => null, 'is_shift1_closed' => false, 'is_shift2_closed' => false, 'is_day_closed' => false],
    'sunday' => ['shift1_start' => null, 'shift1_end' => null, 'shift2_start' => null, 'shift2_end' => null, 'is_shift1_closed' => false, 'is_shift2_closed' => false, 'is_day_closed' => false],
    'monday' => ['shift1_start' => null, 'shift1_end' => null, 'shift2_start' => null, 'shift2_end' => null, 'is_shift1_closed' => false, 'is_shift2_closed' => false, 'is_day_closed' => false],
    'tuesday' => ['shift1_start' => null, 'shift1_end' => null, 'shift2_start' => null, 'shift2_end' => null, 'is_shift1_closed' => false, 'is_shift2_closed' => false, 'is_day_closed' => false],
    'wednesday' => ['shift1_start' => null, 'shift1_end' => null, 'shift2_start' => null, 'shift2_end' => null, 'is_shift1_closed' => false, 'is_shift2_closed' => false, 'is_day_closed' => false],
    'thursday' => ['shift1_start' => null, 'shift1_end' => null, 'shift2_start' => null, 'shift2_end' => null, 'is_shift1_closed' => false, 'is_shift2_closed' => false, 'is_day_closed' => false],
    'friday' => ['shift1_start' => null, 'shift1_end' => null, 'shift2_start' => null, 'shift2_end' => null, 'is_shift1_closed' => false, 'is_shift2_closed' => false, 'is_day_closed' => false]
];


while ($row12 = mysqli_fetch_assoc($result5)) {
    $shift = $row12['shift'];
    
 
    if ($shift === '1_s') {
        $shift_type = 'shift1_start';
    } elseif ($shift === '1_e') {
        $shift_type = 'shift1_end';
    } elseif ($shift === '2_s') {
        $shift_type = 'shift2_start';
    } elseif ($shift === '2_e') {
        $shift_type = 'shift2_end';
    } else {
        continue;
    }
    
  
    $schedule['saturday'][$shift_type] = $row12['sat'];
    $schedule['sunday'][$shift_type] = $row12['sun'];
    $schedule['monday'][$shift_type] = $row12['mon'];
    $schedule['tuesday'][$shift_type] = $row12['tues'];
    $schedule['wednesday'][$shift_type] = $row12['wed'];
    $schedule['thursday'][$shift_type] = $row12['thurs'];
    $schedule['friday'][$shift_type] = $row12['fri'];
}


$days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

foreach ($days as $day) {
    if ($schedule[$day]['shift1_start'] === '00:00:00' && $schedule[$day]['shift1_end'] === '00:00:00') {
        $schedule[$day]['is_shift1_closed'] = true;
        $schedule[$day]['shift1_start'] = null;
        $schedule[$day]['shift1_end'] = null;
    } else {
        $schedule[$day]['is_shift1_closed'] = false;
    }
    

    if ($schedule[$day]['shift2_start'] === '00:00:00' && $schedule[$day]['shift2_end'] === '00:00:00') {
        $schedule[$day]['is_shift2_closed'] = true;
        $schedule[$day]['shift2_start'] = null;
        $schedule[$day]['shift2_end'] = null;
    } else {
        $schedule[$day]['is_shift2_closed'] = false;
    }
    
    if ($schedule[$day]['is_shift1_closed'] && $schedule[$day]['is_shift2_closed']) {
        $schedule[$day]['is_day_closed'] = true;
    } else {
        $schedule[$day]['is_day_closed'] = false;
    }
}


    $salonId = $id;
    $imagesDirectory = '/home3/ctonfugw/public_html/images/salons/' . $salonId . '/';
    $imageFiles = [];
    
    if (is_dir($imagesDirectory)) {
        $files = scandir($imagesDirectory);
        $files = array_diff($files, array('.', '..'));
        foreach ($files as $file) {
            $filePath = $imagesDirectory . $file;
      
            if (is_file($filePath) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), 
                ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $imageFiles[] = $file; 
            }
        }
        if (empty($imageFiles)) {
            $imageFiles[] = '01.jpg';
        }
    } else {
        $imageFiles[] = '01.jpg';
    }
    
  
  $services = array();
$grouped_services = array();


  
while ($serviceRow = mysqli_fetch_assoc($result1)) {
    $service_id = (int)$serviceRow['service_id'];
    $service_name = $serviceRow['service_name']; 
    $sub_service_name = $serviceRow['sub_service'];
    $sub_service_id = (int)$serviceRow['sub_service_id'];
    
    
    if (!isset($grouped_services[$service_name])) {
        $grouped_services[$service_name] = array(
            "service_name" => $service_name,
            "sub_services" => array()
        );
    }
    
    $current_price = $serviceRow['price'] ?? 0;
    
    if (!isset($grouped_services[$service_name]['sub_services'][$sub_service_id]) || 
        $current_price < ($grouped_services[$service_name]['sub_services'][$sub_service_id]['price'] ?? PHP_INT_MAX)) {
        
        $grouped_services[$service_name]['sub_services'][$sub_service_id] = array(
            "service_id" => $sub_service_id,
            "sub_service_name" => $sub_service_name ?? '',
            "price" => (float)$current_price,
            "duration" => $serviceRow['duration'] ?? '',
            "worker_id" => $serviceRow['worker'] ?? ''
        );
    }
}


foreach ($grouped_services as $service_name => $service_data) {
    $sub_services_array = array();
    
   
    ksort($service_data['sub_services']);
    
    foreach ($service_data['sub_services'] as $sub_service_id => $sub_service_data) {
        $sub_services_array[] = $sub_service_data;
    }
    
    $service_data['sub_services'] = $sub_services_array;
    $services[] = $service_data;
}
    
        $comments = [];
    while ($commentRow = mysqli_fetch_assoc($result2)) {
        $comments[] = [
            'ID' => $commentRow['lst'],
            'full_name' => $commentRow['full_name'],
            'Commenter_ID'=> $commentRow['customer'],
            'detail' => $commentRow['detail'],
            'date' => $commentRow['date'],
            'average_score'=> $commentRow['average_score'],
             'avatar'=> $commentRow['avatar'],
        ];
    }
    
          $team = [];
    while ($teamRow = mysqli_fetch_assoc($result3)) {
        $team[] = [
            'ID' => $teamRow['lst'],
            'worker_id' => $teamRow['worker'],
            'worker_name'=> $teamRow['fname'].' '. $teamRow['lname'],
            'score' => $teamRow['score'],
            'bio' => $teamRow['about'],
            'picture' => $teamRow['avatar']
        ];
    }
    
            $resv = [];
    while ($resvRow = mysqli_fetch_assoc($result4)) {
        $resv[] = [
            'ID' => $resvRow['lst'],
            'service' => $resvRow['service'],
            'worker_id'=> $resvRow['worker'],
            'date' => $resvRow['date'],
            'start_time' => $resvRow['start_time'],
            'end_time' => $resvRow['end_time'],
            'service_duration' => $resvRow['service_duration'],
            'tolerance' => $resvRow['tolerance'],
        ];
    }
    
    
    
    
    
    
    
$current_time = date('H:i:s');
$current_day_index = date('w');
$day_map = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
$current_day = $day_map[$current_day_index];
$current_date = jdate('Y.m.d');

$block_query = mysqli_query($con, "SELECT start_time,end_time FROM turn WHERE salon='$id' AND date='$current_date' AND reg_by=0");
$blocked = [];
while ($row = mysqli_fetch_assoc($block_query)) {
    $blocked[] = [$row['start_time'], $row['end_time']];
}

function isBlockedRange($start, $end, $blocked) {
    foreach ($blocked as $range) {
        if ($start < $range[1] && $end > $range[0]) {
            return true;
        }
    }
    return false;
}

$is_open = false;
$open_at = null;
$closed_at = null;

foreach (['shift1_start','shift2_start'] as $key) {
    $start = $schedule[$current_day][$key];
    $end = $schedule[$current_day][str_replace('_start','_end',$key)];
    if ($start && $end && !$schedule[$current_day]['is_day_closed']) {
        if (!isBlockedRange($start, $end, $blocked)) {
            if ($current_time >= $start && $current_time <= $end) {
                $is_open = true;
                $closed_at = $end;
                break;
            } elseif ($current_time < $start && !$open_at) {
                $open_at = $start;
            }
        }
    }
}

if (!$is_open && !$open_at) {
    for ($i = 1; $i <= 7; $i++) {
        $next_day_index = ($current_day_index + $i) % 7;
        $next_day = $day_map[$next_day_index];
        $next_date = jdate('Y.m.d', strtotime("+$i day"));

        $next_block_query = mysqli_query($con, "SELECT start_time,end_time FROM turn WHERE salon='$id' AND date='$next_date' AND reg_by=0");
        $next_blocked = [];
        while ($row = mysqli_fetch_assoc($next_block_query)) {
            $next_blocked[] = [$row['start_time'], $row['end_time']];
        }

        foreach (['shift1_start','shift2_start'] as $key) {
            $start = $schedule[$next_day][$key];
            $end = $schedule[$next_day][str_replace('_start','_end',$key)];
            if ($start && $end && !$schedule[$next_day]['is_day_closed']) {
                if (!isBlockedRange($start, $end, $next_blocked)) {
                    $open_at = ($i === 1 ? 'فردا ' : 'in '.$i.' days ') . $start;
                    break 2;
                }
            }
        }
    }
}
    
    
    
    
    
    
    
    $salon = [];
    $row = mysqli_fetch_assoc($result);

        $salon[] = [
            'ID' => $row['id'],
            'title' => $row['title'],  
               'is_open_now' => $is_open,
    'open_at' => $is_open ? null : $open_at,
    'closed_at' => $is_open ? $closed_at : null,
            'score' => $row['score'],  
            'pic' => 'salons/'.$row['id'].'/01.jpg',
            'images' => $imageFiles,
            'province' => $row['province'],
            'city' => $row['city'],
            'address' => $row['address'],
            'about' => $row['about'],
            'working_time' => $schedule,
            'calendar_holidays'=>$row['calendar_holidays'],
            'CommentCount' => $row['ccount'],
            'location' => $row['location'],
            'type' => $row['type'],
            'area' => $row['area'],
            'line' => $row['line'],
            'moving' => $row['moving'],
            'floor' => $row['floor'],
            'elevator' => $row['elevator'],
            'child_state' => $row['child_state'],
            'wheel' => $row['wheel'],
            'cooling' => $row['cooling'],
            'heating' => $row['heating'],
            'magazine' => $row['book'],
            'toy' => $row['toy'],
            'h_water' => $row['h_water'],
            'tv' => $row['tv'],
            'music' => $row['music'],
            'park_space' => $row['park_space'],
            'drink' => $row['drink'],
            'bisexual' => $row['bisexual'],
            'animal' => $row['animal'],
            'dirt' => $row['dirt'],
            'dull' => $row['dull'],
            'tolerance' => $row['tolerance'],
            'social_network' => $row['social_network'],
            'about' => $row['about'],
            'team' => $team,
            'services' => $services,
            'comments'=>$comments
        ];
    
    
    return $salon;
}  

$salon = get_salon($id);
mysqli_close($con);

$response = [
    'salon' => $salon, 
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>