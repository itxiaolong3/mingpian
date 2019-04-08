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
		$result = pdo_update("longbing_card_poster", $data, array( "id" => $id ));
	}
	else 
	{
		$data["create_time"] = time();
		$data["uniacid"] = $_W["uniacid"];
		$result = pdo_insert("longbing_card_poster", $data);
	}
	if( $result ) 
	{
		message("操作成功", $this->createWebUrl("manage/poster"), "success");
	}
	message("操作失败", "", "error");
}
$where = array( "uniacid" => $_W["uniacid"] );
$id = 0;
$info = array( );
$type = pdo_getall("longbing_card_poster_type", array( "uniacid" => $uniacid, "status >" => -1 ));
if( isset($_GPC["id"]) ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_poster", array( "uniacid" => $uniacid, "id" => $id ));
}
load()->func("tpl");
include($this->template("manage/posterEdit"));
?>