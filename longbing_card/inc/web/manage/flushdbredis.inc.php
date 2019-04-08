<?php  global $_GPC;
global $_W;
$check_load = "redis";
if( extension_loaded($check_load) ) 
{
	try 
	{
		$config = $_W["config"]["setting"]["redis"];
		if( !isset($config["server"]) || !$config["server"] || !isset($config["port"]) || !$config["port"] ) 
		{
			message("链接redis失败！", "", "error");
		}
		$redis_server = new Redis();
		$res = $redis_server->connect($config["server"], $config["port"]);
		if( $config && isset($config["requirepass"]) && $config["requirepass"] ) 
		{
			$pas_res = $redis_server->auth($config["requirepass"]);
			if( !$pas_res ) 
			{
				message("链接redis失败", "", "error");
			}
		}
		if( $res ) 
		{
			$res = $redis_server->flushDB();
			if( $res ) 
			{
				message("操作成功", "", "success");
			}
			message("清除失败", "", "error");
		}
		else 
		{
			message("链接redis失败", "", "error");
		}
	}
	catch( Exception $e ) 
	{
		message("链接redis失败", "", "error");
	}
}
else 
{
	message("未安装redis或者未安装redis php扩展", "", "error");
}
?>