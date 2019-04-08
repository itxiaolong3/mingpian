<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$record_id = $_GPC["record_id"];
$uniacid = $_W["uniacid"];
if( !$uid || !$record_id ) 
{
	return $this->result(-1, "请传入参数", array( ));
}
$time = time();
$coupon_record_check = pdo_get("longbing_card_coupon_record", array( "user_id" => $uid, "id" => $record_id ));
if( !$coupon_record_check ) 
{
	return $this->result(-2, "未找到该福包领取记录!", array( ));
}
if( $coupon_record_check["type"] != 2 ) 
{
	return $this->result(-2, "只有线下福包才能核销!", array( ));
}
if( $coupon_record_check["end_time"] < $time ) 
{
	return $this->result(-2, "福包已过期!", array( ));
}
$check_load = "gd";
if( extension_loaded($check_load) ) 
{
	include_once($_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/phpqrcode.php");
	$data = array( "user_id" => $uid, "record_id" => $record_id, "full" => $coupon_record_check["full"], "reduce" => $coupon_record_check["reduce"] );
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
	$fileName = "/images/longbing_card/" . $_W["uniacid"] . "/coupon-" . $uid . "-" . $record_id . ".png";
	if( file_exists(ATTACHMENT_ROOT . $fileName) ) 
	{
		$size = @filesize(ATTACHMENT_ROOT . $fileName);
		if( $size < 51220 ) 
		{
			QRcode::png($data, ATTACHMENT_ROOT . $fileName);
		}
	}
	else 
	{
		QRcode::png($data, ATTACHMENT_ROOT . $fileName);
	}
	$path = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $fileName;
	return $this->result(0, "", array( "path" => $path ));
}
return $this->result(-2, "没有加载" . $check_load . "库,您不能使用相关" . $check_load . "操作类函数。请联系管理员", array( ));
?>