<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$type = $_GPC["type"];
$uniacid = $_W["uniacid"];
if( !$type ) 
{
	$type = 0;
}
$beginTime = 0;
$staff_id = $_GPC["staff_id"];
if( !$staff_id ) 
{
	return $this->result(-1, "", array( ));
}
$check_is_boss = $this->check_is_boss($_GPC["user_id"]);
if( !$check_is_boss ) 
{
	return $this->result(-1, "", array( ));
}
switch( $type ) 
{
	case 1: $beginTime = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"));
	break;
	case 2: $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
	break;
	case 3: $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
	break;
	default: $beginTime = 0;
}
if( $beginTime == 0 ) 
{
	$new_client = pdo_getall("longbing_card_collection", array( "to_uid" => $staff_id ), array( "id" ));
	$new_client = count($new_client);
	if( 0 < $new_client ) 
	{
		$new_client -= 1;
	}
	$view_client = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE uniacid = " . $uniacid . " && sign = 'praise' && `type` = 2 && to_uid = " . $staff_id . " GROUP BY user_id";
	$view_client = pdo_fetchall($view_client);
	$view_client = $view_client[0]["count"];
	$mark_client = pdo_getall("longbing_card_user_mark", array( "uniacid" => $uniacid, "staff_id" => $staff_id ), array( "id" ));
	$mark_client = count($mark_client);
	$chat_list = "SELECT chat_id, user_id, target_id FROM " . tablename("longbing_card_message") . " WHERE uniacid = " . $uniacid . " && target_id = " . $staff_id . " GROUP BY chat_id";
	$chat_list = pdo_fetchall($chat_list);
	if( !empty($chat_list) ) 
	{
		$tmp = array( );
		foreach( $chat_list as $k => $v ) 
		{
			array_push($tmp, $v["user_id"], $v["target_id"]);
		}
		$tmp = array_unique($tmp, SORT_NUMERIC);
		$tmp = implode(",", $tmp);
		if( strpos($tmp, ",") ) 
		{
			$tmp = "(" . $tmp . ")";
			$chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,id FROM " . tablename("longbing_card_user") . " WHERE id IN " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
		}
		else 
		{
			$chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,id,create_time FROM " . tablename("longbing_card_user") . " WHERE id = " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
		}
		$chat_list = $chat_list[0]["count"];
	}
	else 
	{
		$chat_list = 0;
	}
	$sale_money = 0;
	$sale_order = 0;
	$orderList = pdo_getall("longbing_card_shop_order", array( "uniacid" => $uniacid, "pay_status" => 1, "order_status !=" => 1, "to_uid" => $staff_id ));
	foreach( $orderList as $index => $item ) 
	{
		$sale_money += $item["total_price"];
	}
	$sale_money = sprintf("%.2f", $sale_money);
	$sale_order = count($orderList);
	$share_count = pdo_getall("longbing_card_forward", array( "uniacid" => $uniacid, "type" => 1, "staff_id" => $staff_id ), array( "id" ));
	$share_count = count($share_count);
	$save_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 2 && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 1 && to_uid = " . $staff_id . ") GROUP BY user_id";
	$save_count = pdo_fetchall($save_count);
	$save_count = $save_count[0]["count"];
	$thumbs_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 1 && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 3 && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'view' && `type` = 4 && to_uid = " . $staff_id . ") GROUP BY user_id";
	$thumbs_count = pdo_fetchall($thumbs_count);
	$thumbs_count = $thumbs_count[0]["count"];
}
else 
{
	$new_client = pdo_getall("longbing_card_collection", array( "to_uid" => $staff_id, "create_time >" => $beginTime ), array( "id" ));
	$new_client = count($new_client);
	if( 0 < $new_client ) 
	{
		$new_client -= 1;
	}
	$view_client = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE uniacid = " . $uniacid . " && sign = 'praise' && `type` = 2 && create_time > " . $beginTime . " && to_uid = " . $staff_id . " GROUP BY user_id";
	$view_client = pdo_fetchall($view_client);
	$view_client = $view_client[0]["count"];
	$mark_client = pdo_getall("longbing_card_user_mark", array( "uniacid" => $uniacid, "create_time >" => $beginTime, "staff_id" => $staff_id ), array( "id" ));
	$mark_client = count($mark_client);
	$chat_list = "SELECT chat_id, user_id, target_id FROM " . tablename("longbing_card_message") . " WHERE uniacid = " . $uniacid . " && create_time > " . $beginTime . " GROUP BY chat_id";
	$chat_list = pdo_fetchall($chat_list);
	if( !empty($chat_list) ) 
	{
		$tmp = array( );
		foreach( $chat_list as $k => $v ) 
		{
			array_push($tmp, $v["user_id"], $v["target_id"]);
		}
		$tmp = array_unique($tmp, SORT_NUMERIC);
		$tmp = implode(",", $tmp);
		if( strpos($tmp, ",") ) 
		{
			$tmp = "(" . $tmp . ")";
			$chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,nickName FROM " . tablename("longbing_card_user") . " WHERE id IN " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
		}
		else 
		{
			$chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,avatarUrl FROM " . tablename("longbing_card_user") . " WHERE id = " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
		}
		$chat_client = $chat_list[0]["count"];
	}
	else 
	{
		$chat_client = 0;
	}
	$sale_money = 0;
	$sale_order = 0;
	$share_count = pdo_getall("longbing_card_forward", array( "uniacid" => $uniacid, "type" => 1, "create_time >" => $beginTime, "staff_id" => $staff_id ), array( "id" ));
	$share_count = count($share_count);
	$save_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 2 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 1 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") GROUP BY user_id";
	$save_count = pdo_fetchall($save_count);
	$save_count = $save_count[0]["count"];
	$thumbs_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 1 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 3 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'view' && `type` = 4 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") GROUP BY user_id";
	$thumbs_count = pdo_fetchall($thumbs_count);
	$thumbs_count = $thumbs_count[0]["count"];
}
$tmp = array( "new_client" => $new_client, "view_client" => $view_client, "mark_client" => $mark_client, "chat_client" => $chat_client, "sale_money" => $sale_money, "sale_order" => $sale_order, "share_count" => $share_count, "save_count" => $save_count, "thumbs_count" => $thumbs_count );
$data["nine"] = $tmp;
$client = pdo_getall("longbing_card_collection", array( "to_uid" => $staff_id ), array( "id" ));
$client = count($client);
if( 0 < $client ) 
{
	$client -= 1;
}
$mark_client = pdo_getall("longbing_card_user_mark", array( "uniacid" => $uniacid, "staff_id" => $staff_id ), array( "id" ));
$mark_client = count($mark_client);
$deal_client = pdo_getall("longbing_card_user_mark", array( "uniacid" => $uniacid, "mark" => 2, "staff_id" => $staff_id ), array( "id" ));
$deal_client = count($deal_client);
$data["dealRate"] = array( "client" => $client, "mark_client" => $mark_client, "deal_client" => $deal_client );
$sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE sign = 'view' && type = 6 && uniacid = " . $uniacid . " && to_uid = " . $staff_id;
$compony = pdo_fetchall($sql);
$compony = count($compony);
$sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && type = 2 && uniacid = " . $uniacid . " && to_uid = " . $staff_id . ") OR (sign = 'copy' && type = 1 && uniacid = " . $uniacid . " && to_uid = " . $staff_id . ")";
$goods = pdo_fetchall($sql);
$goods = count($goods);
$sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && uniacid = " . $uniacid . " && to_uid = " . $staff_id . ") OR (sign != 'praise' && uniacid = " . $uniacid . " && to_uid = " . $staff_id . ")";
$staff = pdo_fetchall($sql);
$staff = count($staff);
$total = $compony + $goods + $staff;
$data2 = array( "compony" => array( "number" => $compony, "rate" => 0 ), "goods" => array( "number" => $goods, "rate" => 0 ), "staff" => array( "number" => $staff, "rate" => 0 ) );
if( $total ) 
{
	foreach( $data2 as $k => $v ) 
	{
		$data2[$k]["rate"] = sprintf("%.2f", $v["number"] / $total) * 100;
	}
}
$data["interest"] = $data2;
$last = 15;
$data2 = array( );
for( $i = 0; $i < $last; $i++ ) 
{
	$beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
	$endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
	$sql = "SELECT id FROM " . tablename("longbing_card_count") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && to_uid = " . $staff_id;
	$count = pdo_fetchall($sql);
	$count = count($count);
	$sql = "SELECT id FROM " . tablename("longbing_card_forward") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && staff_id = " . $staff_id;
	$forward = pdo_fetchall($sql);
	$forward = count($forward);
	$sql = "SELECT id FROM " . tablename("longbing_card_user_phone") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && to_uid = " . $staff_id;
	$phone = pdo_fetchall($sql);
	$phone = count($phone);
	$tmp = array( "date" => date("m/d", $beginTime), "time" => $beginTime, "number" => $count + $forward + $phone );
	array_push($data2, $tmp);
}
array_multisort(array_column($data2, "time"), SORT_ASC, $data2);
$data["activity"] = $data2;
$data2 = array( );
$beginTime = mktime(0, 0, 0, date("m"), date("d") - $last, date("Y"));
$thumbs = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where (sign = 'view' && `type` = 4 && uniacid = " . $uniacid . " && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") OR (sign = 'praise' && `type` = 1 && uniacid = " . $uniacid . " && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") OR (sign = 'praise' && `type` = 3 && uniacid = " . $uniacid . " && create_time > " . $beginTime . " && to_uid = " . $staff_id . ")");
$thumbs = count($thumbs);
$data2[] = array( "title" => "点赞", "number" => $thumbs, "rate" => 0 );
$save_phone = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where sign = 'copy' && `type` = 1 && uniacid = " . $uniacid . " && create_time > " . $beginTime . " && to_uid = " . $staff_id);
$save_phone = count($save_phone);
$data2[] = array( "title" => "保存手机", "number" => $save_phone, "rate" => 0 );
$timelines = pdo_getall("longbing_card_timeline", array( "user_id in" => array( 0, $staff_id ) ));
$ids = array( );
foreach( $timelines as $index => $item ) 
{
	array_push($ids, $item["id"]);
}
if( empty($item) ) 
{
}
else 
{
	$ids_str = implode(",", $ids);
	if( count($ids) == 1 ) 
	{
		$comment = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_timeline_comment") . " where uniacid = " . $uniacid . " && create_time > " . $beginTime . " && timeline_id = " . $ids_str);
	}
	else 
	{
		$ids_str = "(" . $ids_str . ")";
		$comment = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_timeline_comment") . " where uniacid = " . $uniacid . " && create_time > " . $beginTime . " && timeline_id in " . $ids_str);
	}
	$comment = count($comment);
	$data2[] = array( "title" => "评论", "number" => $comment, "rate" => 0 );
}
$copy_wechat = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where sign = 'copy' && `type` = 4 && uniacid = " . $uniacid . " && create_time > " . $beginTime . " && to_uid = " . $staff_id);
$copy_wechat = count($copy_wechat);
$data2[] = array( "title" => "复制微信", "number" => $copy_wechat, "rate" => 0 );
$total = $thumbs + $save_phone + $comment + $copy_wechat;
if( $total ) 
{
	foreach( $data2 as $k => $v ) 
	{
		$data2[$k]["rate"] = sprintf("%.2f", $v["number"] / $total) * 100;
	}
}
$data["activityBarGraph"] = $data2;
return $this->result(0, "", $data);
?>