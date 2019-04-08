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
$time = time();
if( $_GPC["action"] == "addStaff" ) 
{
	$check_res = checkstafflimit($_W["uniacid"]);
	if( $check_res ) 
	{
		message("创建名片达到上限" . ", 如需增加请购买高级版本", "", "error");
	}
	$checkExists = pdo_tableexists("longbing_cardauth2_config");
	if( $checkExists ) 
	{
		$auth_info = pdo_get("longbing_cardauth2_config", array( "modular_id" => $_W["uniacid"] ));
		if( $auth_info ) 
		{
			$list = pdo_getall("longbing_card_user", array( "is_staff" => 1, "uniacid" => $_W["uniacid"] ));
			$count = count($list);
			if( $auth_info["end_time"] < $time ) 
			{
				message("授权已到期, 请联系管理员", "", "error");
			}
			if( $auth_info["number"] <= $count ) 
			{
				message("创建名片上限为: " . $auth_info["number"] . ", 如需增加请联系管理员", "", "error");
			}
		}
	}
	$user = pdo_get("longbing_card_user", array( "id" => $_GPC["id"] ));
	if( !$user ) 
	{
		message("未找到用户", "", "error");
	}
	if( $user["is_staff"] == 1 ) 
	{
		message("该用户已经是员工了", "", "error");
	}
	$user = pdo_get("longbing_card_user_info", array( "fans_id" => $_GPC["id"] ));
	pdo_update("longbing_card_user", array( "update_time" => $time, "is_staff" => 1 ), array( "id" => $_GPC["id"] ));
	if( !$user || empty($user) ) 
	{
		$result = pdo_insert("longbing_card_user_info", array( "fans_id" => $_GPC["id"], "create_time" => $time, "update_time" => $time, "is_staff" => 1, "status" => 1, "uniacid" => $_W["uniacid"], "is_default" => 1 ));
		if( $result ) 
		{
			changecollction($_GPC["id"], 1);
			message("添加成功", $this->createWebUrl("manage/users"), "success");
		}
	}
	$result = pdo_update("longbing_card_user_info", array( "status" => 1, "is_staff" => 1, "is_default" => 1, "update_time" => time() ), array( "fans_id" => $_GPC["id"] ));
	if( $result ) 
	{
		changecollction($_GPC["id"], 1);
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
			message("设置成功2", $this->createWebUrl("manage/users"), "success");
		}
		message("添加成功", $this->createWebUrl("manage/users"), "success");
	}
	message("添加失败", "", "error");
}
if( $_GPC["action"] == "addBoss" ) 
{
	$user = pdo_get("longbing_card_user", array( "id" => $_GPC["id"] ));
	if( !$user ) 
	{
		message("未找到用户", "", "error");
	}
	if( $user["is_staff"] != 1 ) 
	{
		message("该用户还不是员工", "", "error");
	}
	$result = pdo_update("longbing_card_user", array( "status" => 1, "is_staff" => 1, "update_time" => time(), "is_boss" => 1 ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
			message("设置成功2", $this->createWebUrl("manage/users"), "success");
		}
		message("设置成功", $this->createWebUrl("manage/users"), "success");
	}
	message("设置失败", "", "error");
}
if( $_GPC["action"] == "delBoss" ) 
{
	$user = pdo_get("longbing_card_user", array( "id" => $_GPC["id"] ));
	if( !$user ) 
	{
		message("未找到用户", "", "error");
	}
	if( $user["is_staff"] != 1 ) 
	{
		message("该用户还不是员工", "", "error");
	}
	$result = pdo_update("longbing_card_user", array( "status" => 1, "is_boss" => 0, "update_time" => time() ), array( "id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
			message("设置成功2", $this->createWebUrl("manage/users"), "success");
		}
		message("设置成功", $this->createWebUrl("manage/users"), "success");
	}
	message("设置失败", "", "error");
}
if( $_GPC["action"] == "delStaff" ) 
{
	$user = pdo_get("longbing_card_user", array( "id" => $_GPC["id"] ));
	if( !$user ) 
	{
		message("未找到用户", "", "error");
	}
	if( $user["is_staff"] == 0 ) 
	{
		message("该用户不是员工了", "", "error");
	}
	$user = pdo_get("longbing_card_user_info", array( "fans_id" => $_GPC["id"] ));
	pdo_update("longbing_card_user", array( "update_time" => $time, "is_staff" => 0, "is_boss" => 0 ), array( "id" => $_GPC["id"] ));
	$result = pdo_update("longbing_card_user_info", array( "status" => -1, "is_staff" => 0, "update_time" => time() ), array( "fans_id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		changecollction($_GPC["id"], 2);
		message("移除成功!", $this->createWebUrl("manage/users"), "success");
	}
	message("移除失败", "", "error");
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
		if( !$appid || !$appsecret ) 
		{
			message("编辑失败，appid和appsecret为必填写", "", "error");
		}
		$result = pdo_update("account_wxapp", array( "key" => $appid, "secret" => $appsecret ), array( "uniacid" => $_W["uniacid"] ));
		if( $result === 0 ) 
		{
			message("未做任何修改", $this->createWebUrl("manage/config"), "success");
		}
		if( $result ) 
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
		message("未做任何修改", $this->createWebUrl("manage/users"), "success");
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("编辑成功", $this->createWebUrl("manage/users"), "success");
	}
	message("编辑失败", "", "error");
}
if( $_GPC["action"] == "addDefault" ) 
{
	$info = pdo_get("longbing_card_user_info", array( "fans_id" => $_GPC["id"] ));
	if( !$info ) 
	{
		message("未找到名片信息", "", "error");
	}
	$user = pdo_get("longbing_card_user", array( "id" => $_GPC["id"] ));
	if( !$user ) 
	{
		message("未找到用户信息", "", "error");
	}
	if( !$user["is_staff"] ) 
	{
		message("请到用户管理里把该用户设置为员工", "", "error");
	}
	$result = pdo_update("longbing_card_user_info", array( "is_default" => 1, "update_time" => time() ), array( "fans_id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("设置成功", $this->createWebUrl("manage/users"), "success");
	}
	message("设置失败", "", "error");
}
if( $_GPC["action"] == "delDefault" ) 
{
	$result = pdo_update("longbing_card_user_info", array( "is_default" => 0, "update_time" => time() ), array( "fans_id" => $_GPC["id"] ));
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("设置成功", $this->createWebUrl("manage/users"), "success");
	}
	message("设置失败", "", "error");
}
if( $_GPC["action"] == "recreate" ) 
{
	$destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
	$image = $destination_folder . "/" . $_W["uniacid"] . "-" . $_GPC["id"] . "qr.png";
	$path = "longbing_card/pages/index/index?to_uid=" . $_GPC["id"] . "&is_qr=1&currentTabBar=toCard";
	$res = createQr3($image, $path, $uniacid);
	if( is_array($res) && isset($res["errcode"]) ) 
	{
		$appid = $_W["account"]["key"];
		$appidMd5 = md5($appid);
		if( is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt") ) 
		{
			unlink(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt");
		}
		message($res["errmsg"] . "请重试", "", "error");
	}
	if( $res != true ) 
	{
		message("设置失败", "", "error");
	}
	pdo_update("longbing_card_user", array( "qr_path" => "images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $_GPC["id"] . "qr.png" ), array( "id" => $_GPC["id"] ));
	if( $redis_sup_v3 ) 
	{
		$redis_server_v3->flushDB();
	}
	message("设置成功", $this->createWebUrl("manage/users"), "success");
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"] );
$curr = 1;
$id_arr = array( );
$users_search = array( );
if( isset($_GPC["keyword"]) && $_GPC["keyword"] ) 
{
	$keyword = $_GPC["keyword"];
	$where["nickName like"] = "%" . $_GPC["keyword"] . "%";
	$infos = pdo_getall("longbing_card_user_info", array( "uniacid" => $_W["uniacid"], "name like" => "%" . $_GPC["keyword"] . "%" ));
	foreach( $infos as $index => $item ) 
	{
		array_push($id_arr, $item["fans_id"]);
	}
	if( !empty($id_arr) ) 
	{
		$users_search = pdo_getall("longbing_card_user", array( "uniacid" => $_W["uniacid"], "id in" => $id_arr ));
	}
}
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$users = pdo_getslice("longbing_card_user", $where, $limit, $count, array( ), "", array( "is_boss desc", "is_staff desc", "create_time desc" ));
if( !empty($users_search) ) 
{
	foreach( $users_search as $index => $item ) 
	{
		$sign = true;
		foreach( $users as $index2 => $item2 ) 
		{
			if( $item["id"] == $item2["id"] ) 
			{
				$sign = false;
				break;
			}
		}
		if( $sign ) 
		{
			array_push($users, $item);
		}
	}
}
foreach( $users as $k => $v ) 
{
	$phone = pdo_get("longbing_card_user_phone", array( "user_id" => $v["id"] ));
	$users[$k]["phone"] = $phone["phone"];
	if( $v["is_staff"] == 1 ) 
	{
		$info = pdo_get("longbing_card_user_info", array( "fans_id" => $v["id"] ));
		$users[$k]["is_default"] = ($info ? $info["is_default"] : 0);
		$users[$k]["name"] = ($info ? $info["name"] : "");
		$checkFile = false;
		if( $v["qr_path"] ) 
		{
			if( file_exists(ATTACHMENT_ROOT . "/" . $v["qr_path"]) ) 
			{
				$size = @filesize(ATTACHMENT_ROOT . "/" . $v["qr_path"]);
				if( $size < 51220 ) 
				{
					$checkFile = true;
				}
			}
			else 
			{
				$checkFile = true;
			}
		}
		if( $v["qr_path"] == "" || $checkFile ) 
		{
			$users[$k]["qr_path"] = "";
		}
	}
	else 
	{
		$users[$k]["qr_path"] = "";
	}
}
$info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
$config_id = $info["id"];
$perPage = 15;
load()->func("tpl");
include($this->template("manage/users"));
function checkStaffLimit($uniacid) 
{
	$list = pdo_getall("longbing_card_user", array( "is_staff" => 1, "uniacid" => $uniacid ));
	$count = count($list);
	$checkExists = pdo_tableexists("longbing_cardauth2_default");
	if( $checkExists ) 
	{
		$auth_info = pdo_get("longbing_cardauth2_default");
		$config_info = pdo_get("longbing_cardauth2_config", array( "modular_id" => $uniacid ));
		if( !$config_info && $auth_info["card_number"] && $auth_info["card_number"] <= $count ) 
		{
			return true;
		}
	}
	if( LONGBING_AUTH_CARD == 0 ) 
	{
		return false;
	}
	if( LONGBING_AUTH_CARD <= $count ) 
	{
		return true;
	}
	return false;
}
function changeCollction($id, $type) 
{
	global $_GPC;
	global $_W;
	$info = pdo_get("longbing_card_collection", array( "uid" => $id, "to_uid" => $id, "uniacid" => $_W["uniacid"] ));
	if( $type == 1 ) 
	{
		if( !$info ) 
		{
			@pdo_insert("longbing_card_collection", array( "uniacid" => $_W["uniacid"], "uid" => $id, "to_uid" => $id, "create_time" => @time(), "update_time" => @time() ));
		}
		else 
		{
			pdo_update("longbing_card_collection", array( "status" => 1, "update_time" => time() ), array( "to_uid" => $id ));
		}
	}
	else 
	{
		if( empty($info) ) 
		{
		}
		else 
		{
			pdo_update("longbing_card_collection", array( "status" => 0, "update_time" => time() ), array( "to_uid" => $id ));
		}
	}
}
function createQr3($image, $path, $uniacid) 
{
	global $_GPC;
	global $_W;
	if( !is_dir(ATTACHMENT_ROOT . "/" . "images") ) 
	{
		mkdir(ATTACHMENT_ROOT . "/" . "images");
	}
	if( !is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card") ) 
	{
		mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card");
	}
	if( !is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/") ) 
	{
		mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/");
	}
	$appid = $_W["account"]["key"];
	$appsecret = $_W["account"]["secret"];
	$access_token = getAccessToken2();
	if( !$access_token ) 
	{
		return false;
	}
	$url = "https://api.weixin.qq.com/wxa/getwxacode?access_token=" . $access_token;
	$postData = array( "path" => $path );
	$postData = json_encode($postData);
	$response = ihttp_post($url, $postData);
	$content = json_decode($response["content"], true);
	if( isset($content["errcode"]) ) 
	{
		return $content;
	}
	if( 200 < strlen($response["content"]) ) 
	{
		$res = file_put_contents($image, $response["content"]);
		return true;
	}
	return false;
}
function getAccessToken2() 
{
	global $_GPC;
	global $_W;
	$appid = $_W["account"]["key"];
	$appsecret = $_W["account"]["secret"];
	$appidMd5 = md5($appid);
	if( !is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt") ) 
	{
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
		$data = ihttp_get($url);
		$data = json_decode($data["content"], true);
		if( !isset($data["access_token"]) ) 
		{
			return false;
		}
		$access_token = $data["access_token"];
		file_put_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array( "at" => $access_token, "time" => time() + 6200 )));
		return $access_token;
	}
	if( is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt") ) 
	{
		$fileInfo = file_get_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt");
		if( !$fileInfo ) 
		{
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
			$data = ihttp_get($url);
			$data = json_decode($data["content"], true);
			if( !isset($data["access_token"]) ) 
			{
				return false;
			}
			$access_token = $data["access_token"];
			file_put_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array( "at" => $access_token, "time" => time() + 6200 )));
			return $access_token;
		}
		$fileInfo = json_decode($fileInfo, true);
		if( $fileInfo["time"] < time() ) 
		{
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
			$data = ihttp_get($url);
			$data = json_decode($data["content"], true);
			if( !isset($data["access_token"]) ) 
			{
				return false;
			}
			$access_token = $data["access_token"];
			file_put_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array( "at" => $access_token, "time" => time() + 6200 )));
			return $access_token;
		}
		return $fileInfo["at"];
	}
	return false;
}
?>