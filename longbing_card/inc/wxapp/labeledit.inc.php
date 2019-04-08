<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$label_id = $_GPC["label_id"];
$name = $_GPC["name"];
$delete = $_GPC["delete"];
$uniacid = $_W["uniacid"];
if( !$uid || !$label_id ) 
{
	return $this->result(-1, "", array( ));
}
if( !$name && !$delete ) 
{
	return $this->result(-1, "", array( ));
}
$label = pdo_get("longbing_card_label", array( "uniacid" => $uniacid, "id" => $label_id ));
if( !$label ) 
{
	return $this->result(-2, "", array( ));
}
if( $delete ) 
{
	$result = pdo_delete("longbing_card_user_label", array( "lable_id" => $label_id, "staff_id" => $uid ));
	if( $result ) 
	{
		return $this->result(0, "", array( ));
	}
	return $this->result(-1, "", array( ));
}
$old_id = $label_id;
if( !$name ) 
{
	return $this->result(-1, "", array( ));
}
$check = pdo_get("longbing_card_label", array( "uniacid" => $uniacid, "name" => $name ));
if( $check ) 
{
	$new_id = $check["id"];
}
else 
{
	$time = time();
	$result = pdo_insert("longbing_card_label", array( "uniacid" => $uniacid, "name" => $name, "create_time" => $time, "update_time" => $time ));
	if( !$result ) 
	{
		return $this->result(-1, "", array( ));
	}
	$new_id = pdo_insertid();
}
$result = pdo_update("longbing_card_user_label", array( "lable_id" => $new_id ), array( "lable_id" => $old_id, "staff_id" => $uid ));
if( $result ) 
{
	return $this->result(0, "", array( ));
}
return $this->result(-1, "", array( ));
?>