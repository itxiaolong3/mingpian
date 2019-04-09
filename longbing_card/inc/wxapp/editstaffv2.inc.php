<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$staff_id = $_GPC["staff_id"];
$code = $_GPC["code"];
if( $staff_id ) 
{
	$uid = $staff_id;
}
$uniacid = $_W["uniacid"];
//if( !$uid || !$_GPC["job_id"] )
//{
//	return $this->result(-1, "", array( ));
//}
if( !$uid)
{
    return $this->result(-1, "请指定用户", array( ));
}
$info = pdo_get("longbing_card_user", array( "id" => $uid ), array( "nickName", "avatarUrl", "is_staff", "id" ));
$config = pdo_get("longbing_card_config", array( "uniacid" => $uniacid ));
if( $info["is_staff"] != 1 ) 
{
}
$images = $this->transImageBack($_GPC["images"]);
$avatar = $this->transImageBack($_GPC["avatar"]);
$voice = $this->transImageBack($_GPC["voice"]);
$_GPC["desc"] = str_replace(" ", "&nbsp;", $_GPC["desc"]);
$data = array( "uniacid" => $uniacid, "avatar" => $avatar,"companylogo" => $_GPC["companylogo"],"wxqrcode" => $_GPC["wxqrcode"], "name" => $_GPC["name"], "job" => $_GPC["job"], "company" => $_GPC["company"],
    "company2" => $_GPC["company2"],"company3" => $_GPC["company3"],"comaddre" => $_GPC["comaddre"],"phone2" => $_GPC["phone2"],
    "phone" => $_GPC["phone"], "wechat" => $_GPC["wechat"], "telephone" => $_GPC["telephone"], "job_id" => $_GPC["job_id"],
    "email" => $_GPC["email"], "desc" => $_GPC["desc"], "comdesc" => $_GPC["comdesc"], "company_id" => $_GPC["company_id"],
    "voice" => $voice, "voice_time" => $_GPC["voice_time"],
    "card_type" => $_GPC["card_type"], "my_url" => $_GPC["my_url"],
    "ww_account" => $_GPC["ww_account"], "images" => $images, "is_default" => 1, "update_time" => time() );
if( $_GPC["my_video"] ) 
{
	$data["my_video"] = $_GPC["my_video"];
}
$user_info = pdo_get("longbing_card_user_info", array( "fans_id" => $info["id"], "uniacid" => $uniacid ), array( "name", "phone", "fans_id", "id", "create_time" ));
if( empty($user_info) ) 
{
	$data["fans_id"] = $info["id"];
	$data["create_time"] = time();
	if( $config && $config["code"] ) 
	{
		if( !$code ) 
		{
			return $this->result(-1, "need code", $info);
		}
		if( $code != $config["code"] ) 
		{
			return $this->result(-1, "code error", $info);
		}
	}
	$result = pdo_insert("longbing_card_user_info", $data);
}
else 
{
	if( !$user_info["create_time"] ) 
	{
		$data["create_time"] = time();
	}
	$result = pdo_update("longbing_card_user_info", $data, array( "fans_id" => $info["id"] ));
}
$create_auth = true;
if( $code && $config && $config["code"] && $code == $config["code"] ) 
{
	if( LONGBING_AUTH_CARD != 0 ) 
	{
		$list = pdo_getall("longbing_card_user", array( "is_staff" => 1, "uniacid" => $uniacid ));
		$count = count($list);
		if( LONGBING_AUTH_CARD <= $count ) 
		{
			$create_auth = false;
		}
	}
	if( $create_auth ) 
	{
		@pdo_update("longbing_card_user", array( "is_staff" => 1, "status" => 1 ), array( "id" => $uid ));
		@pdo_update("longbing_card_user_info", array( "is_staff" => 1, "status" => 1 ), array( "fans_id" => $uid ));
		$check_coll = pdo_get("longbing_card_collection", array( "uid" => $uid, "to_uid" => $uid ));
		if( !$check_coll ) 
		{
			$time = time();
			@pdo_insert("longbing_card_collection", array( "uniacid" => $uniacid, "uid" => $uid, "to_uid" => $uid, "from_uid" => 0, "create_time" => $time, "update_time" => $time, "scene" => 0, "status" => 1, "is_qr" => 0, "openGId" => "", "is_group" => 0, "type" => 0, "target_id" => 0 ));
		}
	}
}
if( $result ) 
{
	if( $this->redis_sup_v3 ) 
	{
		$this->redis_server_v3->flushDB();
	}
	if( $create_auth ) 
	{
		if( $code ) 
		{
			$config = pdo_get("longbing_card_config", array( "uniacid" => $uniacid ));
			if( $config && $config["code"] ) 
			{
				if( $code != $config["code"] ) 
				{
					echo json_encode(array( "errno" => -2, "message" => "免审口令错误，需要管理员审核", "data" => array( ) ), JSON_UNESCAPED_UNICODE);
				}
				else 
				{
					echo json_encode(array( "errno" => 0, "message" => "请求成功", "data" => array( ) ), JSON_UNESCAPED_UNICODE);
				}
			}
			else 
			{
				echo json_encode(array( "errno" => 0, "message" => "请求成功", "data" => array( ) ), JSON_UNESCAPED_UNICODE);
			}
		}
		else 
		{
			echo json_encode(array( "errno" => 0, "message" => "请求成功", "data" => array( ) ), JSON_UNESCAPED_UNICODE);
		}
	}
	else 
	{
		echo json_encode(array( "errno" => -2, "message" => "创建名片数量已经达到上限，如需增加请联系管理员！", "data" => array( ) ), JSON_UNESCAPED_UNICODE);
	}
}
else 
{
	echo json_encode(array( "errno" => -1, "message" => "更新失败", "data" => array( ) ), JSON_UNESCAPED_UNICODE);
}
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
	@unlink(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/share-" . $uid . ".png");
}
$check = pdo_get("longbing_card_user", array( "id" => $uid, "uniacid" => $_W["uniacid"] ));
$check = pdo_get("longbing_card_user_info", array( "fans_id" => $uid, "uniacid" => $_W["uniacid"] ));
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
createsharepng($gData, $uid, $_W["uniacid"]);
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