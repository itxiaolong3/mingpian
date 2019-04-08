<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "edit" ) 
{
	$time = time();
	$data = $_GPC["formData"];
	$data["update_time"] = $time;
	$id = $_GPC["id"];
	$result = false;
	$result = pdo_update("longbing_card_config", $data, array( "id" => $id ));
	if( $result === 0 ) 
	{
		message("未做任何修改", $this->createWebUrl("manage/message"), "success");
	}
	if( $result ) 
	{
		message("编辑成功", $this->createWebUrl("manage/message"), "success");
	}
	message("编辑失败", "", "error");
}
$where = array( "uniacid" => $_W["uniacid"] );
$info = pdo_get("longbing_card_config", $where);
if( !$info || empty($info) ) 
{
	pdo_insert("longbing_card_config", array( "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time(), "copyright" => "", "mini_template_id" => "" ));
	$info = pdo_get("longbing_card_config", $where);
}
$id = $info["id"];
load()->func("tpl");
include($this->template("manage/message"));
?>