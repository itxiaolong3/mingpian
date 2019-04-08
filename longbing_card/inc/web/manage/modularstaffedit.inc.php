<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$redis_sup_v3 = false;
$redis_server_v3 = false;
include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/func_longbing.php");
if( function_exists("longbing_check_redis") ) 
{
	$config = $_W["config"]["setting"]["redis"];
	$password = "";
	if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
	{
		$password = $config["requirepass"];
	}
	if( $config && isset($config["server"]) && $config["server"] && isset($config["port"]) && $config["port"] ) 
	{
		list($redis_sup_v3, $redis_server_v3) = longbing_check_redis($config["server"], $config["port"], $password);
	}
}
if( $_GPC["action"] == "editSub" ) 
{
	$table_name = false;
	$id = false;
	$data = array( );
	foreach( $_GPC["formData"] as $k => $v ) 
	{
		if( $k == "table_name" ) 
		{
			$table_name = $v;
		}
		else 
		{
			if( $k == "id" ) 
			{
				$id = $v;
			}
			else 
			{
				$data[$k] = $v;
			}
		}
	}
	$data["update_time"] = time();
	$id = $_GPC["id"];
	$result = false;
	if( $id ) 
	{
		$result = pdo_update($table_name, $data, array( "id" => $id ));
	}
	else 
	{
		$data["create_time"] = time();
		$data["uniacid"] = $_W["uniacid"];
		$result = pdo_insert($table_name, $data);
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("操作成功", $this->createWebUrl("manage/modular"), "success");
	}
	message("操作失败", "", "error");
}
if( $_GPC["action"] == "change" ) 
{
	$table_name = $_GPC["table_name"];
	$data["update_time"] = time();
	$data["status"] = 0;
	$info = pdo_get($table_name, array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到该数据", "", "error");
	}
	if( $info["status"] == 0 ) 
	{
		$data["status"] = 1;
	}
	$result = pdo_update($table_name, $data, array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("操作成功", "", "success");
	}
	message("操作失败", "", "error");
}
if( $_GPC["action"] == "delete" ) 
{
	$table_name = $_GPC["table_name"];
	$data["update_time"] = time();
	$info = pdo_get($table_name, array( "id" => $_GPC["id"] ));
	if( !$info || empty($info) ) 
	{
		message("未找到该数据", "", "error");
	}
	$data["status"] = -1;
	$result = pdo_update($table_name, $data, array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("操作成功", "", "success");
	}
	message("操作失败", "", "error");
}
$where = array( "uniacid" => $_W["uniacid"] );
$id = 0;
$info = array( );
$table_name = $_GPC["table_name"];
if( !$table_name ) 
{
	message("失败", "", "error");
}
if( isset($_GPC["id"]) ) 
{
	$id = $_GPC["id"];
	$where["id"] = $_GPC["id"];
	$id = $_GPC["id"];
	$info = pdo_get($table_name, $where);
}
$modular_id = $_GPC["modular_id"];
load()->func("tpl");
include($this->template("manage/modularStaffEdit"));
?>