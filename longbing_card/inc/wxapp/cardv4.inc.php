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
	return $this->result(-1, "请传入参数", array( ));
}
if( $uid == $to_uid ) 
{
	$check_is_staff = pdo_get("longbing_card_user", array( "id" => $uid, "uniacid" => $_W["uniacid"] ));
	if( empty($check_is_staff) || $check_is_staff["is_staff"] != 1 ) 
	{
		return $this->result(-1, "请求错误", array( ));
	}
}
$data = array( "user_id" => $uid, "to_uid" => $to_uid, "type" => 2, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "praise", "scene" => $_GPC["scene"], "create_time" => $time, "update_time" => $time );
pdo_insert("longbing_card_count", $data);
if( $this->redis_sup_v2 ) 
{
	$redis_key = "longbing_cardsv4_" . $to_uid . "_" . $_W["uniacid"];
	$data2 = $this->redis_server_v2->get($redis_key);
	if( $data2 ) 
	{
		$data2 = json_decode($data2, true);
		$data = array_merge($data, $data2);
		$data["from_redis"] = 1;
		$this->sendTplStaff($uid, $to_uid, 1, $_W["uniacid"]);
		return $this->result(0, "请求成功redis", $data);
	}
}
$from_uid = 0;
if( isset($_GPC["from_uid"]) ) 
{
	$from_uid = $_GPC["from_uid"];
}
if( !$to_uid ) 
{
	$have = pdo_get("longbing_card_collection", array( "uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"] ));
	if( empty($have) ) 
	{
		pdo_insert("longbing_card_collection", array( "uniacid" => $_W["uniacid"], "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene ));
	}
}
else 
{
	$have = pdo_get("longbing_card_collection", array( "uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"] ));
	if( empty($have) ) 
	{
		pdo_insert("longbing_card_collection", array( "uniacid" => $_W["uniacid"], "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene ));
	}
	else 
	{
		pdo_update("longbing_card_collection", array( "to_uid" => $to_uid, "scene" => $scene, "status" => 1 ), array( "id" => $have["id"] ));
	}
}
$check = pdo_get("longbing_card_user_info", array( "fans_id" => $to_uid, "uniacid" => $_W["uniacid"] ));
if( !$check || empty($check) ) 
{
	return $this->result(-1, "未找到该名片", array( ));
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
	$info["avatar"] = transimage2($info["avatar"]);
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
if( $this->redis_sup_v2 ) 
{
	$redis_key = "longbing_cardsv4_" . $to_uid . "_" . $_W["uniacid"];
	$this->redis_server_v2->set($redis_key, json_encode($data));
	$this->redis_server_v2->EXPIRE($redis_key, 30 * 60);
}
$res = $this->sendTplStaff($uid, $to_uid, 1, $_W["uniacid"]);
$data["check_redis"] = $this->redis_sup_v2;
return $this->result(0, "", $data);
function http_request($url, $data = NULL) 
{
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	if( !empty($data) ) 
	{
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	}
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	$info = curl_exec($curl);
	curl_close($curl);
	return $info;
}
function transImage2($path) 
{
	$path = tomedia($path);
	global $_GPC;
	global $_W;
	$arr = explode("/", $path);
	$fileName = "images/longbing_card/" . $_W["uniacid"] . "/" . $arr[count($arr) - 1];
	$size = @filesize(ATTACHMENT_ROOT . $fileName);
	if( $size < 51220 && file_exists(ATTACHMENT_ROOT . $fileName) ) 
	{
		@unlink(ATTACHMENT_ROOT . $fileName);
	}
	@unlink(ATTACHMENT_ROOT . $fileName);
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
	if( file_exists(ATTACHMENT_ROOT . $fileName) ) 
	{
		$path = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $fileName;
		return $path;
	}
	if( !strstr($path, $_SERVER["HTTP_HOST"]) ) 
	{
		file_put_contents(ATTACHMENT_ROOT . "/" . $fileName, http_request($path));
		$path = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $fileName;
	}
	else 
	{
		if( strstr($path, "." . $_SERVER["HTTP_HOST"]) ) 
		{
			file_put_contents(ATTACHMENT_ROOT . "/" . $fileName, http_request($path));
			$path = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $fileName;
		}
		else 
		{
			$path = str_replace("ttp://", "ttps://", $path);
			if( !strstr($path, "ttps://") ) 
			{
				$path = "https://" . $path;
			}
		}
	}
	return $path;
}
?>