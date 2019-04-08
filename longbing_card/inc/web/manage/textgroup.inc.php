<?php  global $_GPC;
global $_W;
include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/sms/SignatureHelper.php");
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$where = array( "uniacid" => $_W["uniacid"] );
$info = pdo_get("longbing_card_config", $where);
$AccessKeyID = $info["aliyun_sms_access_key_id"];
$AccessKeySecret = $info["aliyun_sms_access_key_secret"];
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
		message("未做任何修改", $this->createWebUrl("manage/textgroup"), "success");
	}
	if( $result ) 
	{
		if( $redis_sup_v3 ) 
		{
			$redis_server_v3->flushDB();
		}
		message("编辑成功", $this->createWebUrl("manage/textgroup"), "success");
	}
	message("编辑失败", "", "error");
}
if( $_GPC["action"] == "send" ) 
{
	$check_send = checksend();
	if( $check_send == false ) 
	{
		message("暂无短信群发权限, 请联系管理员开通!", "", "error");
	}
	if( isset($_GPC["code"]) && is_array($_GPC["code"]) ) 
	{
		foreach( $_GPC["code"] as $index => $item ) 
		{
			if( !$item ) 
			{
				unset($_GPC["code"][$index]);
			}
		}
	}
	if( isset($_GPC["value"]) && is_array($_GPC["value"]) ) 
	{
		foreach( $_GPC["value"] as $index => $item ) 
		{
			if( !$item ) 
			{
				unset($_GPC["value"][$index]);
			}
		}
	}
	if( !isset($_GPC["default_voice_switch"]) ) 
	{
		message("发送失败发送对象错误", "", "error");
	}
	$phoneArr = getphonearr($_GPC["default_voice_switch"]);
	if( empty($phoneArr) ) 
	{
		message("发送失败未找到手机号", "", "error");
	}
	sort($phoneArr);
	if( !isset($_GPC["sign"]) || !$_GPC["sign"] ) 
	{
		message("发送失败签名错误", "", "error");
	}
	$sign = array( );
	foreach( $phoneArr as $item ) 
	{
		array_push($sign, $_GPC["sign"]);
	}
	if( !isset($_GPC["modular"]) || !$_GPC["modular"] ) 
	{
		message("发送失败模板CODE错误", "", "error");
	}
	$TemplateCode = $_GPC["modular"];
	if( count($_GPC["code"]) != count($_GPC["value"]) ) 
	{
		message("发送失败变量与值个数对应个数错误", "", "error");
	}
	$TemplateParamJson = array( );
	$TemplateParamJson_tmp = array( );
	foreach( $_GPC["code"] as $index => $item ) 
	{
		$TemplateParamJson_tmp[$_GPC["code"][$index]] = $_GPC["value"][$index];
	}
	if( empty($TemplateParamJson_tmp) ) 
	{
		$TemplateParamJson_tmp["tmp"] = "value";
	}
	foreach( $phoneArr as $item ) 
	{
		array_push($TemplateParamJson, $TemplateParamJson_tmp);
	}
	if( 50 < count($phoneArr) ) 
	{
		$times = ceil(count($phoneArr) / 50);
		for( $i = 0; $i < $times; $i++ ) 
		{
			$offset = $i * 50;
			$array = array_slice($phoneArr, $offset, 50);
			$sign = array( );
			foreach( $array as $item ) 
			{
				array_push($sign, $_GPC["sign"]);
			}
			$TemplateParamJson = array( );
			$TemplateParamJson_tmp = array( );
			foreach( $_GPC["code"] as $index => $item ) 
			{
				$TemplateParamJson_tmp[$_GPC["code"][$index]] = $_GPC["value"][$index];
			}
			if( empty($TemplateParamJson_tmp) ) 
			{
				$TemplateParamJson_tmp["tmp"] = "value";
			}
			foreach( $array as $item ) 
			{
				array_push($TemplateParamJson, $TemplateParamJson_tmp);
			}
			$result = sendbatchsms($AccessKeyID, $AccessKeySecret, $array, $sign, $TemplateCode, $TemplateParamJson);
		}
	}
	else 
	{
		$result = sendbatchsms($AccessKeyID, $AccessKeySecret, $phoneArr, $sign, $TemplateCode, $TemplateParamJson);
	}
	if( $result->Code == "OK" ) 
	{
		message("发送成功", "", "success");
	}
	message($result->Message, "", "error");
}
if( false ) 
{
	ini_set("display_errors", "on");
	error_reporting(32767);
	set_time_limit(0);
	header("Content-Type: text/plain; charset=utf-8");
	print_r(sendbatchsms($AccessKeyID, $AccessKeySecret));
}
$info = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
$config_id = $info["id"];
load()->func("tpl");
include($this->template("manage/textgroup"));
function getPhoneArr($type) 
{
	global $_GPC;
	global $_W;
	$tmp = array( );
	if( $type == 1 || $type == 2 ) 
	{
		$is_staff = ($type == 1 ? 1 : 0);
		$target = pdo_getall("longbing_card_user", array( "uniacid" => $_W["uniacid"], "is_staff" => $is_staff ));
		$targetIdArr = array( );
		foreach( $target as $index => $item ) 
		{
			array_push($targetIdArr, $item["id"]);
		}
		if( empty($targetIdArr) ) 
		{
			return array( );
		}
		$list = pdo_getall("longbing_card_user_phone", array( "uniacid" => $_W["uniacid"], "user_id in" => $targetIdArr ));
	}
	else 
	{
		$list = pdo_getall("longbing_card_user_phone", array( "uniacid" => $_W["uniacid"] ));
	}
	foreach( $list as $index => $item ) 
	{
		array_push($tmp, $item["phone"]);
	}
	$tmp = array_unique($tmp);
	return $tmp;
}
function sendBatchSms($AccessKeyID, $AccessKeySecret, $phoneArr, $sign, $TemplateCode, $TemplateParamJson) 
{
	$params = array( );
	$security = false;
	$accessKeyId = $AccessKeyID;
	$accessKeySecret = $AccessKeySecret;
	$params["PhoneNumberJson"] = $phoneArr;
	$params["SignNameJson"] = $sign;
	$params["TemplateCode"] = $TemplateCode;
	$params["TemplateParamJson"] = $TemplateParamJson;
	$params["TemplateParamJson"] = json_encode($params["TemplateParamJson"], JSON_UNESCAPED_UNICODE);
	$params["SignNameJson"] = json_encode($params["SignNameJson"], JSON_UNESCAPED_UNICODE);
	$params["PhoneNumberJson"] = json_encode($params["PhoneNumberJson"], JSON_UNESCAPED_UNICODE);
	if( !empty($params["SmsUpExtendCodeJson"]) && is_array($params["SmsUpExtendCodeJson"]) ) 
	{
		$params["SmsUpExtendCodeJson"] = json_encode($params["SmsUpExtendCodeJson"], JSON_UNESCAPED_UNICODE);
	}
	$helper = new Aliyun\DySDKLite\SignatureHelper();
	$content = $helper->request($accessKeyId, $accessKeySecret, "dysmsapi.aliyuncs.com", array_merge($params, array( "RegionId" => "cn-hangzhou", "Action" => "SendBatchSms", "Version" => "2017-05-25" )), $security);
	return $content;
}
function sendBatchSms2($AccessKeyID, $AccessKeySecret, $phoneArr, $sign, $TemplateCode, $TemplateParamJson) 
{
	$params = array( );
	$security = false;
	$accessKeyId = $AccessKeyID;
	$accessKeySecret = $AccessKeySecret;
	$params["PhoneNumberJson"] = $phoneArr;
	$params["SignNameJson"] = $sign;
	$params["TemplateCode"] = $TemplateCode;
	$params["TemplateParamJson"] = $TemplateParamJson;
	$params["TemplateParamJson"] = json_encode($params["TemplateParamJson"], JSON_UNESCAPED_UNICODE);
	$params["SignNameJson"] = json_encode($params["SignNameJson"], JSON_UNESCAPED_UNICODE);
	$params["PhoneNumberJson"] = json_encode($params["PhoneNumberJson"], JSON_UNESCAPED_UNICODE);
	if( !empty($params["SmsUpExtendCodeJson"]) && is_array($params["SmsUpExtendCodeJson"]) ) 
	{
		$params["SmsUpExtendCodeJson"] = json_encode($params["SmsUpExtendCodeJson"], JSON_UNESCAPED_UNICODE);
	}
	$helper = new Aliyun\DySDKLite\SignatureHelper();
	$content = $helper->request($accessKeyId, $accessKeySecret, "dysmsapi.aliyuncs.com", array_merge($params, array( "RegionId" => "cn-hangzhou", "Action" => "SendBatchSms", "Version" => "2017-05-25" )), $security);
	return $content;
}
function checkSend() 
{
	global $_GPC;
	global $_W;
	if( !defined("LONGBING_AUTH_MINI") || LONGBING_AUTH_MINI != 0 ) 
	{
		return true;
	}
	$checkExists = pdo_tableexists("longbing_cardauth2_config");
	if( $checkExists ) 
	{
		$auth_info = pdo_get("longbing_cardauth2_config", array( "modular_id" => $_W["uniacid"] ));
		if( $auth_info ) 
		{
			$time = time();
			if( $auth_info["end_time"] < $time ) 
			{
				message("授权已到期, 请联系管理员", "", "error");
			}
			if( $auth_info["send_switch"] == 1 ) 
			{
				return true;
			}
			return false;
		}
	}
	$checkExists = pdo_tableexists("longbing_cardauth2_default");
	if( $checkExists ) 
	{
		$default_info = pdo_get("longbing_cardauth2_default");
		if( $default_info && $default_info["send_switch"] == 0 ) 
		{
			return false;
		}
	}
	return true;
}
?>