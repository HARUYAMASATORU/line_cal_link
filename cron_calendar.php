<?php
require_once dirname(dirname(__FILE__)).'/wp-load.php';
require_once dirname(dirname(__FILE__)).'/wp-content/themes/mywiki/functions.php';


function get_org_cal(){
  global $wpdb;
  $arg =
    "SELECT *
    FROM apple_help_calendar
    WHERE id in (1,2,3,4,5)
    LIMIT 5 ";
  $resuls = $wpdb->get_results($wpdb->prepare($arg));
  return $resuls;
}
function event_get_uri_create($org){ //https://www.worksapis.com/v1.0/users/{userId}/calendars/{calendarId}/events
  $cal_url1 ="https://www.worksapis.com/v1.0/users/";
  $cal_userid = $org["cal_userid"];
  $cal_url2 = "/calendars/";
  $cal_id = $org["read_calendar"];
  $cal_url3 = "/events";
  $cal_url_events = $cal_url1 . $cal_userid . $cal_url2 . $cal_id . $cal_url3;
  return $cal_url_events;
}
function parameter_combine($event_get_uri,$start,$end){
$current_datetime = date('Y-m-d 00:00:00', strtotime($start));
$current_endtime = date("Y-m-d 00:00:00", strtotime($end));
$current_datetime = (string)$current_datetime;
$current_endtime = (string)$current_endtime;

$current_datetime = str_replace(" ","T",$current_datetime);
$current_endtime = str_replace(" ","T",$current_endtime);

$data1 = 'fromDateTime=' . $current_datetime . "%2B00:00";
$data2 = 'untilDateTime=' . $current_endtime . "%2B00:00";

$event_get_uri .= "?" . $data1 ."&". $data2;
return $event_get_uri;
}

function in_box($events){
$event = array();
$event['eventId'] = $events['eventComponents'][0]["eventId"];//イベントid
//$event['eventId'] = substr($events['eventComponents'][0]["eventId"], 0, -13);//イベントid
$event['updatedTime'] = $events['eventComponents'][0]["updatedTime"]["dateTime"];//更新日
$event['summary'] = $events['eventComponents'][0]["summary"];//件名
$event['startdate'] = $events['eventComponents'][0]["start"]["date"];//開始時間
$event['startdateTime'] = $events['eventComponents'][0]["start"]["dateTime"];//開始時間
$event['enddate'] = $events['eventComponents'][0]["end"]["date"];//終了時間
$event['enddateTime'] = $events['eventComponents'][0]["end"]["dateTime"];//終了時間
$event['attendees'] = $events['eventComponents'][0]["attendees"][0]["displayName"];//支援者名
return $event;
}
function attended_check($event,$access_token2){
$position = mb_strpos($event['summary'],"→");
if($position):
  $name = mb_substr($event['summary'], $position+1); //var_dump($name);
  if($name == "山崎"){
	$event['summary'] = str_replace("山崎", "山﨑", $event['summary']);
    $name = "山﨑";
  }
  if(strpos($event['attendees'],$name) === false or is_null(strpos($event['attendees'],$name)) == true){ 
	$arg =
	  "SELECT *
	  FROM apple_help_member
	  WHERE name LIKE '%".$name."%' ";
    $resuls = $wpdb->get_results($wpdb->prepare($arg));
	$event['attendees'] = array(
	  0 => [
        "email" => $resuls[0]->lw_mail,
        "displayName"=> $resuls[0]->name,
        "partstat"=> "NEEDS-ACTION",
        "isOptional"=> false,
        "isResource"=> false
      ]
	);

  //https://www.worksapis.com/v1.0/users/{userId}/calendars/{calendarId}/events/{eventId}
  $test_url ="https://www.worksapis.com/v1.0/users/";
  $test_url .="315cf7ba-af0e-4c9e-1697-044b7b444f4d";
  $test_url .="/calendars/";
  $test_url .="c_400009476_49dfc5ba-903e-44ac-9ffb-55bfdc4c2448";
  $test_url .="/events/";
  $test_url .=urlencode($event['eventId']);

  $header = [
    'Authorization: Bearer '.$access_token2,
    'Content-Type: application/json'
  ];

  $body = array(
    "eventComponents" => [
	  0 => [
	    "eventId" => $event['eventId'],
        "summary" => (string)$event['summary'],
        "start" => [
          "timeZone" => "Asia/Tokyo"
        ],
        "end" => [
          "timeZone" => "Asia/Tokyo"
        ],
	    "attendees" => $event['attendees'] 
      ]
    ]
  );
  if($event['startdate']):
    //終日
    $body["eventComponents"][0]["start"]['date'] = (string)$event['startdate'];
    $body["eventComponents"][0]["end"]['date'] = (string)$event['enddate'];
  else:
    //時間指定
    $body["eventComponents"][0]["start"]['dateTime'] = (string)$event['startdateTime'];
    $body["eventComponents"][0]["end"]['dateTime'] = (string)$event['enddateTime'];
  endif;
	  
  $body = json_encode($body,JSON_UNESCAPED_UNICODE);

  $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_POST, true); 	
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 
    $result = curl_exec($ch); error_log($result);
  curl_close($ch);
  }
