<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$time = time();
$to_uid = $_GPC["to_uid"];
$uid = $_GPC["user_id"];
if( !$to_uid ) 
{
	return $this->result(-1, "", array( ));
}
$data = array( "user_id" => $_GPC["user_id"], "to_uid" => $to_uid, "type" => 2, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "praise", "scene" => $_GPC["scene"] );
if( $this->redis_sup_v3 ) 
{
	$redis_key = "longbing_cardshow_" . $to_uid . "_" . $_W["uniacid"];
	$data2 = $this->redis_server_v3->get($redis_key);
	if( $data2 ) 
	{
		$data2 = json_decode($data2, true);
		foreach( $data as $k => $v ) 
		{
			$data2[$k] = $v;
		}
		$data2["from_redis"] = 2;
		$data = $data2;
		return $this->result(0, "redis", $data);
	}
}
$check = pdo_get("longbing_card_user_info", array( "fans_id" => $to_uid, "uniacid" => $_W["uniacid"], "is_staff" => 1 ));
if( !$check || empty($check) ) 
{
	return $this->result(-2, "card not found", array( ));
}
$check["bg"] = tomedia($check["bg"]);
$check["myCompany"] = array( );
if( $check["company_id"] ) 
{
	$com = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "id" => $check["company_id"], "status" => 1 ));
	if( !$com ) 
	{
		$com = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
	}
	if( $com ) 
	{
		$com["logo"] = $this->transImage($com["logo"]);
		$com["logo"] = str_replace("ttp://", "ttps://", $com["logo"]);
		if( !strstr($com["logo"], "ttps://") ) 
		{
			$com["logo"] = "https://" . $com["logo"];
		}
		$check["myCompany"] = $com;
	}
}
else 
{
	$com = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
	if( $com ) 
	{
		$com["logo"] = $this->transImage($com["logo"]);
		$com["logo"] = str_replace("ttp://", "ttps://", $com["logo"]);
		if( !strstr($com["logo"], "ttps://") ) 
		{
			$com["logo"] = "https://" . $com["logo"];
		}
		$check["myCompany"] = $com;
	}
}
if( !empty($check["myCompany"]) ) 
{
	if( 18 < mb_strlen($check["myCompany"]["addr"], "utf8") ) 
	{
		$check["myCompany"]["addrMore"] = mb_substr($check["myCompany"]["addr"], 0, 43, "UTF-8") . "...";
	}
	else 
	{
		$check["myCompany"]["addrMore"] = $check["myCompany"]["addr"];
	}
}
$info = $check;
if( $info["avatar"] ) 
{
	$tmp = $info["avatar"];
	$info["avatar_2"] = tomedia($tmp);
	$info["avatar"] = $this->transImage($info["avatar_2"]);
	$info["avatar"] = str_replace("ttp://", "ttps://", $info["avatar"]);
	if( !strstr($info["avatar"], "ttps://") ) 
	{
		$info["avatar"] = "https://" . $info["avatar"];
	}
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
$job = pdo_get("longbing_card_job", array( "id" => $info["job_id"], "uniacid" => $_W["uniacid"], "status" => 1 ));
$info["job_name"] = ($job ? $job["name"] : "暂无职称");
$short_name_tmp = ($info["myCompany"]["short_name"] ? $info["myCompany"]["short_name"] : $info["myCompany"]["name"]);
if( $info["share_text"] ) 
{
	$info["share_text"] = str_replace("\$company", $short_name_tmp, $info["share_text"]);
	$info["share_text"] = str_replace("#公司#", $short_name_tmp, $info["share_text"]);
	$info["share_text"] = str_replace("\$job", $info["job_name"], $info["share_text"]);
	$info["share_text"] = str_replace("#职务#", $info["job_name"], $info["share_text"]);
	$info["share_text"] = str_replace("\$name", $info["name"], $info["share_text"]);
	$info["share_text"] = str_replace("#我的名字#", $info["name"], $info["share_text"]);
}
else 
{
	//$info["share_text"] = "您好，我是" . $short_name_tmp . "的" . $info["job_name"] . $info["name"] . "，请惠存。";
	$info["share_text"] = "您好，我是" . $info['company'] . "的" . $info["job"] . $info["name"] . "，请惠存。";
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
$extension = pdo_getall("longbing_card_extension", array( "user_id" => $to_uid ), array( "goods_id" ));
if( !$extension ) 
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
		$sql = "SELECT id,`name`,cover,price,status,unit FROM " . tablename("longbing_card_goods") . " WHERE id IN " . $ids . " && status = 1 ORDER BY top DESC";
	}
	else 
	{
		$sql = "SELECT id,`name`,cover,price,status,unit FROM " . tablename("longbing_card_goods") . " WHERE id = " . $ids . " && status = 1 ORDER BY top DESC";
	}
	$goods = pdo_fetchall($sql);
	foreach( $goods as $k => $v ) 
	{
		$goods[$k]["cover"] = tomedia($v["cover"]);
	}
	$data["goods"] = $goods;
}
$data["peoplesInfo"] = array( );
$view_count = pdo_fetchall("SELECT * FROM ( SELECT id, user_id FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $to_uid . " && user_id != " . $to_uid . " ORDER BY id DESC )t GROUP BY user_id");
array_multisort(array_column($view_count, "id"), SORT_DESC, $view_count);
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
$image = str_replace("ttp://", "ttps://", $image);
if( !strstr($image, "ttps://") ) 
{
	$image = "https://" . $image;
}
$data["qr"] = $image;
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
	$redis_key = "longbing_cardshow_" . $to_uid . "_" . $_W["uniacid"];
	$this->redis_server_v3->set($redis_key, json_encode($data));
	$this->redis_server_v3->EXPIRE($redis_key, 3600);
}
return $this->result(0, "", $data);
?>