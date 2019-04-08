<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"], "is_staff" => 0 );
$curr = 1;
$keyword = "";
if( isset($_GPC["search"]) && $_GPC["search"] ) 
{
	$keyword = $_GPC["search"];
	$where["nickName like"] = "%" . $_GPC["keyword"] . "%";
}
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$mark_arr = array( array( "id" => 3, "value" => "未跟进" ), array( "id" => 1, "value" => "跟进中" ), array( "id" => 2, "value" => "已成交" ) );
$deal_arr = array( array( "id" => 1, "value" => "未成交" ), array( "id" => 2, "value" => "已成交" ) );
$mark_value = "";
$deal_value = "";
if( $_GPC["form"] && ($_GPC["mark"] || $_GPC["deal"] || $_GPC["search"]) ) 
{
	$user_ids = array( );
	if( $_GPC["mark"] ) 
	{
		$mark_value = $_GPC["mark"];
		if( $_GPC["mark"] == 3 ) 
		{
			$list_mark = pdo_getall("longbing_card_user_mark", array( "uniacid" => $uniacid ));
			$tmp_arr = array( );
			foreach( $list_mark as $k => $v ) 
			{
				array_push($tmp_arr, $v["user_id"]);
			}
			if( 1 < count($tmp_arr) ) 
			{
				$tmp_arr = "(" . implode(",", $tmp_arr) . ")";
				$list_mark = pdo_fetchall("select id FROM " . tablename("longbing_card_user") . " WHERE uniacid = " . $uniacid . " && id NOT IN " . $tmp_arr);
			}
			else 
			{
				if( count($tmp_arr) == 1 ) 
				{
					$tmp_arr = implode(",", $tmp_arr);
					$list_mark = pdo_fetchall("select id FROM " . tablename("longbing_card_user") . " WHERE uniacid = " . $uniacid . " && id != " . $tmp_arr);
				}
				else 
				{
					$list_mark = array( );
				}
			}
			foreach( $list_mark as $k => $v ) 
			{
				array_push($user_ids, $v["id"]);
			}
		}
		else 
		{
			$list_mark = pdo_getall("longbing_card_user_mark", array( "mark" => $_GPC["mark"], "uniacid" => $uniacid ));
			foreach( $list_mark as $k => $v ) 
			{
				array_push($user_ids, $v["user_id"]);
			}
		}
	}
	if( $_GPC["deal"] ) 
	{
		$deal_value = $_GPC["deal"];
		if( $_GPC["deal"] == 1 ) 
		{
			$list_mark = pdo_getall("longbing_card_user_mark", array( "mark" => 2, "uniacid" => $uniacid ));
			$tmp_arr = array( );
			foreach( $list_mark as $k => $v ) 
			{
				array_push($tmp_arr, $v["user_id"]);
			}
			if( 1 < count($tmp_arr) ) 
			{
				$tmp_arr = "(" . implode(",", $tmp_arr) . ")";
				$list_mark = pdo_fetchall("select id FROM " . tablename("longbing_card_user") . " WHERE uniacid = " . $uniacid . " && id NOT IN " . $tmp_arr);
			}
			else 
			{
				if( count($tmp_arr) == 1 ) 
				{
					$tmp_arr = implode(",", $tmp_arr);
					$list_mark = pdo_fetchall("select id FROM " . tablename("longbing_card_user") . " WHERE uniacid = " . $uniacid . " && id != " . $tmp_arr);
				}
				else 
				{
					$list_mark = array( );
				}
			}
			if( $_GPC["mark"] ) 
			{
				$tmp1 = array( );
				$tmp2 = $user_ids;
				foreach( $list_mark as $k => $v ) 
				{
					if( in_array($v["id"], $tmp2) ) 
					{
						array_push($tmp1, $v["id"]);
					}
				}
				$user_ids = $tmp1;
			}
			else 
			{
				foreach( $list_mark as $k => $v ) 
				{
					array_push($user_ids, $v["id"]);
				}
			}
		}
		else 
		{
			$list_mark = pdo_getall("longbing_card_user_mark", array( "mark" => 2, "uniacid" => $uniacid ));
			if( $_GPC["mark"] ) 
			{
				$tmp1 = array( );
				$tmp2 = $user_ids;
				foreach( $list_mark as $k => $v ) 
				{
					if( in_array($v["user_id"], $tmp2) ) 
					{
						array_push($tmp1, $v["user_id"]);
					}
				}
				$user_ids = $tmp1;
			}
			else 
			{
				foreach( $list_mark as $k => $v ) 
				{
					array_push($user_ids, $v["user_id"]);
				}
			}
		}
	}
	if( $_GPC["search"] ) 
	{
		$keyword = $_GPC["search"];
		$search = "%" . $_GPC["search"] . "%";
		$users1 = pdo_getall("longbing_card_client_info", array( "uniacid" => $uniacid, "name like" => $search ));
		$users2 = pdo_getall("longbing_card_user", array( "uniacid" => $uniacid, "nickName like" => $search ));
		$tmp = array( );
		foreach( $users1 as $k => $v ) 
		{
			if( !in_array($v["user_id"], $tmp) ) 
			{
				array_push($tmp, $v["user_id"]);
			}
		}
		foreach( $users2 as $k => $v ) 
		{
			if( !in_array($v["id"], $tmp) ) 
			{
				array_push($tmp, $v["id"]);
			}
		}
		$tmp = array_unique($tmp);
		if( $_GPC["mark"] || $_GPC["deal"] ) 
		{
			$tmp1 = array( );
			$tmp2 = $user_ids;
			foreach( $tmp as $k => $v ) 
			{
				if( in_array($v, $tmp2) ) 
				{
					array_push($tmp1, $v);
				}
			}
			$user_ids = $tmp1;
		}
		else 
		{
			foreach( $tmp as $k => $v ) 
			{
				array_push($user_ids, $v);
			}
		}
	}
	$where["id in"] = $user_ids;
	$users = pdo_getslice("longbing_card_user", $where, $limit, $count, array( ), "");
}
else 
{
	if( $_GPC["laypage"] && ($_GPC["mark"] || $_GPC["deal"] || $_GPC["search"]) ) 
	{
		$user_ids = array( );
		if( $_GPC["mark"] ) 
		{
			$mark_value = $_GPC["mark"];
			if( $_GPC["mark"] == 3 ) 
			{
				$list_mark = pdo_getall("longbing_card_user_mark", array( "uniacid" => $uniacid ));
				$tmp_arr = array( );
				foreach( $list_mark as $k => $v ) 
				{
					array_push($tmp_arr, $v["user_id"]);
				}
				if( 1 < count($tmp_arr) ) 
				{
					$tmp_arr = "(" . implode(",", $tmp_arr) . ")";
					$list_mark = pdo_fetchall("select id FROM " . tablename("longbing_card_user") . " WHERE uniacid = " . $uniacid . " && id NOT IN " . $tmp_arr);
				}
				else 
				{
					if( count($tmp_arr) == 1 ) 
					{
						$tmp_arr = implode(",", $tmp_arr);
						$list_mark = pdo_fetchall("select id FROM " . tablename("longbing_card_user") . " WHERE uniacid = " . $uniacid . " && id != " . $tmp_arr);
					}
					else 
					{
						$list_mark = array( );
					}
				}
				foreach( $list_mark as $k => $v ) 
				{
					array_push($user_ids, $v["id"]);
				}
			}
			else 
			{
				$list_mark = pdo_getall("longbing_card_user_mark", array( "mark" => $_GPC["mark"], "uniacid" => $uniacid ));
				foreach( $list_mark as $k => $v ) 
				{
					array_push($user_ids, $v["user_id"]);
				}
			}
		}
		if( $_GPC["deal"] ) 
		{
			$deal_value = $_GPC["deal"];
			if( $_GPC["deal"] == 1 ) 
			{
				$list_mark = pdo_getall("longbing_card_user_mark", array( "mark" => 2, "uniacid" => $uniacid ));
				$tmp_arr = array( );
				foreach( $list_mark as $k => $v ) 
				{
					array_push($tmp_arr, $v["user_id"]);
				}
				if( 1 < count($tmp_arr) ) 
				{
					$tmp_arr = "(" . implode(",", $tmp_arr) . ")";
					$list_mark = pdo_fetchall("select id FROM " . tablename("longbing_card_user") . " WHERE uniacid = " . $uniacid . " && id NOT IN " . $tmp_arr);
				}
				else 
				{
					if( count($tmp_arr) == 1 ) 
					{
						$tmp_arr = implode(",", $tmp_arr);
						$list_mark = pdo_fetchall("select id FROM " . tablename("longbing_card_user") . " WHERE uniacid = " . $uniacid . " && id != " . $tmp_arr);
					}
					else 
					{
						$list_mark = array( );
					}
				}
				if( $_GPC["mark"] ) 
				{
					$tmp1 = array( );
					$tmp2 = $user_ids;
					foreach( $list_mark as $k => $v ) 
					{
						if( in_array($v["id"], $tmp2) ) 
						{
							array_push($tmp1, $v["id"]);
						}
					}
					$user_ids = $tmp1;
				}
				else 
				{
					foreach( $list_mark as $k => $v ) 
					{
						array_push($user_ids, $v["id"]);
					}
				}
			}
			else 
			{
				$list_mark = pdo_getall("longbing_card_user_mark", array( "mark" => 2, "uniacid" => $uniacid ));
				if( $_GPC["mark"] ) 
				{
					$tmp1 = array( );
					$tmp2 = $user_ids;
					foreach( $list_mark as $k => $v ) 
					{
						if( in_array($v["user_id"], $tmp2) ) 
						{
							array_push($tmp1, $v["user_id"]);
						}
					}
					$user_ids = $tmp1;
				}
				else 
				{
					foreach( $list_mark as $k => $v ) 
					{
						array_push($user_ids, $v["user_id"]);
					}
				}
			}
		}
		if( $_GPC["search"] ) 
		{
			$keyword = $_GPC["search"];
			$search = "%" . $_GPC["search"] . "%";
			$users1 = pdo_getall("longbing_card_client_info", array( "uniacid" => $uniacid, "name like" => $search ));
			$users2 = pdo_getall("longbing_card_user", array( "uniacid" => $uniacid, "nickName like" => $search ));
			$tmp = array( );
			foreach( $users1 as $k => $v ) 
			{
				if( !in_array($v["user_id"], $tmp) ) 
				{
					array_push($tmp, $v["user_id"]);
				}
			}
			foreach( $users2 as $k => $v ) 
			{
				if( !in_array($v["id"], $tmp) ) 
				{
					array_push($tmp, $v["id"]);
				}
			}
			$tmp = array_unique($tmp);
			if( $_GPC["mark"] || $_GPC["deal"] ) 
			{
				$tmp1 = array( );
				$tmp2 = $user_ids;
				foreach( $tmp as $k => $v ) 
				{
					if( in_array($v, $tmp2) ) 
					{
						array_push($tmp1, $v);
					}
				}
				$user_ids = $tmp1;
			}
			else 
			{
				foreach( $tmp as $k => $v ) 
				{
					array_push($user_ids, $v);
				}
			}
		}
		$where["id in"] = $user_ids;
		$users = pdo_getslice("longbing_card_user", $where, $limit, $count, array( ), "");
	}
	else 
	{
		$users = pdo_getslice("longbing_card_user", $where, $limit, $count, array( ), "", array( "id desc" ));
	}
}
foreach( $users as $k => $v ) 
{
	$phone = pdo_get("longbing_card_user_phone", array( "user_id" => $v["id"] ));
	$users[$k]["phone"] = $phone["phone"];
	if( $v["is_staff"] == 1 ) 
	{
		$info = pdo_get("longbing_card_user_info", array( "fans_id" => $v["id"] ));
		$users[$k]["is_default"] = ($info ? $info["is_default"] : 0);
	}
	$users[$k]["user_name"] = "";
	$client_info = pdo_get("longbing_card_client_info", array( "user_id" => $v["id"] ));
	if( $client_info ) 
	{
		$users[$k]["user_name"] = $client_info["name"];
	}
	$users[$k]["deal_time"] = "";
	$mark = pdo_getall("longbing_card_user_mark", array( "user_id" => $v["id"] ), array( ), "", "mark desc");
	if( !$mark ) 
	{
		$users[$k]["mark"] = 0;
		$users[$k]["mark_staff"] = "";
	}
	else 
	{
		if( $mark[0]["mark"] == 2 ) 
		{
			$users[$k]["deal_time"] = date("Y-m-d H:i:s", $mark[0]["create_time"]);
		}
		$staffIdArr = array( );
		$users[$k]["mark"] = $mark[0]["mark"];
		$users[$k]["mark_staff"] = "";
		foreach( $mark as $k2 => $v2 ) 
		{
			array_push($staffIdArr, $v2["staff_id"]);
		}
		$staffIdArr = array_unique($staffIdArr);
		$staff_info = pdo_getall("longbing_card_user_info", array( "fans_id in" => $staffIdArr ));
		foreach( $staff_info as $k2 => $v2 ) 
		{
			$users[$k]["mark_staff"] .= "," . $v2["name"];
		}
		$users[$k]["mark_staff"] = trim($users[$k]["mark_staff"], ",");
	}
	$users[$k]["rate"] = rate($v["id"], $uniacid);
}
$perPage = 15;
load()->func("tpl");
include($this->template("manage/client"));
function rate($client_id, $uniacid) 
{
	$check = pdo_getall("longbing_card_rate", array( "user_id" => $client_id ), array( ), "", array( "rate desc" ));
	$time = time();
	$rate = 0;
	$beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
	if( !empty($check) ) 
	{
		if( 86400 < $check[0]["create_time"] - $beginTime ) 
		{
			$rate = countRate($client_id, $uniacid);
		}
		else 
		{
			$rate = $check[0]["rate"];
		}
	}
	else 
	{
		$rate = countRate($client_id, $uniacid);
	}
	return $rate;
}
function countRate($client_id, $uniacid) 
{
	$coo = pdo_get("longbing_card_collection", array( "uid" => $client_id ));
	if( !$coo ) 
	{
		return 0;
	}
	$uid = $coo["to_uid"];
	$check = pdo_get("longbing_card_rate", array( "user_id" => $client_id, "staff_id" => $uid ));
	$is_deal = pdo_getall("longbing_card_user_mark", array( "user_id" => $client_id ), array( ), "", array( "mark desc" ));
	if( !empty($is_deal) && $is_deal[0]["mark"] == 2 ) 
	{
		if( $check ) 
		{
			pdo_update("longbing_card_rate", array( "rate" => 100, "update_time" => time() ), array( "id" => $check["id"] ));
		}
		else 
		{
			pdo_insert("longbing_card_rate", array( "user_id" => $client_id, "staff_id" => $uid, "rate" => 100, "create_time" => time(), "update_time" => time(), "uniacid" => $uniacid ));
		}
		return 100;
	}
	$staff_count = 0;
	$client_count = 0;
	if( !empty($is_deal) ) 
	{
		$staff_count += 5;
	}
	$chat = pdo_fetch("SELECT id,user_id,target_id,create_time FROM " . tablename("longbing_card_chat") . " where (user_id = " . $uid . " && target_id = " . $client_id . ") OR (user_id = " . $client_id . " && target_id = " . $uid . ")");
	if( !empty($chat) ) 
	{
		$mesage = pdo_getall("longbing_card_message", array( "chat_id" => $chat["id"] ));
		$count = count($mesage);
		if( $count ) 
		{
			$client_count += 4;
		}
		if( 15 < $count ) 
		{
			$count = 15;
		}
		$staff_count += $count;
	}
	$label = pdo_getall("longbing_card_user_label", array( "user_id" => $client_id, "staff_id" => $uid ));
	$count = count($label);
	if( 10 < $count ) 
	{
		$count = 10;
	}
	$staff_count += $count * 2;
	$info = pdo_get("longbing_card_user_phone", array( "user_id" => $client_id, "to_uid" => $uid ));
	if( !empty($info) ) 
	{
		$client_count += 6;
	}
	$info = pdo_get("longbing_card_count", array( "user_id" => $client_id, "to_uid" => $uid, "sign" => "copy", "type" => 2 ));
	if( !empty($info) ) 
	{
		$client_count += 4;
	}
	$info = pdo_get("longbing_card_count", array( "user_id" => $client_id, "to_uid" => $uid, "sign" => "copy", "type" => 1 ));
	if( !empty($info) ) 
	{
		$client_count += 4;
	}
	$info = pdo_get("longbing_card_count", array( "user_id" => $client_id, "to_uid" => $uid, "sign" => "copy", "type" => 4 ));
	if( !empty($info) ) 
	{
		$client_count += 4;
	}
	$info = pdo_get("longbing_card_count", array( "user_id" => $client_id, "to_uid" => $uid, "sign" => "praise", "type" => 1 ));
	if( !empty($info) ) 
	{
		$client_count += 1;
	}
	$info = pdo_get("longbing_card_count", array( "user_id" => $client_id, "to_uid" => $uid, "sign" => "praise", "type" => 3 ));
	if( !empty($info) ) 
	{
		$client_count += 1;
	}
	$client_count += 2;
	$info = pdo_get("longbing_card_count", array( "user_id" => $client_id, "to_uid" => $uid, "sign" => "view", "type" => 1 ));
	if( !empty($info) ) 
	{
		$client_count += 2;
	}
	$info = pdo_get("longbing_card_count", array( "user_id" => $client_id, "to_uid" => $uid, "sign" => "view", "type" => 2 ));
	if( !empty($info) ) 
	{
		$client_count += 2;
	}
	$info = pdo_get("longbing_card_count", array( "user_id" => $client_id, "to_uid" => $uid, "sign" => "view", "type" => 3 ));
	if( !empty($info) ) 
	{
		$client_count += 2;
	}
	$info = pdo_get("longbing_card_count", array( "user_id" => $client_id, "to_uid" => $uid, "sign" => "view", "type" => 6 ));
	if( !empty($info) ) 
	{
		$client_count += 2;
	}
	$info = pdo_get("longbing_card_user", array( "id" => $client_id ));
	if( !empty($info) && $info["avatarUrl"] ) 
	{
		$client_count += 2;
	}
	$info = pdo_get("longbing_card_forward", array( "user_id" => $client_id, "staff_id" => $uid, "type" => 1 ));
	if( !empty($info) ) 
	{
		$client_count += 4;
	}
	$count = $staff_count + $client_count;
	if( 92 < $count ) 
	{
		$count = 92;
	}
	if( $check ) 
	{
		pdo_update("longbing_card_rate", array( "rate" => $count, "update_time" => time() ), array( "id" => $check["id"] ));
	}
	else 
	{
		pdo_insert("longbing_card_rate", array( "user_id" => $client_id, "staff_id" => $uid, "rate" => $count, "create_time" => time(), "update_time" => time(), "uniacid" => $uniacid ));
	}
	return $count;
}
?>