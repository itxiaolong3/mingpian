<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$to_uid = $_GPC["to_uid"];
pdo_delete("longbing_card_collection", array( "to_uid" => 0 ));
pdo_delete("longbing_card_user_info", array( "fans_id" => 0 ));
if( !$uid ) 
{
	return $this->result(-1, "", array( ));
}
$limit = array( 1, $this->limit );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$where = array( "uniacid" => $_W["uniacid"] );
if( $this->redis_sup ) 
{
	$redis_key = "longbing_card_companylist_" . $_W["uniacid"];
	$company = $this->redis_server->get($redis_key);
	if( $company ) 
	{
		$company = json_decode($company, true);
		$company[0]["from_redis"] = 1;
	}
}
$company = pdo_getall("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
$where["uid"] = $uid;
$where["to_uid !="] = $uid;
$where["status"] = 1;
$cards = pdo_getslice("longbing_card_collection", $where, $limit, $count, array( ), "", array( "id desc" ));
if( empty($cards) ) 
{
	$list_card = pdo_getall("longbing_card_user_info", array( "fans_id !=" => 0, "uniacid" => $_W["uniacid"], "status" => 1, "is_default" => 1 ), array( ), "", array( "top desc" ));
	if( empty($list_card) ) 
	{
		$data = array( "page" => $curr, "total_page" => ceil($count / $this->limit), "list" => array( ), "company" => $company );
		return $this->result(0, "", $data);
	}
	foreach( $list_card as $k => $v ) 
	{
		$user = $v;
		$user["avatar"] = tomedia($user["avatar"]);
		$job = pdo_get("longbing_card_job", array( "id" => $v["job_id"] ));
		$user["job_name"] = $job["name"];
		if( $v["from_uid"] ) 
		{
			$userFrom = pdo_get("longbing_card_user_info", array( "fans_id" => $v["from_uid"] ));
			$cards[$k]["shareBy"] = $userFrom["name"];
		}
		$info = pdo_get("longbing_card_user", array( "id" => $v["fans_id"] ));
		$i = $info;
		$message = pdo_getall("longbing_card_message", array( "user_id" => $v["fans_id"], "target_id" => $uid, "uniacid" => $_W["uniacid"], "status" => 1 ));
		$cards[$k]["userInfo"] = $user;
		$cards[$k]["info"] = $info;
		$cards[$k]["type"] = "no";
		$cards[$k]["message"] = count($message);
		$cards[$k]["create_time"] = time();
		$cards[$k]["shareBy"] = "搜索";
	}
	$count = count($list_card);
}
else 
{
	$i = pdo_get("longbing_card_user", array( "id" => $uid ));
	if( $i["is_staff"] && $curr == 1 ) 
	{
		$card_self = pdo_getall("longbing_card_collection", array( "uid" => $uid, "to_uid" => $uid ));
		if( $card_self ) 
		{
			$card_tmp[0] = $card_self[0];
			$cards = array_merge($card_tmp, $cards);
		}
	}
	foreach( $cards as $k => $v ) 
	{
		$user = pdo_get("longbing_card_user_info", array( "fans_id" => $v["to_uid"], "uniacid" => $_W["uniacid"] ));
		$user["avatar"] = tomedia($user["avatar"]);
		$images = $user["images"];
		$images = trim($images, ",");
		$images = explode(",", $images);
		$tmp = array( );
		foreach( $images as $k2 => $v2 ) 
		{
			$tmpUrl = tomedia($v2);
			array_push($tmp, $tmpUrl);
		}
		$user["images"] = $tmp;
		$job = pdo_get("longbing_card_job", array( "id" => $user["job_id"] ));
		$user["job_name"] = $job["name"];
		$cards[$k]["userInfo"] = $user;
		$cards[$k]["shareBy"] = "";
		$cards[$k]["type"] = "yes";
		$message = pdo_getall("longbing_card_message", array( "user_id" => $v["to_uid"], "target_id" => $uid, "uniacid" => $_W["uniacid"], "status" => 1 ));
		if( $v["is_qr"] ) 
		{
			$cards[$k]["shareBy"] = "扫码";
		}
		if( $v["is_group"] ) 
		{
			$cards[$k]["shareBy"] = "群分享";
		}
		if( $v["type"] == 1 ) 
		{
			$cards[$k]["shareBy"] = "自定义码";
		}
		if( $v["type"] == 2 ) 
		{
			$cards[$k]["shareBy"] = "产品分享";
		}
		if( $v["type"] == 3 ) 
		{
			$cards[$k]["shareBy"] = "动态分享";
		}
		$cards[$k]["message"] = count($message);
		if( $v["from_uid"] ) 
		{
			$userFrom = pdo_get("longbing_card_user_info", array( "fans_id" => $v["from_uid"] ));
			$cards[$k]["shareBy"] = $userFrom["name"];
		}
	}
}
$i = pdo_get("longbing_card_user", array( "id" => $uid ));
if( $i["is_staff"] ) 
{
	$cardsTmp = array( );
	foreach( $cards as $k => $v ) 
	{
		if( $v["to_uid"] == $uid ) 
		{
			array_push($cardsTmp, $v);
			break;
		}
	}
	foreach( $cards as $k => $v ) 
	{
		if( $v["to_uid"] != $uid ) 
		{
			array_push($cardsTmp, $v);
		}
	}
	$cards = $cardsTmp;
}
foreach( $cards as $k => $v ) 
{
	$cards[$k]["userInfo"]["myCompany"] = array( );
	$cards[$k]["create_time2"] = date("Y-m-d H:i:s", $v["create_time"]);
	if( $v["userInfo"]["company_id"] ) 
	{
		foreach( $company as $k2 => $v2 ) 
		{
			if( $v["userInfo"]["company_id"] == $v2["id"] ) 
			{
				$cards[$k]["userInfo"]["myCompany"] = $v2;
			}
		}
	}
}
$data = array( "page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $cards, "company" => $company );
return $this->result(0, "", $data);
?>