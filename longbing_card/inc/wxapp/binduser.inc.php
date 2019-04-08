<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$user_id = $_GPC["user_id"];
$from_id = $_GPC["from_id"];
$uniacid = $_W["uniacid"];
if( !$user_id || !$from_id ) 
{
	return $this->result(-1, "fail1", array( ));
}
if( $user_id == $from_id ) 
{
	return $this->result(-1, "fail2", array( ));
}
$user = pdo_get("longbing_card_user", array( "id" => $user_id ));
if( !$user ) 
{
	return $this->result(-1, "fail user", array( ));
}
if( $user["pid"] ) 
{
	return $this->result(-1, "fail used", array( ));
}
$result = pdo_update("longbing_card_user", array( "pid" => $from_id, "update_time" => time() ), array( "id" => $user_id ));
if( $result ) 
{
	return $this->result(0, "suc", array( ));
}
return $this->result(-1, "fail", array( ));
?>