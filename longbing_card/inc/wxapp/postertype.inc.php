<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$uid = $_GPC["user_id"];
$uniacid = $_W["uniacid"];
$to_uid = $_GPC["to_uid"];
$type = $_GPC["type"];
$user_info = $_GPC["user_info"];
if( !$uid || !$to_uid ) 
{
	return $this->result(-1, "", array( ));
}
$list = pdo_getall("longbing_card_poster_type", array( "uniacid" => $uniacid, "status" => 1 ), array( "id", "title", "top" ), "", array( "top desc", "id desc" ));
$data["post_type_list"] = $list;
$list_img = array( );
if( $type ) 
{
	$list_img = pdo_getall("longbing_card_poster", array( "uniacid" => $uniacid, "status" => 1, "type_id" => $type ), array( "id", "title", "img", "top", "type_id" ), "", array( "top desc", "id desc" ));
}
else 
{
	if( $type == 0 ) 
	{
		$list_img = pdo_getall("longbing_card_poster", array( "uniacid" => $uniacid, "status" => 1 ), array( "id", "title", "img", "top", "type_id" ), "", array( "top desc", "id desc" ));
	}
}
foreach( $list_img as $index => $item ) 
{
	$list_img[$index]["img_2"] = $this->transImage($item["img"]);
	$list_img[$index]["img"] = $list_img[$index]["img_2"];
}
$data["post_img"] = $list_img;
if( $user_info ) 
{
	$user = pdo_get("longbing_card_user_info", array( "fans_id" => $uid ), array( "id", "fans_id", "job_id", "phone", "avatar", "name", "company_id" ));
	if( $user ) 
	{
		$job = pdo_get("longbing_card_job", array( "id" => $user["job_id"] ));
		$user["job_name"] = ($job ? $job["name"] : "");
		$user["avatar_2"] = $this->transImage($user["avatar"]);
		$user["avatar"] = $user["avatar_2"];
		$destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
		$destination_folder2 = "/images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $uid . "qr.png";
		$image = $destination_folder . "/" . $_W["uniacid"] . "-" . $uid . "qr.png";
		if( file_exists($image) ) 
		{
			$size = @filesize($image);
			if( 51220 < $size ) 
			{
				$user["qr_path"] = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $destination_folder2;
			}
			else 
			{
				$path = "longbing_card/pages/index/index?to_uid=" . $uid . "&currentTabBar=toCard&is_qr=1";
				$res = $this->createQr($image, $path);
				$user["qr_path"] = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $destination_folder2;
			}
		}
		else 
		{
			$path = "longbing_card/pages/index/index?to_uid=" . $uid . "&currentTabBar=toCard&is_qr=1";
			$res = $this->createQr($image, $path);
			$user["qr_path"] = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $destination_folder2;
			$user["qr_path2"] = 0;
		}
		$companyList = pdo_getall("longbing_card_company", array( "uniacid" => $uniacid ));
		if( !$companyList ) 
		{
			$companyList = array( array( ) );
		}
		$company = $companyList[0];
		foreach( $companyList as $k => $v ) 
		{
			if( $v["id"] == $user["company_id"] ) 
			{
				$company = $v;
				break;
			}
		}
		$data["post_user"] = $user;
		$data["post_company"] = $company;
	}
}
return $this->result(0, "", $data);
?>