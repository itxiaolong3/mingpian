<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$tag = $_GPC["tag"];
$tag_id = $_GPC["tag_id"];
$uniacid = $_W["uniacid"];
if( !$uid ) 
{
	return $this->result(-1, "error param", array( ));
}
if( !$tag_id && !$tag ) 
{
	return $this->result(-1, "error param", array( ));
}
if( $tag_id ) 
{
	$result = pdo_delete("longbing_card_tags", array( "id" => $tag_id ));
	if( $result ) 
	{
		return $this->result(0, "suc", array( ));
	}
	return $this->result(-1, "fail", array( ));
}
$where = array( "uniacid" => $_W["uniacid"], "status" => 1, "tag" => $tag, "user_id" => $uid );
$item = pdo_getall("longbing_card_tags", $where);
if( $item ) 
{
	return $this->result(-1, "already", array( ));
}
$where["create_time"] = time();
$where["update_time"] = time();
$result = pdo_insert("longbing_card_tags", $where);
if( $result ) 
{
	$id = pdo_insertid();
	return $this->result(0, "suc", array( "tag_id" => $id ));
}
return $this->result(-1, "fail", array( ));
?>