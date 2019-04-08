<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$keyword = $_GPC["keyword"];
$sql = "SELECT count( a.id ) AS `count`,a.id,a.lable_id,a.update_time,b.name FROM " . tablename("longbing_card_user_label") . " a LEFT JOIN " . tablename("longbing_card_label") . " b ON a.lable_id = b.id WHERE a.staff_id = " . $uid . " GROUP BY lable_id";
if( $keyword ) 
{
	$keyword = "%" . $keyword . "%";
	$sql = "SELECT count( a.id ) AS `count`,a.id,a.lable_id,a.update_time,b.name FROM " . tablename("longbing_card_user_label") . " a LEFT JOIN " . tablename("longbing_card_label") . " b ON a.lable_id = b.id WHERE a.staff_id = " . $uid . " && b.name LIKE '" . $keyword . "' GROUP BY lable_id";
}
$list = pdo_fetchall($sql);
array_multisort(array_column($list, "count"), SORT_DESC, $list);
return $this->result(0, "", $list);
?>