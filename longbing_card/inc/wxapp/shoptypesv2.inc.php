<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$this->checkOrderTime();
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
$to_uid = $_GPC["to_uid"];
if( $this->redis_sup_v3 && false ) 
{
	$redis_key = "longbing_card_shoptypes_" . $to_uid . "_" . $uniacid;
	$data = $this->redis_server_v3->get($redis_key);
	if( $data ) 
	{
		$data = json_decode($data, true);
		$data["from_redis"] = 1;
		return $this->result(0, "", $data);
	}
}
$check_myshop = $this->checkMyShop($uid);
$list = pdo_getall("longbing_card_shop_type", array( "uniacid" => $uniacid, "status" => 1 ), array( "id", "title", "pid", "top", "cover" ), "", array( "top desc", "id desc" ));
$data = array( );
foreach( $list as $k => $v ) 
{
	if( $v["pid"] == 0 ) 
	{
		$v["cover_true"] = tomedia($v["cover"]);
		$data[$v["id"]] = $v;
	}
}
foreach( $list as $k => $v ) 
{
	if( $v["pid"] != 0 ) 
	{
		$v["cover_true"] = tomedia($v["cover"]);
		if( isset($data[$v["pid"]]) ) 
		{
			$data[$v["pid"]]["sec"][] = $v;
		}
	}
}
array_multisort(array_column($data, "top"), SORT_DESC, $data);
$limit = array( 1, 10 );
foreach( $data as $k => $v ) 
{
	if( !isset($v["sec"]) ) 
	{
		$where = array( "uniacid" => $uniacid, "status" => 1, "type_p" => $v["id"] );
		if( $check_myshop != false ) 
		{
			$where["id in"] = $check_myshop;
		}
		$goods = pdo_getslice("longbing_card_goods", $where, array( 1, 9 ), $count, array( "id", "name", "cover", "top", "recommend", "price", "is_collage", "unit" ), "", array( "recommend desc", "top desc", "id desc" ));
		foreach( $goods as $k2 => $v2 ) 
		{
			$goods[$k2]["cover_true"] = tomedia($v2["cover"]);
		}
		$data[$k]["goods"] = $goods;
	}
}
$data2["shop_type"] = $data;
$data = $data2;
$where = array( "uniacid" => $uniacid, "status" => 1 );
if( $check_myshop != false ) 
{
	$where["id in"] = $check_myshop;
}
$list = pdo_getslice("longbing_card_goods", $where, $limit, $count, array( "id", "name", "cover", "top", "recommend", "price", "is_collage", "unit" ), "", array( "recommend desc", "top desc", "id desc" ));
foreach( $list as $index => $item ) 
{
	$list[$index]["trueCover"] = tomedia($item["cover"]);
}
$dataALl = array( "page" => 1, "total_page" => ceil($count / 10), "list" => $list );
$data["shop_all"] = $dataALl;
$companyList = pdo_getall("longbing_card_company", array( "status" => 1, "uniacid" => $uniacid ));
if( !$companyList ) 
{
	$companyList = array( array( ) );
}
$company = $companyList[0];
if( $to_uid ) 
{
	$user_info = pdo_get("longbing_card_user_info", array( "fans_id" => $to_uid ));
	if( $user_info ) 
	{
		foreach( $companyList as $k => $v ) 
		{
			if( $v["id"] == $user_info["company_id"] ) 
			{
				$company = $v;
				break;
			}
		}
	}
}
$company["logo"] = tomedia($company["logo"]);
$company["desc"] = tomedia($company["desc"]);
$data["shop_company"] = $company;
if( $this->redis_sup_v3 ) 
{
	$redis_key = "longbing_card_shoptypes_" . $to_uid . "_" . $uniacid;
	$this->redis_server_v3->set($redis_key, json_encode($data));
	$this->redis_server_v3->EXPIRE($redis_key, 30 * 60);
}
$this->insertView($uid, $to_uid, 1, $_W["uniacid"]);
return $this->result(0, "", $data);
?>