<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
$to_uid = $_GPC["to_uid"];
if( $this->redis_sup ) 
{
	$redis_key = "longbing_card_shoptypes_" . $to_uid . "_" . $uniacid;
	$data = $this->redis_server->get($redis_key);
	if( $data ) 
	{
		$data = json_decode($data, true);
		$data["from_redis"] = 1;
		return $this->result(0, "", $data);
	}
}
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
		$goods = pdo_getslice("longbing_card_goods", array( "uniacid" => $uniacid, "status" => 1, "type_p" => $v["id"] ), array( 1, 9 ), $count, array( "id", "name", "cover", "top", "recommend", "price", "is_collage" ), "", array( "recommend desc", "top desc", "id desc" ));
		foreach( $goods as $k2 => $v2 ) 
		{
			$goods[$k2]["cover_true"] = tomedia($v2["cover"]);
		}
		$data[$k]["goods"] = $goods;
	}
}
if( $this->redis_sup ) 
{
	$redis_key = "longbing_card_shoptypes_" . $to_uid . "_" . $uniacid;
	$this->redis_server->set($redis_key, json_encode($data));
	$this->redis_server->EXPIRE($redis_key, 30 * 60);
}
return $this->result(0, "", $data);
?>