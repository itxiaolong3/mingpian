<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( (!defined("LONGBING_AUTH_FORM") || LONGBING_AUTH_FORM == 0) && (!defined("LONGBING_AUTH_PLUG_AUTH") || LONGBING_AUTH_PLUG_AUTH == 0) ) 
{
	echo "未开通插件，请联系管理员付费开通！";
	exit();
}
if( $_GPC["action"] == "down" ) 
{
	$result = pdo_update("longbing_card_plug_form", array( "status" => 2, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("编辑成功", $this->createWebUrl("manage/plugform"), "success");
	}
	message("编辑失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_plug_form", array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_delete("longbing_card_plug_form", array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("删除成功", $this->createWebUrl("manage/plugform"), "success");
	}
	message("删除失败", "", "error");
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"] );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$list = pdo_getslice("longbing_card_plug_form", $where, $limit, $count, array( ), "", array( "id desc" ));
$info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
$perPage = 15;
load()->func("tpl");
include($this->template("manage/plugForm"));
?>