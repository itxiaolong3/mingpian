<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$tag_id = $_GPC["tag_id"];
$uniacid = $_W["uniacid"];
if( !$tag_id ) 
{
	return $this->result(-1, "error param", array( ));
}
$where = array( "uniacid" => $_W["uniacid"], "status" => 1, "id" => $tag_id );
$item = pdo_get("longbing_card_tags", $where);
if( !$item ) 
{
	return $this->result(-1, "error data", array( ));
}
pdo_update("longbing_card_tags", array( "count" => $item["count"] + 1 ), array( "id" => $item["id"] ));
$result = pdo_insert("longbing_card_user_tags", array( "user_id" => $uid, "tag_id" => $tag_id, "uniacid" => $uniacid, "status" => 1, "create_time" => time(), "update_time" => time() ));
if( $result ) 
{
	return $this->result(0, "suc", array( ));
}
return $this->result(-1, "fail", array( ));
?>