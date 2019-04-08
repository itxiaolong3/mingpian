<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$keyword = $_GPC["keyword"];
$uniacid = $_W["uniacid"];
if( !$uid ) 
{
	return $this->result(-1, "", array( ));
}
$curr = 1;
$len = 15;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$start = ($curr - 1) * $len;
if( $keyword ) 
{
	$keyword = "%" . $keyword . "%";
	$list = pdo_fetchall("SELECT a.id,a.user_id,b.nickName,b.avatarUrl FROM " . tablename("longbing_card_start") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.user_id = b.id WHERE a.staff_id = " . $uid . " && b.nickName LIKE '" . $keyword . "' ORDER BY a.id DESC LIMIT " . $start . ", " . $len);
}
else 
{
	$list = pdo_fetchall("SELECT a.id,a.user_id,b.nickName,b.avatarUrl FROM " . tablename("longbing_card_start") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.user_id = b.id WHERE a.staff_id = " . $uid . " ORDER BY a.id DESC LIMIT " . $start . ", " . $len);
}
$count = pdo_fetch("SELECT COUNT(*) as count_start FROM " . tablename("longbing_card_start") . " WHERE staff_id = " . $uid);
foreach( $list as $index => $item ) 
{
	$last = pdo_getall("ims_longbing_card_count", array( "user_id" => $item["user_id"], "to_uid" => $uid ), array( "id", "type", "sign", "target" ), ["id desc"]);
	$list[$index]["count"] = count($last);
	$list[$index]["user"]["last"] = array( );
	if( !empty($last) ) 
	{
		$last = $last[0];
		list($way, $detail) = $this->getRadarDetail($last["sign"], $last["type"], $last["target"]);
		if( $detail ) 
		{
			$detail = "：" . $detail;
		}
		$list[$index]["user"]["last_way"] = $way . $detail;
		$list[$index]["last"] = $way . $detail;
	}
	$list[$index]["user"]["nickName"] = $item["nickName"];
	$list[$index]["user"]["avatarUrl"] = $item["avatarUrl"];
	$start = pdo_get("longbing_card_start", array( "staff_id" => $uid, "user_id" => $item["user_id"] ));
	$list[$index]["user"]["start"] = 0;
	if( $start ) 
	{
		$list[$index]["user"]["start"] = 1;
	}
	$last = pdo_get("longbing_card_count", array( "to_uid" => $uid, "user_id" => $item["user_id"] ), array( ), "", array( "id desc" ));
	$list[$index]["user"]["last"] = ($last ? $last["create_time"] : 0);
	$phone = pdo_get("longbing_card_user_phone", array( "user_id" => $item["user_id"] ));
	$list[$index]["user"]["phone"] = ($phone ? $phone["phone"] : 0);
	$mark = pdo_get("longbing_card_user_mark", array( "user_id" => $item["user_id"], "staff_id" => $uid ));
	if( !$mark ) 
	{
		$list[$index]["user"]["mark"] = 0;
	}
	else 
	{
		$list[$index]["user"]["mark"] = $mark["mark"];
	}
}
$data = array( "page" => $curr, "total_page" => ceil($count["count_start"] / 15), "total_count" => $count["count_start"], "list" => $list );
return $this->result(0, "", $data);
?>