<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$uid = $_GPC["user_id"];
$goods_id = $_GPC["goods_id"];
$list = pdo_getall("longbing_card_shop_user_collage", array( "user_id" => $uid, "uniacid" => $uniacid ));
$data = array( "all" => 0, "going" => 0, "suc" => 0, "fail" => 0 );
$data["all"] = count($list);
foreach( $list as $k => $v ) 
{
	if( $v["collage_status"] == 0 ) 
	{
		continue;
	}
	if( $v["collage_status"] == 1 ) 
	{
		$data["going"] += 1;
	}
	else 
	{
		if( $v["collage_status"] == 2 ) 
		{
			continue;
		}
		if( $v["collage_status"] == 3 ) 
		{
			$data["suc"] += 1;
		}
		else 
		{
			if( $v["collage_status"] == 4 ) 
			{
				$data["fail"] += 1;
			}
		}
	}
}
return $this->result(0, "", $data);
?>