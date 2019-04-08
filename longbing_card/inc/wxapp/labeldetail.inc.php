<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$label_id = $_GPC["label_id"];
$keyword = $_GPC["keyword"];
$uniacid = $_W["uniacid"];
if( !$uid || !$label_id ) 
{
	return $this->result(-1, "", array( ));
}
$label = pdo_get("longbing_card_label", array( "uniacid" => $uniacid, "id" => $label_id ));
if( !$label ) 
{
	return $this->result(-2, "label not found", array( ));
}
$limit = array( 1, 15 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
if( $keyword ) 
{
	$list = pdo_getall("longbing_card_user_label", array( "staff_id" => $uid, "uniacid" => $uniacid, "lable_id" => $label_id ), array( "id", "user_id" ), "", "id desc");
	$curr = 1;
	$count = count($list);
}
else 
{
	$list = pdo_getslice("longbing_card_user_label", array( "staff_id" => $uid, "uniacid" => $uniacid, "lable_id" => $label_id ), $limit, $count, array( "id", "user_id" ), "", "id desc");
}
$user_ids = array( );
foreach( $list as $index => $item ) 
{
	array_push($user_ids, $item["user_id"]);
}
$user_ids = array_unique($user_ids);
if( !empty($list) ) 
{
	if( $keyword ) 
	{
		$users_info = pdo_getall("longbing_card_user", array( "id in" => $user_ids, "nickName like" => "%" . $keyword . "%" ), array( "id", "avatarUrl", "nickName" ));
		$tmp = array( );
		foreach( $list as $index => $item ) 
		{
			foreach( $users_info as $k => $v ) 
			{
				if( $item["user_id"] == $v["id"] ) 
				{
					$item["user"] = $v;
					array_push($tmp, $item);
					break;
				}
			}
		}
		$list = $tmp;
	}
	else 
	{
		$users_info = pdo_getall("longbing_card_user", array( "id in" => $user_ids ), array( "id", "avatarUrl", "nickName" ));
	}
}
if( !empty($list) ) 
{
	foreach( $list as $index => $item ) 
	{
		foreach( $users_info as $k => $v ) 
		{
			if( $item["user_id"] == $v["id"] ) 
			{
				$list[$index]["user"] = $v;
				break;
			}
		}
		if( !isset($list[$index]["user"]) ) 
		{
			unset($list[$index]);
			continue;
		}
		$last = pdo_getall("ims_longbing_card_count", array( "user_id" => $item["user_id"], "to_uid" => $uid ), array( "id", "type", "sign", "target" ), ["id desc"]);
		$list[$index]["count"] = count($last);
		$list[$index]["last"] = array( );
		if( !empty($last) ) 
		{
			$last = $last[0];
			list($way, $detail) = $this->getRadarDetail($last["sign"], $last["type"], $last["target"]);
			if( $detail ) 
			{
				$detail = "：" . $detail;
			}
			$list[$index]["last"] = $way . $detail;
		}
		if( isset($list[$index]["user"]) ) 
		{
			$start = pdo_get("longbing_card_start", array( "staff_id" => $uid, "user_id" => $list[$index]["user"]["id"] ));
			$list[$index]["user"]["start"] = 0;
			if( $start ) 
			{
				$list[$index]["user"]["start"] = 1;
			}
			$last = pdo_get("longbing_card_count", array( "to_uid" => $uid, "user_id" => $list[$index]["user"]["id"] ), array( ), "", array( "id desc" ));
			$list[$index]["user"]["last"] = ($last ? $last["create_time"] : 0);
			$phone = pdo_get("longbing_card_user_phone", array( "user_id" => $list[$index]["user"]["id"] ));
			$list[$index]["user"]["phone"] = ($phone ? $phone["phone"] : 0);
			$mark = pdo_get("longbing_card_user_mark", array( "user_id" => $list[$index]["user"]["id"], "staff_id" => $uid ));
			if( !$mark ) 
			{
				$list[$index]["user"]["mark"] = 0;
			}
			else 
			{
				$list[$index]["user"]["mark"] = $mark["mark"];
			}
		}
	}
}
$tmp = array( );
foreach( $list as $index => $item ) 
{
	array_push($tmp, $item);
}
$data = array( "page" => $curr, "total_page" => ceil($count / 15), "total_count" => $count, "list" => $tmp );
if( $keyword ) 
{
	$data["total_page"] = 1;
}
return $this->result(0, "", $data);
?>