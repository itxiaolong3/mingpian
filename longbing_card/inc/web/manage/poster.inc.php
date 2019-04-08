<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "disable" ) 
{
	$item = pdo_get("longbing_card_poster", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	if( $item["status"] == 0 ) 
	{
		message("该数据已被禁用", "", "error");
	}
	$result = pdo_update("longbing_card_poster", array( "status" => 0, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("禁用成功", $this->createWebUrl("manage/poster"), "success");
	}
	message("禁用失败", "", "error");
}
if( $_GPC["action"] == "enable" ) 
{
	$item = pdo_get("longbing_card_poster", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	if( $item["status"] == 1 ) 
	{
		message("该内容已启用", "", "error");
	}
	$result = pdo_update("longbing_card_poster", array( "status" => 1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("启用成功", $this->createWebUrl("manage/poster"), "success");
	}
	message("启用失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$item = pdo_get("longbing_card_poster", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_poster", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("删除成功", $this->createWebUrl("manage/poster"), "success");
	}
	message("删除失败", "", "error");
}
$limit = array( 1, 10 );
$where = array( "uniacid" => $_W["uniacid"], "status >" => -1 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
if( $_GPC["searchType"] ) 
{
	$where["type_id"] = $_GPC["searchType"];
}
$list = pdo_getslice("longbing_card_poster", $where, $limit, $count, array( ), "", array( "top desc", "create_time desc" ));
$type = pdo_getall("longbing_card_poster_type", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
$type_new = array( );
foreach( $type as $index => $item ) 
{
	$type_new[$item["id"]] = $item;
}
foreach( $list as $index => $item ) 
{
	$list[$index]["type_name"] = $type_new[$item["type_id"]]["title"];
}
$perPage = 10;
load()->func("tpl");
include($this->template("manage/poster"));
?>