endif;	
}

function update_check($event){
  global $wpdb;
  $eventId = $event['eventId'];
  $arg =
    "SELECT eventId,updatedTime
    FROM apple_help_event
    WHERE eventId = '".$eventId."'
    LIMIT 1 ";
  $resuls = $wpdb->get_results($wpdb->prepare($arg));
  $update_check = array();
  $update_check['eventId'] = $resuls[0]->eventId;
  $update_check['updatedTime'] = $resuls[0]->updatedTime;
  return $update_check;
}

function shop_cal($event){//店舗名を取り出し、週休を除外するフィルタ
  global $wpdb;
  $summary = $event['summary'];
  $summary = preg_replace('/➀|②|③/', '', $summary);
  $position = mb_strpos($summary,'全');
  //var_dump($position);
  if($position == false):
    $position = mb_strpos($summary,'半');
  endif;
  //var_dump($position);
  if($position == false):
	  return;
  endif;
  $initial = mb_substr($summary,0,$position);
  //var_dump($initial);

  //イニシャルでLIKE検索
  $initial = $initial . '%';
  $arg =
    "SELECT *
    FROM apple_help_calendar
    WHERE decision_calendar like '".$initial."'
    LIMIT 1";
  $resuls = $wpdb->get_results($wpdb->prepare($arg));
  //var_dump($resuls);
  $shop_cal = array();
  $shop_cal['shop_name'] = $resuls[0]->shop_name;
  $shop_cal['read_calendar'] = $resuls[0]->read_calendar;
  $shop_cal['written_cal'] = $resuls[0]->written_cal;
  $shop_cal['cal_userid'] = $resuls[0]->cal_userid;
  return $shop_cal;
}

function event_create_uri($event,$shop_cal,$access_token){ //https://www.worksapis.com/v1.0/users/{userId}/calendars/{calendarId}/events
$cal_url1 ="https://www.worksapis.com/v1.0/users/";
$cal_userid = $shop_cal['cal_userid']; //"81927f62-d8b5-4897-11bc-0409146d4c51"; //userid
$cal_url2 = "/calendars/";
$cal_id = $shop_cal['written_cal'];//カレンダーid
$cal_url3 = "/events";
$cal_url = $cal_url1 . $cal_userid . $cal_url2 . $cal_id .$cal_url3;
	
$header = [
'Authorization: Bearer '.$access_token,
'Content-Type: application/json'
  ];

$body =	array(
"eventComponents" => [
	0 => [
	  "eventId" => $event['eventId'],
      "summary" => (string)$event['summary'],
      "start" => [
        "timeZone" => "Asia/Tokyo"
      ],
      "end" => [
        "timeZone" => "Asia/Tokyo"
      ],
  ]
]);
if($event['startdate']):
  //終日
  $body["eventComponents"][0]["start"]['date'] = (string)$event['startdate'];
  $body["eventComponents"][0]["end"]['date'] = (string)$event['enddate'];
else:
  //時間指定
  $body["eventComponents"][0]["start"]['dateTime'] = (string)$event['startdateTime'];
  $body["eventComponents"][0]["end"]['dateTime'] = (string)$event['enddateTime'];
endif;

lw_curl_send($cal_url,$header,$body);
}

