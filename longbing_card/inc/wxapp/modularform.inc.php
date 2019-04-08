<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$name = $_GPC["name"];
$phone = $_GPC["phone"];
$content = $_GPC["content"];
$modular_id = $_GPC["modular_id"];
if( !$uid || !$name || !$phone || !$content || !$modular_id ) 
{
	return $this->result(-1, $uid . "-" . $name . "-" . $phone . "-" . $content . "-" . $modular_id, array( ));
}
$time = time();
$result = pdo_insert("longbing_card_form", array( "name" => $name, "phone" => $phone, "content" => $content, "uniacid" => $uniacid, "bac1" => $uid, "create_time" => $time, "update_time" => $time, "modular_id" => $modular_id ));
if( $result ) 
{
	return $this->result(0, "", array( ));
}
return $this->result(-1, "error", array( ));
?>