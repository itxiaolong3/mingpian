<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$type = $_GPC["type"];
$staff_id = $_GPC["staff_id"];
$uniacid = $_W["uniacid"];
if( $staff_id ) 
{
	$uid = $staff_id;
	$type = 1;
}
if( !$type ) 
{
	$type = 1;
}
$limit = array( 1, 15 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$ids = "";
if( $type == 1 ) 
{
	$info = pdo_getall("longbing_card_collection", array( "uniacid" => $_W["uniacid"], "to_uid" => $uid, "uid !=" => $uid ), array( ), "", array( "id desc" ));
	$count = count($info);
	foreach( $info as $k => $v ) 
	{
		$user = pdo_get("longbing_card_user", array( "id" => $v["uid"] ), array( "nickName", "avatarUrl" ));
		$info[$k]["user"] = $user;
		$check1 = pdo_get("longbing_card_chat", array( "user_id" => $v["uid"], "target_id" => $uid ));
		if( empty($check1) ) 
		{
			$check2 = pdo_get("longbing_card_chat", array( "user_id" => $uid, "target_id" => $v["uid"] ));
			if( $check2 ) 
			{
				$chat_id = 0;
			}
			else 
			{
				$chat_id = $check2["id"];
			}
		}
		else 
		{
			$chat_id = $check1["id"];
		}
		if( $chat_id ) 
		{
			$message = pdo_getall("longbing_card_message", array( "chat_id" => $chat_id ), array( "create_time" ), "", array( "id desc" ));
			$info[$k]["count"] = count($message);
			$info[$k]["last_time"] = $message[0]["create_time"];
		}
		else 
		{
			$info[$k]["count"] = 0;
			$info[$k]["last_time"] = 0;
		}
		$info[$k]["last_time"] = intval($info[$k]["last_time"]);
	}
	array_multisort(array_column($info, "last_time"), SORT_DESC, $info);
	$offset = ($curr - 1) * 15;
	$info = array_slice($info, $offset, 15);
	if( !empty($info) ) 
	{
		foreach( $info as $k => $v ) 
		{
			$ids .= "," . $v["uid"];
		}
		$ids = trim($ids, ",");
	}
}
else 
{
	if( $type == 2 ) 
	{
		$list = pdo_getslice("longbing_card_user_mark", array( "staff_id" => $uid, "mark" => 1 ), $limit, $count, array( ), "", array( "create_time desc" ));
		if( !empty($list) ) 
		{
			foreach( $list as $k => $v ) 
			{
				$ids .= "," . $v["user_id"];
			}
			$ids = trim($ids, ",");
		}
	}
	else 
	{
		if( $type == 3 ) 
		{
			$list = pdo_getslice("longbing_card_user_mark", array( "staff_id" => $uid, "mark" => 2 ), $limit, $count, array( ), "", array( "create_time desc" ));
			if( !empty($list) ) 
			{
				foreach( $list as $k => $v ) 
				{
					$ids .= "," . $v["user_id"];
				}
				$ids = trim($ids, ",");
			}
		}
		else 
		{
			return $this->result(-1, "", array( ));
		}
	}
}
if( !$ids && $type != 1 ) 
{
	return $this->result(0, "", array( ));
}
if( strpos($ids, ",") ) 
{
	$sql = "SELECT id,nickName,avatarUrl FROM " . tablename("longbing_card_user") . " where `id` in (" . $ids . ")";
}
else 
{
	$sql = "SELECT id,nickName,avatarUrl FROM " . tablename("longbing_card_user") . " where `id` = " . $ids;
}
$users = pdo_fetchall($sql);
foreach( $users as $k => $v ) 
{
	$praise = pdo_getall("longbing_card_count", array( "user_id" => $v["id"], "to_uid" => $uid, "sign" => "praise" ), array( "id", "create_time" ), "", array( "create_time desc" ));
	$message1 = pdo_getall("longbing_card_message", array( "user_id" => $v["id"], "target_id" => $uid ), array( "id", "create_time" ), "", array( "create_time desc" ));
	$message2 = pdo_getall("longbing_card_message", array( "user_id" => $uid, "target_id" => $v["id"] ), array( "id", "create_time" ), "", array( "create_time desc" ));
	$view = pdo_getall("longbing_card_count", array( "user_id" => $uid, "to_uid" => $v["id"], "sign" => "view" ), array( "id", "create_time" ), "", array( "create_time desc" ));
	$copy = pdo_getall("longbing_card_count", array( "user_id" => $uid, "to_uid" => $v["id"], "sign" => "copy" ), array( "id", "create_time" ), "", array( "create_time desc" ));
	$users[$k]["count"] = count($praise) + count($message1) + count($message2) + count($view) + count($copy);
	$times = array( );
	$times[] = $praise[0]["create_time"];
	$times[] = $message1[0]["create_time"];
	$times[] = $message2[0]["create_time"];
	$times[] = $view[0]["create_time"];
	$times[] = $copy[0]["create_time"];
	rsort($times);
	$users[$k]["last_time"] = ($times[0] ? $times[0] : 0);
	$phone = pdo_get("longbing_card_user_phone", array( "user_id" => $v["id"] ));
	$client_info = pdo_get("longbing_card_client_info", array( "user_id" => $v["id"] ));
	$client_phone = "";
	if( !empty($client_info) && $client_info["phone"] ) 
	{
		$client_phone = $client_info["phone"];
	}
	$users[$k]["phone"] = (!empty($phone) ? $phone["phone"] : $client_phone);
}
if( $staff_id ) 
{
	foreach( $users as $k => $v ) 
	{
		$client_info = pdo_get("longbing_card_client_info", array( "user_id" => $v["id"], "uniacid" => $uniacid ));
		$users[$k]["name"] = (!$client_info ? "" : $client_info["name"]);
		$rate = pdo_getall("longbing_card_rate", array( "user_id" => $v["id"], "uniacid" => $uniacid ), array( ), "", array( "rate desc" ));
		$users[$k]["rate"] = (!$rate ? 0 : $rate[0]["rate"]);
		$date = pdo_getall("longbing_card_date", array( "user_id" => $v["id"], "uniacid" => $uniacid ), array( ), "", array( "date desc" ));
		$users[$k]["date"] = (!$date ? 0 : $date[0]["date"]);
		$mark = @pdo_getall("longbing_card_user_mark", array( "user_id" => $v["id"] ), array( ), "", array( "status desc", "mark desc" ));
		$users[$k]["mark"] = (!$mark ? 0 : $mark[0]["mark"]);
		$users[$k]["order"] = 0;
		$users[$k]["money"] = 0;
	}
}
if( $type != 1 ) 
{
	$count = count($users);
}
$data = array( "page" => $curr, "total_page" => ceil($count / 15), "list" => $users, "total_count" => $count );
return $this->result(0, "", $data);
?>