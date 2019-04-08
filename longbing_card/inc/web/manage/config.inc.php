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
if( $_GPC["action"] == "edit" ) 
{
	$time = time();
	if( !LONGBING_AUTH_COPYRIGHT ) 
	{
		$_GPC["formData"]["copyright"] = "https://retail.xiaochengxucms.com/images/12/2018/11/crDXyl3TyBRLUBch6ToqXL6e9D96hY.jpg";
	}
	if( isset($_GPC["formData"]) && isset($_GPC["formData"]["is_mini"]) ) 
	{
		$appid = $_GPC["formData"]["appid"];
		$appsecret = $_GPC["formData"]["appsecret"];
		$paynum = $_GPC["formData"]["paynum"];
		$paykey = $_GPC["formData"]["paykey"];
		if( !$appid || !$appsecret )
		{
			message("编辑失败，appid和appsecret为必填写", "", "error");
		}
		$result = pdo_update("account_wxapp", array( "key" => $appid, "secret" => $appsecret ), array( "uniacid" => $_W["uniacid"] ));
		$respayinfo = pdo_update("longbing_card_configpayinfo", array( "paynum" => $paynum, "paykey" => $paykey,"uniacid" => $_W["uniacid"]), array( "id" => 1 ));
		if( $result === 0 &&$respayinfo===0)
		{
			message("未做任何修改", $this->createWebUrl("manage/config"), "success");
		}
		if( $result||$respayinfo)
		{
			message("编辑成功", $this->createWebUrl("manage/config"), "success");
		}
		message("编辑失败", "", "error");
	}
	$checkExists = pdo_tableexists("longbing_cardauth2_config");
	if( $checkExists ) 
	{
		$auth_info = pdo_get("longbing_cardauth2_config", array( "modular_id" => $_W["uniacid"] ));
		if( $auth_info && $auth_info["copyright_id"] ) 
		{
			$copyright = pdo_get("longbing_cardauth2_copyright", array( "id" => $auth_info["copyright_id"] ));
			if( $copyright ) 
			{
				$_GPC["formData"]["copyright"] = tomedia($copyright["image"]);
				$_GPC["formData"]["logo_text"] = $copyright["text"];
				$_GPC["formData"]["logo_phone"] = $copyright["phone"];
				$_GPC["formData"]["logo_switch"] = 2;
			}
		}
	}
	$data = $_GPC["formData"];
	$data["logo_text"] = str_replace(" ", "&nbsp;", $data["logo_text"]);
	if( $data["order_overtime"] ) 
	{
		$data["order_overtime"] = intval($data["order_overtime"]);
		if( $data["order_overtime"] < 1800 || !$data["order_overtime"] ) 
		{
			$data["order_overtime"] = 1800;
		}
	}
	if( $data["collage_overtime"] ) 
	{
		$data["collage_overtime"] = intval($data["collage_overtime"]);
		if( $data["collage_overtime"] < 1800 || !$data["collage_overtime"] ) 
		{
			$data["collage_overtime"] = 1800;
		}
	}
	if( $data["staff_extract"] ) 
	{
		$data["staff_extract"] = intval($data["staff_extract"]);
		if( $data["staff_extract"] < 0 || 100 < $data["staff_extract"] ) 
		{
			$data["staff_extract"] = 0;
		}
	}
	if( $data["first_extract"] ) 
	{
		$data["first_extract"] = intval($data["first_extract"]);
		if( $data["first_extract"] < 0 || 100 < $data["first_extract"] ) 
		{
			$data["first_extract"] = 0;
		}
	}
	if( $data["sec_extract"] ) 
	{
		$data["sec_extract"] = intval($data["sec_extract"]);
		if( $data["sec_extract"] < 0 || 100 < $data["sec_extract"] ) 
		{
			$data["sec_extract"] = 0;
		}
	}
	if( $data["receiving"] ) 
	{
		$data["receiving"] = intval($data["receiving"]);
		if( $data["receiving"] < 5 ) 
		{
			$data["receiving"] = 5;
		}
	}
	$data["update_time"] = $time;
	$id = $_GPC["id"];
	$result = false;
	$result = pdo_update("longbing_card_config", $data, array( "id" => $id ));
	if( $result === 0 ) 
	{
		message("未做任何修改", $this->createWebUrl("manage/config"), "success");
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("编辑成功", $this->createWebUrl("manage/config"), "success");
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
$allow = true;
$checkExists = pdo_tableexists("longbing_cardauth2_config");
if( $checkExists ) 
{
	$auth_info = pdo_get("longbing_cardauth2_config", array( "modular_id" => $_W["uniacid"] ));
	if( $auth_info && $auth_info["copyright_id"] ) 
	{
		$allow = false;
	}
}
$payinfo = pdo_get("longbing_card_configpayinfo", array( "uniacid" => $_W["uniacid"],'id'=>1));
$paynum = $payinfo["paynum"];
$paykey = $payinfo["paykey"];
$account = pdo_get("account_wxapp", array( "uniacid" => $_W["uniacid"] ));
$appid = $account["key"];
$appsecret = $account["secret"];
load()->func("tpl");
include($this->template("manage/config"));
?>