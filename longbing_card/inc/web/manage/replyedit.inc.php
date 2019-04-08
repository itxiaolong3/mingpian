<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "edit" ) 
{
	$data = $_GPC["formData"];
	$data["update_time"] = time();
	$id = $_GPC["id"];
	$result = false;
	if( $id ) 
	{
		$result = pdo_update("longbing_card_quick_reply", $data, array( "id" => $id ));
	}
	else 
	{
		$data["create_time"] = time();
		$data["uniacid"] = $_W["uniacid"];
		$result = pdo_insert("longbing_card_quick_reply", $data);
	}
	if( $result ) 
	{
		message("操作成功", $this->createWebUrl("manage/reply"), "success");
	}
	message("操作失败", "", "error");
}
$where = array( "uniacid" => $_W["uniacid"] );
$id = 0;
$info = array( );
$replyType = pdo_getall("longbing_card_reply_type", array( "uniacid" => $_W["uniacid"], "status >" => -1 ));
if( isset($_GPC["id"]) ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_quick_reply", array( "uniacid" => $_W["uniacid"], "id" => $id ));
	if( $info["user_id"] == 0 ) 
	{
		$info["name"] = "管理员";
	}
	else 
	{
		$user = pdo_get("longbing_card_user_info", array( "fans_id" => $info["user_id"] ));
		$info["name"] = $user["name"];
	}
	foreach( $replyType as $k2 => $v2 ) 
	{
		if( $info["type"] == $v2["id"] ) 
		{
			$info["type"] = $v2["title"];
		}
	}
}
load()->func("tpl");
include($this->template("manage/replyEdit"));
?>