function lw_curl_send($cal_post_url,$header,$body){
  $body = json_encode($body,JSON_UNESCAPED_UNICODE);
	
  $ch = curl_init($cal_post_url); 
    curl_setopt($ch, CURLOPT_POST, true); 
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST'); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 
    $result = curl_exec($ch); error_log($result); 
    curl_close($ch);
}

function event_delete_uri($event,$shop_cal,$access_token){//https://www.worksapis.com/v1.0/users/{userId}/calendars/{calendarId}/events/{eventId}
$cal_url1 = "https://www.worksapis.com/v1.0/users/";
$cal_userid = $shop_cal['cal_userid'];
$cal_url2 = "/calendars/";
$cal_id = $shop_cal['written_cal'];
$cal_url3 = "/events/";
$event_id = urlencode($event['eventId']);
$cal_post_url = $cal_url1 . $cal_userid . $cal_url2 . $cal_id .$cal_url3 .$event_id;

$header = [
'Authorization: Bearer '.$access_token,
  ];

$ch = curl_init($cal_post_url); 
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 
$result = curl_exec($ch); error_log($result);
curl_close($ch);
}

function all_delete($org,$access_token){
  $event_get_uri = event_get_uri_create($org);
  $event_get_uri = parameter_combine($event_get_uri,"+0day","+30day");
  $result_event = curl_sending($event_get_uri ,$access_token); //ここでeventを配列で取得
	foreach($result_event['events'] as $events):
      $event = in_box($events);//イベントを連想配列にした
	var_dump($event);
      event_delete_uri($event,$org,$access_token);
	endforeach;
}

$user = wp_get_current_user();
$user_id = $user->id;

$access_token = get_access_token(); //有料版LINEアカウントのトークン
$access_token2 = get_access_token2(); //無料版LINEアカウントのトークン

//有料版から無料版への転記
$current_date = date('d');
if($current_date == 30){
  $orgs = get_org_cal();//各営業所のidを取得
  foreach($orgs as $org_one):
	$org = array();
      $org["id"] = $org_one->id;
      $org["read_calendar"] = $org_one->read_calendar;
      $org["cal_userid"] = $org_one->cal_userid;
	$event_get_uri = event_get_uri_create($org);
    $event_get_uri = parameter_combine($event_get_uri,"+30day","+60day");
    $result_event = curl_sending($event_get_uri ,$access_token); //ここでeventを配列で取得
	foreach($result_event['events'] as $events):
      $event = in_box($events);//イベントを連想配列にした
  	  $shop_cal = array(
		  'cal_userid' => '81927f62-d8b5-4897-11bc-0409146d4c51',
		  'written_cal' => 'c_400495549_17f01412-9bc7-4674-8d52-2c7965895200'
	  );//yoshidasannnisuru
	  $event_create_uri = event_create_uri($event,$shop_cal,$access_token);
	endforeach;
  endforeach;
}//無料版から有料版への転記ここまで


//新規カレンダーを作成する時はここ。
//$cal_new_id = '81927f62-d8b5-4897-11bc-0409146d4c51'; //ここに組織IDを入れる
//cal_new_create($access_token,$cal_new_id);


//$cal_url1 ="https://www.worksapis.com/v1.0/users";
$cal_url1 ="https://www.worksapis.com/v1.0/users/";
$cal_userid ="81927f62-d8b5-4897-11bc-0409146d4c51";
$cal_url2 ="/calendar";
$cal_url = $cal_url1 . $cal_userid . $cal_url2;
$cal_list = "https://www.worksapis.com/v1.0/users/315cf7ba-af0e-4c9e-1697-044b7b444f4d/calendar-personals";
//$cal_list = "https://www.worksapis.com/v1.0/users/81927f62-d8b5-4897-11bc-0409146d4c51/calendar-personals";
//$access_token = get_access_token();
//$result = curl_sending($cal_url1,$access_token2);
//$result = curl_sending($cal_url,$access_token);
$result = curl_sending($cal_list,$access_token2);

//var_dump($result);
$cal_username = $result['calendarName'];
$cal_id = $result['calendarId'];
//var_dump($cal_username);
//var_dump($cal_id);
//ここからスタート
//
//まず５つのカレンダーから予定を削除
  $orgs = get_org_cal();//各営業所のidを取得
  foreach($orgs as $org_one):
	$org = array();
      $org["id"] = $org_one->id;
      $org["read_calendar"] = $org_one->written_cal;//書き込みカレンダーを読み込ませたい
      $org["cal_userid"] = $org_one->cal_userid;
      $org["written_cal"] = $org_one->written_cal;
      all_delete($org,$access_token);
  endforeach;
