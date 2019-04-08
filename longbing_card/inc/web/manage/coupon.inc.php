<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "disable" ) 
{
	$goods = pdo_get("longbing_card_coupon", array( "id" => $_GPC["id"] ));
	if( !$goods || empty($goods) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_coupon", array( "status" => 0, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("操作成功", $this->createWebUrl("manage/coupon"), "success");
	}
	message("操作失败", "", "error");
}
if( $_GPC["action"] == "enable" ) 
{
	$goods = pdo_get("longbing_card_coupon", array( "id" => $_GPC["id"] ));
	if( !$goods || empty($goods) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_coupon", array( "status" => 1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("发布成功", $this->createWebUrl("manage/coupon"), "success");
	}
	message("发布失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_coupon", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_update("longbing_card_coupon", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("删除成功", $this->createWebUrl("manage/coupon"), "success");
	}
	message("删除失败", "", "error");
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"], "status >" => -1 );
$keyword = "";
if( isset($_GPC["keyword"]) ) 
{
	$keyword = $_GPC["keyword"];
	$where["title like"] = "%" . $keyword . "%";
}
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$list = pdo_getslice("longbing_card_coupon", $where, $limit, $count, array( ), "", array( "top desc", "create_time desc" ));
$perPage = 15;
load()->func("tpl");
include($this->template("manage/coupon"));
?>