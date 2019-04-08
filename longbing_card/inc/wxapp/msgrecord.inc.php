<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$limit = array( 1, 10 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$list = pdo_getslice("longbing_card_group_sending", array( "staff_id" => $uid ), $limit, $count, array( "id", "remark", "content" ), "", "id desc");
foreach( $list as $index => $item ) 
{
	$labels = pdo_getall("longbing_card_label", array( "id in" => explode(",", $item["remark"]) ), array( "id", "name" ));
	$list[$index]["labels_name"] = "";
	foreach( $labels as $k => $v ) 
	{
		$list[$index]["labels_name"] .= $v["name"] . "，";
	}
	$list[$index]["labels_name"] = trim($list[$index]["labels_name"], "，");
	$list[$index]["labels"] = $labels;
	$list[$index]["label_count"] = count(explode(",", $item["remark"]));
}
$data = array( "page" => $curr, "total_page" => ceil($count / 10), "list" => $list, "total_count" => $count );
return $this->result(0, "", $data);
?>