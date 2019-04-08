<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$scene = $_GPC["scene"];
if( !$scene ) 
{
	$scene = 0;
}
$time = time();
$to_uid = $_GPC["to_uid"];
if( !$uid || !$to_uid ) 
{
	return $this->result(-1, "", array( ));
}
$data = array( "user_id" => $uid, "to_uid" => $to_uid, "type" => 2, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "praise", "scene" => $_GPC["scene"], "create_time" => $time, "update_time" => $time );
if( $this->redis_sup_v3 ) 
{
	$redis_key = "longbing_cardsv5_" . $to_uid . "_" . $_W["uniacid"];
	$data2 = $this->redis_server_v3->get($redis_key);
	if( $data2 ) 
	{
		$data2 = json_decode($data2, true);
		$data = array_merge($data, $data2);
		$data["from_redis"] = 1;
		return $this->result(0, "", $data);
	}
}
pdo_delete("longbing_card_collection", array( "to_uid" => 0 ));
pdo_delete("longbing_card_user_info", array( "fans_id" => 0 ));
if( $uid == $to_uid ) 
{
	$check_is_staff = pdo_get("longbing_card_user", array( "id" => $uid, "uniacid" => $_W["uniacid"] ));
	if( empty($check_is_staff) || $check_is_staff["is_staff"] != 1 ) 
	{
		return $this->result(-1, "", array( ));
	}
}
$from_uid = 0;
if( isset($_GPC["from_id"]) ) 
{
	$from_uid = $_GPC["from_id"];
}
if( !$to_uid ) 
{
	$have = pdo_get("longbing_card_collection", array( "uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"] ));
	if( empty($have) ) 
	{
		$insert_data = array( "uniacid" => $_W["uniacid"], "from_uid" => $from_uid, "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene, "is_qr" => ($_GPC["is_qr"] ? $_GPC["is_qr"] : 0), "openGId" => ($_GPC["openGId"] ? $_GPC["openGId"] : ""), "is_group" => ($_GPC["is_group"] ? $_GPC["is_group"] : ""), "type" => ($_GPC["type"] ? $_GPC["type"] : ""), "target_id" => ($_GPC["target_id"] ? $_GPC["target_id"] : "") );
		pdo_insert("longbing_card_collection", $insert_data);
	}
}
else 
{
	$have = pdo_get("longbing_card_collection", array( "uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"] ));
	if( empty($have) ) 
	{
		$insert_data = array( "uniacid" => $_W["uniacid"], "from_uid" => $from_uid, "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene, "is_qr" => ($_GPC["is_qr"] ? $_GPC["is_qr"] : 0), "openGId" => ($_GPC["openGId"] ? $_GPC["openGId"] : ""), "is_group" => ($_GPC["is_group"] ? $_GPC["is_group"] : ""), "type" => ($_GPC["type"] ? $_GPC["type"] : ""), "target_id" => ($_GPC["target_id"] ? $_GPC["target_id"] : "") );
		pdo_insert("longbing_card_collection", $insert_data);
	}
	else 
	{
		pdo_update("longbing_card_collection", array( "to_uid" => $to_uid, "scene" => $scene, "status" => 1 ), array( "id" => $have["id"] ));
	}
}
$check = pdo_get("longbing_card_user_info", array( "fans_id" => $to_uid, "uniacid" => $_W["uniacid"] ));
if( !$check || empty($check) ) 
{
	return $this->result(-1, "", array( ));
}
if( $check["company_id"] ) 
{
	$com = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "id" => $check["company_id"], "status" => 1 ));
	if( !$com ) 
	{
		$com = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
	}
	$com["logo"] = $this->transImage($com["logo"]);
	$check["myCompany"] = $com;
}
else 
{
	$com = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
	$com["logo"] = $this->transImage($com["logo"]);
	$check["myCompany"] = $com;
}
if( 18 < mb_strlen($check["myCompany"]["addr"], "utf8") ) 
{
	$check["myCompany"]["addrMore"] = mb_substr($check["myCompany"]["addr"], 0, 20, "UTF-8") . "...";
}
else 
{
	$check["myCompany"]["addrMore"] = $check["myCompany"]["addr"];
}
$info = $check;
if( $info["avatar"] ) 
{
	$tmp = $info["avatar"];
	$info["avatar_2"] = tomedia($tmp);
	$info["avatar"] = $this->transImage($info["avatar_2"]);
}
if( $info["my_video"] ) 
{
	$info["my_video"] = tomedia($info["my_video"]);
}
if( $info["my_video_cover"] ) 
{
	$info["my_video_cover"] = tomedia($info["my_video_cover"]);
}
$info["voice"] = tomedia($info["voice"]);
$images = $info["images"];
$images = trim($images, ",");
$images = explode(",", $images);
$tmp = array( );
foreach( $images as $k2 => $v2 ) 
{
	$tmpUrl = tomedia($v2);
	array_push($tmp, $tmpUrl);
}
$info["images"] = $tmp;
$job = pdo_get("longbing_card_job", array( "id" => $info["job_id"], "uniacid" => $_W["uniacid"] ));
$info["job_name"] = $job["name"];
$short_name_tmp = ($info["myCompany"]["short_name"] ? $info["myCompany"]["short_name"] : $info["myCompany"]["name"]);
if( $info["share_text"] ) 
{
	$info["share_text"] = str_replace("\$company", $short_name_tmp, $info["share_text"]);
	$info["share_text"] = str_replace("\$job", $info["job_name"], $info["share_text"]);
	$info["share_text"] = str_replace("\$name", $info["name"], $info["share_text"]);
}
else 
{
	$info["share_text"] = "您好，我是" . $short_name_tmp . "的" . $info["job_name"] . $info["name"] . "，请惠存。";
}
$data["info"] = $info;
$sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 2 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " && `user_id` != " . $to_uid . " GROUP BY user_id";
$count = pdo_fetchall($sql);
$data["peoples"] = count($count);
$sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 3 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " GROUP BY user_id";
$count = pdo_fetchall($sql);
$data["thumbs_up"] = count($count);
$sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 4 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " GROUP BY user_id";
$count = pdo_fetchall($sql);
$data["share"] = count($count);
$isT = pdo_get("longbing_card_count", array( "type" => 1, "user_id" => $uid, "to_uid" => $to_uid, "sign" => "praise" ));
$isT2 = pdo_get("longbing_card_count", array( "type" => 3, "user_id" => $uid, "to_uid" => $to_uid, "sign" => "praise" ));
if( $isT ) 
{
	$data["voiceThumbs"] = 1;
}
else 
{
	$data["voiceThumbs"] = 0;
}
if( $isT2 ) 
{
	$data["isThumbs"] = 1;
}
else 
{
	$data["isThumbs"] = 0;
}
$info = pdo_get("longbing_card_user", array( "uniacid" => $_W["uniacid"], "id" => $uid ));
if( $info && $info["is_staff"] ) 
{
	$data["is_staff"] = 1;
	$data["is_boss"] = $info["is_boss"];
}
else 
{
	$data["is_staff"] = 0;
}
$extension = pdo_getall("longbing_card_extension", array( "user_id" => $to_uid ), array( "goods_id" ));
if( empty($extension) ) 
{
	$data["goods"] = array( );
}
else 
{
	$ids = array( );
	foreach( $extension as $k => $v ) 
	{
		array_push($ids, $v["goods_id"]);
	}
	$ids = implode(",", $ids);
	if( 1 < count($extension) ) 
	{
		$ids = "(" . $ids . ")";
		$sql = "SELECT id,`name`,cover,price,status FROM " . tablename("longbing_card_goods") . " WHERE id IN " . $ids . " && status = 1 ORDER BY top DESC";
	}
	else 
	{
		$sql = "SELECT id,`name`,cover,price,status FROM " . tablename("longbing_card_goods") . " WHERE id = " . $ids . " && status = 1 ORDER BY top DESC";
	}
	$goods = pdo_fetchall($sql);
	foreach( $goods as $k => $v ) 
	{
		if( $v["status"] == 1 ) 
		{
			$goods[$k]["cover"] = tomedia($v["cover"]);
		}
	}
	$data["goods"] = $goods;
}
$data["peoplesInfo"] = array( );
$view_count = pdo_fetchall("SELECT id, user_id FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $to_uid . " && user_id != " . $to_uid . " ORDER BY id DESC LIMIT 100");
if( empty($view_count) ) 
{
	$peoplesInfo = array( );
}
else 
{
	if( count($view_count) == 1 ) 
	{
		$peoplesInfo = pdo_getall("longbing_card_user", array( "id" => $view_count[0]["user_id"] ), array( "id", "avatarUrl" ));
	}
	else 
	{
		$checkArr = array( );
		$peoplesInfo = array( );
		foreach( $view_count as $k => $v ) 
		{
			if( in_array($v["user_id"], $checkArr) ) 
			{
				continue;
			}
			if( $v["user_id"] == $to_uid ) 
			{
				continue;
			}
			array_push($checkArr, $v["user_id"]);
			$userInfo = pdo_get("longbing_card_user", array( "id" => $v["user_id"] ), array( "id", "avatarUrl" ));
			if( $userInfo["avatarUrl"] ) 
			{
				array_push($peoplesInfo, $userInfo);
				if( count($peoplesInfo) == 7 ) 
				{
					break;
				}
			}
		}
	}
}
$data["peoplesInfo"] = $peoplesInfo;
$info = pdo_get("longbing_card_user", array( "id" => $to_uid, "uniacid" => $_W["uniacid"] ));
if( $info["qr_path"] ) 
{
	$size = @filesize(ATTACHMENT_ROOT . "/" . $info["qr_path"]);
	if( 51220 < $size ) 
	{
		$image = $this->transImage($info["qr_path"]);
		$data["qr"] = $image;
	}
	else 
	{
		load()->func("file");
		if( !is_dir(ATTACHMENT_ROOT . "/" . "images") ) 
		{
			mkdir(ATTACHMENT_ROOT . "/" . "images");
		}
		if( !is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card") ) 
		{
			mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card");
		}
		if( !is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/") ) 
		{
			mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/");
		}
		$destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
		$image = $destination_folder . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png";
		$path = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCard&is_qr=1";
		$res = $this->createQr($image, $path);
		$image = tomedia("images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png");
		if( !strstr($image, "ttp") ) 
		{
			$image = "https://" . $image;
		}
		pdo_update("longbing_card_user", array( "qr_path" => "images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png" ), array( "id" => $to_uid ));
		$image = $this->transImage($image);
	}
}
else 
{
	load()->func("file");
	if( !is_dir(ATTACHMENT_ROOT . "/" . "images") ) 
	{
		mkdir(ATTACHMENT_ROOT . "/" . "images");
	}
	if( !is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card") ) 
	{
		mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card");
	}
	if( !is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/") ) 
	{
		mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/");
	}
	$destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
	$image = $destination_folder . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png";
	$path = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCard&is_qr=1";
	$res = $this->createQr($image, $path);
	$image = tomedia("images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png");
	if( !strstr($image, "ttp") ) 
	{
		$image = "https://" . $image;
	}
	pdo_update("longbing_card_user", array( "qr_path" => "images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png" ), array( "id" => $to_uid ));
	$image = $this->transImage($image);
}
$data["qr"] = $image;
$time = time();
$coupon = pdo_getall("longbing_card_coupon", array( "end_time >" => $time, "status" => 1, "uniacid" => $_W["uniacid"] ), array( ), "", array( "top desc", "id desc" ));
$data["coupon"] = array( );
$data["coupon_last_record"] = array( );
$data["coupon_last_record_user"] = array( );
if( $coupon ) 
{
	foreach( $coupon as $index => $item ) 
	{
		$record_list = pdo_getall("longbing_card_coupon_record", array( "coupon_id" => $item["id"], "staff_id" => $to_uid, "uniacid" => $_W["uniacid"] ), array( ), "", array( "id desc" ));
		if( count($record_list) < $item["number"] ) 
		{
			$data["coupon"] = $item;
			if( count($record_list) ) 
			{
				$data["coupon_last_record"] = $record_list;
			}
			break;
		}
	}
}
if( !empty($data["coupon_last_record"]) ) 
{
	foreach( $data["coupon_last_record"] as $index => $item ) 
	{
		$user = pdo_get("longbing_card_user", array( "id" => $item["user_id"] ));
		if( 4 < mb_strlen($user["nickName"], "utf8") ) 
		{
			$user["nickName"] = mb_substr($user["nickName"], 0, 4, "UTF-8") . "...";
		}
		$data["coupon_last_record"][$index]["user_info"] = $user;
	}
}
$data["share_img"] = "";
if( file_exists(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/share-" . $to_uid . ".png") ) 
{
	$size = @filesize(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/share-" . $to_uid . ".png");
	if( 51220 < $size ) 
	{
		$fileName = "images/longbing_card/" . $_W["uniacid"] . "/share-" . $to_uid . ".png";
		$data["share_img"] = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $fileName;
	}
	else 
	{
		@unlink(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/share-" . $to_uid . ".png");
	}
}
if( $this->redis_sup_v3 ) 
{
	$redis_key = "longbing_cardsv5_" . $to_uid . "_" . $_W["uniacid"];
	$this->redis_server_v3->set($redis_key, json_encode($data));
	$this->redis_server_v3->EXPIRE($redis_key, 3600);
}
$this->sendTplStaff($uid, $to_uid, 1, $_W["uniacid"]);
$data["check_redis"] = $this->redis_sup_v3;
return $this->result(0, "", $data);
?>