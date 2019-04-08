<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$time = time();
$redis_sup_v3 = false;
$redis_server_v3 = false;
$sign = 1;
$check_load = "redis";
if( extension_loaded($check_load) ) 
{
	try 
	{
		$config = $_W["config"]["setting"]["redis"];
		$redis_server = new Redis();
		$res = $redis_server->connect($config["server"], $config["port"]);
		if( $res ) 
		{
			$redis_sup_v3 = true;
			$redis_server_v3 = $redis_server;
			$sign = 2;
		}
		else 
		{
			$redis_sup_v3 = false;
			$redis_server_v3 = false;
			$sign = 1;
		}
		if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
		{
			$pas_res = $redis_server->auth($config["requirepass"]);
			if( !$pas_res ) 
			{
				$redis_sup_v3 = false;
				$redis_server_v3 = false;
				$sign = 1;
			}
			else 
			{
				$redis_sup_v3 = true;
				$redis_server_v3 = $redis_server;
				$sign = 2;
			}
		}
	}
	catch( Exception $e ) 
	{
		$redis_sup_v3 = false;
		$redis_server_v3 = false;
		$sign = 1;
	}
}
else 
{
	$redis_sup_v3 = false;
	$redis_server_v3 = false;
	$sign = 1;
}
$overview = $this->createWebUrl("manage/overview");
$companyList = $this->createWebUrl("manage/company");
$companyEdit = $this->createWebUrl("manage/companyedit");
$dutiesList = $this->createWebUrl("manage/duties");
$usersList = $this->createWebUrl("manage/users");
$typeList = $this->createWebUrl("manage/type");
$goodsList = $this->createWebUrl("manage/goods");
$addGoods = $this->createWebUrl("manage/goodsEdit");
$orderList = $this->createWebUrl("manage/orders");
$timelineList = $this->createWebUrl("manage/timeline");
$timelineEdit = $this->createWebUrl("manage/timelineedit");
$commentList = $this->createWebUrl("manage/comment");
$modularList = $this->createWebUrl("manage/modular");
$message = $this->createWebUrl("manage/message");
$config = $this->createWebUrl("manage/config");
$userCollage = $this->createWebUrl("manage/userCollage");
$bossExplain = $this->createWebUrl("manage/bossExplain");
$staffExplain = $this->createWebUrl("manage/staffExplain");
$users = pdo_getall("longbing_card_user", array( "uniacid" => $uniacid, "is_staff" => 1 ));
$users_count = count($users);
$card_number = LONGBING_AUTH_CARD;
$checkExists = pdo_tableexists("longbing_cardauth2_config");
if( $checkExists ) 
{
	$auth_info = pdo_get("longbing_cardauth2_config", array( "modular_id" => $uniacid ));
	if( $auth_info ) 
	{
		$card_number = $auth_info["number"];
	}
	else 
	{
		$checkExists2 = pdo_tableexists("longbing_cardauth2_default");
		if( $checkExists2 ) 
		{
			$default_info = pdo_get("longbing_cardauth2_default");
			if( $default_info ) 
			{
				$card_number = $default_info["card_number"];
			}
		}
	}
}
if( $card_number == 0 ) 
{
	$card_number = "无限制";
}
$user = pdo_get("longbing_card_user", array( "uniacid" => $uniacid ));
$create_time = $user["create_time"];
$create_date = 0;
$users_days = 0;
if( $create_time ) 
{
	$users_days = ceil(($time - $create_time) / 86400);
	$create_date = date("Y年m月d日", $create_time);
}
$left_times = 0;
$left_days = 0;
$left_date = 0;
$account = pdo_get("account", array( "uniacid" => $uniacid ));
if( $account ) 
{
	$left_times = $account["endtime"];
}
$checkExists = pdo_tableexists("longbing_cardauth2_config");
if( $checkExists && $auth_info ) 
{
	$left_times = $auth_info["end_time"];
}
if( $left_times ) 
{
	$left_days = ceil(($left_times - $time) / 86400);
	if( $left_days < 0 ) 
	{
		$left_days = 0;
	}
	if( $left_days == 0 && $account["endtime"] == 0 && !$checkExists ) 
	{
		$left_days = "无限制";
	}
	$left_date = date("Y年m月d日", $left_times);
}
else 
{
	if( $left_days == 0 ) 
	{
		$left_days = "无限制";
	}
}
$clients = pdo_getall("longbing_card_user", array( "uniacid" => $uniacid, "is_staff" => 0 ));
$clients_count = count($clients);
$last = 30;
$data_new = array( );
for( $i = 0; $i < $last; $i++ ) 
{
	$beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
	$endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
	$sql = "SELECT id FROM " . tablename("longbing_card_user") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
	$info = pdo_fetchall($sql);
	$tmp = array( "date" => date("m-d", $beginTime), "time" => $beginTime, "number" => count($info) );
	array_push($data_new, $tmp);
}
array_multisort(array_column($data_new, "time"), SORT_ASC, $data_new);
$data_new = json_encode($data_new);
$data_user_power = bossAi($uniacid);
$data_user_power = json_encode($data_user_power);
$last = 30;
$data_message = array( );
$list = pdo_getall("longbing_card_user", array( "uniacid" => $uniacid, "is_staff" => 1 ), array( "id" ));
$ids = "";
if( !empty($list) ) 
{
	foreach( $list as $k => $v ) 
	{
		$ids .= "," . $v["id"];
	}
	$ids = trim($ids, ",");
}
if( 1 < count($list) ) 
{
	$ids = "(" . $ids . ")";
}
for( $i = 0; $i < $last; $i++ ) 
{
	$beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
	$endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
	if( empty($list) ) 
	{
		$sql = "SELECT user_id FROM " . tablename("longbing_card_message") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " GROUP BY user_id";
		$info = array( );
	}
	else 
	{
		if( 1 < count($list) ) 
		{
			$sql = "SELECT user_id FROM " . tablename("longbing_card_message") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && user_id NOT IN " . $ids . " GROUP BY user_id";
		}
		else 
		{
			$sql = "SELECT user_id FROM " . tablename("longbing_card_message") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && user_id != " . $ids . " GROUP BY user_id";
		}
		$info = pdo_fetchall($sql);
	}
	$tmp = array( "date" => date("m-d", $beginTime), "time" => $beginTime, "number" => count($info) );
	array_push($data_message, $tmp);
}
array_multisort(array_column($data_message, "time"), SORT_ASC, $data_message);
$data_message = json_encode($data_message);
$sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE sign = 'view' && type = 6 && uniacid = " . $uniacid;
$compony = pdo_fetchall($sql);
$compony = count($compony);
$sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && type = 2 && uniacid = " . $uniacid . ") OR (sign = 'copy' && type = 1 && uniacid = " . $uniacid . ")";
$goods = pdo_fetchall($sql);
$goods = count($goods);
$sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && uniacid = " . $uniacid . ") OR (sign != 'praise' && uniacid = " . $uniacid . ")";
$staff = pdo_fetchall($sql);
$staff = count($staff);
$total = $compony + $goods + $staff;
$data_int = array( "company" => array( "number" => $compony, "rate" => 0 ), "goods" => array( "number" => $goods, "rate" => 0 ), "staff" => array( "number" => $staff, "rate" => 0 ) );
if( $total ) 
{
	foreach( $data_int as $k => $v ) 
	{
		$data_int[$k]["rate"] = sprintf("%.2f", $v["number"] / $total) * 100;
	}
}
$data_int = json_encode($data_int);
load()->func("tpl");
include($this->template("manage/overview"));
function bossAi($uniacid) 
{
	$default = array( "client" => 0, "charm" => 0, "interaction" => 0, "product" => 0, "website" => 0, "active" => 0 );
	$max = array( "client" => 0, "charm" => 0, "interaction" => 0, "product" => 0, "website" => 0, "active" => 0 );
	$staff_list = pdo_getall("longbing_card_user", array( "uniacid" => $uniacid, "is_staff" => 1 ), array( "id", "nickName", "avatarUrl" ));
	foreach( $staff_list as $k => $v ) 
	{
		$info = pdo_get("longbing_card_user_info", array( "uniacid" => $uniacid, "fans_id" => $v["id"] ), array( "name", "avatar", "phone", "job_id" ));
		$job = pdo_get("longbing_card_job", array( "id" => $info["job_id"] ));
		$info["job_name"] = (!empty($job) ? $job["name"] : "");
		$total = 0;
		$value = bossGetAiValue($v["id"], $uniacid);
		foreach( $value as $k2 => $v2 ) 
		{
			if( $max[$k2] < $v2["value"] ) 
			{
				$max[$k2] = $v2["value"];
			}
			$total += $v2["value"];
		}
		$staff_list[$k]["value"] = $value;
		$staff_list[$k]["total"] = $total;
		$info["avatar"] = tomedia($info["avatar"]);
		$staff_list[$k]["info"] = $info;
	}
	array_multisort(array_column($staff_list, "total"), SORT_DESC, $staff_list);
	$com = pdo_get("longbing_card_company", array( "uniacid" => $uniacid ));
	$data = array( "list" => $staff_list, "max" => $max, "com" => $com );
	return $data;
}
function bossGetAiValue($id, $uniacid) 
{
	$value = array( "client" => 0, "charm" => 0, "interaction" => 0, "product" => 0, "website" => 0, "active" => 0 );
	$check = pdo_get("longbing_card_value", array( "staff_id" => $id ));
	if( empty($check) || !empty($check) && 24 * 60 * 60 < $check["update_time"] - time() ) 
	{
		$client = pdo_getall("longbing_card_collection", array( "status" => 1, "to_uid" => $id ));
		$client = count($client);
		$value["client"] = $client;
		$list1 = pdo_getall("longbing_card_count", array( "type" => "praise", "type" => 1, "to_uid" => $id ));
		$list2 = pdo_getall("longbing_card_count", array( "type" => "praise", "type" => 3, "to_uid" => $id ));
		$list3 = pdo_getall("longbing_card_count", array( "type" => "copy", "to_uid" => $id ));
		$count = count($list1) + count($list2) + count($list3);
		$value["charm"] = $count;
		$list1 = pdo_getall("longbing_card_message", array( "user_id" => $id ));
		$list2 = pdo_getall("longbing_card_message", array( "target_id" => $id ));
		$list3 = pdo_getall("longbing_card_count", array( "type" => "view", "to_uid" => $id ));
		$count = count($list1) + count($list2) + count($list3);
		$value["interaction"] = $count;
		$list1 = pdo_getall("longbing_card_extension", array( "user_id" => $id, "uniacid" => $uniacid ));
		$list2 = pdo_getall("longbing_card_user_mark", array( "staff_id" => $id, "uniacid" => $uniacid, "mark" => 2 ));
		$list3 = pdo_getall("longbing_card_forward", array( "staff_id" => $id, "uniacid" => $uniacid, "type" => 2 ));
		$list4 = pdo_getall("longbing_card_share_group", array( "user_id" => $id, "uniacid" => $uniacid, "view_goods !=" => "" ));
		$count = count($list1) + count($list2) + count($list3) + count($list4);
		$value["product"] = $count;
		$list1 = pdo_getall("longbing_card_count", array( "type" => "view", "type" => 6, "to_uid" => $id ));
		$list2 = pdo_getall("longbing_card_forward", array( "staff_id" => $id, "uniacid" => $uniacid, "type" => 4 ));
		$count = count($list1) + count($list2);
		$value["website"] = $count;
		$list1 = pdo_getall("longbing_card_message", array( "user_id" => $id ));
		$list2 = pdo_getall("longbing_card_message", array( "target_id" => $id ));
		$list3 = pdo_getall("longbing_card_user_follow", array( "staff_id" => $id ));
		$list4 = pdo_getall("longbing_card_user_mark", array( "staff_id" => $id ));
		$count = count($list1) + count($list2) + count($list3) + count($list4);
		$value["active"] = $count;
		$insertData = $value;
		$insertData["staff_id"] = $id;
		$time = time();
		$insertData["create_time"] = $time;
		$insertData["update_time"] = $time;
		$insertData["uniacid"] = $uniacid;
		if( empty($check) ) 
		{
			pdo_insert("longbing_card_value", $insertData);
		}
		else 
		{
			$updateData = $value;
			$insertData["update_time"] = $time;
			pdo_update("longbing_card_value", $insertData, array( "id" => $check["id"] ));
		}
	}
	else 
	{
		$value = array( "client" => $check["client"], "charm" => $check["charm"], "interaction" => $check["interaction"], "product" => $check["product"], "website" => $check["website"], "active" => $check["active"] );
	}
	$data = array( "client" => array( "titlle" => "获客能力值", "value" => $value["client"] ), "charm" => array( "titlle" => "个人魅力值", "value" => $value["charm"] ), "interaction" => array( "titlle" => "客户互动值", "value" => $value["interaction"] ), "product" => array( "titlle" => "产品推广值", "value" => $value["product"] ), "website" => array( "titlle" => "官网推广度", "value" => $value["website"] ), "active" => array( "titlle" => "销售主动性值", "value" => $value["active"] ) );
	return $data;
}
function ppp($data) 
{
	echo "<pre>";
	var_dump($data);
	echo "</pre>";
	exit();
}
?>