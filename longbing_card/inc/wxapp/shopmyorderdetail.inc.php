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
	return $this->result(-1, "", array( ));
}
if( $info["pay_status"] == 0 && $info["order_status"] != 1 ) 
{
	$wechat = $_W["account"]["setting"]["payment"]["wechat"];
	$log = pdo_get("core_paylog", array( "tid" => $info["out_trade_no"] ));
	if( $log ) 
	{
		$appid = $_W["account"]["key"];
		$url = "https://api.mch.weixin.qq.com/pay/orderquery";
		$o = $log["uniontid"];
		$string = "appid=" . $appid . "&mch_id=" . $wechat["mchid"] . "&nonce_str=ec2316275641faa3aacf3cc599e8730f&out_trade_no=" . $o;
		$string .= "&key=" . $wechat["signkey"];
		$string = md5($string);
		$string = strtoupper($string);
		$data = "<xml>\r\n            <appid>" . $appid . "</appid>\r\n            <mch_id>" . $wechat["mchid"] . "</mch_id>\r\n            <nonce_str>ec2316275641faa3aacf3cc599e8730f</nonce_str>\r\n            <out_trade_no>" . $o . "</out_trade_no>\r\n            <sign>" . $string . "</sign>\r\n            </xml>";
		$xml = $this->curlPost($url, $data);
		if( $xml && strstr($xml, "SUCCESS") && strstr($xml, "支付成功") && strstr($xml, "OK") ) 
		{
			$info = pdo_update("longbing_card_shop_order", array( "pay_status" => 1 ), $where);
			$info = pdo_get("longbing_card_shop_order", $where);
			if( !$info ) 
			{
				return $this->result(-1, "", array( ));
			}
		}
	}
}
$sql = "SELECT a.id,a.order_id,a.goods_id,a.name,a.cover,a.number,a.price,a.spe_price_id,b.id,b.unit,b.is_self FROM " . tablename("longbing_card_shop_order_item") . " a LEFT JOIN " . tablename("longbing_card_goods") . " b ON a.goods_id = b.id WHERE a.order_id = " . $id . " && a.uniacid = " . $uniacid;
$goods_info = pdo_fetchall($sql);
foreach( $goods_info as $k => $v ) 
{
	$goods_info[$k]["cover_true"] = tomedia($v["cover"]);
	$spe_price = pdo_get("longbing_card_shop_spe_price", array( "id" => $v["spe_price_id"], "uniacid" => $uniacid ));
	$spe_id_1 = $spe_price["spe_id_1"];
	$arr = explode("-", $spe_id_1);
	$str = implode(",", $arr);
	if( strpos($str, ",") ) 
	{
		$str = "(" . $str . ")";
		$sql = "SELECT * FROM " . tablename("longbing_card_shop_spe") . " WHERE id IN " . $str;
	}
	else 
	{
		$sql = "SELECT * FROM " . tablename("longbing_card_shop_spe") . " WHERE id = " . $str;
	}
	$speList = pdo_fetchall($sql);
	$titles = "";
	foreach( $speList as $k2 => $v2 ) 
	{
		$titles .= "-" . $v2["title"];
	}
	$titles = trim($titles, "-");
	$goods_info[$k]["titles"] = $titles;
}
if( $info["type"] == 1 ) 
{
	$collage = pdo_get("longbing_card_shop_collage_list", array( "id" => $info["collage_id"] ));
	$info["collage_info"] = $collage;
	$users = pdo_getall("longbing_card_shop_user_collage", array( "collage_id" => $info["collage_id"] ), array( "user_id" ), "", array( "id asc" ));
	$ids = "";
	foreach( $users as $k2 => $v2 ) 
	{
		$ids .= "," . $v2["user_id"];
	}
	$ids = trim($ids, ",");
	if( 1 < count($users) ) 
	{
		$ids = "(" . $ids . ")";
		$sql = "SELECT id, nickName, `avatarUrl` FROM " . tablename("longbing_card_user") . " WHERE  uniacid = " . $uniacid . " && id in " . $ids;
	}
	else 
	{
		$sql = "SELECT id, nickName, `avatarUrl` FROM " . tablename("longbing_card_user") . " WHERE  uniacid = " . $uniacid . " && id = " . $ids;
	}
	$users = pdo_fetchall($sql);
	$info["users"] = array( );
	foreach( $users as $k2 => $v2 ) 
	{
		if( $v2["id"] == $collage["user_id"] ) 
		{
			$info["own"] = $v2;
		}
		else 
		{
			$info["users"][] = $v2;
		}
	}
}
$info["goods_info"] = $goods_info;
$order_overtime = 1800;
$info_config = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ), array( "order_overtime" ));
$order_overtime = $info_config["order_overtime"];
if( !$order_overtime ) 
{
	$order_overtime = 1800;
}
if( $info["pay_status"] == 0 ) 
{
	$left_time = $order_overtime - (time() - $info["create_time"]);
	$info["left_time"] = $left_time;
}
$user_info = pdo_get("longbing_card_user_info", array( "fans_id" => $info["to_uid"] ));
$user_info["avatar_true"] = tomedia($user_info["avatar"]);
$info["user_info"] = $user_info;
return $this->result(0, "", $info);
?>