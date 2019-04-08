<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$to_uid = $_GPC["to_uid"];
$scene = $_GPC["scene"];
if( !$scene ) 
{
	$scene = 0;
}
$time = time();
if( !$uid || !$to_uid ) 
{
	return $this->result(-1, "", array( ));
}
$data = array( "user_id" => $uid, "to_uid" => $to_uid, "type" => 2, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "praise", "scene" => $scene, "create_time" => $time, "update_time" => $time );
$data_insert_count = $data;
pdo_insert("longbing_card_count", $data_insert_count);
$this->sendTplStaff($uid, $to_uid, 2, $_W["uniacid"]);
pdo_delete("longbing_card_collection", array( "to_uid" => 0 ));
pdo_delete("longbing_card_user_info", array( "fans_id" => 0 ));
$from_uid = 0;
if( isset($_GPC["from_id"]) ) 
{
	$from_uid = $_GPC["from_id"];
}
$have = pdo_get("longbing_card_collection", array( "uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"] ));
if( empty($have) || !$have ) 
{
	$insert_data = array( "uniacid" => $_W["uniacid"], "from_uid" => $from_uid, "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene, "is_qr" => ($_GPC["is_qr"] ? $_GPC["is_qr"] : 0), "openGId" => ($_GPC["openGId"] ? $_GPC["openGId"] : ""), "is_group" => ($_GPC["is_group"] ? $_GPC["is_group"] : ""), "type" => ($_GPC["type"] ? $_GPC["type"] : ""), "target_id" => ($_GPC["target_id"] ? $_GPC["target_id"] : "") );
	pdo_insert("longbing_card_collection", $insert_data);
}
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
	$data["is_boss"] = 0;
}
$check = pdo_get("longbing_card_user_info", array( "fans_id" => $to_uid ));
$data["staff_info"] = $check;
$data["v2"] = 1;
$sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 2 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " && `user_id` != " . $to_uid . " GROUP BY user_id";
$count = pdo_fetchall($sql);
$data["peoples"] = count($count);
$sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 3 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " GROUP BY user_id";
$count = pdo_fetchall($sql);
$data["thumbs_up"] = count($count);
$sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 4 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " GROUP BY user_id";
$count = pdo_fetchall($sql);
$data["share"] = count($count);
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
				if( count($peoplesInfo) == 8 ) 
				{
					break;
				}
			}
		}
	}
}
$data["peoplesInfo"] = $peoplesInfo;
$tags = pdo_getall("longbing_card_tags", array( "user_id" => $to_uid, "status" => 1 ));
if( $tags ) 
{
	$user_tags = pdo_getall("longbing_card_user_tags", array( "user_id" => $uid ));
	$user_tags_arr = array( );
	if( $user_tags ) 
	{
		foreach( $user_tags as $index => $item ) 
		{
			array_push($user_tags_arr, $item["tag_id"]);
		}
	}
	foreach( $tags as $index => $item ) 
	{
		if( in_array($item["id"], $user_tags_arr) ) 
		{
			$tags[$index]["clicked"] = 1;
		}
		else 
		{
			$tags[$index]["clicked"] = 0;
		}
	}
}
else 
{
	$tags = array( );
}
$data["tags"] = $tags;
echo json_encode(array( "errno" => 0, "message" => "suc", "data" => $data ), JSON_UNESCAPED_UNICODE);
if( function_exists("fastcgi_finish_request") ) 
{
	@fastcgi_finish_request();
}
if( !is_dir(IA_ROOT . "/data") ) 
{
	mkdir(IA_ROOT . "/data");
}
if( !is_dir(IA_ROOT . "/data/tpl") ) 
{
	mkdir(IA_ROOT . "/data/tpl");
}
if( !is_dir(IA_ROOT . "/data/tpl/web") ) 
{
	mkdir(IA_ROOT . "/data/tpl/web");
}
if( file_exists(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/share-" . $uid . ".png") ) 
{
	$size = @filesize(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/share-" . $to_uid . ".png");
	if( 51220 < $size ) 
	{
		exit();
	}
}
$check = pdo_get("longbing_card_user_info", array( "fans_id" => $to_uid, "uniacid" => $_W["uniacid"] ));
$job = pdo_get("longbing_card_job", array( "id" => $check["job_id"], "uniacid" => $_W["uniacid"] ));
if( $check["company_id"] ) 
{
	$com = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "id" => $check["company_id"], "status" => 1 ));
	if( !$com ) 
	{
		$com = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
	}
	$com["logo"] = tomedia($com["logo"]);
	$check["myCompany"] = $com;
}
else 
{
	$com = pdo_get("longbing_card_company", array( "uniacid" => $_W["uniacid"], "status" => 1 ));
	$com["logo"] = tomedia($com["logo"]);
	$check["myCompany"] = $com;
}
$gData = array( "company_logo" => $check["myCompany"]["logo"], "company_name" => sortstr($check["myCompany"]["name"], 10), "name" => sortstr($check["name"], 10), "job" => sortstr($job["name"], 10), "phone" => sortstr($check["phone"], 12), "email" => sortstr($check["email"], 18), "address" => sortstr($check["myCompany"]["addr"], 10), "img" => tomedia($check["avatar"]) );
createsharepng($gData, $to_uid, $_W["uniacid"]);
function get_lt_rounder_corner($radius) 
{
	$img = imagecreatetruecolor($radius, $radius);
	$bgcolor = imagecolorallocate($img, 210, 210, 210);
	$fgcolor = imagecolorallocate($img, 0, 0, 0);
	imagefill($img, 0, 0, $bgcolor);
	imagefilledarc($img, $radius, $radius, $radius * 2, $radius * 2, 180, 270, $fgcolor, IMG_ARC_PIE);
	imagecolortransparent($img, $fgcolor);
	return $img;
}
function sortStr($str, $len) 
{
	if( $len < mb_strlen($str, "utf8") ) 
	{
		$str = mb_substr($str, 0, $len, "UTF-8") . "...";
	}
	return $str;
}
function getImageExt($src = "") 
{
	$src = explode(".", $src);
	$count = count($src);
	if( $count < 2 ) 
	{
		return false;
	}
	$ext = strtolower($src[$count - 1]);
	if( $ext == "jpg" ) 
	{
		return "jpg";
	}
	if( $ext == "png" ) 
	{
		return "png";
	}
	if( $ext == "jpeg" ) 
	{
		return "jpeg";
	}
	return false;
}
function createSharePng($gData, $codeName, $uniacid) 
{
	$im = imagecreatetruecolor(738, 420);
	$color = imagecolorallocate($im, 255, 255, 255);
	imagefill($im, 0, 0, $color);
	$font_file = $_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/vista.ttf";
	$font_file_bold = $_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/vista_bold.ttf";
	$font_color_1 = ImageColorAllocate($im, 140, 140, 140);
	$font_color_2 = ImageColorAllocate($im, 28, 28, 28);
	$font_color_3 = ImageColorAllocate($im, 129, 129, 129);
	$font_color_4 = ImageColorAllocate($im, 50, 50, 50);
	$font_color_5 = ImageColorAllocate($im, 68, 68, 68);
	$font_color_red = ImageColorAllocate($im, 217, 45, 32);
	list($l_w, $l_h) = getimagesize($gData["img"]);
	$ext = getimageext($gData["img"]);
	if( $ext == "jpg" || $ext == "jpeg" ) 
	{
		$logoImg = @imagecreatefromjpeg($gData["img"]);
	}
	else 
	{
		if( $ext == "png" ) 
		{
			$logoImg = @imagecreatefrompng($gData["img"]);
		}
		else 
		{
			return false;
		}
	}
	imagecopyresized($im, $logoImg, 358, 0, 0, 0, 420, 420, $l_w, $l_h);
	list($l_w1, $l_h1) = getimagesize("http://retail.xiaochengxucms.com/images/2/2018/12/F9O1e9o7EfFC9ZT3eVE3w739irRWs1.png");
	$logoImg1 = @imagecreatefrompng("http://retail.xiaochengxucms.com/images/2/2018/12/F9O1e9o7EfFC9ZT3eVE3w739irRWs1.png");
	imagecopyresized($im, $logoImg1, 0, 0, 0, 0, 738, 420, $l_w1, $l_h1);
	list($l_w, $l_h) = getimagesize($gData["company_logo"]);
	$ext = getimageext($gData["company_logo"]);
	if( $ext == "jpg" || $ext == "jpeg" ) 
	{
		$logoImg = @imagecreatefromjpeg($gData["company_logo"]);
	}
	else 
	{
		if( $ext == "png" ) 
		{
			$logoImg = @imagecreatefrompng($gData["company_logo"]);
		}
		else 
		{
			return false;
		}
	}
	imagecopyresized($im, $logoImg, 32, 22, 0, 0, 30, 30, $l_w, $l_h);
	imagettftext($im, 14, 0, 68, 42, $font_color_4, $font_file, $gData["company_name"]);
	imagettftext($im, 14, 0, 78, 250, $font_color_5, $font_file, $gData["phone"]);
	imagettftext($im, 14, 0, 78, 295, $font_color_5, $font_file, $gData["email"]);
	imagettftext($im, 14, 0, 78, 338, $font_color_5, $font_file, $gData["address"]);
	imagettftext($im, 22, 0, 30, 115, $font_color_4, $font_file, $gData["name"]);
	imagettftext($im, 14, 0, 30, 155, $font_color_5, $font_file, $gData["job"]);
	$radius = 30;
	$lt_corner = get_lt_rounder_corner($radius);
	imagecopymerge($im, $lt_corner, 0, 0, 0, 0, $radius, $radius, 100);
	$lb_corner = imagerotate($lt_corner, 90, 0);
	imagecopymerge($im, $lb_corner, 0, 420 - $radius, 0, 0, $radius, $radius, 100);
	$rb_corner = imagerotate($lt_corner, 180, 0);
	imagecopymerge($im, $rb_corner, 738 - $radius, 420 - $radius, 0, 0, $radius, $radius, 100);
	$rt_corner = imagerotate($lt_corner, 270, 0);
	imagecopymerge($im, $rt_corner, 738 - $radius, 0, 0, 0, $radius, $radius, 100);
	$fileName = ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $uniacid . "/share-" . $codeName . ".png";
	imagepng($im, $fileName);
	$im = imagecreatetruecolor(780, 624);
	$color = imagecolorallocate($im, 223, 223, 223);
	imagefill($im, 0, 0, $color);
	list($l_w1, $l_h1) = getimagesize("http://retail.xiaochengxucms.com/images/2/2018/12/WzRC39R9CsgmC9Rqq8b3rm8xBTsYG9.png");
	$bg = @imagecreatefrompng("http://retail.xiaochengxucms.com/images/2/2018/12/WzRC39R9CsgmC9Rqq8b3rm8xBTsYG9.png");
	imagecopyresized($im, $bg, 0, 0, 0, 0, 780, 624, $l_w1, $l_h1);
	list($l_w1, $l_h1) = getimagesize($fileName);
	$bg = @imagecreatefrompng($fileName);
	imagecopyresized($im, $bg, 20, 20, 0, 0, 740, 420, $l_w1, $l_h1);
	$fileName = ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $uniacid . "/share-" . $codeName . ".png";
	imagepng($im, $fileName);
	imagedestroy($im);
	imagedestroy($logoImg);
	imagedestroy($logoImg1);
	imagedestroy($bg);
	return true;
}
?>