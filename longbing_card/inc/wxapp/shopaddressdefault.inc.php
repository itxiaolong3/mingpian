<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$id = $_GPC["id"];
$type = $_GPC["type"];
if( !$id ) 
{
	return $this->result(-1, "", array( ));
}
if( !$type ) 
{
	$type = 1;
}
pdo_update("longbing_card_shop_address", array( "is_default" => 0 ), array( "user_id" => $uid ));
if( $type == 1 ) 
{
	pdo_update("longbing_card_shop_address", array( "is_default" => 1 ), array( "id" => $id ));
}
return $this->result(0, "", array( ));
?>