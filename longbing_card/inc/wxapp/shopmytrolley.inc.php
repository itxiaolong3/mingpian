<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$sql = "SELECT a.id,a.spe_price_id,a.number,a.goods_id,b.name,b.cover,b.price,b.freight,b.unit,b.is_self FROM " . tablename("longbing_card_shop_trolley") . " a LEFT JOIN " . tablename("longbing_card_goods") . " b ON a.goods_id = b.id WHERE a.user_id = " . $uid . " && a.status = 1";
$list = pdo_fetchall($sql);
$freightArr = array( );
$price = 0;
$total_price = 0;
$total_freight = 0;
foreach( $list as $k => $v ) 
{
	$spe_price = pdo_get("longbing_card_shop_spe_price", array( "id" => $v["spe_price_id"], "uniacid" => $uniacid ));
	if( empty($spe_price) ) 
	{
		return $this->result(-1, "", array( ));
	}
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
	$tmp_price = sprintf("%.2f", $v["number"] * $spe_price["price"]);
	$list[$k]["cover_true"] = tomedia($v["cover"]);
	$list[$k]["spe"] = $titles;
	$list[$k]["price"] = $tmp_price;
	$list[$k]["price2"] = $spe_price["price"];
	$list[$k]["stock"] = $spe_price["stock"];
	$price += sprintf("%.2f", $spe_price["price"] * $v["number"]);
	if( !in_array($v["goods_id"], $freightArr) ) 
	{
		$total_freight += $v["freight"];
		array_push($freightArr, $v["goods_id"]);
	}
}
$total_price = $price + $total_freight;
$data = array( "price" => $price, "total_freight" => $total_freight, "total_price" => $total_price, "list" => $list );
return $this->result(0, "", $data);
?>