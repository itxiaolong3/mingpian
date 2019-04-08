<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "add" ) 
{
	$where = array( "uniacid" => $_W["uniacid"], "status >" => -1, "content" => $_GPC["content"], "type" => $_GPC["itemType"], "user_id" => 0 );
	$item = pdo_getall("longbing_card_quick_reply", $where);
	if( $item ) 
	{
		message("已经存在该内容了", "", "error");
	}
	$time = time();
	$data = array( "uniacid" => $_W["uniacid"], "status" => 1, "content" => $_GPC["content"], "type" => $_GPC["itemType"], "top" => $_GPC["top"], "user_id" => 0, "create_time" => $time, "update_time" => $time );
	$result = pdo_insert("longbing_card_quick_reply", $data);
	if( $result ) 
	{
		message("添加成功", $this->createWebUrl("reply"), "success");
	}
	message("添加失败", "", "error");
}
if( $_GPC["action"] == "disable" ) 
{
	$item = pdo_get("longbing_card_quick_reply", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	if( $item["status"] == 0 ) 
	{
		message("该数据已被禁用", "", "error");
	}
	$result = pdo_update("longbing_card_quick_reply", array( "status" => 0, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("禁用成功", $this->createWebUrl("manage/reply"), "success");
	}
	message("禁用失败", "", "error");
}
if( $_GPC["action"] == "enable" ) 
{
	$item = pdo_get("longbing_card_quick_reply", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	if( $item["status"] == 1 ) 
	{
		message("该内容已启用", "", "error");
	}
	$result = pdo_update("longbing_card_quick_reply", array( "status" => 1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("启用成功", $this->createWebUrl("manage/reply"), "success");
	}
	message("启用失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$item = pdo_get("longbing_card_quick_reply", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_quick_reply", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("删除成功", $this->createWebUrl("manage/reply"), "success");
	}
	message("删除失败", "", "error");
}
if( $_GPC["action"] == "edit" ) 
{
	$item = pdo_get("longbing_card_quick_reply", array( "id" => $_GPC["id"] ));
	if( !$item || empty($item) ) 
	{
		message("未找到该数据", "", "error");
	}
	$result = pdo_update("longbing_card_quick_reply", array( "content" => $_GPC["content"], "type" => $_GPC["type"], "top" => $_GPC["top"], "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("编辑成功", $this->createWebUrl("reply"), "success");
	}
	message("编辑失败", "", "error");
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"], "status >" => -1, "type >" => 0 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
if( isset($_GPC["keyword"]) ) 
{
	$keyword = $_GPC["keyword"];
	$where["content like"] = "%" . $_GPC["keyword"] . "%";
}
$reply = pdo_getslice("longbing_card_quick_reply", $where, $limit, $count);
$replyType = pdo_getall("longbing_card_reply_type", array( "uniacid" => $_W["uniacid"], "status >" => -1 ));
foreach( $reply as $k => $v ) 
{
	if( $v["user_id"] == 0 ) 
	{
		$reply[$k]["name"] = "管理员";
	}
	else 
	{
		$user = pdo_get("longbing_card_user_info", array( "fans_id" => $v["user_id"] ));
		$reply[$k]["name"] = $user["name"];
	}
	foreach( $replyType as $k2 => $v2 ) 
	{
		if( $v["type"] == $v2["id"] ) 
		{
			$reply[$k]["type"] = $v2["title"];
		}
	}
}
$perPage = 15;
load()->func("tpl");
include($this->template("manage/reply"));
?>