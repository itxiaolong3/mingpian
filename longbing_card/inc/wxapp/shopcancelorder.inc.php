<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$id = $_GPC["id"];
if( !$uid || !$id ) 
{
	return $this->result(-1, "", array( ));
}
$where = array( "uniacid" => $uniacid, "user_id" => $uid, "id" => $id );
$info = pdo_get("longbing_card_shop_order", $where);
if( !$info ) 
{
	return $this->result(-1, "未找到订单信息", array( ));
}
if( $info["pay_status"] != 0 ) 
{
	return $this->result(-1, "只能取消未支付的订单", array( ));
}
$result = pdo_update("longbing_card_shop_order", array( "order_status" => 1 ), $where);
if( !$result ) 
{
	return $this->result(-1, "取消订单失败2", array( $result ));
}
if( $info["type"] == 1 ) 
{
	$check = pdo_get("longbing_card_shop_collage_list", array( "id" => $info["collage_id"] ));
	if( $check ) 
	{
		$check["user_id"] = $uid;
		if( $check["user_id"] ) 
		{
			pdo_delete("longbing_card_shop_collage_list", array( "id" => $info["collage_id"] ));
		}
		else 
		{
			pdo_update("longbing_card_shop_collage_list", array( "left_number +=" => 1 ), array( "id" => $info["collage_id"] ));
		}
		pdo_delete("longbing_card_shop_user_collage", array( "collage_id" => $info["collage_id"], "user_id" => $uid ));
	}
}
return $this->result(0, "", array( ));
?>