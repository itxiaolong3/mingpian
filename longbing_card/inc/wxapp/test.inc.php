<?php  global $_GPC;
global $_W;
$phone = $_GPC["phone"];
$uniacid = $_W["uniacid"];
if( !$phone ) 
{
	return $this->result(-1, "请传入手机号", array( ));
}
$info = pdo_get("longbing_card_user_phone", array( "phone" => $phone ));
if( !$info ) 
{
	return $this->result(-1, "未找到手机号", array( ));
}
$user_id = $info["user_id"];
$start = 1549123200;
$end = 1549814399;
$limit = array( 1, 20 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$offset = ($curr - 1) * 15;
$sql = "SELECT a.create_time as a_create_time, b.avatarUrl, b.nickName FROM " . tablename("longbing_card_collection") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.uid = b.id WHERE a.create_time BETWEEN " . $start . " AND " . $end . " && a.uniacid = " . $uniacid . " && a.from_uid = " . $user_id . " && b.id > 0 GROUP BY a.uid LIMIT " . $offset . ", 15";
$list = pdo_fetchall($sql);
$count = pdo_fetchall("SELECT b.id FROM " . tablename("longbing_card_collection") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.uid = b.id WHERE a.create_time BETWEEN " . $start . " AND " . $end . " && a.uniacid = " . $uniacid . " && a.from_uid = " . $user_id . " && b.id > 0 GROUP BY a.uid");
$count = count($count);
$total_page = ceil($count / 15);
$data = array( "page" => $curr, "total_page" => $total_page, "count" => $count, "list" => $list );
return $this->result(0, "suc", $data);
?>