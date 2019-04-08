<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uniacid = $_W["uniacid"];
$check_is_boss = $this->check_is_boss($_GPC["user_id"]);
if( !$check_is_boss ) 
{
	return $this->result(-1, "", array( ));
}
$default = array( "client" => 0, "charm" => 0, "interaction" => 0, "product" => 0, "website" => 0, "active" => 0 );
$max = array( );
$staff_list = pdo_getall("longbing_card_user", array( "uniacid" => $uniacid, "is_staff" => 1 ), array( "id", "nickName", "avatarUrl" ));
foreach( $staff_list as $k => $v ) 
{
	$info = pdo_get("longbing_card_user_info", array( "uniacid" => $uniacid, "fans_id" => $v["id"] ), array( "name", "avatar", "phone", "job_id" ));
	$job = pdo_get("longbing_card_job", array( "id" => $info["job_id"] ));
	$info["job_name"] = (!empty($job) ? $job["name"] : "");
	$total = 0;
	$value = $this->bossGetAiValue($v["id"]);
	$tmpValue = array( );
	foreach( $value as $k2 => $v2 ) 
	{
		if( $max[$k2] < $v2["value"] ) 
		{
			$max[$k2] = $v2["value"];
		}
		$total += $v2["value"];
		$v2["title_en"] = $k2;
		array_push($tmpValue, $v2);
	}
	$staff_list[$k]["value"] = $value;
	$staff_list[$k]["value_2"] = $tmpValue;
	$staff_list[$k]["total"] = $total;
	$info["avatar"] = tomedia($info["avatar"]);
	$staff_list[$k]["info"] = $info;
}
array_multisort(array_column($staff_list, "total"), SORT_DESC, $staff_list);
$limit = array( 1, 10 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$offset = ($curr - 1) * 10;
$array = array_slice($staff_list, $offset, 10);
$com = pdo_get("longbing_card_company", array( "uniacid" => $uniacid ));
$com["logo"] = $this->transImage($com["logo"]);
$data = array( "list" => $array, "max" => $max, "com" => $com, "page" => $curr, "total_page" => ceil(count($staff_list) / 10) );
return $this->result(0, "", $data);
?>