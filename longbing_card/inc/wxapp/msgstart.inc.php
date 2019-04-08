<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$client_id = $_GPC["client_id"];
$uniacid = $_W["uniacid"];
if( !$uid || !$client_id ) 
{
	return $this->result(-1, "", array( ));
}
$check = pdo_get("longbing_card_start", array( "staff_id" => $uid, "user_id" => $client_id, "uniacid" => $uniacid ));
if( $check ) 
{
	$result = pdo_delete("longbing_card_start", array( "staff_id" => $uid, "user_id" => $client_id ));
}
else 
{
	$time = time();
	$result = pdo_insert("longbing_card_start", array( "staff_id" => $uid, "user_id" => $client_id, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time ));
}
if( $result ) 
{
	return $this->result(0, "", array( ));
}
return $this->result(-1, "", array( ));
?>