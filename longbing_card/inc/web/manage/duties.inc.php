<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/" . $_W["current_module"]["name"] . "/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$dutiesEdit = $this->createWebUrl("manage/dutiesedit");
if( $_GPC["action"] == "down" ) 
{
	$item = pdo_get("longbing_card_job", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	$check_used = pdo_get("longbing_card_user_info", array( "job_id" => $_GPC["id"] ));
	if( $check_used ) 
	{
		message("下架失败，还有员工在使用该职务", "", "error");
	}
	$result = pdo_update("longbing_card_job", array( "status" => 0, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("下架成功", $this->createWebUrl("manage/duties"), "success");
	}
	message("下架失败", "", "error");
}
if( $_GPC["action"] == "up" ) 
{
	$item = pdo_get("longbing_card_job", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_job", array( "status" => 1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("上架成功", $this->createWebUrl("manage/duties"), "success");
	}
	message("上架失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_job", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$check_used = pdo_get("longbing_card_user_info", array( "job_id" => $_GPC["id"] ));
	if( $check_used ) 
	{
		message("下架失败，还有员工在使用该职务", "", "error");
	}
	$result = pdo_update("longbing_card_job", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("删除成功", $this->createWebUrl("manage/duties"), "success");
	}
	message("删除失败", "", "error");
}
if( $_GPC["action"] == "edit" ) 
{
	$time = time();
	$name = $_GPC["job_value"];
	$top = $_GPC["job_top"];
	$id = $_GPC["id"];
	if( !$name ) 
	{
		message("失败, 请传入参数", "", "error");
	}
	if( $id ) 
	{
		$data = array( "name" => $name, "top" => $top, "update_time" => $time );
		$result = pdo_update("longbing_card_job", $data, array( "id" => $id ));
	}
	else 
	{
		$data = array( "uniacid" => $_W["uniacid"], "name" => $name, "top" => $top, "create_time" => $time, "update_time" => $time );
		$result = pdo_insert("longbing_card_job", $data);
	}
	if( !$result ) 
	{
		message("请求失败", "", "error");
	}
	message("请求成功", $this->createWebUrl("manage/duties"), "success");
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"], "status >" => -1 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
if( isset($_GPC["keyword"]) ) 
{
	$keyword = $_GPC["keyword"];
	$where["name like"] = "%" . $_GPC["keyword"] . "%";
}
$list = pdo_getslice("longbing_card_job", $where, $limit, $count, array( ), "", array( "top desc" ));
$perPage = 15;
load()->func("tpl");
include($this->template("manage/duties"));
?>