<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$id = $_GPC["id"];
$uniacid = $_W["uniacid"];
$result = pdo_delete("longbing_card_user_poster", array( "id" => $id, "user_id" => $uid ));
if( $result ) 
{
	return $this->result(0, "suc", array( ));
}
return $this->result(-1, "fail", array( ));
?>