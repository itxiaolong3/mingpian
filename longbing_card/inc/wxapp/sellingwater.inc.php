<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$user_id = $_GPC["user_id"];
$type = intval($_GPC["type"]);
$sort = intval($_GPC["desc"]);
$uniacid = $_W["uniacid"];
if( !$user_id ) 
{
	return $this->result(-1, "", array( ));
}
if( $type != 1 && $type != 2 ) 
{
	$type = 1;
}
if( $sort == 2 ) 
{
	$sort = "asc";
}
else 
{
	$sort = "desc";
}
$limit = array( 1, 10 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$where = array( "status" => 1, "price >" => 0, "uniacid" => $uniacid );
if( $type == 1 ) 
{
	$list = pdo_getslice("longbing_card_goods", $where, $limit, $count, array( ), "", array( "sale_count " . $sort, "id desc" ));
}
else 
{
	if( $type == 2 ) 
	{
		$list = pdo_getslice("longbing_card_goods", $where, $limit, $count, array( ), "", array( "price " . $sort, "id desc" ));
	}
	else 
	{
		$list = array( );
	}
}
$config = pdo_get("longbing_card_config", array( "uniacid" => $uniacid ));
$first_extract = 0;
if( $config && isset($config["first_extract"]) && $config["first_extract"] ) 
{
	$first_extract = $config["first_extract"];
}
foreach( $list as $index => $item ) 
{
	$list[$index]["cover"] = tomedia($item["cover"]);
	$list[$index]["create_time2"] = date("Y-m-d H:i:s", $item["create_time"]);
	$list[$index]["create_time3"] = date("Y-m-d H:i", $item["create_time"]);
	$list[$index]["create_time4"] = date("Y-m-d", $item["create_time"]);
	if( $item["extract"] == 0 && $first_extract ) 
	{
		$list[$index]["extract"] = $first_extract;
	}
}
$data = array( "page" => $curr, "total_page" => intval(ceil($count / 10)), "list" => $list, "total_count" => $count );
return $this->result(0, "", $data);
?>