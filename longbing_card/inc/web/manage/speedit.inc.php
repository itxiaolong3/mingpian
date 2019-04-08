<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( $_GPC["action"] == "editSpe" ) 
{
	$time = time();
	if( empty($_GPC["dataSpe"]) ) 
	{
		$_GPC["dataSpe"] = array( array( "title" => "默认", "sec" => array( array( "title" => "默认" ) ) ) );
	}
	$arr_old = array( );
	foreach( $_GPC["dataSpe"] as $k => $v ) 
	{
		array_push($arr_old, $v["id"]);
		$check = pdo_get("longbing_card_shop_spe", array( "id" => $v["id"] ));
		$pid = $v["id"];
		if( empty($check) ) 
		{
			$result = pdo_insert("longbing_card_shop_spe", array( "goods_id" => $_GPC["id"], "title" => $v["title"], "uniacid" => $_W["uniacid"], "create_time" => $time, "update_time" => $time ));
			if( !$result ) 
			{
				message("操作失败", "", "error");
				exit();
			}
			$pid = pdo_insertid();
			array_push($arr_old, $pid);
			foreach( $_GPC["listIdTitle"]["title"] as $kIT => $vIT ) 
			{
				if( $vIT == $v["title"] ) 
				{
					$_GPC["listIdTitle"]["id"][$kIT] = $pid;
				}
			}
		}
		if( isset($v["sec"]) ) 
		{
			foreach( $v["sec"] as $k2 => $v2 ) 
			{
				$check = pdo_get("longbing_card_shop_spe", array( "id" => $v2["id"], "pid" => $pid ));
				$sid = $v2["id"];
				array_push($arr_old, $sid);
				$result = false;
				if( empty($check) ) 
				{
					$result = pdo_insert("longbing_card_shop_spe", array( "goods_id" => $_GPC["id"], "title" => $v2["title"], "uniacid" => $_W["uniacid"], "create_time" => $time, "update_time" => $time, "pid" => $pid ));
					if( $result ) 
					{
						$sid = pdo_insertid();
						array_push($arr_old, $sid);
						foreach( $_GPC["listIdTitle"]["title"] as $kIT => $vIT ) 
						{
							if( $vIT == $v2["title"] ) 
							{
								$_GPC["listIdTitle"]["id"][$kIT] = $sid;
							}
						}
					}
				}
			}
		}
	}
	$check_old = pdo_getall("longbing_card_shop_spe", array( "goods_id" => $_GPC["id"], "status" => 1 ));
	foreach( $check_old as $k => $v ) 
	{
		if( !in_array($v["id"], $arr_old) ) 
		{
			pdo_update("longbing_card_shop_spe", array( "status" => -1 ), array( "id" => $v["id"] ));
		}
	}
	if( $_GPC["dataPrice"] ) 
	{
		pdo_update("longbing_card_shop_spe_price", array( "status" => -1 ), array( "goods_id" => $_GPC["id"], "uniacid" => $_W["uniacid"] ));
		pdo_update("longbing_card_shop_collage", array( "status" => -1 ), array( "goods_id" => $_GPC["id"], "uniacid" => $_W["uniacid"] ));
		foreach( $_GPC["dataPrice"]["title"] as $k3 => $v3 ) 
		{
			$arr = explode("-", $v3);
			$id1 = "";
			foreach( $arr as $k4 => $v4 ) 
			{
				foreach( $_GPC["listIdTitle"]["title"] as $kIT => $vIT ) 
				{
					if( $vIT == $v4 ) 
					{
						$id1 .= "-" . $_GPC["listIdTitle"]["id"][$kIT];
					}
				}
			}
			$id1 = trim($id1, "-");
			$stockK = $_GPC["dataPrice"]["stock"][$k3];
			if( $stockK < 1 ) 
			{
				$stockK = 0;
			}
			$dataInsrt = array( "goods_id" => $_GPC["id"], "spe_id_1" => $id1, "spe_id_2" => "", "price" => $_GPC["dataPrice"]["price"][$k3], "stock" => $stockK, "uniacid" => $_W["uniacid"], "create_time" => $time, "update_time" => $time );
			pdo_insert("longbing_card_shop_spe_price", $dataInsrt);
		}
	}
	message("操作成功", $this->createWebUrl("manage/goods"), "success");
}
if( !isset($_GPC["id"]) ) 
{
	return false;
}
$where["id"] = $_GPC["id"];
$id = $_GPC["id"];
$where = array( "uniacid" => $_W["uniacid"], "goods_id" => $id, "status" => 1 );
$where2 = array( "uniacid" => $_W["uniacid"], "goods_id" => $id, "status" => 1, "title !=" => "默认" );
$list = pdo_getall("longbing_card_shop_spe", $where2);
$data = array( );
foreach( $list as $k => $v ) 
{
	if( $v["pid"] == 0 ) 
	{
		array_push($data, $v);
	}
}
foreach( $list as $k => $v ) 
{
	foreach( $data as $k2 => $v2 ) 
	{
		if( $v2["id"] == $v["pid"] ) 
		{
			$data[$k2]["sec"][] = $v;
		}
	}
}
$json = json_encode($data);
if( empty($data) ) 
{
	$json = 0;
}
$priceList = pdo_getall("longbing_card_shop_spe_price", $where);
$priceJson = json_encode($priceList);
if( empty($priceList) ) 
{
	$priceJson = 0;
}
load()->func("tpl");
include($this->template("manage/speEdit"));
?>