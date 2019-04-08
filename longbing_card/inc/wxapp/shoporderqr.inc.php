<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$id = $_GPC["id"];
$uniacid = $_W["uniacid"];
if( !$uid || !$id ) 
{
	return $this->result(-1, "请传入参数", array( ));
}
$time = time();
$order = pdo_get("longbing_card_shop_order", array( "id" => $id ));
if( !$order ) 
{
	return $this->result(-2, "未找到订单记录!", array( ));
}
if( $order["pay_status"] != 1 || $order["order_status"] != 0 || $order["address"] != "自提" ) 
{
	return $this->result(-2, "只有已支付的自提订单才能扫码核销", array( ));
}
$check_load = "gd";
if( extension_loaded($check_load) ) 
{
	include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/phpqrcode.php");
	$data = array( "order_id" => $id );
	$data = json_encode($data);
	if( !is_dir(ATTACHMENT_ROOT . "/" . "images") ) 
	{
		mkdir(ATTACHMENT_ROOT . "/" . "images");
	}
	if( !is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card") ) 
	{
		mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card");
	}
	if( !is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/") ) 
	{
		mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/");
	}
	$fileName = "/images/longbing_card/" . $_W["uniacid"] . "/order-" . $uid . "-" . $id . "write-off" . ".png";
	if( file_exists(ATTACHMENT_ROOT . $fileName) ) 
	{
		$size = @filesize(ATTACHMENT_ROOT . $fileName);
		if( $size < 51220 ) 
		{
			QRcode::png($data, ATTACHMENT_ROOT . $fileName, "L", 6, 1);
		}
	}
	else 
	{
		QRcode::png($data, ATTACHMENT_ROOT . $fileName, "L", 6, 1);
	}
	$path = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $fileName;
	return $this->result(0, "", array( "path" => $path ));
}
return $this->result(-2, "没有加载" . $check_load . "库,您不能使用相关" . $check_load . "操作类函数。请联系管理员", array( ));
?>