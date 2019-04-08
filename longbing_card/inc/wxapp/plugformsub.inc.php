<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$to_uid = $_GPC["to_uid"];
$name = $_GPC["name"];
$phone = $_GPC["phone"];
$content = $_GPC["content"];
$uniacid = $_W["uniacid"];
$time = time();
if( !$uid || !$to_uid || !$name || !$phone || !$content ) 
{
	return $this->result(-1, "", array( ));
}
$result = pdo_insert("longbing_card_plug_form", array( "uniacid" => $uniacid, "user_id" => $uid, "to_uid" => $to_uid, "create_time" => $time, "update_time" => $time, "name" => $name, "phone" => $phone, "content" => $content ));
if( $result ) 
{
	return $this->result(0, "", array( ));
}
return $this->result(-1, "", array( ));
?>