<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tehran');
require_once '/home3/ctonfugw/public_html/api/jdate.php';
function respond(int $code, $data) {
    global $con;
    if (isset($con) && $con) @mysqli_close($con);
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
function is_valid_jdate(string $d): bool {
    return (bool) preg_match('/^\d{4}\.(0[1-9]|1[0-2])\.(0[1-9]|[12]\d|3[01])$/', $d);
}
function jalali_range(string $start, string $end): array {
    $out = [];
    list($sy,$sm,$sd) = explode('.', $start);
    list($ey,$em,$ed) = explode('.', $end);
    list($gsy,$gsm,$gsd) = jalali_to_gregorian((int)$sy,(int)$sm,(int)$sd);
    list($gey,$gem,$ged) = jalali_to_gregorian((int)$ey,(int)$em,(int)$ed);
    $ts = strtotime("$gsy-$gsm-$gsd");
    $end_ts = strtotime("$gey-$gem-$ged");
    if ($ts === false || $end_ts === false) return $out;
    while ($ts <= $end_ts) {
        $out[] = jdate('Y.m.d', $ts);
        $ts = strtotime('+1 day', $ts);
    }
    return $out;
}
function normalize_time(string $t): string {
    if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t.':00';
    return $t;
}
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) respond(400, ['error'=>'Invalid JSON input']);
$type = isset($in['type']) ? intval($in['type']) : null;
if (!in_array($type, [0,1,2,4], true)) respond(422, ['error'=>'Field type is required and must be 0,1,2 or 4']);
$con = mysqli_connect('localhost','ctonfugw_shahab','kwN?Cx#v77,u','ctonfugw_main');
if (!$con) respond(500, ['error'=>'Database connection failed']);
if ($type === 0) {
    $salon_id = $in['salon_id'] ?? null;
    $worker_id = $in['worker_id'] ?? null;
    if (!isset($salon_id) || !isset($worker_id)) respond(422, ['error'=>'salon_id and worker_id are required for type 0']);
    if (!preg_match('/^\d+$/', $salon_id) || !preg_match('/^\d+$/', $worker_id)) respond(422, ['error'=>'salon_id and worker_id must be numeric']);
    $qSalon = mysqli_query($con, "SELECT ID FROM salon WHERE ID='".mysqli_real_escape_string($con,$salon_id)."' LIMIT 1");
    if (!$qSalon) respond(500, ['error'=>'Failed to query salon','mysql_error'=>mysqli_error($con)]);
    if (mysqli_num_rows($qSalon) === 0) respond(404, ['error'=>'Salon not found']);
    $qWorker = mysqli_query($con, "SELECT lst, username FROM users WHERE lst='".mysqli_real_escape_string($con,$worker_id)."' LIMIT 1");
    if (!$qWorker) respond(500, ['error'=>'Failed to query worker','mysql_error'=>mysqli_error($con)]);
    if (mysqli_num_rows($qWorker) === 0) respond(404, ['error'=>'Worker not found']);
    $workerRow = mysqli_fetch_assoc($qWorker);
    $worker_username = $workerRow['username'];
    $qTeam = mysqli_query($con, "SELECT LST FROM teams WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker_user='".mysqli_real_escape_string($con,$worker_username)."' LIMIT 1");
    if (!$qTeam) respond(500, ['error'=>'Failed to check team membership','mysql_error'=>mysqli_error($con)]);
    if (mysqli_num_rows($qTeam) === 0) respond(403, ['error'=>'Worker is not a member of this salon']);
    $q = "SELECT title, MIN(date) AS min_date, MAX(date) AS max_date, MIN(LST) AS main_id, memo FROM turn WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker='".mysqli_real_escape_string($con,$worker_id)."' AND reg_by = 0 GROUP BY title, memo ORDER BY min_date ASC";
    $res = mysqli_query($con, $q);
    if (!$res) respond(500, ['error'=>'Query failed','mysql_error'=>mysqli_error($con)]);
    $blocks = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $title = $r['title'] ?? '';
        if ($title === '') $title = '(بدون عنوان)';
        $blocks[] = [
            'main_id' => intval($r['main_id']),
            'title' => $title,
            'memo' => $r['memo'] ?? null,
            'date_start' => $r['min_date'],
            'date_end' => $r['max_date']
        ];
    }
    respond(200, ['salon_id'=>intval($salon_id),'worker_id'=>intval($worker_id),'blocks'=>$blocks]);
}
if ($type === 2) {
    $block_id = $in['block_id'] ?? null;
    if (!isset($block_id) || !preg_match('/^\d+$/', $block_id)) respond(422, ['error'=>'block_id is required and must be numeric for type 2']);
    $q0 = mysqli_query($con, "SELECT salon, worker, title, memo, start_time, end_time, reg_by FROM turn WHERE LST='".mysqli_real_escape_string($con,$block_id)."' LIMIT 1");
    if (!$q0) respond(500, ['error'=>'Failed to query block id','mysql_error'=>mysqli_error($con)]);
    if (mysqli_num_rows($q0) === 0) respond(404, ['error'=>'Block id not found']);
    $row0 = mysqli_fetch_assoc($q0);
    if (isset($row0['reg_by']) && intval($row0['reg_by']) === 1) respond(409, ['error'=>'Provided id belongs to a reservation (reg_by=1). This endpoint only deletes block times (reg_by=0).']);
    $salon_id = $row0['salon'];
    $worker_id = $row0['worker'];
    $title = $row0['title'] ?? '';
    $qUser = mysqli_query($con, "SELECT username FROM users WHERE lst='".mysqli_real_escape_string($con,$worker_id)."' LIMIT 1");
    if (!$qUser) respond(500, ['error'=>'Failed to query worker username','mysql_error'=>mysqli_error($con)]);
    $urow = mysqli_fetch_assoc($qUser);
    $worker_username = $urow['username'] ?? null;
    if ($worker_username === null) respond(404, ['error'=>'Worker not found']);
    $qTeam = mysqli_query($con, "SELECT LST FROM teams WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker_user='".mysqli_real_escape_string($con,$worker_username)."' LIMIT 1");
    if (!$qTeam) respond(500, ['error'=>'Failed to check team membership','mysql_error'=>mysqli_error($con)]);
    if (mysqli_num_rows($qTeam) === 0) respond(403, ['error'=>'Worker is not a member of this salon']);
    $qList = "SELECT MIN(LST) AS main_id, MIN(date) AS min_date, MAX(date) AS max_date FROM turn WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker='".mysqli_real_escape_string($con,$worker_id)."' AND reg_by = 0 AND title = '".mysqli_real_escape_string($con,$title)."' AND start_time = '".mysqli_real_escape_string($con,$row0['start_time'])."' AND end_time = '".mysqli_real_escape_string($con,$row0['end_time'])."'";
    $rl = mysqli_query($con, $qList);
    if (!$rl) respond(500, ['error'=>'Failed to list block records','mysql_error'=>mysqli_error($con)]);
    $meta = mysqli_fetch_assoc($rl);
    $del_q = "DELETE FROM turn WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker='".mysqli_real_escape_string($con,$worker_id)."' AND reg_by = 0 AND title = '".mysqli_real_escape_string($con,$title)."' AND start_time = '".mysqli_real_escape_string($con,$row0['start_time'])."' AND end_time = '".mysqli_real_escape_string($con,$row0['end_time'])."'";
    $del = mysqli_query($con, $del_q);
    if (!$del) respond(500, ['error'=>'Delete failed','mysql_error'=>mysqli_error($con)]);
    $deleted_count = mysqli_affected_rows($con);
    respond(200, ['message'=>'Block series deleted','main_id'=>intval($meta['main_id'] ?? $block_id),'title'=>$title,'date_start'=>$meta['min_date'] ?? null,'date_end'=>$meta['max_date'] ?? null,'deleted_count'=>intval($deleted_count)]);
}
if ($type === 4) {
    $block_id = $in['block_id'] ?? null;
    if (!isset($block_id) || !preg_match('/^\d+$/', $block_id)) respond(422, ['error'=>'block_id is required and must be numeric for type 4']);
    $q0 = mysqli_query($con, "SELECT LST, salon, worker, title, memo, start_time, end_time, service_duration, created_time, reg_by FROM turn WHERE LST='".mysqli_real_escape_string($con,$block_id)."' LIMIT 1");
    if (!$q0) respond(500, ['error'=>'Failed to query block id','mysql_error'=>mysqli_error($con)]);
    if (mysqli_num_rows($q0) === 0) respond(404, ['error'=>'Block id not found']);
    $row0 = mysqli_fetch_assoc($q0);
    if (isset($row0['reg_by']) && intval($row0['reg_by']) === 1) respond(409, ['error'=>'Provided id belongs to a reservation (reg_by=1). This endpoint only returns block time details (reg_by=0).']);
    $salon_id = $row0['salon'];
    $worker_id = $row0['worker'];
    $title = $row0['title'] ?? '';
    $memo = $row0['memo'] ?? null;
    $start_time = $row0['start_time'] ?? null;
    $end_time = $row0['end_time'] ?? null;
    $service_duration = isset($row0['service_duration']) ? intval($row0['service_duration']) : null;
    $created_time = $row0['created_time'] ?? null;
    $qMeta = "SELECT MIN(date) AS date_start, MAX(date) AS date_end, COUNT(*) AS cnt FROM turn WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker='".mysqli_real_escape_string($con,$worker_id)."' AND reg_by = 0 AND title = '".mysqli_real_escape_string($con,$title)."' AND start_time = '".mysqli_real_escape_string($con,$start_time)."' AND end_time = '".mysqli_real_escape_string($con,$end_time)."'";
    $rMeta = mysqli_query($con, $qMeta);
    if (!$rMeta) respond(500, ['error'=>'Failed to fetch block metadata','mysql_error'=>mysqli_error($con)]);
    $meta = mysqli_fetch_assoc($rMeta);
    $date_start = $meta['date_start'] ?? null;
    $date_end = $meta['date_end'] ?? null;
    $cnt = isset($meta['cnt']) ? intval($meta['cnt']) : 0;
    $repeat = $cnt > 1 ? 1 : 0;
    respond(200, [
        'block_id' => intval($block_id),
        'salon_id' => intval($salon_id),
        'worker_id' => intval($worker_id),
        'title' => $title,
        'memo' => $memo,
        'date_start' => $date_start,
        'date_end' => $date_end,
        'repeat' => $repeat,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'duration' => $service_duration,
        'created_time' => $created_time
    ]);
}
if ($type === 1) {
    $required = ['date_start','start_time','end_time','repeat','title','block_id'];
    foreach ($required as $k) {
        if (!isset($in[$k]) || $in[$k] === '') respond(422, ['error'=>"Field {$k} is required for type 1"]);
    }
    $block_id = $in['block_id'];
    if (!preg_match('/^\d+$/', $block_id)) respond(422, ['error'=>'block_id must be numeric']);
    $q0 = mysqli_query($con, "SELECT salon, worker, title, memo, start_time, end_time, reg_by FROM turn WHERE LST='".mysqli_real_escape_string($con,$block_id)."' LIMIT 1");
    if (!$q0) respond(500, ['error'=>'Failed to query block id','mysql_error'=>mysqli_error($con)]);
    if (mysqli_num_rows($q0) === 0) respond(404, ['error'=>'Block id not found']);
    $orig = mysqli_fetch_assoc($q0);
    if (isset($orig['reg_by']) && intval($orig['reg_by']) === 1) respond(409, ['error'=>'Provided id belongs to a reservation (reg_by=1). This endpoint only edits block times (reg_by=0).']);
    $salon_id = $orig['salon'];
    $worker_id = $orig['worker'];
    $orig_title = $orig['title'] ?? '';
    $orig_memo = $orig['memo'] ?? null;
    $orig_start_time = $orig['start_time'] ?? null;
    $orig_end_time = $orig['end_time'] ?? null;
    $date_start = $in['date_start'];
    $start_time = $in['start_time'];
    $end_time = $in['end_time'];
    $repeat = intval($in['repeat']);
    $date_end = $in['date_end'] ?? null;
    $memo = isset($in['memo']) ? $in['memo'] : null;
    $title_new = trim((string)$in['title']);
    if ($repeat !== 0 && $repeat !== 1) respond(422, ['error'=>'repeat must be 0 or 1']);
    if (!is_valid_jdate($date_start)) respond(422, ['error'=>'date_start must be Jalali YYYY.MM.DD']);
    if ($repeat === 1) {
        if ($date_end === null || $date_end === '') respond(422, ['error'=>'date_end is required when repeat is 1']);
        if (!is_valid_jdate($date_end)) respond(422, ['error'=>'date_end must be Jalali YYYY.MM.DD']);
        if (strcmp($date_end, $date_start) < 0) respond(422, ['error'=>'date_end must not be before date_start']);
    }
    if (!is_string($title_new) || $title_new === '') respond(422, ['error'=>'title is required']);
    if (mb_strlen($title_new) > 50) respond(422, ['error'=>'title must be at most 50 characters']);
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) respond(422, ['error'=>'Invalid start_time format']);
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) respond(422, ['error'=>'Invalid end_time format']);
    $start_time = normalize_time($start_time);
    $end_time = normalize_time($end_time);
    try {
        $dt_start_time = new DateTime(substr($start_time,0,5).":00");
        $dt_end_time = new DateTime(substr($end_time,0,5).":00");
    } catch (Exception $e) { respond(422, ['error'=>'Provided times are not parsable']); }
    if ($dt_end_time <= $dt_start_time) respond(422, ['error'=>'end_time must be after start_time']);
    $today_j = jdate('Y.m.d');
    $now_time = date('H:i:s');
    if (strcmp($date_start, $today_j) < 0) respond(422, ['error'=>'date_start must not be before today']);
    if ($date_start === $today_j) {
        try { $dt_now = new DateTime(substr($now_time,0,5).":00"); } catch (Exception $e) { respond(500, ['error'=>'Server time error']); }
        if ($dt_start_time <= $dt_now) respond(422, ['error'=>'start_time must be after current time for today']);
    }
    $qUser = mysqli_query($con, "SELECT username FROM users WHERE lst='".mysqli_real_escape_string($con,$worker_id)."' LIMIT 1");
    if (!$qUser) respond(500, ['error'=>'Failed to query worker username','mysql_error'=>mysqli_error($con)]);
    $urow = mysqli_fetch_assoc($qUser);
    $worker_username = $urow['username'] ?? null;
    if ($worker_username === null) respond(404, ['error'=>'Worker not found']);
    $qTeam = mysqli_query($con, "SELECT LST FROM teams WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker_user='".mysqli_real_escape_string($con,$worker_username)."' LIMIT 1");
    if (!$qTeam) respond(500, ['error'=>'Failed to check team membership','mysql_error'=>mysqli_error($con)]);
    if (mysqli_num_rows($qTeam) === 0) respond(403, ['error'=>'Worker is not a member of this salon']);
    $qOld = "SELECT LST, date, start_time, end_time, title, memo, service, service_duration FROM turn WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker='".mysqli_real_escape_string($con,$worker_id)."' AND reg_by = 0 AND title = '".mysqli_real_escape_string($con,$orig_title)."' AND start_time = '".mysqli_real_escape_string($con,$orig_start_time)."' AND end_time = '".mysqli_real_escape_string($con,$orig_end_time)."' ORDER BY date ASC";
    $rOld = mysqli_query($con, $qOld);
    if (!$rOld) respond(500, ['error'=>'Failed to fetch existing series','mysql_error'=>mysqli_error($con)]);
    $old_rows = [];
    while ($rr = mysqli_fetch_assoc($rOld)) $old_rows[] = $rr;
    $orig_repeat = count($old_rows) > 1 ? 1 : 0;
    if ($repeat !== $orig_repeat) respond(422, ['error'=>'Changing the repeat value is not allowed. To change the repeat, first delete this time block and then create a new block with the repeat value.']);
    $dates = $repeat === 1 ? jalali_range($date_start, $date_end) : [$date_start];
    if (empty($dates)) respond(422, ['error'=>'No dates to process']);
    $conflicts = [];
    $existing_block_conflicts = [];
    foreach ($dates as $d) {
        $q1 = "SELECT LST, start_time, end_time, status FROM turn WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker='".mysqli_real_escape_string($con,$worker_id)."' AND date='".mysqli_real_escape_string($con,$d)."' AND reg_by = 1 AND status NOT IN (3,4)";
        $r1 = mysqli_query($con, $q1);
        if (!$r1) respond(500, ['error'=>'Failed to query existing reservations','mysql_error'=>mysqli_error($con)]);
        while ($ex = mysqli_fetch_assoc($r1)) {
            $ex_start = normalize_time($ex['start_time']);
            $ex_end = normalize_time($ex['end_time']);
            try { $ex_dt_start = new DateTime($ex_start); $ex_dt_end = new DateTime($ex_end); } catch (Exception $e) { continue; }
            if (!($dt_end_time <= $ex_dt_start || $dt_start_time >= $ex_dt_end)) {
                $conflicts[] = ['date'=>$d,'conflict_type'=>'reservation','turn_id'=>intval($ex['LST']),'start_time'=>$ex_start,'end_time'=>$ex_end,'status'=>intval($ex['status'])];
            }
        }
        $q2 = "SELECT LST, start_time, end_time, title FROM turn WHERE salon='".mysqli_real_escape_string($con,$salon_id)."' AND worker='".mysqli_real_escape_string($con,$worker_id)."' AND date='".mysqli_real_escape_string($con,$d)."' AND reg_by = 0 AND title != '".mysqli_real_escape_string($con,$orig_title)."'";
        $r2 = mysqli_query($con, $q2);
        if (!$r2) respond(500, ['error'=>'Failed to query existing blocks','mysql_error'=>mysqli_error($con)]);
        while ($ex = mysqli_fetch_assoc($r2)) {
            $ex_start = normalize_time($ex['start_time']);
            $ex_end = normalize_time($ex['end_time']);
            try { $ex_dt_start = new DateTime($ex_start); $ex_dt_end = new DateTime($ex_end); } catch (Exception $e) { continue; }
            if (!($dt_end_time <= $ex_dt_start || $dt_start_time >= $ex_dt_end)) {
                $existing_block_conflicts[] = ['date'=>$d,'conflict_type'=>'existing_block','turn_id'=>intval($ex['LST']),'start_time'=>$ex_start,'end_time'=>$ex_end,'title'=>$ex['title']];
            }
        }
    }
    if (!empty($conflicts) || !empty($existing_block_conflicts)) respond(409, ['error'=>'Conflicts detected, edit aborted','conflicts'=>$conflicts,'existing_blocks'=>$existing_block_conflicts]);
    mysqli_begin_transaction($con);
    $old_ids = array_map(function($r){ return intval($r['LST']); }, $old_rows);
    $new_dates = $dates;
    $updated_ids = [];
    $inserted_ids = [];
    $deleted_ids = [];
    $min = min(count($old_ids), count($new_dates));
    for ($i = 0; $i < $min; $i++) {
        $id = $old_ids[$i];
        $d = $new_dates[$i];
        $sql = "UPDATE turn SET date='".mysqli_real_escape_string($con,$d)."', start_time='".mysqli_real_escape_string($con,$start_time)."', end_time='".mysqli_real_escape_string($con,$end_time)."', title='".mysqli_real_escape_string($con, mb_substr($title_new,0,50))."', memo=".($memo===null?"NULL":"'".mysqli_real_escape_string($con,$memo)."'").", service=1 WHERE LST=".intval($id)." LIMIT 1";
        $u = mysqli_query($con, $sql);
        if (!$u) { mysqli_rollback($con); respond(500, ['error'=>'Failed to update existing record','mysql_error'=>mysqli_error($con),'id'=>$id]); }
        $updated_ids[] = $id;
    }
    if (count($new_dates) > count($old_ids)) {
        for ($j = $min; $j < count($new_dates); $j++) {
            $d = $new_dates[$j];
            $duration = intval((intval(substr($end_time,0,2))*60 + intval(substr($end_time,3,2))) - (intval(substr($start_time,0,2))*60 + intval(substr($start_time,3,2))));
            $created_time = jdate('Y.m.d - H:i');
            $sql = "INSERT INTO turn (customer,service,salon,worker,status,date,start_time,end_time,service_duration,tolerance,pay_state,price,reg_by,created_time,memo,title) VALUES (1,1,".intval($salon_id).",".intval($worker_id).",0,'".mysqli_real_escape_string($con,$d)."','".mysqli_real_escape_string($con,$start_time)."','".mysqli_real_escape_string($con,$end_time)."',".intval($duration).",0,0,0,0,'".mysqli_real_escape_string($con,$created_time)."',".($memo===null?"NULL":"'".mysqli_real_escape_string($con,$memo)."'").",'".mysqli_real_escape_string($con, mb_substr($title_new,0,50))."')";
            $ins = mysqli_query($con, $sql);
            if (!$ins) { mysqli_rollback($con); respond(500, ['error'=>'Failed to insert new record','mysql_error'=>mysqli_error($con)]); }
            $new_id = mysqli_insert_id($con);
            $inserted_ids[] = $new_id;
        }
    } elseif (count($old_ids) > count($new_dates)) {
        $to_delete = array_slice($old_ids, $min);
        $ids_sql = implode(',', $to_delete);
        $del_q = "DELETE FROM turn WHERE LST IN ($ids_sql)";
        $del = mysqli_query($con, $del_q);
        if (!$del) { mysqli_rollback($con); respond(500, ['error'=>'Failed to delete extra old records','mysql_error'=>mysqli_error($con)]); }
        $deleted_ids = $to_delete;
    }
    $old_min_date = null;
    $old_max_date = null;
    if (!empty($old_rows)) {
        $dates_only = array_map(function($r){ return $r['date']; }, $old_rows);
        sort($dates_only);
        $old_min_date = $dates_only[0];
        $old_max_date = $dates_only[count($dates_only)-1];
    }
    $new_min_date = null;
    $new_max_date = null;
    if (!empty($new_dates)) {
        $nd = $new_dates;
        sort($nd);
        $new_min_date = $nd[0];
        $new_max_date = $nd[count($nd)-1];
    }
    $changes_summary = [];
    if ($orig_title !== $title_new) $changes_summary['title'] = ['old'=>$orig_title,'new'=>$title_new];
    if ($orig_start_time !== $start_time) $changes_summary['start_time'] = ['old'=>$orig_start_time,'new'=>$start_time];
    if ($orig_end_time !== $end_time) $changes_summary['end_time'] = ['old'=>$orig_end_time,'new'=>$end_time];
    if ($orig_memo !== $memo) $changes_summary['memo'] = ['old'=>$orig_memo,'new'=>$memo];
    $orig_service_val = isset($old_rows[0]['service']) ? intval($old_rows[0]['service']) : 0;
    if ($orig_service_val !== 1) $changes_summary['service'] = ['old'=>$orig_service_val,'new'=>1];
    if ($old_min_date !== $new_min_date || $old_max_date !== $new_max_date) $changes_summary['date_range'] = ['old'=>['start'=>$old_min_date,'end'=>$old_max_date],'new'=>['start'=>$new_min_date,'end'=>$new_max_date]];
    mysqli_commit($con);
    respond(200, [
        'message'=>'Block series edited successfully',
        'updated_count'=>count($updated_ids),
        'inserted_count'=>count($inserted_ids),
        'deleted_count'=>count($deleted_ids),
        'summary'=>$changes_summary
    ]);
}
respond(400, ['error'=>'Unhandled type']);
?>
