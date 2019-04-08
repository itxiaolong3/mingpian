<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$to_uid = $_GPC["to_uid"];
$type = $_GPC["type"];
$sign = $_GPC["sign"];
$target = $_GPC["target"];
$scene = $_GPC["scene"];
$uniacid = $_W["uniacid"];
if( (!$uid || !$to_uid || !$type || !$sign) && !$scene ) 
{
	$scene = 0;
}
if( !$target ) 
{
	$target = "";
}
$time = time();
$data = array( "user_id" => $uid, "to_uid" => $to_uid, "type" => $type, "sign" => $sign, "target" => $target, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time, "scene" => $scene );
$result = pdo_insert("longbing_card_count", $data);
if( $result ) 
{
	$count_id = pdo_insertid();
	$this->sendTotal($count_id);
	$this->result(0, "", array( ));
}
$this->result(-1, "", array( ));
?>