//まず５つのカレンダーから予定を削除 ここまで
//
//
//"afe11ff7-83de-4540-2379-046be13019de" //支援課org_id
//ap.87011@applecarenetcoltd-2
//20241010T091148Z-43373@jvcweb013.wcal.nfra.io　target
//
$test_url = "https://www.worksapis.com/v1.0/orgunits";
$test_url = "https://www.worksapis.com/v1.0/users";
$header = [
'Authorization: Bearer '.$access_token2,
  ];

$ch = curl_init($test_url); 
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 
$result = curl_exec($ch); error_log($result);
curl_close($ch);
//	var_dump($result);

//本部カレンダーを
$org = array();
  $org["id"] = "315cf7ba-af0e-4c9e-1697-044b7b444f4d";//AC本部(管理用)のid
  $org["read_calendar"] = "c_400009476_49dfc5ba-903e-44ac-9ffb-55bfdc4c2448";//AC本部(管理用)のカレンダー
  $org["cal_userid"] = "315cf7ba-af0e-4c9e-1697-044b7b444f4d";//AC本部(管理用)のid
$event_get_uri = event_get_uri_create($org);
$event_get_uri = parameter_combine($event_get_uri,"-2day","+3day");

//$event_get_uri = parameter_combine($event_get_uri,"+0day","+30day");
$result_event = curl_sending($event_get_uri ,$access_token2); //ここでeventを配列で取得
foreach($result_event['events'] as $events):
  $event = in_box($events);//イベントを連想配列にした
	//var_dump($event);


global $wpdb;
//ここからattendedの漏れを修正
if($event['eventId'] == "20241010T091148Z-43373@jvcweb013.wcal.nfra.io"){
  //var_dump($event);
  attended_check($event,$access_token2);//無料LINEWORKS内のattendedの記入漏れを修正更新
}
  //ここからデータベースの処理
  $update_check = update_check($event);
//var_dump($update_check['eventId']);
  if($event['eventId'] == $update_check['eventId']):
    if($event['updatedTime'] == $update_check['updatedTime'])://更新の有無を確認
      //更新なし
      $shop_cal = shop_cal($event);
      $event_create_uri = event_create_uri($event,$shop_cal,$access_token);
    else:
      //更新あり
      //DB更新
      $res = $wpdb->update('apple_help_event',array('updatedTime' => $event['updatedTime'],'summary' => $event['summary'],'start' => $event['startdate'],'start_dateTime' => $event['startdateTime'],'end' => $event['enddate'],'end_dateTime' => $event['enddateTime'],'attendees' => $event['attendees']),array('eventId' => (string)$event['eventId']),array('%s','%s','%s','%s','%s','%s','%s'),array('%s'));
      $shop_cal = shop_cal($event);
//      $event_delete_uri = event_delete_uri($event,$shop_cal,$access_token);
      $event_create_uri = event_create_uri($event,$shop_cal,$access_token);
    endif;//更新処理ここまで
  else://idがデータベースにない
    //DBに追加
    $res = $wpdb->insert('apple_help_event', array('eventId' => (string)$event['eventId'], 'updatedTime' => $event['updatedTime'], 'summary' => $event['summary'], 'start' => $event['startdate'], 'start_dateTime' => $event['startdateTime'], 'end' => $event['enddate'], 'end_dateTime' => $event['enddateTime'], 'attendees' => $event['attendees']),array('%s','%s','%s','%s','%s','%s','%s','%s'));
//	var_dump($res);
	if($event['attendees']):
      //参加者あり
  	  $shop_cal = shop_cal($event);
	  $event_create_uri = event_create_uri($event,$shop_cal,$access_token);
	else:
	  //参加者なし
	endif;
  endif;
endforeach;

        
"{
"calendarName":"支援依頼カレンダー",
"description":"支援依頼用",
"members":[
{
"id":"155f248b-650f-4989-2476-04e8d343451b",
"type":"ORGUNIT",
"role":"EVENT_READ_WRITE "
}
],
"isPublic":true
}" 