<?php
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');
require_once '/home3/ctonfugw/public_html/api/jdate.php';

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

function jalaliToTimestamp(string $j): ?int {
    if (!preg_match('/^(\d{4})\.(0[1-9]|1[0-2])\.(0[1-9]|[12]\d|3[01])$/', $j, $m)) return null;
    [$_, $jy, $jm, $jd] = $m;
    [$gy, $gm, $gd] = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
    return mktime(0, 0, 0, $gm, $gd, $gy);
}

$raw = json_decode(file_get_contents('php://input'), true);
$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error'=>'database connection failed']);
mysqli_set_charset($con, 'utf8');

$owner_id = intval(mysqli_real_escape_string($con, $raw['owner_user_id'] ?? '0'));
$salon_id = intval(mysqli_real_escape_string($con, $raw['salon_id']        ?? '0'));
$start_j  = mysqli_real_escape_string($con, $raw['start_date']          ?? '');
$end_j    = mysqli_real_escape_string($con, $raw['end_date']            ?? '');

if ($owner_id <= 0)         respond(400, ['error'=>'owner_user_id is required']);
if ($salon_id <= 0)         respond(400, ['error'=>'salon_id is required']);
$start_ts = jalaliToTimestamp($start_j);
$end_ts   = jalaliToTimestamp($end_j);
if ($start_ts === null)     respond(400, ['error'=>'start_date invalid format']);
if ($end_ts   === null)     respond(400, ['error'=>'end_date invalid format']);
$today_ts = mktime(0,0,0);
if ($start_ts < $today_ts)  respond(400, ['error'=>'start_date must be >= today']);
if ($end_ts <= $start_ts)   respond(400, ['error'=>'end_date must be > start_date']);
//if ((($end_ts - $start_ts)/86400) > 7) respond(400, ['error'=>'range must be at most 7 days']);

$stmt = mysqli_prepare($con, "SELECT 1 FROM salon WHERE ID = ? AND owner = ? LIMIT 1");
mysqli_stmt_bind_param($stmt,'ii',$salon_id,$owner_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) === 0) {
    mysqli_close($con);
    respond(403, ['error'=>'not owner of this salon']);
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($con,
    "SELECT DISTINCT wt.worker, u.fname, u.lname, u.score, u.avatar
       FROM worker_time wt
       JOIN users u ON u.lst = wt.worker
      WHERE wt.salon = ?"
);
mysqli_stmt_bind_param($stmt,'i',$salon_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$colMap = [
    0 => 'sun', 1 => 'mon', 2 => 'tues', 3 => 'wed',
    4 => 'thurs', 5 => 'fri', 6 => 'sat'
];

$output = [];
while ($u = mysqli_fetch_assoc($res)) {
    $workerId    = intval($u['worker']);
    $firstLetter = mb_substr($u['fname'], 0, 1, 'UTF-8');

    $stmt2 = mysqli_prepare($con,
        "SELECT shift,sat,sun,mon,tues,wed,thurs,fri
           FROM worker_time
          WHERE salon = ? AND worker = ?"
    );
    mysqli_stmt_bind_param($stmt2,'ii',$salon_id,$workerId);
    mysqli_stmt_execute($stmt2);
    $r2 = mysqli_stmt_get_result($stmt2);
    $shifts = [];
    while ($row2 = mysqli_fetch_assoc($r2)) {
        $shifts[$row2['shift']] = $row2;
    }
    mysqli_stmt_close($stmt2);

    $schedule = [];
    $totalSec = 0;
    for ($ts = $start_ts; $ts <= $end_ts; $ts += 86400) {
        $date_j    = jdate('Y.m.d', $ts);
        $weekday   = html_entity_decode(jdate('l', $ts), ENT_QUOTES, 'UTF-8');
        $monthName = html_entity_decode(jdate('F', $ts), ENT_QUOTES, 'UTF-8');
        $dayNum    = (int) jdate('j', $ts);
        $w         = date('w', $ts);
        $col       = $colMap[$w] ?? null;
        $dayShifts = [];

        for ($i = 1; $i <= 2; $i++) {
            $sKey = "{$i}_s";
            $eKey = "{$i}_e";
            $sT   = $col && isset($shifts[$sKey][$col]) ? $shifts[$sKey][$col] : null;
            $eT   = $col && isset($shifts[$eKey][$col]) ? $shifts[$eKey][$col] : null;
            if ($sT && $eT) {
                $delta = strtotime($eT) - strtotime($sT);
                if ($delta > 0) $totalSec += $delta;
            }
            $dayShifts[] = ['shift'=>$i,'start'=>$sT,'end'=>$eT];
        }

        $schedule[] = [
            'date_j'  => $date_j,
            'weekday' => $weekday,
            'month'   => $monthName,
            'day'     => $dayNum,
            'shifts'  => $dayShifts
        ];
    }

    $weeklyHours = round($totalSec / 3600);
    $output[] = [
        'worker_id'    => $workerId,
        'fname'        => $u['fname'],
        'first_letter' => $firstLetter,
        'lname'        => $u['lname'],
        'weekly_hours'=> $weeklyHours,
        'avatar'       => intval($u['avatar']),
        'schedule'     => $schedule
    ];
}

mysqli_close($con);
respond(200, ['data'=>$output]);
