<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
if( !$uid ) 
{
	return $this->result(-1, "", array( ));
}
$list = pdo_getall("longbing_card_shop_address", array( "user_id" => $uid, "uniacid" => $uniacid, "status" => 1 ));
return $this->result(0, "", $list);
?>