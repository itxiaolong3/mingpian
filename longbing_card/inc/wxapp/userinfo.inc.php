<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$user = pdo_get("longbing_card_user", array( "id" => $uid, "uniacid" => $uniacid ));
$profit = pdo_get("longbing_card_selling_profit", array( "user_id" => $uid ));
if( $profit ) 
{
	$user["profit"] = $profit["profit"];
}
else 
{
	$user["profit"] = 0;
}
return $this->result(0, "", $user);
?>