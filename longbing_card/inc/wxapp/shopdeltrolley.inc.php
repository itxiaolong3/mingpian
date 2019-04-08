<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$id = $_GPC["id"];
$check = pdo_get("longbing_card_shop_trolley", array( "user_id" => $uid, "id" => $id, "status" => 1 ));
if( empty($check) ) 
{
	return $this->result(-1, "", array( ));
}
$result = pdo_update("longbing_card_shop_trolley", array( "status" => -1 ), array( "id" => $id ));
if( $result ) 
{
	return $this->result(0, "", array( ));
}
return $this->result(-1, "", array( ));
?>