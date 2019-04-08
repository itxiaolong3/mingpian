<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$to_uid = $_GPC["to_uid"];
$keyword = $_GPC["keyword"];
if( !$uid ) 
{
	return $this->result(-1, "未获取到用户", array( ));
}
$limit = array( 1, 10 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$total_page = 0;
$where = array( "uniacid" => $_W["uniacid"] );
if( $keyword ) 
{
	$keyword = "%" . $keyword . "%";
	$staffs = pdo_getall("longbing_card_user_info", array( "uniacid" => $_W["uniacid"], "status" => 1, "name like" => $keyword ));
	$staff_ids = array( );
	foreach( $staffs as $k => $v ) 
	{
		array_push($staff_ids, $v["fans_id"]);
	}
	if( !empty($staff_ids) ) 
	{
		if( count($staff_ids) == 1 ) 
		{
			$where["to_uid"] = $staff_ids[0];
		}
		else 
		{
			$where["to_uid in"] = $staff_ids;
		}
	}
	else 
	{
		$where["to_uid"] = 0;
	}
}
else 
{
	$where["to_uid !="] = $uid;
}
$company = pdo_getall("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
$where["uid"] = $uid;
$where["status"] = 1;
$cards = pdo_getslice("longbing_card_collection", $where, $limit, $count, array( ), "", array( "id desc" ));
$default = false;
if( empty($cards) && !$keyword ) 
{
	$list_card = pdo_fetchall("SELECT * FROM " . tablename("longbing_card_user_info") . " WHERE fans_id != 0 && uniacid = " . $_W["uniacid"] . " && status = 1 && is_default = 1 && is_staff = 1 ORDER BY top DESC");
	if( $this->redis_sup_v3 && !$keyword ) 
	{
		$redis_key = "longbing_cardlistv2_default_" . "_" . $_W["uniacid"] . "_" . $curr;
		$data = $this->redis_server_v3->get($redis_key);
		if( $data ) 
		{
			$data = json_decode($data, true);
			$data["from_redis"] = 1;
			return $this->result(0, "请求成功redis", $data);
		}
	}
	$default = true;
	if( empty($list_card) ) 
	{
		$data = array( "page" => $curr, "total_page" => 0, "list" => array( ), "company" => $company );
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
	$total_page = 1;
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
		if( $v["handover_name"] ) 
		{
			$cards[$k]["shareBy"] = "来自" . $v["handover_name"] . "的工作交接";
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
if( $i["is_staff"] && $curr == 1 ) 
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
$check_arr = array( );
$tmp_arr = array( );
foreach( $cards as $k => $v ) 
{
	if( in_array($v["to_uid"], $check_arr) ) 
	{
		continue;
	}
	array_push($check_arr, $v["to_uid"]);
	array_push($tmp_arr, $v);
}
foreach( $cards as $k => $v ) 
{
	$cards[$k]["userInfo"]["myCompany"] = array( );
	$cards[$k]["create_time2"] = date("Y-m-d H:i", $v["create_time"]);
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
$data = array( "page" => $curr, "total_page" => ceil($count / 10), "list" => $cards, "company" => $company );
if( $total_page ) 
{
	$data = array( "page" => $curr, "total_page" => $total_page, "list" => $cards, "company" => $company );
}
if( $this->redis_sup_v3 && !$keyword && $default ) 
{
	$redis_key = "longbing_cardlistv2_default_" . "_" . $_W["uniacid"] . "_" . $curr;
	$this->redis_server_v3->set($redis_key, json_encode($data));
	$this->redis_server_v3->EXPIRE($redis_key, 7200);
}
$data["where"] = $where;
return $this->result(0, "", $data);
?>