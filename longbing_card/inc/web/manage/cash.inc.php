<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "onCash" ) 
{
	$id = $_GPC["id"];
	if( !$id ) 
	{
		message("请求失败，参数错误", "", "error");
	}
	$cash_info = pdo_get("longbing_card_selling_cash_water", array( "id" => $id ));
	if( !$cash_info || !isset($cash_info["status"]) ) 
	{
		message("请求失败，数据有误", "", "error");
	}
	if( $cash_info["status"] != 0 ) 
	{
		message("只能操作未到账的记录", "", "error");
	}
	$user_id = $cash_info["user_id"];
	$profit = pdo_get("longbing_card_selling_profit", array( "user_id" => $user_id ));
	if( !$profit ) 
	{
		message("未找到信息", "", "error");
	}
	$result = pdo_update("longbing_card_selling_cash_water", array( "status" => 1, "update_time" => time() ), array( "id" => $id ));
	if( $result ) 
	{
		$postaling = $profit["postaling"];
		if( $postaling ) 
		{
			$postaling = $postaling - $cash_info["money"];
			$total_postal = $profit["total_postal"] + $cash_info["money"];
			$postaling = sprintf("%.2f", $postaling);
			$total_postal = sprintf("%.2f", $total_postal);
			if( $postaling < 0 ) 
			{
				$postaling = 0;
			}
			pdo_update("longbing_card_selling_profit", array( "postaling" => $postaling, "total_postal" => $total_postal, "update_time" => time() ), array( "user_id" => $user_id ));
		}
		$res = @sendmsg($user_id, $cash_info["money"]);
		message("成功", $this->createWebUrl("manage/cash"), "success");
	}
	message("失败", "", "error");
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"] );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$keyword = "";
if( isset($_GPC["keyword"]) && $_GPC["keyword"] ) 
{
	$keyword = $_GPC["keyword"];
	$where["cash_no like"] = "%" . $keyword . "%";
}
$list = pdo_getslice("longbing_card_selling_cash_water", $where, $limit, $count, array( ), "", array( "status asc", "id desc" ));
foreach( $list as $index => $item ) 
{
	$list[$index]["user_info"] = array( );
	$list[$index]["staff_info"] = array( );
	$user_info = pdo_get("longbing_card_user", array( "id" => $item["user_id"] ), array( "nickName", "is_staff" ));
	if( $user_info ) 
	{
		$list[$index]["user_info"] = $user_info;
		if( $user_info["is_staff"] == 1 ) 
		{
			$staff_info = pdo_get("longbing_card_user_info", array( "fans_id" => $item["user_id"] ), array( "name" ));
			if( $staff_info ) 
			{
				$list[$index]["staff_info"] = $staff_info;
			}
		}
	}
}
$perPage = 15;
load()->func("tpl");
include($this->template("manage/cash"));
function sendMsg($user_id, $money) 
{
	global $_GPC;
	global $_W;
	$user = pdo_get("longbing_card_user", array( "id" => $user_id ));
	$config = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
	$page = "longbing_card/voucher/pages/user/myearning/myearning";
	if( $user && $config ) 
	{
		$send_body = "您提现的佣金已到账, 金额为:" . $money . ", 请注意查收";
		if( $user["is_staff"] && $config["notice_switch"] == 1 && $config["wx_appid"] && $config["wx_tplid"] ) 
		{
			sendPublicMsg($config, $user, $user, $send_body, $page);
		}
		else 
		{
			if( $user["is_staff"] && $config["notice_switch"] == 2 && $config["corpid"] && $config["corpsecret"] && $config["agentid"] ) 
			{
				sendEnterpriseMsg($config, $user, $user, $send_body, $page);
			}
			else 
			{
				sendServerMsg($config, $user, $user, $send_body, $page);
			}
		}
	}
}
function getFormId($to_uid) 
{
	$beginTime = mktime(0, 0, 0, date("m"), date("d") - 6, date("Y"));
	pdo_delete("longbing_card_formId", array( "create_time <" => $beginTime ));
	$formId = pdo_getall("longbing_card_formId", array( "user_id" => $to_uid ), array( ), "", "id asc", 1);
	if( empty($formId) ) 
	{
		return false;
	}
	if( $formId[0]["create_time"] < $beginTime ) 
	{
		pdo_delete("longbing_card_formId", array( "id" => $formId[0]["id"] ));
		getFormId($to_uid);
	}
	else 
	{
		pdo_delete("longbing_card_formId", array( "id" => $formId[0]["id"] ));
		return $formId[0]["formId"];
	}
}
function getAccessToken() 
{
	global $_GPC;
	global $_W;
	$appid = $_W["account"]["key"];
	$appsecret = $_W["account"]["secret"];
	$appidMd5 = md5($appid);
	if( !is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt") && is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/") ) 
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
function sendServerMsg($config, $send, $target, $send_body, $page) 
{
	$openid = $target["openid"];
	$date = date("Y-m-d H:i");
	$form = getformid($target["id"]);
	if( !$form ) 
	{
		return false;
	}
	$access_token = getaccesstoken();
	if( !$access_token ) 
	{
		return false;
	}
	$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
	$page = "longbing_card/staff/radar/radar";
	$postData = array( "touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array( "keyword1" => array( "value" => $send["nickName"] ), "keyword2" => array( "value" => $send_body ), "keyword3" => array( "value" => $date ) ) );
	$postData = json_encode($postData, JSON_UNESCAPED_UNICODE);
	$response = ihttp_post($url, $postData);
}
function sendPublicMsg($config, $send, $target, $send_body, $page) 
{
	global $_GPC;
	global $_W;
	$access_token = getaccesstoken();
	if( !$access_token ) 
	{
		return false;
	}
	$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=" . $access_token;
	$date = date("Y-m-d H:i");
	$appid = $_W["account"]["key"];
	$data = array( "touser" => $target["openid"], "mp_template_msg" => array( "appid" => $config["wx_appid"], "url" => "http://weixin.qq.com/download", "template_id" => $config["wx_tplid"], "miniprogram" => array( "appid" => $appid, "pagepath" => $page ), "data" => array( "first" => array( "value" => "", "color" => "#c27ba0" ), "keyword1" => array( "value" => $send["nickName"], "color" => "#93c47d" ), "keyword2" => array( "value" => $send_body, "color" => "#0000ff" ), "remark" => array( "value" => $date, "color" => "#45818e" ) ) ) );
	$data = json_encode($data, JSON_UNESCAPED_UNICODE);
	$res = curlPost($url, $data);
	if( $res ) 
	{
		$res = json_decode($res, true);
		if( isset($res["errcode"]) && $res["errcode"] != 0 ) 
		{
			$form = getformid($target["id"]);
			if( $form ) 
			{
				$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
				$postData = array( "touser" => $target["openid"], "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array( "keyword1" => array( "value" => $send["nickName"] ), "keyword2" => array( "value" => $send_body ), "keyword3" => array( "value" => $date ) ) );
				$postData = json_encode($postData);
				$response = curlPost($url, $postData);
			}
		}
	}
	return true;
}
function sendEnterpriseMsg($config, $send, $target, $send_body, $page) 
{
	global $_GPC;
	global $_W;
	$appid = $config["corpid"];
	$appsecret = $config["corpsecret"];
	$agentid = $config["agentid"];
	$user_info = pdo_get("longbing_card_user_info", array( "fans_id" => $target["id"] ));
	$touser = $user_info["ww_account"];
	if( !$touser ) 
	{
		return true;
	}
	$data = array( "touser" => $touser, "msgtype" => "text", "agentid" => $agentid, "text" => array( "content" => $send_body ) );
	include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/work.weixin.class.php");
	$work = new work($appid, $appsecret);
	$result = $work->send($data);
	return true;
}
?>