<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "change" ) 
{
	$table_name = $_GPC["table_name"];
	$data["update_time"] = time();
	$data["status"] = 2;
	$result = pdo_update($table_name, $data, array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("操作成功", "", "success");
	}
	message("操作失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$table_name = $_GPC["table_name"];
	$id = $_GPC["id"];
	$info = pdo_get($table_name, array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到数据", "", "error");
	}
	$result = pdo_delete($table_name, array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		message("删除成功", "", "success");
	}
	message("删除失败", "", "error");
}
?>