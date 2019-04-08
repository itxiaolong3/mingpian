<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$id = $_GPC["id"];
$pwd = $_GPC["pwd"];
if( !$uid || !$id || !$pwd ) 
{
	return $this->result(-1, "require", array( ));
}
$user = pdo_get("longbing_card_user", array( "id" => $uid ));
$config = pdo_get("longbing_card_config", array( "uniacid" => $uniacid ));
if( !$config || !$config["order_pwd"] ) 
{
	return $this->result(-1, "后台没有设置核销密码", array( ));
}
if( $pwd != $config["order_pwd"] ) 
{
	return $this->result(-1, "核销密码错误", array( ));
}
$order = pdo_get("longbing_card_shop_order", array( "id" => $id ));
if( !$order ) 
{
	return $this->result(-1, "没有找到订单", array( ));
}
if( $order["pay_status"] != 1 ) 
{
	return $this->result(-1, "订单未支付, 无法核销", array( ));
}
if( $order["order_status"] == 1 ) 
{
	return $this->result(-1, "订单已取消, 无法核销", array( ));
}
if( 2 < $order["order_status"] ) 
{
	return $this->result(-1, "订单已完成, 无法核销", array( ));
}
$result = pdo_update("longbing_card_shop_order", array( "order_status" => 3, "write_off_id" => $uid, "update_time" => time() ), array( "id" => $id ));
if( $result ) 
{
	return $this->result(0, "核销成功", array( "user" => $user ));
}
return $this->result(-1, "核销失败", array( ));
?>