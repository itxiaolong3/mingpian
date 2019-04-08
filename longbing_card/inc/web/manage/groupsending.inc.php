<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
$beginTime = mktime(0, 0, 0, date("m"), date("d") - 6, date("Y"));
pdo_delete("longbing_card_formId", array( "create_time <" => $beginTime ));
$time = time();
if( $_GPC["action"] == "sending" ) 
{
	$data = $_GPC["formData"];
	if( !isset($data["content"]) || !isset($data["type"]) ) 
	{
		message("请正确填入数据", "", "error");
	}
	@pdo_insert("longbing_card_group_sending", array( "content" => $data["content"], "type" => $data["type"], "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time ));
	switch( $data["type"] ) 
	{
		case 1: sendclient($data["content"], $_W["uniacid"]);
		break;
		case 2: sendstaff($data["content"], $_W["uniacid"]);
		break;
		default: sendall($data["content"], $_W["uniacid"]);
	}
	message("发送成功", $this->createWebUrl("manage/groupsending"), "success");
}
load()->func("tpl");
include($this->template("manage/groupsending"));
function sendAll($content, $uniacid) 
{
	global $_GPC;
	global $_W;
	$appid = $_W["account"]["key"];
	$users = pdo_fetchall("SELECT a.id,a.openid,a.is_staff,a.uniacid,b.formId,b.id as bid FROM " . tablename("longbing_card_user") . " a INNER JOIN " . tablename("longbing_card_formId") . " b ON a.id = b.user_id WHERE a.uniacid = " . $uniacid . " GROUP BY b.user_id");
	$dataClient = array( );
	$dataStaff = array( );
	$access_token = getAccessToken2();
	if( !$access_token ) 
	{
		return false;
	}
	$config = pdo_get("longbing_card_config", array( "uniacid" => $uniacid ));
	$page = "";
	$date = date("Y-m-d H:i");
	foreach( $users as $index => $item ) 
	{
		$form = $item["formId"];
		if( !$form ) 
		{
			continue;
		}
		@pdo_delete("longbing_card_formId", array( "id" => $item["bid"] ));
		$postData = array( "touser" => $item["openid"], "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array( "keyword1" => array( "value" => "系统通知" ), "keyword2" => array( "value" => $content ), "keyword3" => array( "value" => $date ) ) );
		if( $item["is_staff"] == 1 ) 
		{
			continue;
		}
		$postData["page"] = "longbing_card/pages/index/index";
		$postData = json_encode($postData);
		if( !$config["mini_template_id"] ) 
		{
			continue;
		}
		$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
		$tmp = array( "url" => $url, "data" => $postData );
		array_push($dataStaff, $tmp);
	}
	$data = array_merge($dataClient, $dataStaff);
	curl_m($data);
	sendStaff($content, $uniacid);
	return true;
}
function sendStaff($content, $uniacid) 
{
	global $_GPC;
	global $_W;
	$appid = $_W["account"]["key"];
	$appsecret = $_W["account"]["secret"];
	$users = pdo_fetchall("SELECT a.id,a.openid,a.nickName,a.uniacid,b.formId,b.id as bid,c.ww_account FROM " . tablename("longbing_card_user") . " a INNER JOIN " . tablename("longbing_card_formId") . " b ON a.id = b.user_id LEFT JOIN " . tablename("longbing_card_user_info") . " c ON a.id = c.fans_id WHERE a.uniacid = " . $uniacid . " && a.is_staff = 1 GROUP BY b.user_id");
	$access_token = getAccessToken2();
	if( !$access_token ) 
	{
		return false;
	}
	$config = pdo_get("longbing_card_config", array( "uniacid" => $uniacid ));
	$page = "";
	$date = date("Y-m-d H:i");
	$dataSend = array( );
	if( $config["notice_switch"] == 1 ) 
	{
		if( !$config["wx_appid"] ) 
		{
			return false;
		}
		if( !$config["wx_tplid"] ) 
		{
			return false;
		}
		foreach( $users as $index => $item ) 
		{
			$page = "longbing_card/staff/radar/radar";
			$date = date("Y-m-d H:i");
			$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=" . $access_token;
			$data = array( "touser" => $item["openid"], "mp_template_msg" => array( "appid" => $config["wx_appid"], "url" => "http://weixin.qq.com/download", "template_id" => $config["wx_tplid"], "miniprogram" => array( "appid" => $appid, "pagepath" => $page ), "data" => array( "first" => array( "value" => "系统通知", "color" => "#c27ba0" ), "keyword1" => array( "value" => "管理员", "color" => "#93c47d" ), "keyword2" => array( "value" => $content, "color" => "#0000ff" ), "remark" => array( "value" => $date, "color" => "#45818e" ) ) ) );
			$data = json_encode($data);
			$tmp = array( "url" => $url, "data" => $data );
			array_push($dataSend, $tmp);
		}
		curl_m($dataSend);
		return true;
	}
	else 
	{
		if( $config["notice_switch"] == 2 ) 
		{
			$appid = $config["corpid"];
			$appsecret = $config["corpsecret"];
			$agentid = $config["agentid"];
			if( !$appid || !$appsecret || !$agentid ) 
			{
				return false;
			}
			$dataSend = array( );
			foreach( $users as $index => $item ) 
			{
				$touser = $item["ww_account"];
				if( !$touser ) 
				{
					continue;
				}
				$data = array( "touser" => $touser, "msgtype" => "text", "agentid" => $agentid, "text" => array( "content" => $content ) );
				$tmp = array( "url" => "", "data" => json_encode($data) );
				array_push($dataSend, $tmp);
			}
			include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/work.weixin.class.php");
			$work = new work($appid, $appsecret);
			$work->send_multi($dataSend);
			return true;
		}
		else 
		{
			$access_token = getAccessToken2();
			if( !$access_token ) 
			{
				return false;
			}
			$dataSend = array( );
			$page = "longbing_card/staff/radar/radar";
			foreach( $users as $index => $item ) 
			{
				$form = $item["formId"];
				if( !$form ) 
				{
					continue;
				}
				@pdo_delete("longbing_card_formId", array( "id" => $item["bid"] ));
				$postData = array( "touser" => $item["openid"], "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array( "keyword1" => array( "value" => "系统通知" ), "keyword2" => array( "value" => $content ), "keyword3" => array( "value" => $date ) ) );
				$postData["page"] = "longbing_card/pages/index/index";
				$postData = json_encode($postData);
				if( !$config["mini_template_id"] ) 
				{
					continue;
				}
				$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
				$tmp = array( "url" => $url, "data" => $postData );
				array_push($dataSend, $tmp);
			}
			curl_m($dataSend);
			return true;
		}
	}
}
function sendClient($content, $uniacid) 
{
	$users = pdo_fetchall("SELECT a.id,a.openid,a.uniacid,b.formId,b.id as bid FROM " . tablename("longbing_card_user") . " a INNER JOIN " . tablename("longbing_card_formId") . " b ON a.id = b.user_id WHERE a.uniacid = " . $uniacid . " && a.is_staff = 0 GROUP BY b.user_id");
	$dataClient = array( );
	$dataStaff = array( );
	$access_token = getAccessToken2();
	if( !$access_token ) 
	{
		return false;
	}
	$config = pdo_get("longbing_card_config", array( "uniacid" => $uniacid ));
	$page = "";
	$date = date("Y-m-d H:i");
	foreach( $users as $index => $item ) 
	{
		$form = $item["formId"];
		if( !$form ) 
		{
			continue;
		}
		@pdo_delete("longbing_card_formId", array( "id" => $item["bid"] ));
		$postData = array( "touser" => $item["openid"], "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array( "keyword1" => array( "value" => "系统通知" ), "keyword2" => array( "value" => $content ), "keyword3" => array( "value" => $date ) ) );
		$postData["page"] = "longbing_card/pages/index/index";
		$postData = json_encode($postData);
		if( !$config["mini_template_id"] ) 
		{
			continue;
		}
		$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
		$tmp = array( "url" => $url, "data" => $postData );
		array_push($dataStaff, $tmp);
	}
	$data = array_merge($dataClient, $dataStaff);
	curl_m($data);
	return true;
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
function curl_m($array) 
{
	global $_GPC;
	global $_W;
	$mh = curl_multi_init();
	$curls = array( );
	foreach( $array as $index => $item ) 
	{
		$tmp = curl_init();
		curl_setopt($tmp, CURLOPT_URL, $item["url"]);
		curl_setopt($tmp, CURLOPT_HEADER, 0);
		curl_setopt($tmp, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($tmp, CURLOPT_POST, 1);
		curl_setopt($tmp, CURLOPT_POSTFIELDS, $item["data"]);
		curl_setopt($tmp, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($tmp, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($tmp, CURLOPT_HEADER, 0);
		curl_setopt($tmp, CURLOPT_TIMEOUT, 100);
		array_push($curls, $tmp);
		curl_multi_add_handle($mh, $tmp);
	}
	$running = NULL;
	do 
	{
		$mrc = curl_multi_exec($mh, $running);
	}
	while( $mrc == CURLM_CALL_MULTI_PERFORM );
	while( $running && $mrc == CURLM_OK ) 
	{
		if( curl_multi_select($mh) != -1 ) 
		{
			do 
			{
				$mrc = curl_multi_exec($mh, $running);
			}
			while( $mrc == CURLM_CALL_MULTI_PERFORM );
		}
	}
	foreach( $curls as $index => $item ) 
	{
		curl_multi_remove_handle($mh, $item);
	}
	curl_multi_close($mh);
	return true;
}
?>