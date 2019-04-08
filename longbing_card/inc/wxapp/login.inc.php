<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$code = $_GPC["code"];
$pid = $_GPC["from_id"];
$uniacid = $_W["uniacid"];
$openGId = false;
$this->checkOrderTime();
if( LONGBING_AUTH_MINI ) 
{
	$sql = "SELECT id, uniacid FROM " . tablename("longbing_card_user") . " GROUP BY uniacid";
	$check_mini1 = pdo_fetchall($sql);
	$check_mini12 = count($check_mini1);
	$sql = "SELECT id, uniacid FROM " . tablename("longbing_card_user_info") . " GROUP BY uniacid";
	$check_mini2 = pdo_fetchall($sql);
	$check_mini22 = count($check_mini2);
	$sql = "SELECT id, uniacid FROM " . tablename("longbing_card_count") . " GROUP BY uniacid";
	$check_mini3 = pdo_fetchall($sql);
	$check_mini32 = count($check_mini3);
	if( LONGBING_AUTH_MINI < $check_mini12 || LONGBING_AUTH_MINI < $check_mini22 || LONGBING_AUTH_MINI < $check_mini32 ) 
	{
		foreach( $check_mini1 as $index => $item ) 
		{
			if( LONGBING_AUTH_MINI < $index + 1 && $uniacid == $item["uniacid"] ) 
			{
				return $this->result(-2, "1-too many" . $check_mini12 . "-" . LONGBING_AUTH_MINI, array( ));
			}
		}
		foreach( $check_mini2 as $index => $item ) 
		{
			if( LONGBING_AUTH_MINI < $index + 1 && $uniacid == $item["uniacid"] ) 
			{
				return $this->result(-2, "2-too many" . $check_mini22 . "-" . LONGBING_AUTH_MINI, array( ));
			}
		}
		foreach( $check_mini3 as $index => $item ) 
		{
			if( LONGBING_AUTH_MINI < $index + 1 && $uniacid == $item["uniacid"] ) 
			{
				return $this->result(-2, "3-too many" . $check_mini32 . "-" . LONGBING_AUTH_MINI, array( ));
			}
		}
	}
}
$encryptedData = $_GPC["encryptedData"];
$iv = $_GPC["iv"];
$this->checkEmpty();
if( !$code ) 
{
	return $this->result(-1, "need code", array( ));
}
if( !$pid ) 
{
	$pid = 0;
}
$is_qr = 0;
if( isset($_GPC["is_qr"]) ) 
{
	$is_qr = $_GPC["is_qr"];
}
$appid = $_W["account"]["key"];
$appsecret = $_W["account"]["secret"];
$url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $appid . "&secret=" . $appsecret . "&js_code=" . $code . "&grant_type=authorization_code";
$info = file_get_contents($url);
$info = @json_decode($info, true);
if( !is_array($info) ) 
{
	return $this->result(-1, "fail wechat", $info);
}
if( !isset($info["session_key"]) ) 
{
	return $this->result(-2, json_decode($info) . 2, $info);
}
$session_key = $info["session_key"];
if( $encryptedData ) 
{
	include_once(ROOT_PATH . "/wxBizDataCrypt.php");
	$pc = new WXBizDataCrypt($appid, $session_key);
	$errCode = $pc->decryptData($encryptedData, $iv, $data);
	if( $errCode == 0 ) 
	{
		$data = json_decode($data, true);
		$openGId = $data["openGId"];
	}
}
$time = time();
$randName = $this->getRandStr(4);
if( isset($info["openid"]) ) 
{
	$openid = $info["openid"];
	$user = pdo_get("longbing_card_user", array( "openid" => $openid, "uniacid" => $uniacid ));
	if( !$user ) 
	{
		$data = array( "openid" => $openid );
		$data["nickName"] = $randName;
		$data["scene"] = $_GPC["scene"];
		$data["pid"] = $pid;
		$data["uniacid"] = $uniacid;
		$data["is_qr"] = $is_qr;
		$data["create_time"] = $time;
		$data["update_time"] = $time;
		$data["jointime"] = date('Y-m-d H:i:s',$time);
		if( $openGId )
		{
			$data["openGId"] = $openGId;
		}
		if( isset($_GPC["is_group"]) && $_GPC["is_group"] ) 
		{
			$data["is_group"] = $_GPC["is_group"];
		}
		if( isset($_GPC["type"]) && $_GPC["type"] ) 
		{
			$data["type"] = $_GPC["type"];
		}
		if( isset($_GPC["target_id"]) && $_GPC["target_id"] ) 
		{
			$data["target_id"] = $_GPC["target_id"];
		}
		$insert = pdo_insert("longbing_card_user", $data);
		if( $insert ) 
		{
			$uid = pdo_insertid();
			$user = pdo_get("longbing_card_user", array( "openid" => $openid, "uniacid" => $uniacid ));
			if( $openGId ) 
			{
				$user["openGId_2"] = $openGId;
			}
			$user["phone"] = "";
		}
		$user["old"] = 0;
	}
	else 
	{
		$user["old"] = 2;
		if( $time - $user["create_time"] < 120 && $user["create_time"] == $user["update_time"] ) 
		{
			$data = array( );
			$data["pid"] = ($user["pid"] ? $user["pid"] : $pid);
			$data["scene"] = ($_GPC["scene"] ? $_GPC["scene"] : 0);
			$data["update_time"] = time();
			if( $openGId ) 
			{
				$data["openGId"] = $openGId;
			}
			if( isset($_GPC["is_group"]) && $_GPC["is_group"] ) 
			{
				$data["is_group"] = $_GPC["is_group"];
			}
			if( isset($_GPC["type"]) && $_GPC["type"] ) 
			{
				$data["type"] = $_GPC["type"];
			}
			if( isset($_GPC["target_id"]) && $_GPC["target_id"] ) 
			{
				$data["target_id"] = $_GPC["target_id"];
			}
			$ress = pdo_update("longbing_card_user", $data, array( "openid" => $openid, "uniacid" => $uniacid ));
			$user = pdo_get("longbing_card_user", array( "openid" => $openid, "uniacid" => $uniacid ));
			$user["old"] = 1;
		}
		$result = pdo_get("longbing_card_user_phone", array( "user_id" => $user["id"], "uniacid" => $uniacid ));
		$uid = $user["id"];
		$user["phone"] = ($result ? $result["phone"] : "");
		if( $openGId ) 
		{
			$user["openGId_2"] = $openGId;
		}
	}
	if( !$uid ) 
	{
		return $this->result(-1, "fail", array( ));
	}
	$check_sk = pdo_get("longbing_card_user_sk", array( "user_id" => $uid ));
	if( !$check_sk ) 
	{
		$time = time();
		@pdo_insert("longbing_card_user_sk", array( "user_id" => $uid, "sk" => $session_key, "uniacid" => $uniacid, "status" => 1, "create_time" => $time, "update_time" => $time ));
	}
	else 
	{
		$time = time();
		@pdo_update("longbing_card_user_sk", array( "sk" => $session_key, "update_time" => $time ), array( "id" => $check_sk["id"] ));
	}
	return $this->result(0, "suc", array( "user_id" => $uid, "user" => $user ));
}
return $this->result(-2, json_decode($info), $info);
?>