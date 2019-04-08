<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$target_id = $_GPC["target_id"];
if( !$uid || !$target_id ) 
{
	return $this->result(-1, "", array( ));
}
$chat = pdo_get("longbing_card_chat", array( "user_id" => $uid, "target_id" => $target_id ));
if( !$chat ) 
{
	$chat = pdo_get("longbing_card_chat", array( "user_id" => $target_id, "target_id" => $uid ));
}
if( !$chat ) 
{
	return $this->result(-1, "", array( ));
}
$data["chat_id"] = $chat["id"];
$user_info = pdo_get("longbing_card_user", array( "id" => $uid ));
$target_info = pdo_get("longbing_card_user", array( "id" => $target_id ));
if( !$user_info || !$target_info ) 
{
	return $this->result(-1, "", array( ));
}
if( $user_info["is_staff"] ) 
{
	$staff = pdo_get("longbing_card_user_info", array( "fans_id" => $uid ));
	$user_info["info"] = $staff;
	$user_info["phone"] = ($staff ? $staff["phone"] : "");
}
else 
{
	$staff = pdo_get("longbing_card_user_phone", array( "user_id" => $uid ));
	$user_info["info"] = $staff;
	$user_info["phone"] = ($staff ? $staff["phone"] : "");
}
if( $target_info["is_staff"] ) 
{
	$staff = pdo_get("longbing_card_user_info", array( "fans_id" => $uid ));
	$target_info["info"] = $staff;
	$target_info["phone"] = ($staff ? $staff["phone"] : "");
}
else 
{
	$staff = pdo_get("longbing_card_user_phone", array( "user_id" => $uid ));
	$target_info["info"] = $staff;
	$target_info["phone"] = ($staff ? $staff["phone"] : "");
}
$data["user_info"] = $user_info;
$data["target_info"] = $target_info;
return $this->result(0, "", $data);
?>