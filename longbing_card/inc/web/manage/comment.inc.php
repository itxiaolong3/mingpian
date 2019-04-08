<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"] );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$where["status >"] = -1;
if( $_GPC["action"] == "delete" ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_timeline_comment", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_update("longbing_card_timeline_comment", array( "status" => -1, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("删除成功", $this->createWebUrl("manage/comment"), "success");
	}
	message("删除失败", "", "error");
}
$keyword = "";
if( isset($_GPC["keyword"]) ) 
{
	$where["content like"] = "%" . $_GPC["keyword"] . "%";
	$keyword = $_GPC["keyword"];
}
$comments = pdo_getslice("longbing_card_timeline_comment", $where, $limit, $count, array( ), "", "id desc");
foreach( $comments as $k => $v ) 
{
	$timeline = pdo_get("longbing_card_timeline", array( "id" => $v["timeline_id"] ));
	$comments[$k]["title"] = "";
	if( $timeline ) 
	{
		$comments[$k]["title"] = $timeline["title"];
	}
}
$perPage = 15;
load()->func("tpl");
include($this->template("manage/comment"));
?>