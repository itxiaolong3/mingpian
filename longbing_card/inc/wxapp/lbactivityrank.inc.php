<?php  global $_GPC;
global $_W;
$phone = $_GPC["phone"];
$uniacid = $_W["uniacid"];
$user_id = NULL;
if( $phone ) 
{
	$info = pdo_get("longbing_card_user_phone", array( "phone" => $phone, "uniacid" => $uniacid ));
	if( $info ) 
	{
		$user_id = $info["user_id"];
	}
}
$start = 1549123200;
$end = 1550591999;
$limit = array( 1, 20 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$rank = pdo_fetchall("SELECT count(a.pid) AS `count`, a.pid, b.id, b.nickName, b.avatarUrl FROM ims_longbing_card_user a \r\nLEFT JOIN ims_longbing_card_user b ON a.pid = b.id \r\nWHERE  a.pid != 0 && a.uniacid = 4 && b.id > 0 && \r\n a.create_time BETWEEN 1549123200 AND 1550591999 GROUP BY a.pid ORDER BY `count` DESC");
$ranking = 0;
$limit = 0;
$count = count($rank);
$rank = array_slice($rank, 0, 50);
$my_count = 0;
$my_name = "";
if( $user_id ) 
{
	foreach( $rank as $index => $item ) 
	{
		if( $item["id"] == $user_id ) 
		{
			if( $index != 0 ) 
			{
				$limit = $rank[$index - 1]["count"] - $rank[$index]["count"];
				$limit = ($limit == 0 ? 1 : $limit);
			}
			$ranking = $index + 1;
			$my_count = $item["count"];
			$my_name = $item["nickName"];
		}
	}
}
$user_ids = "";
foreach( $rank as $index => $item ) 
{
	$user_ids .= "," . $item["pid"];
}
$user_ids = trim($user_ids, ",");
$user_ids = "(" . $user_ids . ")";
$phones = pdo_fetchall("SELECT * FROM ims_longbing_card_user_phone WHERE user_id in " . $user_ids);
$phones_tmp = array( );
foreach( $phones as $index => $item ) 
{
	$phones_tmp[$item["user_id"]] = $item["phone"];
}
foreach( $rank as $index => $item ) 
{
	$num = (isset($phones_tmp[$item["id"]]) ? $phones_tmp[$item["id"]] : "18888888888");
	$num = ($num ? $num : "18888888888");
	$num = substr_replace($num, "****", 3, 4);
	$rank[$index]["phone"] = $num;
	$rank[$index]["rank_this"] = $index + 1;
}
$rank = array_slice($rank, 0, 50);
$data = array( "count" => $my_count, "ranking" => $ranking, "my_name" => $my_name, "limit" => $limit, "list" => $rank );
return $this->result(0, "suc", $data);
?>