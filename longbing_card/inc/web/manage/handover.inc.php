<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$redis_sup_v3 = false;
$redis_server_v3 = false;
$check_load = "redis";
include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/func_longbing.php");
if( function_exists("longbing_check_redis") ) 
{
	$config = $_W["config"]["setting"]["redis"];
	$password = "";
	if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
	{
		$password = $config["requirepass"];
	}
	if( $config && isset($config["server"]) && $config["server"] && isset($config["port"]) && $config["port"] ) 
	{
		list($redis_sup_v3, $redis_server_v3) = longbing_check_redis($config["server"], $config["port"], $password);
	}
}
if( $_GPC["action"] == "handover" ) 
{
	$uniacid = $_W["uniacid"];
	$time = $old = $_GPC["formData"]["old"];
	$new = $_GPC["formData"]["new"];
	$old_info = pdo_get("longbing_card_user_info", array( "fans_id" => $old ));
	$old_name = "";
	if( $old_info ) 
	{
		$old_name = $old_info["name"];
	}
	else 
	{
		$old_info = pdo_get("longbing_card_user", array( "id" => $old ));
		if( $old_info ) 
		{
			$old_name = $old_info["nickName"];
		}
	}
	if( !$old || !$new ) 
	{
		message("操作失败", "", "error");
	}
	if( $old == $new ) 
	{
		message("操作失败，员工为同一人", "", "error");
	}
	$list = pdo_getall("longbing_card_collection", array( "to_uid" => $old, "uniacid" => $uniacid ));
	$sql = "INSERT INTO " . tablename("longbing_card_collection") . " (id, uniacid, uid, to_uid, from_uid, create_time, update_time, scene, `status`, is_qr, openGId, is_group, `type`, target_id, `handover_id`, `handover_name`) \r\n        VALUES ";
	@pdo_delete("longbing_card_collection", array( "to_uid" => $old, "uniacid" => $uniacid ));
	foreach( $list as $index => $item ) 
	{
		$value = "(null, " . $item["uniacid"] . ", " . $item["uid"] . ", " . $new . ", " . $item["from_uid"] . ", " . $item["create_time"] . ", " . $item["update_time"] . ", " . $item["scene"] . ", " . $item["status"] . ", " . $item["is_qr"] . ", '" . $item["openGId"] . "', " . $item["is_group"] . ", " . $item["type"] . ", " . $item["target_id"] . ", " . $old . ", '" . $old_name . "')";
		if( 0 < $index ) 
		{
			$value = ", " . $value;
		}
		$sql .= $value;
	}
	$sql .= " ON DUPLICATE KEY UPDATE uid=VALUES(uid), to_uid=VALUES(to_uid)";
	if( $list ) 
	{
		$result = pdo_query($sql);
	}
	$list = pdo_getall("longbing_card_client_info", array( "staff_id" => $old, "uniacid" => $uniacid ));
	$sql = "INSERT INTO " . tablename("longbing_card_client_info") . " (id, user_id, staff_id, `name`, `sex`, `phone`, `email`, `company`, `position`, `address`, `birthday`, `is_mask`, `remark`, `status`, `uniacid`, create_time, update_time) \r\n        VALUES ";
	@pdo_delete("longbing_card_client_info", array( "staff_id" => $old, "uniacid" => $uniacid ));
	foreach( $list as $index => $item ) 
	{
		$value = "(null, '" . $item["user_id"] . "', '" . $new . "', '" . $item["name"] . "', '" . $item["sex"] . "', '" . $item["phone"] . "', '" . $item["email"] . "', '" . $item["company"] . "', '" . $item["position"] . "', '" . $item["address"] . "', '" . $item["birthday"] . "', '" . $item["is_mask"] . "', '" . $item["remark"] . "', '" . $item["status"] . "', '" . $item["uniacid"] . "', '" . $item["create_time"] . "', '" . $item["update_time"] . "')";
		if( 0 < $index ) 
		{
			$value = ", " . $value;
		}
		$sql .= $value;
	}
	$sql .= " ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), staff_id=VALUES(staff_id)";
	if( $list ) 
	{
		$result = pdo_query($sql);
	}
	$result = @pdo_update("longbing_card_count", array( "to_uid" => $new ), array( "to_uid" => $old, "uniacid" => $uniacid ));
	@pdo_update("longbing_card_user", array( "is_staff" => 0, "is_boss" => 0 ), array( "id" => $old, "uniacid" => $uniacid ));
	@pdo_update("longbing_card_user_info", array( "is_staff" => 0, "is_default" => 0 ), array( "fans_id" => $old, "uniacid" => $uniacid ));
	$list = pdo_getall("longbing_card_coupon_record", array( "staff_id" => $old, "uniacid" => $uniacid ));
	$sql = "INSERT INTO " . tablename("longbing_card_coupon_record") . " (`id`, `user_id`, `staff_id`, `coupon_id`, `title`, `type`, `company_id`, `full`, `reduce`, `end_time`, `uniacid`, `status`, `create_time`, `update_time`, `desc_coupon`) \r\n        VALUES ";
	@pdo_delete("longbing_card_coupon_record", array( "staff_id" => $old, "uniacid" => $uniacid ));
	foreach( $list as $index => $item ) 
	{
		$value = "(null, " . $item["user_id"] . ", " . $new . ", " . $item["coupon_id"] . ", '" . $item["title"] . "', " . $item["type"] . ", " . $item["company_id"] . ", " . $item["full"] . ", " . $item["reduce"] . ", " . $item["end_time"] . ", " . $item["uniacid"] . ", " . $item["status"] . ", " . $item["create_time"] . ", " . $item["update_time"] . ", '" . $item["desc_coupon"] . "')";
		if( 0 < $index ) 
		{
			$value = ", " . $value;
		}
		$sql .= $value;
	}
	$sql .= " ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), staff_id=VALUES(staff_id)";
	if( $list ) 
	{
		$result = pdo_query($sql);
	}
	$list = pdo_getall("longbing_card_date", array( "staff_id" => $old, "uniacid" => $uniacid ));
	$sql = "INSERT INTO " . tablename("longbing_card_date") . " (`id`, `user_id`, `staff_id`, `date`, `uniacid`, `create_time`, `update_time`) \r\n        VALUES ";
	@pdo_delete("longbing_card_date", array( "staff_id" => $old, "uniacid" => $uniacid ));
	foreach( $list as $index => $item ) 
	{
		$value = "(null, '" . $item["user_id"] . "', '" . $new . "', '" . $item["date"] . "', '" . $item["uniacid"] . "', '" . $item["create_time"] . "', '" . $item["update_time"] . "')";
		if( 0 < $index ) 
		{
			$value = ", " . $value;
		}
		$sql .= $value;
	}
	$sql .= " ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), staff_id=VALUES(staff_id)";
	if( $list ) 
	{
		$result = pdo_fetchall($sql);
	}
	$list = pdo_getall("longbing_card_forward", array( "staff_id" => $old, "uniacid" => $uniacid ));
	$sql = "INSERT INTO " . tablename("longbing_card_forward") . " (`id`, `user_id`, `staff_id`, `type`, `target_id`, `status`, `uniacid`, `create_time`, `update_time`) \r\n        VALUES ";
	@pdo_delete("longbing_card_forward", array( "staff_id" => $old, "uniacid" => $uniacid ));
	foreach( $list as $index => $item ) 
	{
		$value = "(null, '" . $item["user_id"] . "', '" . $new . "', '" . $item["type"] . "', '" . $item["target_id"] . "', '" . $item["status"] . "', '" . $item["uniacid"] . "', '" . $item["create_time"] . "', '" . $item["update_time"] . "')";
		if( 0 < $index ) 
		{
			$value = ", " . $value;
		}
		$sql .= $value;
	}
	$sql .= " ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), staff_id=VALUES(staff_id)";
	if( $list ) 
	{
		$result3 = pdo_fetchall($sql);
	}
	@pdo_update("longbing_card_user_follow", array( "staff_id" => $new ), array( "staff_id" => $old, "uniacid" => $uniacid ));
	$list = pdo_getall("longbing_card_user_label", array( "staff_id" => $old, "uniacid" => $uniacid ));
	$sql = "INSERT INTO " . tablename("longbing_card_user_label") . " (`id`, `user_id`, `staff_id`, `lable_id`, `status`, `uniacid`, `create_time`, `update_time`) \r\n        VALUES ";
	@pdo_delete("longbing_card_user_label", array( "staff_id" => $old, "uniacid" => $uniacid ));
	foreach( $list as $index => $item ) 
	{
		$value = "(null, " . $item["user_id"] . ", " . $new . ", " . $item["lable_id"] . ", " . $item["status"] . ", " . $item["uniacid"] . ", " . $item["create_time"] . ", " . $item["update_time"] . ")";
		if( 0 < $index ) 
		{
			$value = ", " . $value;
		}
		$sql .= $value;
	}
	$sql .= " ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), staff_id=VALUES(staff_id), lable_id=VALUES(lable_id)";
	if( $list ) 
	{
		$result2 = pdo_fetchall($sql);
	}
	$list = pdo_getall("longbing_card_user_mark", array( "staff_id" => $old, "uniacid" => $uniacid ));
	$sql = "INSERT INTO " . tablename("longbing_card_user_mark") . " (`id`, `user_id`, `staff_id`, `mark`, `status`, `uniacid`, `create_time`, `update_time`) \r\n        VALUES ";
	@pdo_delete("longbing_card_user_mark", array( "staff_id" => $old, "uniacid" => $uniacid ));
	foreach( $list as $index => $item ) 
	{
		$value = "(null, " . $item["user_id"] . ", " . $new . ", " . $item["mark"] . ", " . $item["status"] . ", " . $item["uniacid"] . ", " . $item["create_time"] . ", " . $item["update_time"] . ")";
		if( 0 < $index ) 
		{
			$value = ", " . $value;
		}
		$sql .= $value;
	}
	$sql .= " ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), staff_id=VALUES(staff_id)";
	if( $list ) 
	{
		$result = pdo_fetchall($sql);
	}
	@pdo_update("longbing_card_user_phone", array( "to_uid" => $new ), array( "to_uid" => $old, "uniacid" => $uniacid ));
	if( $redis_sup_v3 ) 
	{
		$redis_server_v3->flushDB();
	}
	message("操作成功", $this->createWebUrl("manage/handover"), "success");
	message("操作失败", "", "error");
}
$list = pdo_getall("longbing_card_user_info", array( "uniacid" => $_W["uniacid"], "is_staff" => 1 ), array( ), "", array( "fans_id asc" ));
foreach( $list as $index => $item ) 
{
	if( !$item["name"] ) 
	{
		$list[$index]["name"] = "未填写姓名";
	}
}
load()->func("tpl");
include($this->template("manage/hanover"));
?>