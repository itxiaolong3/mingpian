<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
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
if( $_GPC["action"] == "edit" ) 
{
	$table_name = false;
	$data = $_GPC["formData"];
	$data["update_time"] = time();
	$id = $_GPC["id"];
	$result = false;
	if( $id ) 
	{
		if( isset($data["pid"]) && $data["pid"] ) 
		{
			if( $data["pid"] == $id ) 
			{
				message("自己不能成为自己的二级分类", "", "error");
			}
			$old = pdo_get("longbing_card_shop_type", array( "pid" => $id ));
			if( $old || !empty($old) ) 
			{
				message("该分类存在二级分类不能直接改为二级分类", "", "error");
			}
		}
		$result = pdo_update("longbing_card_shop_type", $data, array( "id" => $id ));
	}
	else 
	{
		$data["create_time"] = time();
		$data["uniacid"] = $_W["uniacid"];
		$result = pdo_insert("longbing_card_shop_type", $data);
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("操作成功", $this->createWebUrl("manage/type"), "success");
	}
	message("操作失败", "", "error");
}
$where = array( "uniacid" => $_W["uniacid"] );
$id = 0;
$info = array( );
if( isset($_GPC["id"]) ) 
{
	$id = $_GPC["id"];
	$where["id"] = $_GPC["id"];
	$id = $_GPC["id"];
	$info = pdo_get("longbing_card_shop_type", $where);
}
$tmp = pdo_getall("longbing_card_shop_type", array( "uniacid" => $_W["uniacid"], "pid" => 0, "status" => 1 ));
$tops = array( array( "id" => 0, "title" => "顶级分类" ) );
$tops = array_merge($tops, $tmp);
load()->func("tpl");
include($this->template("manage/typeEdit"));
?>