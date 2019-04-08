<?php  define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
is_file(ROOT_PATH . "/inc/we7.php") or exit( "Access Denied Longbing" );
require_once(ROOT_PATH . "/inc/we7.php");
global $_GPC;
global $_W;
$user_id = $_GPC["user_id"];
$account = $_GPC["account"];
$money = $_GPC["money"];
$uniacid = $_W["uniacid"];
if( !$user_id || !$account || !$money ) 
{
	return $this->result_self(-1, "", array( ));
}
$money = floatval($money);
$config = pdo_get("longbing_card_config", array( "uniacid" => $uniacid ));
$chsh_mini = 0;
if( $config && isset($config["cash_mini"]) && $config["cash_mini"] ) 
{
	$chsh_mini = $config["cash_mini"];
}
if( $money < $chsh_mini ) 
{
	return $this->result_self(-2, "提现金额低于最低提现金额，无法提现", array( ));
}
$profit = pdo_get("longbing_card_selling_profit", array( "user_id" => $user_id, "uniacid" => $uniacid ));
if( !$profit || empty($profit) || !isset($profit["total_profit"]) ) 
{
	return $this->result_self(-2, "未找到佣金记录，无法提现", array( ));
}
$left_money = $profit["profit"];
$left_money = sprintf("%.2f", $left_money);
if( $left_money < $money ) 
{
	return $this->result_self(-2, "余额不足，无法提现", array( ));
}
$left_money_now = $left_money - $money;
$left_money_now = sprintf("%.2f", $left_money_now);
$time = time();
$cash_no = "t" . $uniacid . uniqid();
$result = pdo_insert("longbing_card_selling_cash_water", array( "user_id" => $user_id, "account" => $account, "money" => $money, "uniacid" => $uniacid, "cash_no" => $cash_no, "status" => 0, "create_time" => $time, "update_time" => $time ));
if( !$result ) 
{
	return $this->result_self(-2, "申请提现失败", array( ));
}
if( $left_money_now < 0 ) 
{
	$left_money_now = 0;
}
$postaling = $profit["postaling"] + $money;
$postaling = sprintf("%.2f", $postaling);
pdo_update("longbing_card_selling_profit", array( "postaling" => $postaling, "profit" => $left_money_now ), array( "user_id" => $user_id, "uniacid" => $uniacid ));
$user = pdo_get("longbing_card_user", array( "id" => $user_id ));
$config = pdo_get("longbing_card_config", array( "uniacid" => $_W["uniacid"] ));
if( $user && $config ) 
{
	$send_body = "您的提现申请已提交, 请加管理员微信等待管理员审核并转账";
	$page = "longbing_card/voucher/pages/user/myearning/myearning";
	if( $user["is_staff"] && $config["notice_switch"] == 1 && $config["wx_appid"] && $config["wx_tplid"] ) 
	{
		$this->sendPublicMsg($config, $user, $user, $send_body, $page);
	}
	else 
	{
		if( $user["is_staff"] && $config["notice_switch"] == 2 && $config["corpid"] && $config["corpsecret"] && $config["agentid"] ) 
		{
			$this->sendEnterpriseMsg($config, $user, $user, $send_body, $page);
		}
		else 
		{
			$this->sendServerMsg($config, $user, $user, $send_body, $page);
		}
	}
}
return $this->result_self(0, "", array( "cash_no" => $cash_no ));
?>