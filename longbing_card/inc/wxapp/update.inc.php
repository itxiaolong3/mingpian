<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$user_id = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
$openGId = false;
$encryptedData = $_GPC["encryptedData"];
$iv = $_GPC["iv"];
if( !$user_id ) 
{
	return $this->result(-1, "", array( ));
}
if( $encryptedData ) 
{
	$code = $_GPC["code"];
	$appid = $_W["account"]["key"];
	$appsecret = $_W["account"]["secret"];
	$url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $appid . "&secret=" . $appsecret . "&js_code=" . $code . "&grant_type=authorization_code";
	$info = ihttp_get($url);
	$info = json_decode($info["content"], true);
	if( !isset($info["session_key"]) ) 
	{
		return $this->result(-1, "session_key", array( ));
	}
	$session_key = $info["session_key"];
	if( !$code ) 
	{
		return $this->result(-1, "code", array( ));
	}
	include_once("wxBizDataCrypt.php");
	$pc = new WXBizDataCrypt($appid, $session_key);
	$errCode = $pc->decryptData($encryptedData, $iv, $data);
	if( $errCode == 0 ) 
	{
		$data = json_decode($data, true);
		$openGId = $data["openGId"];
	}
}
$user = pdo_get("longbing_card_user", array( "id" => $user_id, "uniacid" => $uniacid ));
if( !$user ) 
{
	return $this->result(-1, "", array( ));
}
$data = array( "nickName" => $_GPC["nickName"], "avatarUrl" => $_GPC["avatarUrl"], "update_time" => time() );
if( $openGId && !$user["openGId"] ) 
{
	$data["openGId"] = $openGId;
}
pdo_update("longbing_card_user", $data, array( "id" => $user_id ));
return $this->result(0, "", array( ));
?>