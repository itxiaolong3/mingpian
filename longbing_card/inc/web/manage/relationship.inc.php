<?php  global $_GPC;
global $_W;
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
$uniacid = $_W["uniacid"];
$module_name = $_W["current_module"]["name"];
if( isset($_GPC["action"]) && $_GPC["action"] == "search" ) 
{
	$searchText = $_GPC["searchText"];
	$searchTextLike = "%" . $searchText . "%";
	if( is_numeric($searchText) ) 
	{
		$user = pdo_fetch("SELECT * FROM " . tablename("longbing_card_user")  . " WHERE \r\n        uniacid = " . $_W["uniacid"] . " && status = 1 and level>0 && id = '" . $searchText . "'");
		if( !$user ) 
		{
			$user = pdo_fetch("SELECT * FROM " . tablename("longbing_card_user")  . " WHERE uniacid = " . $_W["uniacid"] . " && status = 1 and level>0 && nickName LIKE '" . $searchTextLike . "' OR \r\n        uniacid = " . $_W["uniacid"] . " && status = 1 && id = '" . $searchText . "'");
		}
	}
	else 
	{
		$user = pdo_fetch("SELECT * FROM " . tablename("longbing_card_user") . "  WHERE uniacid = " . $_W["uniacid"] . " && status = 1 and level>0 && nickName LIKE '" . $searchTextLike . "' OR \r\n        uniacid = " . $_W["uniacid"] . " && status = 1 && id = '" . $searchText . "'");
	}

    //总收益
    $symoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=0 and uid='.$user['id']);
    $user['total_profit']=number_format($symoney['allmoney'],2);
    //已提现
    $txmoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=1 and uid='.$user['id']);
    $user['total_postal']=number_format($txmoney['allmoney'],2);

	$user_show = 0;
	$user_p_show = 0;
	$user_list_show = 0;
	$user_p = array( );
	$user_list = array( );
	if( $user["pid"] ) 
	{
		$user_p = pdo_fetch("SELECT * FROM " . tablename("longbing_card_user")  . "  WHERE uniacid = " . $_W["uniacid"] . " && status = 1 and level=1 && id = " . $user["pid"]);
        //总收益
        $symoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=0 and uid='.$user_p['id']);
        $user_p['total_profit']=number_format($symoney['allmoney'],2);
        //已提现
        $txmoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=1 and uid='.$user_p['id']);
        $user_p['total_postal']=number_format($txmoney['allmoney'],2);
	}
	$user_list = pdo_fetchall("SELECT * FROM " . tablename("longbing_card_user") . " WHERE uniacid = " . $_W["uniacid"] . " && status = 1 and level=1 && pid = " . $user["id"]);
    foreach ($user_list as $k=>$v){
        $symoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=0 and uid='.$v['id']);
        //总收益
        $user_list[$k]['total_profit']=number_format($symoney['allmoney'],2);
        //已提现
        $txmoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=1 and uid='.$v['id']);
        $user_list[$k]['total_postal']=number_format($txmoney['allmoney'],2);
    }
	if( $user )
	{
		$user_show = 1;
	}
	if( $user_p ) 
	{
		$user_p_show = 1;
	}
	if( $user_list ) 
	{
		$user_list_show = 1;
	}
}
$limit = array( 1, 15 );
$where = array( "uniacid" => $_W["uniacid"], "pid >" => 0,'level >'=>0 );
$curr = 1;
if( isset($_GPC["page"]) ) 
{
	$limit[0] = $_GPC["page"];
	$curr = $_GPC["page"];
}
$offset = ($curr - 1) * 15;
$teamall=array();
$users = pdo_fetchall("SELECT a.*, b.id as p_id, b.nickName as p_nickName, b.avatarUrl as p_avatarUrl, c.total_profit, c.total_postal FROM " . tablename("longbing_card_user") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.pid = b.id LEFT JOIN " . tablename("longbing_card_selling_profit") . " c ON a.id = c.user_id where a.uniacid = " . $_W["uniacid"] . " && a.status = 1 and a.level>0 && a.pid != 0 ORDER BY a.leveltype DESC LIMIT " . $offset . ", 15");
$allrelation=pdo_getall('longbing_card_relation');
foreach ($users as $k=>$v){
    $users[$k]['allteam']=sizeof(get_downline($allrelation,$v['id']));
    $symoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=0 and uid='.$v['id']);
    //总收益
    $users[$k]['total_profit']=number_format($symoney['allmoney'],2);
    //已提现
    $txmoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=1 and uid='.$v['id']);
    $users[$k]['total_postal']=number_format($txmoney['allmoney'],2);
}
//获取所有下级
function get_downline($members,$mid,$level=0){
    $arr=array();
    foreach ($members as $key => $v) {
        if($v['pid']==$mid){  //pid为0的是顶级分类
            $v['level'] = $level+1;
            $arr[]=$v;
            $arr = array_merge($arr,get_downline($members,$v['uid'],$level+1));
        }
    }
    return $arr;
}
//循环获取单一数据,暂无使用
function getteamcount($uid){
    global $allteam;
    $allchild=pdo_getall('longbing_card_relation',array('pid'=>$uid));
    if (empty($allchild)){
        return $allteam;
    }else{
        foreach ($allchild as $k=>$v){
            $allteam[]=$v['uid'];
            return getteamcount($v['uid']);
        }
    }
}
$count = pdo_getall("longbing_card_user", $where);
$count = count($count);
$perPage = 15;
load()->func("tpl");
include($this->template("manage/relationship"));
?>