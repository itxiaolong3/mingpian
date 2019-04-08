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
	if( !$data["end_time"] ) 
	{
		message("请填写福包到期时间", "", "error");
	}
	if( $data["end_time"] ) 
	{
		$data["end_time"] = strtotime($data["end_time"]);
	}
	$data["full"] = floatval($data["full"]);
	$data["reduce"] = floatval($data["reduce"]);
	$data["number"] = intval($data["number"]);
	$data["end_time"] = intval($data["end_time"]);
	if( !$data["full"] || !$data["reduce"] || !$data["number"] || !$data["end_time"] ) 
	{
		message("请检查填写数据", "", "error");
	}
	$id = $_GPC["id"];
	$result = false;
	if( $id ) 
	{
		$result = pdo_update("longbing_card_coupon", $data, array( "id" => $id ));
	}
	else 
	{
		$data["create_time"] = time();
		$data["uniacid"] = $_W["uniacid"];
		$result = pdo_insert("longbing_card_coupon", $data);
	}
	if( $result ) 
	{
		message("操作成功", $this->createWebUrl("manage/coupon"), "success");
	}
	message("操作失败", "", "error");
}
$where = array( "uniacid" => $_W["uniacid"] );
$id = 0;
$info = array( );
if( isset($_GPC["id"]) ) 
{
	$where["id"] = $_GPC["id"];
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_coupon", $where);
	$info["end_time"] = date("Y-m-d H:i:s", $info["end_time"]);
}
load()->func("tpl");
include($this->template("manage/couponEdit"));
?>