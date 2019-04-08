<?php
/*
 * @ PHP 5.6
 * @ Decoder version : 1.0.0.1
 * @ Release on : 24.03.2018
 * @ Website    : http://EasyToYou.eu
 */

defined("IN_IA") or exit("Access Denied");
define("ROOT_PATH", IA_ROOT . "/addons/longbing_card/");
define("ADDON_PATH", IA_ROOT . "/addons/longbing_card/");
define("PP_DEBUG", false);
define("APP_NAME", "longbing_card");
is_file(ROOT_PATH . "/inc/we7.php") or exit("Access Denied Longbing");
require_once ROOT_PATH . "/inc/we7.php";
is_file(ROOT_PATH . "/inc/html2wxml/class.ToWXML.php") or exit("Access Denied Longbing ToWXML");
require_once ROOT_PATH . "/inc/html2wxml/class.ToWXML.php";
class Longbing_cardModuleWxapp extends WeModuleWxapp
{
    protected $errno = 0;
    protected $message = "";
    protected $data = array();
    protected $limit = 10;
    protected $redis_sup = false;
    protected $redis_server = false;
    protected $redis_sup_v2 = false;
    protected $redis_server_v2 = false;
    protected $redis_sup_v3 = false;
    protected $redis_server_v3 = false;
    public function __construct()
    {
        global $_GPC;
        global $_W;
        if (isset($_GPC["__input"]) && !empty($_GPC["__input"])) {
            foreach ($_GPC["__input"] as $k => $v) {
                $_GPC[$k] = $v;
            }
        }
        $check_load = "redis";
        if (extension_loaded($check_load)) {
            try {
                $config = $_W["config"]["setting"]["redis"];
                $redis_server = new Redis();
                $res = $redis_server->connect($config["server"], $config["port"]);
                if ($res) {
                    $this->redis_sup_v3 = true;
                    $this->redis_server_v3 = $redis_server;
                } else {
                    $this->redis_sup_v3 = false;
                    $this->redis_server_v3 = false;
                }
                if ($config && isset($config["requirepass"]) && $config["requirepass"]) {
                    $pas_res = $redis_server->auth($config["requirepass"]);
                    if (!$pas_res) {
                        $this->redis_sup_v3 = false;
                        $this->redis_server_v3 = false;
                    } else {
                        $this->redis_sup_v3 = true;
                        $this->redis_server_v3 = $redis_server;
                    }
                }
            } catch (Exception $e) {
                $this->redis_sup_v3 = false;
                $this->redis_server_v3 = false;
            }
        } else {
            $this->redis_sup_v3 = false;
            $this->redis_server_v3 = false;
        }
        $domainMd5 = md5($_SERVER["HTTP_HOST"]);
        if (!is_dir(IA_ROOT . "/data/tpl")) {
            mkdir(IA_ROOT . "/data/tpl");
        }
        if (!is_dir(IA_ROOT . "/data/tpl/web")) {
            mkdir(IA_ROOT . "/data/tpl/web");
        }
        if (is_file(IA_ROOT . "/data/tpl/web/" . $domainMd5 . "tplAuth.txt")) {
            $fileInfo = file_get_contents(IA_ROOT . "/data/tpl/web/" . $domainMd5 . "tplAuth.txt");
            if (!$fileInfo) {
                $this->checkExists($_W, $domainMd5);
            } else {
                $fileInfo = date("Y-m-d", $fileInfo);
                if ($fileInfo != date("Y-m-d")) {
                    $this->checkExists($_W, $domainMd5);
                }
            }
        } else {
            $this->checkExists($_W, $domainMd5);
        }
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");
        header("Access-Control-Allow-Headers:x-requested-with,content-type");
    }
    protected function checkExists($_W, $domainMd5)
    {
        @file_put_contents(IA_ROOT . "/data/tpl/web/" . $domainMd5 . "tplAuth.txt", @time());
        $checkExists = pdo_tableexists("longbing_cardauth2_config");
        if ($checkExists) {
            $auth_info = pdo_get("longbing_cardauth2_config", array("modular_id" => $_W["uniacid"]));
            $time = time();
            if ($auth_info && $auth_info["end_time"] < $time) {
                return $this->result(-2, "auth end, contact end", array());
            }
        }
    }
    protected function curlPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
    protected function curlPostTime($url, $data, $time = 1)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $time);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
    public function doPageCards()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        pdo_delete("longbing_card_collection", array("to_uid" => 0));
        pdo_delete("longbing_card_user_info", array("fans_id" => 0));
        if (!$uid) {
            return $this->result(-1, "fail user", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $where = array("uniacid" => $_W["uniacid"]);
        if ($this->redis_sup) {
            $redis_key = "longbing_card_companylist_" . $_W["uniacid"];
            $company = $this->redis_server->get($redis_key);
            if ($company) {
                $company = json_decode($company, true);
                $company[0]["from_redis"] = 1;
            }
        }
        $company = pdo_getall("longbing_card_company", array("uniacid" => $_W["uniacid"], "status" => 1));
        $where["uid"] = $uid;
        $where["to_uid !="] = $uid;
        $where["status"] = 1;
        $cards = pdo_getslice("longbing_card_collection", $where, $limit, $count, array(), "", array("id desc"));
        if (empty($cards)) {
            $list_card = pdo_getall("longbing_card_user_info", array("fans_id !=" => 0, "uniacid" => $_W["uniacid"], "status" => 1, "is_default" => 1), array(), "", array("top desc"));
            if (empty($list_card)) {
                $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => array(), "company" => $company);
                return $this->result(0, "", $data);
            }
            foreach ($list_card as $k => $v) {
                $user = $v;
                $user["avatar"] = tomedia($user["avatar"]);
                $job = pdo_get("longbing_card_job", array("id" => $v["job_id"]));
                $user["job_name"] = $job["name"];
                if ($v["from_uid"]) {
                    $userFrom = pdo_get("longbing_card_user_info", array("fans_id" => $v["from_uid"]));
                    $cards[$k]["shareBy"] = $userFrom["name"];
                }
                $info = pdo_get("longbing_card_user", array("id" => $v["fans_id"]));
                $i = $info;
                $message = pdo_getall("longbing_card_message", array("user_id" => $v["fans_id"], "target_id" => $uid, "uniacid" => $_W["uniacid"], "status" => 1));
                $cards[$k]["userInfo"] = $user;
                $cards[$k]["info"] = $info;
                $cards[$k]["type"] = "no";
                $cards[$k]["message"] = count($message);
                $cards[$k]["create_time"] = time();
                $cards[$k]["shareBy"] = "搜索";
            }
            $count = count($list_card);
        } else {
            $i = pdo_get("longbing_card_user", array("id" => $uid));
            if ($i["is_staff"] && $curr == 1) {
                $card_self = pdo_getall("longbing_card_collection", array("uid" => $uid, "to_uid" => $uid));
                if ($card_self) {
                    $card_tmp[0] = $card_self[0];
                    $cards = array_merge($card_tmp, $cards);
                }
            }
            foreach ($cards as $k => $v) {
                $user = pdo_get("longbing_card_user_info", array("fans_id" => $v["to_uid"], "uniacid" => $_W["uniacid"]));
                $user["avatar"] = tomedia($user["avatar"]);
                $images = $user["images"];
                $images = trim($images, ",");
                $images = explode(",", $images);
                $tmp = array();
                foreach ($images as $k2 => $v2) {
                    $tmpUrl = tomedia($v2);
                    array_push($tmp, $tmpUrl);
                }
                $user["images"] = $tmp;
                $job = pdo_get("longbing_card_job", array("id" => $user["job_id"]));
                $user["job_name"] = $job["name"];
                $cards[$k]["userInfo"] = $user;
                $cards[$k]["shareBy"] = "";
                $cards[$k]["type"] = "yes";
                $message = pdo_getall("longbing_card_message", array("user_id" => $v["to_uid"], "target_id" => $uid, "uniacid" => $_W["uniacid"], "status" => 1));
                if (!empty($i)) {
                    if ($i["is_group"]) {
                        $cards[$k]["shareBy"] = "群分享";
                    }
                    if ($i["type"] == 1) {
                        $cards[$k]["shareBy"] = "自定义码";
                    }
                    if ($i["type"] == 2) {
                        $cards[$k]["shareBy"] = "产品分享";
                    }
                    if ($i["type"] == 3) {
                        $cards[$k]["shareBy"] = "动态分享";
                    }
                }
                $cards[$k]["message"] = count($message);
                if ($v["from_uid"]) {
                    $userFrom = pdo_get("longbing_card_user_info", array("fans_id" => $v["from_uid"]));
                    $cards[$k]["shareBy"] = $userFrom["name"];
                }
            }
        }
        $i = pdo_get("longbing_card_user", array("id" => $uid));
        if ($i["is_staff"]) {
            $cardsTmp = array();
            foreach ($cards as $k => $v) {
                if ($v["to_uid"] == $uid) {
                    array_push($cardsTmp, $v);
                    break;
                }
            }
            foreach ($cards as $k => $v) {
                if ($v["to_uid"] != $uid) {
                    array_push($cardsTmp, $v);
                }
            }
            $cards = $cardsTmp;
        }
        foreach ($cards as $k => $v) {
            $cards[$k]["userInfo"]["myCompany"] = array();
            $cards[$k]["create_time2"] = date("Y-m-d H:i:s", $v["create_time"]);
            if ($v["userInfo"]["company_id"]) {
                foreach ($company as $k2 => $v2) {
                    if ($v["userInfo"]["company_id"] == $v2["id"]) {
                        $cards[$k]["userInfo"]["myCompany"] = $v2;
                    }
                }
            }
        }
        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $cards, "company" => $company);
        return $this->result(0, "", $data);
    }
    public function doPageCard()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $scene = $_GPC["scene"];
        if (!$scene) {
            $scene = 0;
        }
        $time = time();
        $this->checkEmpty();
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid) {
            return $this->result(-1, "fail pra", array());
        }
        if ($uid == $to_uid) {
            $check_is_staff = pdo_get("longbing_card_user", array("id" => $uid, "uniacid" => $_W["uniacid"]));
            if (empty($check_is_staff) || $check_is_staff["is_staff"] != 1) {
                return $this->result(-1, "fail", array());
            }
        }
        $from_uid = 0;
        if (isset($_GPC["from_uid"])) {
            $from_uid = $_GPC["from_uid"];
        }
        if (!$to_uid) {
            $have = pdo_get("longbing_card_collection", array("uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"]));
            if (empty($have)) {
                pdo_insert("longbing_card_collection", array("uniacid" => $_W["uniacid"], "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene));
            }
        } else {
            $have = pdo_get("longbing_card_collection", array("uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"]));
            if (empty($have)) {
                pdo_insert("longbing_card_collection", array("uniacid" => $_W["uniacid"], "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene));
            } else {
                if ($have["to_uid"] == 0) {
                    pdo_update("longbing_card_collection", array("to_uid" => $to_uid, "scene" => $scene), array("id" => $have["id"]));
                }
            }
        }
        $check = pdo_get("longbing_card_user_info", array("fans_id" => $to_uid, "uniacid" => $_W["uniacid"]));
        if (!$check || empty($check)) {
            return $this->result(-1, "fail not found card", array());
        }
        $data = array("user_id" => $uid, "to_uid" => $to_uid, "type" => 2, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "praise", "scene" => $_GPC["scene"], "create_time" => $time, "update_time" => $time);
        pdo_insert("longbing_card_count", $data);
        $info = $check;
        $info["avatar"] = tomedia($info["avatar"]);
        $info["voice"] = tomedia($info["voice"]);
        $images = $info["images"];
        $images = trim($images, ",");
        $images = explode(",", $images);
        $tmp = array();
        foreach ($images as $k2 => $v2) {
            $tmpUrl = tomedia($v2);
            array_push($tmp, $tmpUrl);
        }
        $info["images"] = $tmp;
        $job = pdo_get("longbing_card_job", array("id" => $info["job_id"], "uniacid" => $_W["uniacid"]));
        $info["job_name"] = $job["name"];
        $data["info"] = $info;
        $sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 2 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " && `user_id` != " . $to_uid . " GROUP BY user_id";
        $count = pdo_fetchall($sql);
        $data["peoples"] = count($count);
        $ids = "";
        foreach ($count as $k => $v) {
            $ids .= "," . $v["user_id"];
        }
        $ids = trim($ids, ",");
        $data["peoplesInfo"] = array();
        if ($ids) {
            if (strstr($ids, ",")) {
                $sql = "SELECT * FROM " . tablename("longbing_card_user") . " where `id` in (" . $ids . ") && `avatarUrl` != '' && uniacid = " . $_W["uniacid"];
            } else {
                $sql = "SELECT * FROM " . tablename("longbing_card_user") . " where `id` = " . $ids . " && `avatarUrl` != '' && uniacid = " . $_W["uniacid"];
            }
            $count = pdo_fetchall($sql);
            $data["peoplesInfo"] = $count;
        }
        $sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 3 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " GROUP BY user_id";
        $count = pdo_fetchall($sql);
        $data["thumbs_up"] = count($count);
        $sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 4 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " GROUP BY user_id";
        $count = pdo_fetchall($sql);
        $data["share"] = count($count);
        $isT = pdo_get("longbing_card_count", array("type" => 1, "user_id" => $uid, "to_uid" => $to_uid, "sign" => "praise"));
        $isT2 = pdo_get("longbing_card_count", array("type" => 3, "user_id" => $uid, "to_uid" => $to_uid, "sign" => "praise"));
        if ($isT) {
            $data["voiceThumbs"] = 1;
        } else {
            $data["voiceThumbs"] = 0;
        }
        if ($isT2) {
            $data["isThumbs"] = 1;
        } else {
            $data["isThumbs"] = 0;
        }
        return $this->result(0, "", $data);
    }
    public function doPageCardV2()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $scene = $_GPC["scene"];
        if (!$scene) {
            $scene = 0;
        }
        $time = time();
        $this->checkEmpty();
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid) {
            return $this->result(-1, "fail pra", array());
        }
        if ($uid == $to_uid) {
            $check_is_staff = pdo_get("longbing_card_user", array("id" => $uid, "uniacid" => $_W["uniacid"]));
            if (empty($check_is_staff) || $check_is_staff["is_staff"] != 1) {
                return $this->result(-1, "fail", array());
            }
        }
        $from_uid = 0;
        if (isset($_GPC["from_uid"])) {
            $from_uid = $_GPC["from_uid"];
        }
        if (!$to_uid) {
            $have = pdo_get("longbing_card_collection", array("uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"]));
            if (empty($have)) {
                pdo_insert("longbing_card_collection", array("uniacid" => $_W["uniacid"], "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene));
            }
        } else {
            $have = pdo_get("longbing_card_collection", array("uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"]));
            if (empty($have)) {
                pdo_insert("longbing_card_collection", array("uniacid" => $_W["uniacid"], "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene));
            } else {
                pdo_update("longbing_card_collection", array("to_uid" => $to_uid, "scene" => $scene, "status" => 1), array("id" => $have["id"]));
            }
        }
        $check = pdo_get("longbing_card_user_info", array("fans_id" => $to_uid, "uniacid" => $_W["uniacid"]));
        if (!$check || empty($check)) {
            return $this->result(-1, "fail not found card", array());
        }
        $data = array("user_id" => $uid, "to_uid" => $to_uid, "type" => 2, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "praise", "scene" => $_GPC["scene"], "create_time" => $time, "update_time" => $time);
        pdo_insert("longbing_card_count", $data);
        $info = $check;
        if ($info["avatar"]) {
            $tmp = $info["avatar"];
            $info["avatar_2"] = tomedia($tmp);
            $info["avatar"] = $this->transImage($info["avatar"]);
        }
        $info["voice"] = tomedia($info["voice"]);
        $images = $info["images"];
        $images = trim($images, ",");
        $images = explode(",", $images);
        $tmp = array();
        foreach ($images as $k2 => $v2) {
            $tmpUrl = tomedia($v2);
            array_push($tmp, $tmpUrl);
        }
        $info["images"] = $tmp;
        $job = pdo_get("longbing_card_job", array("id" => $info["job_id"], "uniacid" => $_W["uniacid"]));
        $info["job_name"] = $job["name"];
        $data["info"] = $info;
        $sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 2 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " && `user_id` != " . $to_uid . " GROUP BY user_id";
        $count = pdo_fetchall($sql);
        $data["peoples"] = count($count);
        $ids = "";
        foreach ($count as $k => $v) {
            if ($k == 8) {
                break;
            }
            $ids .= "," . $v["user_id"];
        }
        $ids = trim($ids, ",");
        $data["peoplesInfo"] = array();
        if ($ids) {
            if (strstr($ids, ",")) {
                $sql = "SELECT * FROM " . tablename("longbing_card_user") . " where `id` in (" . $ids . ") && `avatarUrl` != '' && uniacid = " . $_W["uniacid"];
            } else {
                $sql = "SELECT * FROM " . tablename("longbing_card_user") . " where `id` = " . $ids . " && `avatarUrl` != '' && uniacid = " . $_W["uniacid"];
            }
            $count = pdo_fetchall($sql);
            $data["peoplesInfo"] = $count;
        }
        $sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 3 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " GROUP BY user_id";
        $count = pdo_fetchall($sql);
        $data["thumbs_up"] = count($count);
        $sql = "SELECT user_id, count(*) FROM " . tablename("longbing_card_count") . " where `type` = 4 && `to_uid` = " . $to_uid . " && sign = 'praise' && uniacid = " . $_W["uniacid"] . " GROUP BY user_id";
        $count = pdo_fetchall($sql);
        $data["share"] = count($count);
        $isT = pdo_get("longbing_card_count", array("type" => 1, "user_id" => $uid, "sign" => "praise"));
        $isT2 = pdo_get("longbing_card_count", array("type" => 3, "user_id" => $uid, "sign" => "praise"));
        if ($isT) {
            $data["voiceThumbs"] = 1;
        } else {
            $data["voiceThumbs"] = 0;
        }
        if ($isT2) {
            $data["isThumbs"] = 1;
        } else {
            $data["isThumbs"] = 0;
        }
        return $this->result(0, "", $data);
    }
    public function doPageCardV3()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $scene = $_GPC["scene"];
        if (!$scene) {
            $scene = 0;
        }
        $time = time();
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid) {
            return $this->result(-1, "fail pra", array());
        }
        if ($uid == $to_uid) {
            $check_is_staff = pdo_get("longbing_card_user", array("id" => $uid, "uniacid" => $_W["uniacid"]));
            if (empty($check_is_staff) || $check_is_staff["is_staff"] != 1) {
                return $this->result(-1, "fail", array());
            }
        }
        $from_uid = 0;
        if (isset($_GPC["from_uid"])) {
            $from_uid = $_GPC["from_uid"];
        }
        if (!$to_uid) {
            $have = pdo_get("longbing_card_collection", array("uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"]));
            if (empty($have)) {
                pdo_insert("longbing_card_collection", array("uniacid" => $_W["uniacid"], "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene));
            }
        } else {
            $have = pdo_get("longbing_card_collection", array("uid" => $uid, "to_uid" => $to_uid, "uniacid" => $_W["uniacid"]));
            if (empty($have)) {
                pdo_insert("longbing_card_collection", array("uniacid" => $_W["uniacid"], "uid" => $uid, "to_uid" => $to_uid, "create_time" => time(), "update_time" => time(), "scene" => $scene));
            } else {
                pdo_update("longbing_card_collection", array("to_uid" => $to_uid, "scene" => $scene, "status" => 1), array("id" => $have["id"]));
            }
        }
        $check = pdo_get("longbing_card_user_info", array("fans_id" => $to_uid, "uniacid" => $_W["uniacid"]));
        if (!$check || empty($check)) {
            return $this->result(-1, "fail not found card", array());
        }
        if ($check["company_id"]) {
            $com = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"], "id" => $check["company_id"], "status" => 1));
            if (!$com) {
                $com = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"], "status" => 1));
            }
            $com["logo"] = $this->transImage($com["logo"]);
            $check["myCompany"] = $com;
        } else {
            $com = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"], "status" => 1));
            $com["logo"] = $this->transImage($com["logo"]);
            $check["myCompany"] = $com;
        }
        if (18 < mb_strlen($check["myCompany"]["addr"], "utf8")) {
            $check["myCompany"]["addrMore"] = mb_substr($check["myCompany"]["addr"], 0, 20, "UTF-8") . "...";
        } else {
            $check["myCompany"]["addrMore"] = $check["myCompany"]["addr"];
        }
        $data = array("user_id" => $uid, "to_uid" => $to_uid, "type" => 2, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "praise", "scene" => $_GPC["scene"], "create_time" => $time, "update_time" => $time);
        pdo_insert("longbing_card_count", $data);
        $info = $check;
        if ($info["avatar"]) {
            $tmp = $info["avatar"];
            $info["avatar_2"] = tomedia($tmp);
            $info["avatar"] = $this->transImage($info["avatar"]);
        }
        if ($info["my_video"]) {
            $info["my_video"] = tomedia($info["my_video"]);
        }
        if ($info["my_video_cover"]) {
            $info["my_video_cover"] = tomedia($info["my_video_cover"]);
        }
        $info["voice"] = tomedia($info["voice"]);
        $images = $info["images"];
        $images = trim($images, ",");
        $images = explode(",", $images);
        $tmp = array();
        foreach ($images as $k2 => $v2) {
            $tmpUrl = tomedia($v2);
            array_push($tmp, $tmpUrl);
        }
        $info["images"] = $tmp;
        $job = pdo_get("longbing_card_job", array("id" => $info["job_id"], "uniacid" => $_W["uniacid"]));
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
        $isT = pdo_get("longbing_card_count", array("type" => 1, "user_id" => $uid, "to_uid" => $to_uid, "sign" => "praise"));
        $isT2 = pdo_get("longbing_card_count", array("type" => 3, "user_id" => $uid, "to_uid" => $to_uid, "sign" => "praise"));
        if ($isT) {
            $data["voiceThumbs"] = 1;
        } else {
            $data["voiceThumbs"] = 0;
        }
        if ($isT2) {
            $data["isThumbs"] = 1;
        } else {
            $data["isThumbs"] = 0;
        }
        $info = pdo_get("longbing_card_user", array("uniacid" => $_W["uniacid"], "id" => $uid));
        if (!empty($info) && $info["is_staff"]) {
            $data["is_staff"] = 1;
            $data["is_boss"] = $info["is_boss"];
        } else {
            $data["is_staff"] = 0;
        }
        $extension = pdo_getall("longbing_card_extension", array("user_id" => $to_uid), array("goods_id"));
        if (empty($extension)) {
            $data["goods"] = array();
        } else {
            $ids = array();
            foreach ($extension as $k => $v) {
                array_push($ids, $v["goods_id"]);
            }
            $ids = implode(",", $ids);
            if (1 < count($extension)) {
                $ids = "(" . $ids . ")";
                $sql = "SELECT id,`name`,cover,price,status FROM " . tablename("longbing_card_goods") . " WHERE id IN " . $ids . " && status = 1 ORDER BY top DESC";
            } else {
                $sql = "SELECT id,`name`,cover,price,status FROM " . tablename("longbing_card_goods") . " WHERE id = " . $ids . " && status = 1 ORDER BY top DESC";
            }
            $goods = pdo_fetchall($sql);
            foreach ($goods as $k => $v) {
                if ($v["status"] == 1) {
                    $goods[$k]["cover"] = tomedia($v["cover"]);
                }
            }
            $data["goods"] = $goods;
        }
        $data["peoplesInfo"] = array();
        $view_count = pdo_fetchall("SELECT id, user_id FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $to_uid . " && user_id != " . $to_uid . " ORDER BY id DESC LIMIT 100");
        if (empty($view_count)) {
            $peoplesInfo = array();
        } else {
            if (count($view_count) == 1) {
                $peoplesInfo = pdo_getall("longbing_card_user", array("id" => $view_count[0]["user_id"]), array("id", "avatarUrl"));
            } else {
                $checkArr = array();
                $peoplesInfo = array();
                foreach ($view_count as $k => $v) {
                    if (in_array($v["user_id"], $checkArr)) {
                        continue;
                    }
                    if ($v["user_id"] == $to_uid) {
                        continue;
                    }
                    array_push($checkArr, $v["user_id"]);
                    $userInfo = pdo_get("longbing_card_user", array("id" => $v["user_id"]), array("id", "avatarUrl"));
                    if ($userInfo["avatarUrl"]) {
                        array_push($peoplesInfo, $userInfo);
                        if (count($peoplesInfo) == 8) {
                            break;
                        }
                    }
                }
            }
        }
        $data["peoplesInfo"] = $peoplesInfo;
        $info = pdo_get("longbing_card_user", array("id" => $to_uid, "uniacid" => $_W["uniacid"]));
        if ($info["qr_path"]) {
            $size = @filesize(ATTACHMENT_ROOT . "/" . $info["qr_path"]);
            if (51220 < $size) {
                $image = $this->transImage($info["qr_path"]);
                $data["qr"] = $image;
            } else {
                load()->func("file");
                if (!is_dir(ATTACHMENT_ROOT . "/" . "images")) {
                    mkdir(ATTACHMENT_ROOT . "/" . "images");
                }
                if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card")) {
                    mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card");
                }
                if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/")) {
                    mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/");
                }
                $destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
                $image = $destination_folder . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png";
                $path = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCard&is_qr=1";
                $res = $this->createQr($image, $path);
                if ($res != true) {
                    return $this->result(-1, "fail", array());
                }
                $image = tomedia("images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png");
                if (!strstr($image, "ttp")) {
                    $image = "https://" . $image;
                }
                pdo_update("longbing_card_user", array("qr_path" => "images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png"), array("id" => $to_uid));
                $image = $this->transImage($image);
            }
        } else {
            load()->func("file");
            if (!is_dir(ATTACHMENT_ROOT . "/" . "images")) {
                mkdir(ATTACHMENT_ROOT . "/" . "images");
            }
            if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card")) {
                mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card");
            }
            if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/")) {
                mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/");
            }
            $destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
            $image = $destination_folder . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png";
            $path = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCard&is_qr=1";
            $res = $this->createQr($image, $path);
            if ($res != true) {
                return $this->result(-2, "fail", array());
            }
            $image = tomedia("images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png");
            if (!strstr($image, "ttp")) {
                $image = "https://" . $image;
            }
            pdo_update("longbing_card_user", array("qr_path" => "images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png"), array("id" => $to_uid));
            $image = $this->transImage($image);
        }
        $data["qr"] = $image;
        $this->sendTplStaff($uid, $to_uid, 1, $_W["uniacid"]);
        return $this->result(0, "", $data);
    }
    public function doPagePeoplesInfo()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid) {
            return $this->result(-1, "fail pra", array());
        }
        $data["peoplesInfo"] = array();
        $view_count = pdo_fetchall("SELECT id, user_id FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $to_uid . " && user_id != " . $to_uid . " ORDER BY id DESC LIMIT 100");
        if (empty($view_count)) {
            $peoplesInfo = array();
        } else {
            if (count($view_count) == 1) {
                $peoplesInfo = pdo_getall("longbing_card_user", array("id" => $view_count[0]["user_id"]), array("id", "avatarUrl"));
            } else {
                $checkArr = array();
                $peoplesInfo = array();
                foreach ($view_count as $k => $v) {
                    if (in_array($v["user_id"], $checkArr)) {
                        continue;
                    }
                    if ($v["user_id"] == $to_uid) {
                        continue;
                    }
                    array_push($checkArr, $v["user_id"]);
                    $userInfo = pdo_get("longbing_card_user", array("id" => $v["user_id"]), array("id", "avatarUrl"));
                    if ($userInfo["avatarUrl"]) {
                        array_push($peoplesInfo, $userInfo);
                        if (count($peoplesInfo) == 8) {
                            break;
                        }
                    }
                }
            }
        }
        $data["peoplesInfo"] = $peoplesInfo;
        return $this->result(0, "", $data);
    }
    public function doPageThumbs()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        $type = $_GPC["type"];
        if ($type != 1 && $type != 3) {
            return $this->result(-1, "fail type", array());
        }
        if (!$to_uid) {
            return $this->result(-1, "fail id", array());
        }
        $check = pdo_get("longbing_card_count", array("type" => $type, "user_id" => $uid, "to_uid" => $to_uid, "sign" => "praise"));
        $result = false;
        $time = time();
        if (!empty($check)) {
            $result = pdo_delete("longbing_card_count", array("type" => $type, "user_id" => $uid, "to_uid" => $to_uid, "sign" => "praise"));
        } else {
            $data = array("user_id" => $uid, "to_uid" => $to_uid, "type" => $type, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "praise", "scene" => $_GPC["scene"], "create_time" => $time, "update_time" => $time);
            $result = pdo_insert("longbing_card_count", $data);
        }
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "fail type", array());
    }
    public function doPageCompany()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $info = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"]));
        $info["desc"] = tomedia($info["desc"]);
        $info["logo"] = tomedia($info["logo"]);
        $images = $info["culture"];
        $images = trim($images, ",");
        $images = explode(",", $images);
        $tmp = array();
        foreach ($images as $k => $v) {
            $src = tomedia($v);
            array_push($tmp, $src);
        }
        $info["culture"] = $tmp;
        $user = pdo_get("longbing_card_user_info", array("fans_id" => $uid, "is_staff" => 1, "status" => 1));
        if ($user && $user["company_id"]) {
            $company = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"], "id" => $user["company_id"]));
            if ($company) {
                $info["company"] = $company;
            }
        }
        return $this->result(0, "", $info);
    }
    public function doPageCompanyV2()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $info = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"]));
        $info["desc"] = tomedia($info["desc"]);
        $info["logo"] = $this->transImage($info["logo"]);
        $images = $info["culture"];
        $images = trim($images, ",");
        $images = explode(",", $images);
        $tmp = array();
        foreach ($images as $k2 => $v2) {
            $src = tomedia($v2);
            array_push($tmp, $src);
        }
        $info["culture"] = $tmp;
        return $this->result(0, "", $info);
    }
    public function doPageCompanyV3()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $info = pdo_getall("longbing_card_company", array("uniacid" => $_W["uniacid"], "status" => 1));
        foreach ($info as $k => $v) {
            $info[$k]["desc"] = tomedia($v["desc"]);
            $info[$k]["logo"] = $this->transImage($v["logo"]);
            $images = $v["culture"];
            $images = trim($images, ",");
            $images = explode(",", $images);
            $tmp = array();
            foreach ($images as $k2 => $v2) {
                $src = tomedia($v2);
                array_push($tmp, $src);
            }
            $info[$k]["culture"] = $tmp;
        }
        $data["list"] = $info;
        $user = pdo_get("longbing_card_user_info", array("fans_id" => $uid, "is_staff" => 1, "status" => 1));
        if ($user) {
            if ($user["company_id"]) {
                $company = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"], "id" => $user["company_id"]));
                if ($company) {
                    $company["logo"] = $this->transImage($company["logo"]);
                    $data["my"] = $company;
                } else {
                    $data["my"] = $info[0];
                }
            } else {
                $data["my"] = $info[0];
            }
        }
        return $this->result(0, "", $data);
    }
    public function doPageGoods()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid && $to_uid != 0) {
            return $this->result(-1, "fail pra", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $info = pdo_getslice("longbing_card_goods", array("uniacid" => $_W["uniacid"], "status" => 1), $limit, $count, array(), "", array("top desc"));
        foreach ($info as $k => $v) {
            $info[$k]["cover"] = tomedia($v["cover"]);
        }
        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $info);
        $this->insertView($uid, $to_uid, 1, $_W["uniacid"]);
        return $this->result(0, "", $data);
    }
    public function doPageGoodsDetail()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $id = $_GPC["id"];
        $user_id = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        if (!$user_id || !$to_uid) {
            return $this->result(-1, "fail pra", array());
        }
        if (!$id) {
            return $this->result(-1, "fail id", array());
        }
        $info = pdo_get("longbing_card_goods", array("uniacid" => $_W["uniacid"], "id" => $id));
        $info["cover"] = tomedia($info["cover"]);
        $images = $info["images"];
        $images = trim($images, ",");
        $images = explode(",", $images);
        $tmp = array();
        foreach ($images as $k => $v) {
            $src = tomedia($v);
            array_push($tmp, $src);
        }
        $info["images"] = $tmp;
        $info["collStatus"] = 0;
        $check = pdo_get("longbing_card_goods_collection", array("uniacid" => $_W["uniacid"], "user_id" => $user_id, "goods_id" => $id));
        if ($check) {
            $info["collStatus"] = 1;
        }
        pdo_update("longbing_card_goods", array("view_count" => $info["view_count"] + 1), array("id" => $info["id"]));
        $this->insertView($user_id, $to_uid, 2, $_W["uniacid"], $id);
        $info["content"] = $this->toWXml($info["content"]);
        return $this->result(0, "请求成功", $info);
    }
    public function doPageGoodsCollection()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $id = $_GPC["id"];
        $uid = $_GPC["user_id"];
        if (!$id) {
            return $this->result(-1, "fail id", array());
        }
        if (!$uid) {
            return $this->result(-1, "fail id", array());
        }
        $check = pdo_get("longbing_card_goods_collection", array("uniacid" => $_W["uniacid"], "user_id" => $uid, "goods_id" => $id));
        $result = false;
        $time = time();
        if ($check) {
            $result = pdo_delete("longbing_card_goods_collection", array("uniacid" => $_W["uniacid"], "user_id" => $uid, "goods_id" => $id));
        } else {
            $result = pdo_insert("longbing_card_goods_collection", array("user_id" => $uid, "uniacid" => $_W["uniacid"], "goods_id" => $id, "create_time" => $time, "update_time" => $time));
        }
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "fail", array());
    }
    public function doPageModularList()
    {
        global $_GPC;
        global $_W;
        $identification = $_GPC["identification"];
        $uniacid = $_W["uniacid"];
        if (!$identification) {
            return $this->result(-1, "fail ident", array());
        }
        $where = array("uniacid" => $uniacid, "id" => $identification);
        $modular = pdo_get("longbing_card_modular", $where);
        if (!$modular) {
            return $this->result(-2, "该内容不存在或已删除", array());
        }
        if ($modular["status"] == -1) {
            return $this->result(-2, "该内容已删除", array());
        }
        if ($modular["status"] != 1) {
            return $this->result(-2, "该内容已下架", array());
        }
        $table_name = $modular["table_name"];
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $list = pdo_getslice($table_name, array("uniacid" => $_W["uniacid"], "status" => 1, "modular_id" => $identification), $limit, $count, array(), "", array("top desc"));
        foreach ($list as $k => $v) {
            $list[$k]["cover"] = tomedia($v["cover"]);
            if ($modular["type"] == 7) {
                $list[$k]["video"] = tomedia($v["video"]);
            }
        }
        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $list, "table_name" => $table_name);
        return $this->result(0, "请求成功", $data);
    }
    public function doPageModularInfo()
    {
        global $_GPC;
        global $_W;
        $table_name = $_GPC["table_name"];
        $id = $_GPC["id"];
        $uniacid = $_W["uniacid"];
        if (!$table_name) {
            return $this->result(-1, "fail table", array());
        }
        if (!$id) {
            return $this->result(-1, "fail id", array());
        }
        $where = array("uniacid" => $uniacid, "id" => $id);
        $info = pdo_get($table_name, $where);
        if (!$info) {
            return $this->result(-2, "该内容不存在或已删除", array());
        }
        if ($info["status"] == -1) {
            return $this->result(-2, "该内容已删除", array());
        }
        if ($info["status"] != 1) {
            return $this->result(-2, "该内容已下架", array());
        }
        $where = array("uniacid" => $uniacid, "id" => $info["modular_id"]);
        $modular = pdo_get("longbing_card_modular", $where);
        if (!$modular) {
            return $this->result(-2, "该内容不存在或已删除", array());
        }
        if ($modular["status"] != 1) {
            return $this->result(-2, "该内容已下架", array());
        }
        $info["cover"] = tomedia($info["cover"]);
        if (isset($info["video"])) {
            $info["video"] = tomedia($info["video"]);
        }
        $info["content"] = $this->toWXml($info["content"]);
        $info["introduction"] = $this->toWXml($info["introduction"]);
        return $this->result(0, "", $info);
    }
    public function doPageTimeline()
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid && $to_uid != 0) {
            return $this->result(-1, "fail pra", array());
        }
        $this->insertView($uid, $to_uid, 3, $_W["uniacid"]);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $curr = $_GPC["page"];
        }
        $start = ($curr - 1) * 10;
        $end = $curr * 10;
        $limit = $start . "," . $end;
        if ($this->redis_sup_v3) {
            $redis_key = "longbing_card_timeline_" . $to_uid . "_" . $curr . "_" . $uniacid;
            $data = $this->redis_server_v3->get($redis_key);
            if ($data) {
                $data = json_decode($data, true);
                $data["from_redis"] = 1;
                return $this->result(0, "", $data);
            }
        }
        $where = array("uniacid" => $uniacid, "status" => 1, "user_id in" => "0, " . $to_uid);
        $ins = "(0, " . $to_uid . ")";
        $list = pdo_fetchall("SELECT id,title,cover,create_time,user_id,`type`, url_type FROM " . tablename("longbing_card_timeline") . " where uniacid = " . $uniacid . " && status = 1 && user_id IN " . $ins . " ORDER BY top DESC, id DESC LIMIT " . $limit);
        $count = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_timeline") . " where uniacid = " . $uniacid . " && status = 1 && user_id IN " . $ins . " ");
        foreach ($list as $k => $v) {
            $info = array();
            if ($v["user_id"]) {
                $info = pdo_get("longbing_card_user_info", array("fans_id" => $v["user_id"]));
                $info["avatar"] = tomedia($info["avatar"]);
            }
            $list[$k]["user_info"] = $info;
            $list[$k]["is_thumbs"] = 0;
            $check = pdo_get("longbing_card_timeline_thumbs", array("user_id" => $uid, "timeline_id" => $v["id"]));
            if ($check) {
                $list[$k]["is_thumbs"] = 1;
            }
            $thumbs = pdo_getall("longbing_card_timeline_thumbs", array("timeline_id" => $v["id"]));
            foreach ($thumbs as $k2 => $v2) {
                $thumbs[$k2]["user"] = pdo_get("longbing_card_user", array("id" => $v2["user_id"]));
            }
            $list[$k]["thumbs"] = $thumbs;
            $comments = pdo_getall("longbing_card_timeline_comment", array("timeline_id" => $v["id"], "status" => 1));
            foreach ($comments as $k2 => $v2) {
                $comments[$k2]["user"] = pdo_get("longbing_card_user", array("id" => $v2["user_id"]));
            }
            $list[$k]["comments"] = $comments;
            $list[$k]["cover"] = tomedia($v["cover"]);
            $images = $v["cover"];
            $images = trim($images, ",");
            $images = explode(",", $images);
            $tmp = array();
            foreach ($images as $k2 => $v2) {
                $src = tomedia($v2);
                array_push($tmp, $src);
            }
            $list[$k]["cover"] = $tmp;
            if ($v["type"]) {
                $content = pdo_get("longbing_card_timeline", array("id" => $v["id"]), array("content"));
                $list[$k]["content"] = $content["content"];
                if ($v["type"] == 1) {
                    $list[$k]["content"] = tomedia($content["content"]);
                }
            }
        }
        $companyList = pdo_getall("longbing_card_company", array("uniacid" => $uniacid, "status" => 1));
        if (!$companyList) {
            $companyList = array(array());
        }
        $company = $companyList[0];
        if ($to_uid) {
            $user_info = pdo_get("longbing_card_user_info", array("fans_id" => $to_uid));
            if ($user_info) {
                foreach ($companyList as $k => $v) {
                    if ($v["id"] == $user_info["company_id"]) {
                        $company = $v;
                        break;
                    }
                }
            }
        }
        $company["logo"] = tomedia($company["logo"]);
        $company["desc"] = tomedia($company["desc"]);
        $data = array("page" => $curr, "total_page" => ceil(count($count) / 10), "list" => $list);
        $data["timeline_company"] = $company;
        if ($this->redis_sup_v3) {
            $redis_key = "longbing_card_timeline_" . $to_uid . "_" . $curr . "_" . $uniacid;
            $this->redis_server_v3->set($redis_key, json_encode($data));
            $this->redis_server_v3->EXPIRE($redis_key, 30 * 60);
        }
        return $this->result(0, "", $data);
    }
    public function doPageTimelineNew()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        if (!$uid || !$id) {
            return $this->result(-1, "fail par", array());
        }
        $thumbs = pdo_getall("longbing_card_timeline_thumbs", array("timeline_id" => $id));
        foreach ($thumbs as $k2 => $v2) {
            $thumbs[$k2]["user"] = pdo_get("longbing_card_user", array("id" => $v2["user_id"]));
        }
        $comments = pdo_getall("longbing_card_timeline_comment", array("timeline_id" => $id));
        foreach ($comments as $k2 => $v2) {
            $comments[$k2]["user"] = pdo_get("longbing_card_user", array("id" => $v2["user_id"]));
        }
        return $this->result(0, "", array("thumbs" => $thumbs, "comments" => $comments));
    }
    public function doPageTimelineThumbs()
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid && $to_uid != 0) {
            return $this->result(-1, "fail pra", array());
        }
        if (!$id) {
            return $this->result(-1, "fail id", array());
        }
        if (!$uid) {
            return $this->result(-1, "fail id", array());
        }
        if ($this->redis_sup_v3) {
            for ($i = 0; $i < 10; $i++) {
                $redis_key = "longbing_card_timeline_" . $to_uid . "_" . $i . "_" . $uniacid;
                $this->redis_server_v3->set($redis_key, "");
            }
        }
        $check = pdo_get("longbing_card_timeline_thumbs", array("uniacid" => $_W["uniacid"], "user_id" => $uid, "timeline_id" => $id));
        $result = false;
        $time = time();
        if ($check) {
            $result = pdo_delete("longbing_card_timeline_thumbs", array("uniacid" => $uniacid, "user_id" => $uid, "timeline_id" => $id));
        } else {
            $result = pdo_insert("longbing_card_timeline_thumbs", array("user_id" => $uid, "uniacid" => $uniacid, "timeline_id" => $id, "create_time" => $time, "update_time" => $time));
        }
        $this->insertView($uid, $to_uid, 4, $_W["uniacid"]);
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "fail", array());
    }
    public function doPageTimelineComment()
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        $content = $_GPC["content"];
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid && $to_uid != 0) {
            return $this->result(-1, "fail pra", array());
        }
        if (!$id) {
            return $this->result(-1, "fail id", array());
        }
        if (!$uid) {
            return $this->result(-1, "fail id", array());
        }
        if (!$content) {
            return $this->result(-1, "fail content", array());
        }
        if ($this->redis_sup_v3) {
            for ($i = 0; $i < 10; $i++) {
                $redis_key = "longbing_card_timeline_" . $to_uid . "_" . $i . "_" . $uniacid;
                $this->redis_server_v3->set($redis_key, "");
            }
        }
        $result = false;
        $time = time();
        $result = pdo_insert("longbing_card_timeline_comment", array("user_id" => $uid, "uniacid" => $uniacid, "content" => $content, "timeline_id" => $id, "create_time" => $time, "update_time" => $time));
        $this->insertView($uid, $to_uid, 5, $_W["uniacid"], $id);
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "fail", array());
    }
    public function doPageTimelineDetail()
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid && $to_uid != 0) {
            return $this->result(-1, "fail pra", array());
        }
        if (!$id) {
            return $this->result(-1, "fail id", array());
        }
        if (!$uid) {
            return $this->result(-1, "fail id", array());
        }
        $info = pdo_get("longbing_card_timeline", array("id" => $id));
        if (!$info) {
            return $this->result(-2, "该内容不存在或已删除", array());
        }
        if ($info["status"] == -1) {
            return $this->result(-2, "该内容已删除", array());
        }
        if ($info["status"] != 1) {
            return $this->result(-2, "该内容已下架", array());
        }
        $arr = explode(",", $info["cover"]);
        $tmp = array();
        foreach ($arr as $k => $v) {
            array_push($tmp, tomedia($v));
        }
        $info["cover"] = $tmp;
        if ($info["user_id"]) {
            $user = pdo_get("longbing_card_user_info", array("fans_id" => $info["user_id"]));
            $user["avatar"] = tomedia($user["avatar"]);
            $info["info"] = $user;
        }
        $this->insertView($uid, $to_uid, 7, $_W["uniacid"], $id);
        $content = $info["content"];
        if ($info["type"] == 1) {
            $info["content"] = tomedia($info["content"]);
        } else {
            if ($info["type"] == 2) {
            } else {
                $info["content"] = $this->toWXml($content);
            }
        }
        return $this->result(0, "", $info);
    }
    protected function toWXml($content, $is_decode = true)
    {
        $this->cross();
        if ($is_decode) {
            $content = htmlspecialchars_decode($content);
        }
        if ($content != strip_tags($content)) {
        } else {
            $content = "<p><span style=\"color: rgb(0, 0, 0);\">" . $content . "</span></p>";
        }
        $towxml = new ToWXML();
        $json = $towxml->towxml($content, array("type" => "html", "highlight" => true, "linenums" => true, "imghost" => NULL, "encode" => false, "highlight_languages" => array("html", "js", "php", "css")));
        return $json;
    }
    public function doPageQr()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        $info = pdo_get("longbing_card_user", array("id" => $to_uid));
        if (empty($info)) {
            return $this->result(-1, "fail", array());
        }
        if ($info["qr_path"]) {
            $size = @filesize(ATTACHMENT_ROOT . "/" . $info["qr_path"]);
            if (51220 < $size) {
                $image = $this->transImage($info["qr_path"]);
                if ($image != "https://" && $image != "http://") {
                    return $this->result(0, "fail", array("image" => $image));
                }
            }
        }
        if (!$to_uid && $to_uid !== 0 && $to_uid !== "0") {
            return $this->result(-1, "fail", array());
        }
        load()->func("file");
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images");
        }
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card");
        }
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/");
        }
        $destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
        $image = $destination_folder . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png";
        $path = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCard&is_qr=1";
        $res = $this->createQr($image, $path);
        if ($res != true) {
            return $this->result(-1, "fail", array());
        }
        $image = tomedia("images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png");
        if (!strstr($image, "ttp")) {
            $image = "https://" . $image;
        }
        pdo_update("longbing_card_user", array("qr_path" => "images" . "/longbing_card/" . $_W["uniacid"] . "/" . $_W["uniacid"] . "-" . $to_uid . "qr.png"), array("id" => $to_uid));
        $image = $this->transImage($image);
        return $this->result(0, "fail", array("image" => $image));
    }
    public function doPageGoodsQr()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        $id = $_GPC["id"];
        $info = pdo_get("longbing_card_goods", array("id" => $id));
        if (empty($info)) {
            return $this->result(-1, "fail", array());
        }
        if (!$to_uid && $to_uid !== 0 && $to_uid !== "0") {
            return $this->result(-1, "fail", array());
        }
        load()->func("file");
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images");
        }
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card");
        }
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/");
        }
        $destination_folder = "images" . "/longbing_card/" . $_W["uniacid"];
        $image = $destination_folder . "/" . $_W["uniacid"] . "-goods-" . $id . "qr.png";
        $image2 = $destination_folder . "/" . $_W["uniacid"] . "-goods-" . $id . ".png";
        if (file_exists(ATTACHMENT_ROOT . $image)) {
            $size = @filesize(ATTACHMENT_ROOT . $image);
            if ($size < 51220) {
                $path = "longbing_card/pages/shop/detail/detail?id=" . $id . "&to_uid=" . $to_uid;
                $res = $this->createQr(ATTACHMENT_ROOT . $image, $path);
                if ($res != true) {
                    return $this->result(-1, "fail", array());
                }
            }
        } else {
            $path = "longbing_card/pages/shop/detail/detail?id=" . $id . "&to_uid=" . $to_uid;
            $res = $this->createQr(ATTACHMENT_ROOT . $image, $path);
            if ($res != true) {
                return $this->result(-1, "fail", array());
            }
        }
        $url = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $image;
        if (file_exists(ATTACHMENT_ROOT . $image2)) {
            $size2 = @filesize(ATTACHMENT_ROOT . $image2);
            if ($size < 51220) {
                $path = tomedia($info["cover"]);
                $files = file_get_contents($path);
                file_put_contents(ATTACHMENT_ROOT . $image2, $files);
            }
        } else {
            $path = tomedia($info["cover"]);
            $files = file_get_contents($path);
            file_put_contents(ATTACHMENT_ROOT . $image2, $files);
        }
        $urlCover = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $image2;
        return $this->result(0, "fail", array("image" => $url, "cover" => $urlCover));
    }
    protected function createQr($image, $path)
    {
        load()->func("file");
        global $_GPC;
        global $_W;
        $account_api = WeAccount::create();
        $response = $account_api->getCodeLimit($path, 430, array("auto_color" => false, "line_color" => array("r" => "0", "g" => "0", "b" => "0")));
        if (!is_error($response)) {
            $res = file_put_contents($image, $response);
            return $res;
        }
        $cachekey = cache_system_key("accesstoken_key", array("key" => $_W["account"]["key"]));
        cache_delete($cachekey);
        return false;
    }
    public function doPageRecord()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        if ($uid == $to_uid || $to_uid == 0) {
            return $this->result(0, "", array());
        }
        if ($to_uid && 0 < $to_uid) {
            $time = time();
            $data = array("user_id" => $uid, "to_uid" => $to_uid, "type" => 4, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "praise", "scene" => $_GPC["scene"], "create_time" => $time, "update_time" => $time);
            $result = pdo_insert("longbing_card_count", $data);
            if ($result) {
                return $this->result(0, "", array());
            }
            return $this->result(0, "", array());
        }
        return $this->result(0, "", array());
    }
    public function doPageCopyRecord()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        $type = $_GPC["type"];
        if ($uid == $to_uid || $to_uid == 0 || !$type) {
            return $this->result(-1, "", array());
        }
        if ($to_uid && 0 < $to_uid) {
            $time = time();
            $data = array("user_id" => $uid, "to_uid" => $to_uid, "type" => $type, "uniacid" => $_W["uniacid"], "target" => "", "sign" => "copy", "scene" => $_GPC["scene"], "create_time" => $time, "update_time" => $time);
            $result = pdo_insert("longbing_card_count", $data);
            if ($result) {
                return $this->result(0, "", array());
            }
        }
        return $this->result(-1, "", array());
    }
    public function doPageFormid()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $formId = $_GPC["formId"];
        if (!$uid || !$formId) {
            return $this->result(-1, "1", array());
        }
        if ($formId == "the formId is a mock one") {
            return $this->result(0, "", array());
        }
        $time = time();
        $data = array("user_id" => $uid, "uniacid" => $_W["uniacid"], "formId" => $formId, "create_time" => $time, "update_time" => $time);
        $result = pdo_insert("longbing_card_formId", $data);
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "2", array());
    }
    public function doPageImage()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        $imgUrl = $_GPC["imgUrl"];
        load()->func("file");
        $res = file_remote_attach_fetch($imgUrl);
        $res = tomedia($res);
        $res = str_replace("ttp://", "ttps://", $res);
        if (!strstr($res, "ttps://")) {
            $res = "https://" . $res;
        }
        return $this->result(0, "fail", array("image" => $res));
    }
    public function doPagePhone()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        $uniacid = $_W["uniacid"];
        $encryptedData = $_GPC["encryptedData"];
        $iv = $_GPC["iv"];
        $info = pdo_get("longbing_card_user_phone", array("user_id" => $uid, "uniacid" => $uniacid));
        if ($info) {
            return $this->result(0, "fail", array("phone" => $info["phone"], "new" => 3, "iv" => $iv));
        }
        if (!$uid || !$to_uid) {
            return $this->result(-1, "", array());
        }
        $appid = $_W["account"]["key"];
        $appsecret = $_W["account"]["secret"];
        $check_sk = pdo_get("longbing_card_user_sk", array("user_id" => $uid));
        if (!$check_sk) {
            return $this->result(-1, "need login", array());
        }
        $session_key = $check_sk["sk"];
        include_once "wxBizDataCrypt.php";
        $pc = new WXBizDataCrypt($appid, $session_key);
        $errCode = $pc->decryptData($encryptedData, $iv, $data);
        if ($errCode == 0) {
            $data = json_decode($data, true);
            $phone = $data["purePhoneNumber"];
        } else {
            $errCode = $pc->decryptData($encryptedData, $iv, $data);
            if ($errCode == 0) {
                $data = json_decode($data, true);
                $phone = $data["purePhoneNumber"];
            } else {
                return $this->result(-1, $errCode, array("sec" => 1, "iv" => $iv));
            }
        }
        $time = time();
        $data = array("user_id" => $uid, "to_uid" => $to_uid, "phone" => $phone, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time);
        $result = pdo_insert("longbing_card_user_phone", $data);
        if ($result) {
            return $this->result(0, "", array("phone" => $phone, "new" => 3, "iv" => $iv));
        }
        return $this->result(-1, "1", array("new" => 3, "res" => $result, "iv" => $iv));
    }
    public function doPageUserPhone()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $result = pdo_get("longbing_card_user_phone", array("user_id" => $uid, "uniacid" => $uniacid));
        if ($result) {
            return $this->result(0, "", $result);
        }
        return $this->result(0, "fail", array());
    }
    public function doPageAiTime()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        @pdo_delete("longbing_card_count", array("to_uid" => 0));
        $staff_id = $_GPC["staff_id"];
        if ($staff_id && $staff_id != "undefined") {
            $uid = $staff_id;
        }
        if (!$uid) {
            return $this->result(-1, "1", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $info = pdo_getslice("longbing_card_count", array("uniacid" => $uniacid, "to_uid" => $uid, "user_id !=" => $uid), $limit, $count, array(), "", array("id desc"));
        foreach ($info as $k => $v) {
            $user = pdo_get("longbing_card_user", array("id" => $v["user_id"]));
            $client = pdo_get("longbing_card_client_info", array("user_id" => $v["user_id"], "staff_id" => $uid));
            $info[$k]["user"] = $user;
            $info[$k]["name"] = $client["name"] ? $client["name"] : $user["nickName"];
            $phone = pdo_get("longbing_card_user_phone", array("user_id" => $v["user_id"]));
            $info[$k]["phone"] = $phone ? $phone["phone"] : "";
            $mark = pdo_get("longbing_card_user_mark", array("user_id" => $v["user_id"], "staff_id" => $uid, "mark" => 2));
            $info[$k]["mark"] = $mark ? $mark["mark"] : 0;
            if ($v["target"] && $v["sign"] != "order") {
                $lists = pdo_getall("longbing_card_count", array("uniacid" => $uniacid, "to_uid" => $uid, "sign" => $v["sign"], "type" => $v["type"], "id <=" => $v["id"], "user_id" => $v["user_id"], "target" => $v["target"]), array(), "", "id asc");
            } else {
                $lists = pdo_getall("longbing_card_count", array("uniacid" => $uniacid, "to_uid" => $uid, "sign" => $v["sign"], "type" => $v["type"], "id <=" => $v["id"], "user_id" => $v["user_id"]), array(), "", "id asc");
            }
            $info[$k]["count"] = count($lists);
            if ($v["sign"] == "view" && $v["type"] == 2 || $v["sign"] == "view" && $v["type"] == 7) {
                if ($v["type"] == 2) {
                    $target_info = pdo_get("longbing_card_goods", array("id" => $v["target"]));
                    $info[$k]["target_name"] = $target_info["name"];
                } else {
                    $target_info = pdo_get("longbing_card_timeline", array("id" => $v["target"]));
                    $info[$k]["target_name"] = $target_info["title"];
                }
            }
            if ($v["sign"] == "view" && ($v["type"] == 8 || $v["type"] == 9 || $v["type"] == 10)) {
                $target_info = pdo_get("longbing_card_timeline", array("id" => $v["target"]));
                $info[$k]["target_name"] = $target_info["title"];
            }
            if ($v["sign"] == "order") {
                $target_info = pdo_get("longbing_card_shop_order_item", array("order_id" => $v["target"]));
                $target_info2 = pdo_get("longbing_card_shop_order", array("id" => $v["target"]));
                $info[$k]["target_name"] = $target_info["name"];
                $info[$k]["order"] = $target_info2["transaction_id"];
            }
        }
        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $info);
        return $this->result(0, "fail", $data);
    }
    public function doPageAiBehaviorHeader()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        $type = $_GPC["type"];
        if (!$uid) {
            return $this->result(-1, "1", array());
        }
        $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
        if ($type == 2) {
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
        }
        $view_goods_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && create_time > " . $beginTime . " && uniacid = " . $uniacid . " && `type` = 2 && sign='view'");
        $view_goods_count = $view_goods_count[0]["count(to_uid)"];
        $view_web_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && create_time > " . $beginTime . " && uniacid = " . $uniacid . " && `type` = 6 && sign='view'");
        $view_web_count = $view_web_count[0]["count(to_uid)"];
        $copy_wechat_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && create_time > " . $beginTime . " && uniacid = " . $uniacid . " && `type` = 4 && sign='copy'");
        $copy_wechat_count = $copy_wechat_count[0]["count(to_uid)"];
        $share_card_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && create_time > " . $beginTime . " && uniacid = " . $uniacid . " && `type` = 4 && sign='praise'");
        $share_card_count = $share_card_count[0]["count(to_uid)"];
        $data["view_goods_count"] = $view_goods_count;
        $data["view_web_count"] = $view_web_count;
        $data["copy_wechat_count"] = $copy_wechat_count;
        $data["share_card_count"] = $share_card_count;
        return $this->result(0, "fail", $data);
    }
    public function doPageAiBehaviorOther()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        $type = $_GPC["type"];
        if (!$uid) {
            return $this->result(-1, "1", array());
        }
        $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
        if ($type == 2) {
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
        }
        $view_card_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 2 && user_id != " . $uid . " && sign='praise' && create_time > " . $beginTime);
        $view_card_count = $view_card_count[0]["count(to_uid)"];
        $view_timeline_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 7 && sign='view' && create_time > " . $beginTime);
        $view_timeline_count = $view_timeline_count[0]["count(to_uid)"];
        $phone_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_user_phone") . " where to_uid = " . $uid . " && uniacid = " . $uniacid . " && create_time > " . $beginTime);
        $phone_count = $phone_count[0]["count(to_uid)"];
        $ask_goods_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . "  where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 8 && sign='copy' && create_time > " . $beginTime);
        $ask_goods_count = $ask_goods_count[0]["count(to_uid)"];
        $save_phone_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . "  where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 1 && sign='copy' && create_time > " . $beginTime);
        $save_phone_count = $save_phone_count[0]["count(to_uid)"];
        $thumbs_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 3 && user_id != " . $uid . " && sign='praise' && create_time > " . $beginTime);
        $thumbs_count = $thumbs_count[0]["count(to_uid)"];
        $call_phone_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . "  where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 2 && sign='copy' && create_time > " . $beginTime);
        $call_phone_count = $call_phone_count[0]["count(to_uid)"];
        $play_voice_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 9 && sign='copy' && create_time > " . $beginTime);
        $play_voice_count = $play_voice_count[0]["count(to_uid)"];
        $copy_email_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 5 && sign='copy' && create_time > " . $beginTime);
        $copy_email_count = $copy_email_count[0]["count(to_uid)"];
        $data["view_card_count"] = $view_card_count;
        $data["view_timeline_count"] = $view_timeline_count;
        $data["phone_count"] = $phone_count;
        $data["ask_goods_count"] = $ask_goods_count;
        $data["save_phone_count"] = $save_phone_count;
        $data["thumbs_count"] = $thumbs_count;
        $data["call_phone_count"] = $call_phone_count;
        $data["play_voice_count"] = $play_voice_count;
        $data["copy_email_count"] = $copy_email_count;
        return $this->result(0, "fail", $data);
    }
    public function doPageChat()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        if (!$uid) {
            return $this->result(-1, "1", array());
        }
        $chat = pdo_fetchall("SELECT a.id,a.user_id,a.target_id,a.create_time,b.content as last_message,b.create_time as last_time,b.message_type as `type` FROM " . tablename("longbing_card_chat") . " a LEFT JOIN " . tablename("longbing_card_message") . " b ON b.chat_id = a.id where (a.user_id = " . $uid . " && a.target_id != " . $uid . ") OR (a.user_id != " . $uid . " && a.target_id = " . $uid . ") ORDER BY last_time DESC");
        $tmp1 = array();
        $tmp2 = array();
        foreach ($chat as $index => $item) {
            if (in_array($item["id"], $tmp1)) {
                continue;
            }
            array_push($tmp1, $item["id"]);
            array_push($tmp2, $item);
        }
        $chat = $tmp2;
        foreach ($chat as $k => $v) {
        }
        $tmp = array();
        foreach ($chat as $k => $v) {
            if ($v["last_message"]) {
                array_push($tmp, $v);
            }
        }
        array_multisort(array_column($tmp, "last_time"), SORT_DESC, $tmp);
        $limit = array(1, 15);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $offset = ($curr - 1) * 15;
        $count = count($tmp);
        $array = array_slice($tmp, $offset, 15);
        $user_id_arr = array();
        foreach ($array as $index => $item) {
            array_push($user_id_arr, $item["user_id"]);
        }
        $user_id_arr = array_unique($user_id_arr);
        $user_arr = pdo_getall("longbing_card_user", array("id in" => $user_id_arr), array("id", "nickName", "avatarUrl"));
        $phone_arr = pdo_getall("longbing_card_user_phone", array("user_id in" => $user_id_arr));
        foreach ($array as $index => $item) {
            if ($item["user_id"] == $uid) {
                $tid = $item["target_id"];
            } else {
                $tid = $item["user_id"];
            }
            $message_not_read_count = pdo_fetchall("SELECT count(chat_id),create_time FROM " . tablename("longbing_card_message") . " where chat_id = " . $item["id"] . " && status = 1 && target_id = " . $uid . " ORDER BY create_time");
            $array[$index]["message_not_read_count"] = $message_not_read_count[0]["count(chat_id)"];
            $array[$index]["user"] = array();
            $array[$index]["phone"] = 0;
            foreach ($user_arr as $k => $v) {
                if ($tid == $v["id"]) {
                    $array[$index]["user"] = $v;
                    break;
                }
            }
            $user222222222 = pdo_get("longbing_card_user", array("id" => $tid), array("id", "nickName", "avatarUrl"));
            $array[$index]["user"] = $user222222222;
            foreach ($phone_arr as $k => $v) {
                if ($tid == $v["user_id"]) {
                    $array[$index]["phone"] = $v["phone"];
                    break;
                }
            }
        }
        $start = pdo_getall("longbing_card_start", array("staff_id" => $uid), array("user_id"));
        foreach ($array as $index => $item) {
            if ($item["user_id"] == $uid) {
                $tid = $item["target_id"];
            } else {
                $tid = $item["user_id"];
            }
            $array[$index]["start"] = 0;
            foreach ($start as $k => $v) {
                if ($v["user_id"] == $tid) {
                    $array[$index]["start"] = 1;
                    break;
                }
            }
        }
        $adminMsg = pdo_getall("longbing_card_group_sending", array("staff_id" => 0, "uniacid" => $uniacid), array(), "", array("id desc"));
        if ($adminMsg) {
            $adminMsg = $adminMsg[0];
        }
        $data = array("page" => $curr, "total_page" => ceil($count / 15), "list" => $array, "last" => $adminMsg);
        return $this->result(0, "fail", $data);
    }
    public function doPageMessages()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $chat_id = $_GPC["chat_id"];
        $uniacid = $_W["uniacid"];
        if (!$uid || !$chat_id) {
            return $this->result(-1, "1", array());
        }
        $create_time = false;
        if (isset($_GPC["create_time"])) {
            $create_time = $_GPC["create_time"];
        }
        if ($create_time) {
            $messages = pdo_fetchall("SELECT id,user_id,target_id,create_time,content,status,message_type as `type` FROM " . tablename("longbing_card_message") . " WHERE chat_id = " . $chat_id . " && id < " . $create_time . " ORDER BY id DESC LIMIT 10");
        } else {
            $messages = pdo_fetch("SELECT id,user_id,target_id,create_time,content,status,message_type as `type` FROM " . tablename("longbing_card_message") . " WHERE chat_id = " . $chat_id . " && target_id = " . $uid . " && status = 1 ORDER BY id ASC LIMIT 1");
            if (empty($messages)) {
                $messages = pdo_fetchall("SELECT id,user_id,target_id,create_time,content,status,message_type as `type` FROM " . tablename("longbing_card_message") . " WHERE chat_id = " . $chat_id . " ORDER BY create_time DESC LIMIT 10");
            } else {
                $messages = pdo_fetchall("SELECT id,user_id,target_id,create_time,content,status,message_type as `type` FROM " . tablename("longbing_card_message") . " WHERE chat_id = " . $chat_id . " && id >= " . $messages["id"] . " ORDER BY create_time DESC");
                pdo_update("longbing_card_message", array("status" => 2), array("chat_id" => $chat_id, "target_id" => $uid));
            }
        }
        $create_time = 0;
        if ($messages) {
            $create_time = $messages[count($messages) - 1]["id"];
            $create_time2 = $messages[count($messages) - 1]["create_time"];
        }
        if (LONGBING_AUTH_MESSAGE) {
            $b = mktime(0, 0, 0, date("m"), date("d") - LONGBING_AUTH_MESSAGE, date("Y"));
            if ($create_time2 < $b && 0 < $create_time2) {
                $messages = array();
            }
        }
        foreach ($messages as $index => $item) {
            if ($item["type"] == "") {
                $messages[$index]["type"] = "text";
            }
        }
        $data = array("list" => $messages, "create_time" => $create_time);
        return $this->result(0, "", $data);
    }
    public function doPageChatId()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        $uniacid = $_W["uniacid"];
        if (!$uid || !$to_uid) {
            return $this->result(-1, "1", array());
        }
        $check1 = pdo_get("longbing_card_chat", array("user_id" => $uid, "target_id" => $to_uid));
        if ($check1) {
            $user_info = pdo_get("longbing_card_user", array("id" => $uid), array("nickName", "avatarUrl"));
            $target_info = pdo_get("longbing_card_user", array("id" => $to_uid), array("nickName", "avatarUrl"));
            return $this->result(0, "", array("chat_id" => $check1["id"], "user_info" => $user_info, "target_info" => $target_info));
        }
        $check2 = pdo_get("longbing_card_chat", array("user_id" => $to_uid, "target_id" => $uid));
        if ($check2) {
            $user_info = pdo_get("longbing_card_user", array("id" => $uid), array("nickName", "avatarUrl"));
            $target_info = pdo_get("longbing_card_user", array("id" => $to_uid), array("nickName", "avatarUrl"));
            return $this->result(0, "", array("chat_id" => $check2["id"], "user_info" => $user_info, "target_info" => $target_info));
        }
        $data = array("user_id" => $uid, "target_id" => $to_uid, "uniacid" => $uniacid, "create_time" => time(), "update_time" => time());
        $result = pdo_insert("longbing_card_chat", $data);
        if ($result) {
            $insertid = pdo_insertid();
            $user_info = pdo_get("longbing_card_user", array("id" => $uid), array("nickName", "avatarUrl"));
            $target_info = pdo_get("longbing_card_user", array("id" => $to_uid), array("nickName", "avatarUrl"));
            return $this->result(0, "", array("chat_id" => $insertid, "user_info" => $user_info, "target_info" => $target_info));
        }
        return $this->result(-1, "1", array());
    }
    public function doPageExtensionStatistics()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        if (!$uid) {
            return $this->result(-1, "1", array());
        }
        $type = $_GPC["type"];
        if (!$type) {
            $type = 3;
        }
        switch ($type) {
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            case 4:
                $beginTime = mktime(0, 0, 0, date("m"), 1, date("Y"));
                break;
            default:
                $beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        }
        $timeline = pdo_getall("longbing_card_timeline", array("user_id" => $uid));
        if (empty($timeline)) {
            $data["timeline"] = array("count" => 0, "last_time" => 0);
        } else {
            $ids = "";
            foreach ($timeline as $k => $v) {
                $ids .= "," . $v["id"];
            }
            $ids = trim($ids, ",");
            if (1 < count($timeline)) {
                $ids = "(" . $ids . ")";
                $view_timeline_count = pdo_fetchall("SELECT count(to_uid),create_time FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 7 && create_time > " . $beginTime . " && sign='view' && target in " . $ids . " ORDER BY id DESC");
            } else {
                $view_timeline_count = pdo_fetchall("SELECT count(to_uid),create_time FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 7 && create_time > " . $beginTime . " && sign='view' && target = " . $ids . " ORDER BY id DESC");
            }
            $view_timeline_last_time = $view_timeline_count[0]["create_time"];
            $view_timeline_count = $view_timeline_count[0]["count(to_uid)"];
            $data["timeline"] = array("count" => $view_timeline_count, "last_time" => $view_timeline_last_time ? $view_timeline_last_time : 0);
        }
        $extension = pdo_getall("longbing_card_extension", array("user_id" => $uid), array("goods_id"));
        if (empty($extension) && false) {
            $data["extension"] = array("count" => 0, "last_time" => 0);
        } else {
            $ids = "";
            foreach ($extension as $k => $v) {
                $ids .= $v["goods_id"];
            }
            $ids = trim($ids, ",");
            if (1 < count($extension)) {
                $view_goods_count = pdo_fetchall("SELECT count(to_uid) FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && uniacid = " . $uniacid . " && `type` = 2 && create_time > " . $beginTime . " && sign='view' ORDER BY id DESC");
                $view_goods_last_time = $view_goods_count[0]["create_time"];
                $view_goods_count = $view_goods_count[0]["count(to_uid)"];
            } else {
                $view_goods_count = pdo_fetchall("SELECT create_time FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && create_time > " . $beginTime . " && uniacid = " . $uniacid . " && `type` = 2 && sign='view' ORDER BY id DESC");
                $view_goods_last_time = $view_goods_count[0]["create_time"];
                $view_goods_count = count($view_goods_count);
            }
            $data["extension"] = array("count" => $view_goods_count, "last_time" => $view_goods_last_time);
        }
        $view_card_count = pdo_fetchall("SELECT count(staff_id),create_time FROM " . tablename("longbing_card_custom_qr_record") . " where staff_id = " . $uid . " && uniacid = " . $uniacid . " && user_id != " . $uid . " && create_time > " . $beginTime . " ORDER BY id DESC");
        $view_card_last_time = $view_card_count[0]["create_time"];
        $view_card_count = $view_card_count[0]["count(to_uid)"];
        $data["card"] = array("count" => $view_card_count, "last_time" => $view_card_last_time ? $view_card_last_time : 0);
        $count = pdo_fetchall("SELECT create_time FROM " . tablename("longbing_card_count") . " where to_uid = " . $uid . " && user_id != " . $uid . " && create_time > " . $beginTime . " && uniacid = " . $uniacid . " && `type` = 2 && sign='praise' ORDER BY id DESC");
        $count = count($count);
        $last_time = $count[0]["\$beginTime"] ? $count[0]["\$beginTime"] : 0;
        if ($data["card"]["last_time"] < $last_time) {
            $data["card"]["last_time"] = $last_time;
        }
        $data["card"]["count"] += $count;
        return $this->result(0, "", $data);
    }
    public function doPageExtension()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $goods_id = $_GPC["goods_id"];
        $uniacid = $_W["uniacid"];
        if (!$uid || !$goods_id) {
            return $this->result(-1, "", array());
        }
        $check = pdo_get("longbing_card_extension", array("uniacid" => $uniacid, "user_id" => $uid, "goods_id" => $goods_id));
        $result = false;
        $time = time();
        if ($check) {
            $result = pdo_delete("longbing_card_extension", array("uniacid" => $uniacid, "user_id" => $uid, "goods_id" => $goods_id));
        } else {
            $result = pdo_insert("longbing_card_extension", array("user_id" => $uid, "uniacid" => $uniacid, "goods_id" => $goods_id, "create_time" => $time, "update_time" => $time));
        }
        if ($result) {
            if ($this->redis_sup_v3) {
                $redis_key = "longbing_cardshow_" . $uid . "_" . $_W["uniacid"];
                $this->redis_server_v3->set($redis_key, "");
            }
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageExtensions()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $info = pdo_getslice("longbing_card_goods", array("uniacid" => $_W["uniacid"], "status" => 1), $limit, $count, array("id", "name", "cover", "price", "create_time"), "", array("top desc"));
        $extension = pdo_getall("longbing_card_extension", array("user_id" => $uid), array("goods_id"));
        $ids = array();
        $my_shop = pdo_getall("longbing_card_user_shop", array("user_id" => $uid), array("goods_id"));
        $my_shop_ids = array();
        foreach ($extension as $k => $v) {
            array_push($ids, $v["goods_id"]);
        }
        foreach ($my_shop as $k => $v) {
            array_push($my_shop_ids, $v["goods_id"]);
        }
        foreach ($info as $k => $v) {
            $info[$k]["cover"] = tomedia($v["cover"]);
            $info[$k]["is_extension"] = 0;
            $info[$k]["is_my_shop"] = 0;
            if (!empty($ids) && in_array($v["id"], $ids)) {
                $info[$k]["is_extension"] = 1;
            }
            if (!empty($my_shop_ids) && in_array($v["id"], $my_shop_ids)) {
                $info[$k]["is_my_shop"] = 1;
            }
            $info[$k]["create_time2"] = date("Y-m-d H:i:s", $v["create_time"]);
            $info[$k]["create_time3"] = date("Y-m-d H:i", $v["create_time"]);
            $info[$k]["create_time4"] = date("Y-m-d", $v["create_time"]);
            $info[$k]["create_time5"] = date("m-d", $v["create_time"]);
        }
        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $info);
        return $this->result(0, "", $data);
    }
    public function doPageExtensionsSelf()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        if (!$uid || !$to_uid) {
            return $this->result(-1, "", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $extension = pdo_getall("longbing_card_extension", array("user_id" => $to_uid), array("goods_id"));
        if (empty($extension)) {
            return $this->result(0, "", array());
        }
        $ids = array();
        foreach ($extension as $k => $v) {
            array_push($ids, $v["goods_id"]);
        }
        $ids = implode(",", $ids);
        if (1 < count($extension)) {
            $ids = "(" . $ids . ")";
            $sql = "SELECT id,`name`,cover,price,status FROM " . tablename("longbing_card_goods") . " WHERE id IN " . $ids . " ORDER BY top DESC";
        } else {
            $sql = "SELECT id,`name`,cover,price,status FROM " . tablename("longbing_card_goods") . " WHERE id = " . $ids . " ORDER BY top DESC";
        }
        $goods = pdo_fetchall($sql);
        $tmp = array();
        foreach ($goods as $k => $v) {
            if ($v["status"] == 1) {
                $goods[$k]["cover"] = tomedia($v["cover"]);
                array_push($tmp, $goods[$k]);
            }
        }
        return $this->result(0, "", $tmp);
    }
    public function doPageMyTimeline()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $info = pdo_getslice("longbing_card_timeline", array("uniacid" => $_W["uniacid"], "status" => 1, "user_id" => $uid), $limit, $count, array("id", "title", "cover", "create_time"), "", array("create_time desc"));
        load()->func("file");
        foreach ($info as $k => $v) {
            $tmp = $v["cover"];
            $tmp = explode(",", $tmp);
            foreach ($tmp as $k2 => $v2) {
                $tmp[$k2] = tomedia($v2);
            }
            $info[$k]["cover"] = $tmp;
            $info[$k]["create_time2"] = date("Y-m-d", $v["create_time"]);
        }
        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $info);
        return $this->result(0, "", $data);
    }
    public function doPageDeleteTimeline()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        if (!$uid || !$id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_timeline", array("uniacid" => $_W["uniacid"], "id" => $id, "user_id" => $uid));
        if (!$info) {
            return $this->result(-1, "", array());
        }
        $result = pdo_delete("longbing_card_timeline", array("uniacid" => $_W["uniacid"], "id" => $id, "user_id" => $uid));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageReleaseTimeline()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        $title = $_GPC["title"];
        $cover = $_GPC["cover"];
        $content = $_GPC["content"];
        if (!$uid || !$title) {
            return $this->result(-1, "", array());
        }
        if (LONGBING_AUTH_TIMELINE) {
            $list = pdo_getall("longbing_card_timeline", array("uniacid" => $_W["uniacid"], "status >" => -1));
            $count = count($list);
            if (LONGBING_AUTH_TIMELINE <= $count) {
                return $this->result(-2, "添加动态已达到 " . ", 如需增加请购买高级版本", array());
            }
        }
        $time = time();
        $result = pdo_insert("longbing_card_timeline", array("user_id" => $uid, "uniacid" => $uniacid, "cover" => $cover, "content" => $content, "title" => $title, "create_time" => $time, "update_time" => $time));
        if ($result) {
            if ($this->redis_sup_v3) {
                $redis_key = "longbing_card_timeline_" . $uid . "_" . 1 . "_" . $uniacid;
                $this->redis_server_v3->set($redis_key, "");
            }
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageReleaseQr()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        $title = $_GPC["title"];
        $content = $_GPC["content"];
        if (!$uid || !$title || !$content) {
            return $this->result(-1, "", array());
        }
        $time = time();
        if (LONGBING_AUTH_CUSTOM_QR) {
            $list = pdo_getall("longbing_card_custom_qr", array("uniacid" => $_W["uniacid"], "status >" => -1));
            $count = count($list);
            if (LONGBING_AUTH_CUSTOM_QR <= $count) {
                message("添加自定义码已达到上线 " . ", 如需增加请购买高级版本", "", "error");
            }
        }
        $result = pdo_insert("longbing_card_custom_qr", array("user_id" => $uid, "uniacid" => $uniacid, "title" => $title, "content" => $content, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time));
        if ($result) {
            $insertid = pdo_insertid();
            $destination_folder = ATTACHMENT_ROOT . "/";
            if (!file_exists($destination_folder)) {
                mkdir($destination_folder);
            }
            load()->func("file");
            $destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
            if (!file_exists($destination_folder)) {
                mkdirs($destination_folder);
            }
            $image = $destination_folder . "/" . $uid . "-" . $insertid . "releaseQr.png";
            $path = "longbing_card/pages/index/index?to_uid=" . $uid . "&currentTabBar=toCard&custom=" . $insertid . "&is_qr=1";
            $res = $this->createQr($image, $path);
            if ($res != true) {
                return $this->result(-1, "", array());
            }
            pdo_update("longbing_card_custom_qr", array("path" => "images" . "/longbing_card/" . $_W["uniacid"] . "/" . $uid . "-" . $insertid . "releaseQr.png"), array("id" => $insertid));
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageDeleteQr()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        $id = $_GPC["id"];
        if (!$uid || !$id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_custom_qr", array("user_id" => $uid, "id" => $id, "status" => 1));
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        $result = pdo_update("longbing_card_custom_qr", array("status" => -1), array("user_id" => $uid, "id" => $id, "status" => 1));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageUpload()
    {
        global $_GPC;
        global $_W;
        $uptypes = array("image/jpg", "image/jpeg", "image/png", "image/pjpeg", "image/gif", "image/bmp", "image/x-png", "audio/mpeg", "application/octet-stream");
        $max_file_size = 200000000;
        $destination_folder = ATTACHMENT_ROOT . "/" . "images" . "/longbing_card_upload/" . $_W["uniacid"] . "/";
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images");
        }
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card_upload")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card_upload");
        }
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card_upload/" . $_W["uniacid"] . "/")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card_upload/" . $_W["uniacid"] . "/");
        }
        if (!is_uploaded_file($_FILES["upfile"]["tmp_name"])) {
            echo "图片不存在!";
            exit;
        }
        $file = $_FILES["upfile"];
        if ($max_file_size < $file["size"]) {
            echo "文件太大!";
            exit;
        }
        if (!in_array($file["type"], $uptypes)) {
            echo "文件类型不符!" . $file["type"];
            exit;
        }
        load()->func("file");
        if (!file_exists($destination_folder)) {
            mkdirs($destination_folder);
        }
        $filename = $file["tmp_name"];
        $pinfo = pathinfo($file["name"]);
        $ftype = $pinfo["extension"];
        $destination = $destination_folder . str_shuffle(time() . rand(111111, 999999)) . "." . $ftype;
        $overwrite = false;
        if (file_exists($destination) && $overwrite != true) {
            echo "同名文件已经存在了";
            exit;
        }
        if (!move_uploaded_file($filename, $destination)) {
            echo "移动文件出错";
            exit;
        }
        $pinfo = pathinfo($destination);
        $fname = $pinfo["basename"];
        $fname = "images" . "/longbing_card_upload/" . $_W["uniacid"] . "/" . $fname;
        $filename = $fname;
        @file_remote_upload($filename);
        return $this->result(0, "", array("path" => tomedia($fname), "img" => $fname));
    }
    public function doPageReleaseQrList()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $info = pdo_getslice("longbing_card_custom_qr", array("uniacid" => $_W["uniacid"], "user_id" => $uid, "status" => 1), $limit, $count, array("id", "user_id", "title", "path", "create_time"), "", array("create_time desc"));
        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $info);
        return $this->result(0, "", $data);
    }
    public function doPageReleaseQrDetail()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        if (!$uid || !$id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_custom_qr", array("uniacid" => $_W["uniacid"], "id" => $id));
        if ($info["qr_path"]) {
            $size = @filesize(ATTACHMENT_ROOT . "/" . $info["qr_path"]);
            if (51220 < $size) {
                $info["path"] = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $info["qr_path"];
            } else {
                load()->func("file");
                $destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
                if (!file_exists($destination_folder)) {
                    mkdirs($destination_folder);
                }
                $image = $info["qr_path"];
                $path = "longbing_card/pages/index/index?to_uid=" . $uid . "&currentTabBar=toCard&custom=" . $info["id"] . "&is_qr=1";
                $res = $this->createQr($image, $path);
                if ($res != true) {
                    return $this->result(-1, "", array());
                }
            }
        }
        $info["path"] = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $info["qr_path"];
        return $this->result(0, "", $info);
    }
    public function doPageReleaseQrDetailV2()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        if (!$uid || !$id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_custom_qr", array("uniacid" => $_W["uniacid"], "id" => $id));
        $info["path"] = tomedia($info["path"]);
        if (!strstr($info["path"], $_SERVER["HTTP_HOST"])) {
            load()->func("file");
            $res = file_remote_attach_fetch($info["path"]);
            $res = tomedia($res);
            $res = str_replace("ttp://", "ttps://", $res);
            if (!strstr($res, "ttps://")) {
                $res = "https://" . $res;
            }
            $info["path"] = $res;
        }
        return $this->result(0, "", $info);
    }
    public function doPageNewClient()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $info = pdo_getslice("longbing_card_collection", array("uniacid" => $_W["uniacid"], "to_uid" => $uid, "uid !=" => $uid), $limit, $count, array("id", "uid", "create_time"), "", array("create_time desc"));
        foreach ($info as $k => $v) {
            $user = pdo_get("longbing_card_user", array("id" => $v["uid"]), array("nickName", "avatarUrl"));
            $info[$k]["user"] = $user;
            $check1 = pdo_get("longbing_card_chat", array("user_id" => $v["uid"], "target_id" => $uid));
            if (empty($check1)) {
                $check2 = pdo_get("longbing_card_chat", array("user_id" => $uid, "target_id" => $v["uid"]));
                if ($check2) {
                    $chat_id = 0;
                } else {
                    $chat_id = $check2["id"];
                }
            } else {
                $chat_id = $check1["id"];
            }
            if ($chat_id) {
                $message = pdo_getall("longbing_card_message", array("chat_id" => $chat_id), array("create_time"), "", array("id desc"));
                $info[$k]["count"] = count($message);
                $info[$k]["last_time"] = $message[0]["create_time"];
            } else {
                $info[$k]["count"] = 0;
                $info[$k]["last_time"] = 0;
            }
        }
        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $info);
        return $this->result(0, "", $data);
    }
    public function doPageClientView()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid || !$client_id) {
            return $this->result(-1, "1", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $info = pdo_getslice("longbing_card_count", array("uniacid" => $uniacid, "to_uid" => $uid, "user_id" => $client_id), $limit, $count, array(), "", array("id desc"));
        $user = pdo_get("longbing_card_user", array("id" => $client_id));
        $client = pdo_get("longbing_card_client_info", array("user_id" => $client_id, "staff_id" => $uid));
        foreach ($info as $k => $v) {
            $info[$k]["user"] = $user;
            $info[$k]["name"] = $client["name"] ? $client["name"] : $user["nickName"];
            $lists = pdo_getall("longbing_card_count", array("uniacid" => $uniacid, "to_uid" => $uid, "sign" => $v["sign"], "type" => $v["type"], "id <=" => $v["id"], "user_id" => $v["user_id"]), array(), "", "id asc");
            $info[$k]["count"] = count($lists);
            if ($v["sign"] == "view" && $v["type"] == 2 || $v["sign"] == "view" && $v["type"] == 7) {
                if ($v["type"] == 2) {
                    $target_info = pdo_get("longbing_card_goods", array("id" => $v["target"]));
                    $info[$k]["target_name"] = $target_info["name"];
                } else {
                    $target_info = pdo_get("longbing_card_timeline", array("id" => $v["target"]));
                    $info[$k]["target_name"] = $target_info["title"];
                }
            }
            if ($v["sign"] == "view" && ($v["type"] == 8 || $v["type"] == 9 || $v["type"] == 10)) {
                $target_info = pdo_get("longbing_card_timeline", array("id" => $v["target"]));
                $info[$k]["target_name"] = $target_info["title"];
            }
            if ($v["sign"] == "order") {
                $target_info = pdo_get("longbing_card_shop_order_item", array("order_id" => $v["target"]));
                $target_info2 = pdo_get("longbing_card_shop_order", array("id" => $v["target"]));
                $info[$k]["target_name"] = $target_info["name"];
                $info[$k]["order"] = $target_info2["out_trade_no"];
            }
        }
        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $info);
        return $this->result(0, "", $data);
    }
    public function doPageClientInfo()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $uniacid = $_W["uniacid"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$uid || !$client_id) {
            return $this->result(-1, "1", array());
        }
        $user = pdo_get("longbing_card_user", array("id" => $client_id));
        $info = pdo_get("longbing_card_client_info", array("staff_id" => $uid, "user_id" => $client_id));
        if (!$info) {
            $info["is_empty"] = true;
        }
        $info["is_qr"] = $user["is_qr"];
        $user["info"] = $info;
        $user["is_new"] = 0;
        if (time() - $user["cretea_time"] < 60 * 60 * 24) {
            $user["is_new"] = 1;
        }
        $info = pdo_get("longbing_card_user_mark", array("staff_id" => $uid, "user_id" => $client_id));
        if ($info) {
            $user["mark"] = $info["mark"];
            if ($info["mark"] == 1) {
                $user["is_new"] = 2;
            }
            if ($info["mark"] == 2) {
                $user["is_new"] = 3;
            }
        }
        $phone = pdo_get("longbing_card_user_phone", array("user_id" => $client_id));
        $user["phone"] = $phone ? $phone["phone"] : "";
        if ($user["phone"]) {
            $user["info"]["phone"] = $user["phone"];
        }
        $date = pdo_getall("longbing_card_date", array("user_id" => $client_id, "uniacid" => $uniacid), array(), "", array("date desc"));
        $user["info"]["date"] = !$date ? 0 : $date[0]["date"];
        $start = pdo_get("longbing_card_start", array("user_id" => $client_id, "staff_id" => $uid, "uniacid" => $uniacid));
        $user["start"] = 0;
        if ($start) {
            $user["start"] = 1;
        }
        $collection = pdo_get("ims_longbing_card_collection", array("uid" => $client_id, "to_uid" => $uid));
        $user["share_info"] = "";
        $user["share_str"] = "来自搜索";
        if ($collection && $collection["from_uid"]) {
            $share_info = pdo_get("longbing_card_user", array("id" => $collection["from_uid"]));
            if ($share_info) {
                $user["share_info"] = $share_info;
                $user["share_str"] = "来自" . $share_info["nickName"];
                if ($share_info["is_staff"] == 1) {
                    $share_info = pdo_get("longbing_card_user_info", array("fans_id" => $collection["from_uid"]));
                    $user["share_str"] = "来自" . $share_info["name"];
                }
                if ($collection["is_qr"] == 0 && $collection["is_group"] == 0) {
                    $user["share_str"] .= "分享的名片";
                }
                if ($collection["is_qr"]) {
                    $user["share_str"] .= "分享的二维码";
                }
                if ($collection["is_group"]) {
                    $user["share_str"] .= "分享到群//XL:的名片";
                    $user["is_group_opGId"] = $collection["openGId"];
                }
                if ($collection["is_group"] && $collection["is_qr"]) {
                    $user["share_str"] .= "分享到群//XL:的二维码";
                    $user["is_group_opGId"] = $collection["openGId"];
                }
            }
        }
        if ($collection && $collection["from_uid"] == 0) {
            if ($collection["is_qr"]) {
                $user["share_str"] = "来自二维码";
            }
            if ($collection["is_group"]) {
                $user["share_str"] = "来自群//XL:分享";
                $user["is_group_opGId"] = $collection["openGId"];
            }
        }
        if ($collection && $collection["hanover_name"]) {
            $user["share_str"] = "来自" . $collection["hanover_name"] . "的工作交接";
        }
        return $this->result(0, "", $user);
    }
    public function doPageEditClient()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid || !$client_id) {
            return $this->result(-1, "1", array());
        }
        $info = pdo_get("longbing_card_client_info", array("staff_id" => $uid, "user_id" => $client_id));
        $data = array("name" => $_GPC["name"], "sex" => $_GPC["sex"], "phone" => $_GPC["phone"], "email" => $_GPC["email"], "company" => $_GPC["company"], "position" => $_GPC["position"], "address" => $_GPC["address"], "birthday" => $_GPC["birthday"], "is_mask" => $_GPC["is_mask"], "remark" => $_GPC["remark"]);
        if (empty($info)) {
            $data["user_id"] = $client_id;
            $data["staff_id"] = $uid;
            $data["uniacid"] = $uniacid;
            $result = pdo_insert("longbing_card_client_info", $data);
        } else {
            $result = pdo_update("longbing_card_client_info", $data, array("id" => $info["id"]));
        }
        if ($result || $result == 0) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "" . $result, array());
    }
    public function doPageOftenLabel()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $info = pdo_getall("longbing_card_label");
        if (empty($info)) {
            pdo_insert("longbing_card_label", array("name" => "新客户", "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
            pdo_insert("longbing_card_label", array("name" => "跟进中", "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
            pdo_insert("longbing_card_label", array("name" => "老客户", "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
            pdo_insert("longbing_card_label", array("name" => "已成交", "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
            $info = pdo_getall("longbing_card_label", array("id <" => 5));
        }
        $check = pdo_get("longbing_card_user_label", array("staff_id" => $uid));
        if ($check) {
            $sql = "SELECT count( id ) AS `count`,id,lable_id,update_time FROM " . tablename("longbing_card_user_label") . " WHERE staff_id = " . $uid . " GROUP BY lable_id";
            $list = pdo_fetchall($sql);
        } else {
            $list = array();
        }
        array_multisort(array_column($list, "count"), SORT_DESC, $list);
        if (empty($list) || !$check) {
            return $this->result(0, "", array());
        }
        $ids = "";
        foreach ($list as $k => $v) {
            $ids .= "," . $v["lable_id"];
        }
        $ids = trim($ids, ",");
        if (1 < count($list)) {
            $ids = "(" . $ids . ")";
            $sql = "SELECT * FROM " . tablename("longbing_card_label") . " WHERE id in " . $ids;
        } else {
            $sql = "SELECT * FROM " . tablename("longbing_card_label") . " WHERE id = " . $ids;
        }
        $info = pdo_fetchall($sql);
        return $this->result(0, "", $info);
    }
    public function doPageInsertLabel()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $target_id = $_GPC["target_id"];
        $label = $_GPC["label"];
        $label_id = $_GPC["label_id"];
        if (!$label && !$label_id) {
            return $this->result(-1, "", array());
        }
        if (!$target_id) {
            return $this->result(-1, "", array());
        }
        if ($label_id) {
            $check = pdo_get("longbing_card_label", array("id" => $label_id, "uniacid" => $_W["uniacid"]));
            if (empty($check)) {
                return $this->result(-1, "", array());
            }
            $check2 = pdo_get("longbing_card_user_label", array("lable_id" => $label_id, "user_id" => $target_id, "uniacid" => $_W["uniacid"], "staff_id" => $uid));
            if (!empty($check2)) {
                return $this->result(-1, "", array());
            }
            $result = pdo_insert("longbing_card_user_label", array("lable_id" => $label_id, "user_id" => $target_id, "staff_id" => $uid, "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
            if ($result) {
                return $this->result(0, "", array());
            }
            return $this->result(-1, "", array());
        }
        $check = pdo_get("longbing_card_label", array("name" => $label, "uniacid" => $_W["uniacid"]));
        if (empty($check)) {
            $result = pdo_insert("longbing_card_label", array("name" => $label, "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
            if ($result) {
                $label_id = pdo_insertid();
            } else {
                return $this->result(-1, "", array());
            }
        } else {
            $label_id = $check["id"];
        }
        $check = pdo_get("longbing_card_user_label", array("lable_id" => $label_id, "uniacid" => $_W["uniacid"], "user_id" => $target_id));
        if (!empty($check)) {
            return $this->result(-1, "", array());
        }
        $result = pdo_insert("longbing_card_user_label", array("lable_id" => $label_id, "user_id" => $target_id, "staff_id" => $uid, "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
        if ($result) {
            $check = pdo_get("longbing_card_user_mark", array("user_id" => $target_id, "staff_id" => $uid));
            if (empty($check)) {
                pdo_insert("longbing_card_user_mark", array("user_id" => $target_id, "staff_id" => $uid, "mark" => 1, "create_time" => time(), "update_time" => time()));
            }
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageDeleteLabel()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $target_id = $_GPC["target_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $id = $_GPC["id"];
        if (!$target_id || !$id) {
            return $this->result(-1, "", array());
        }
        $result = pdo_delete("longbing_card_user_label", array("staff_id" => $uid, "user_id" => $target_id, "id" => $id));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageLabels()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $target_id = $_GPC["target_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$target_id) {
            return $this->result(-1, "", array());
        }
        $list = pdo_getall("longbing_card_user_label", array("staff_id" => $uid, "user_id" => $target_id), array("id", "lable_id"));
        foreach ($list as $k => $item) {
            $info = pdo_get("longbing_card_label", array("id" => $item["lable_id"]), array("name"));
            $list[$k]["name"] = $info["name"];
        }
        return $this->result(0, "", $list);
    }
    public function doPageAfterShare()
    {
        $this->cross();
        return false;
    }
    public function doPageGetShare()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $encryptedData = $_GPC["encryptedData"];
        $iv = $_GPC["iv"];
        $code = $_GPC["code"];
        $type = $_GPC["type"];
        $target_id = $_GPC["target_id"];
        $uniacid = $_W["uniacid"];
        $to_uid = $_GPC["to_uid"];
        if (!$encryptedData || !$iv || !$code || !$type || !$to_uid) {
            return $this->result(-1, "", array());
        }
        if ($type != 1 && !$target_id) {
            return $this->result(-1, "", array());
        }
        $appid = $_W["account"]["key"];
        $appsecret = $_W["account"]["secret"];
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $appid . "&secret=" . $appsecret . "&js_code=" . $code . "&grant_type=authorization_code";
        $info = ihttp_get($url);
        $info = json_decode($info["content"], true);
        if (!isset($info["session_key"])) {
            return $this->result(-1, "session_key", array());
        }
        $session_key = $info["session_key"];
        $check_sk = pdo_get("longbing_card_user_sk", array("user_id" => $uid));
        if (!$check_sk) {
            $time = time();
            @pdo_insert("longbing_card_user_sk", array("user_id" => $uid, "sk" => $session_key, "uniacid" => $uniacid, "status" => 1, "create_time" => $time, "update_time" => $time));
        } else {
            $time = time();
            @pdo_update("longbing_card_user_sk", array("sk" => $session_key, "update_time" => $time), array("id" => $check_sk["id"]));
        }
        include_once "wxBizDataCrypt.php";
        $pc = new WXBizDataCrypt($appid, $session_key);
        $errCode = $pc->decryptData($encryptedData, $iv, $data);
        if ($errCode == 0) {
            $data = json_decode($data, true);
            $openGId = $data["openGId"];
            $insertData = array("user_id" => $to_uid, "client_id" => $uid, "openGId" => $openGId, "uniacid" => $uniacid, "create_time" => time(), "update_time" => time());
            switch ($type) {
                case 1:
                    $insertData["view_card"] = 1;
                    break;
                case 2:
                    $insertData["view_custom_qr"] = 1;
                    $insertData["target_id"] = $target_id;
                    break;
                case 3:
                    $insertData["view_goods"] = 1;
                    $insertData["target_id"] = $target_id;
                    break;
                case 4:
                    $insertData["view_timeline"] = 1;
                    $insertData["target_id"] = $target_id;
                    break;
            }
            pdo_insert("longbing_card_share_group", $insertData);
            return $this->result(0, "", array());
        }
        return $this->result(-1, $errCode, array());
    }
    public function doPageExtensionDetail()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        $type = $_GPC["type"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$type) {
            return $this->result(-1, "", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        if ($type == 1) {
            $extension = pdo_getslice("longbing_card_extension", array("user_id" => $uid), $limit, $count, array("id", "user_id", "goods_id", "create_time"), "", "id desc");
            $ids = "";
            foreach ($extension as $k => $v) {
                $ids .= "," . $v["goods_id"];
            }
            $ids = trim($ids, ",");
            if (1 < count($extension)) {
                $ids = "(" . $ids . ")";
                $sql = "SELECT id,cover,price,`name` FROM " . tablename("longbing_card_goods") . " WHERE id IN " . $ids;
            } else {
                $sql = "SELECT id,cover,price,`name` FROM " . tablename("longbing_card_goods") . " WHERE id = " . $ids;
            }
            $sql = "SELECT id,cover,price,`name` FROM " . tablename("longbing_card_goods") . " WHERE uniacid = " . $_W["uniacid"] . " && `status` = 1";
            $goods = pdo_getslice("longbing_card_goods", array("uniacid" => $_W["uniacid"]), $limit, $count, array("id", "cover", "price", "name"), "", "");
            foreach ($goods as $k => $v) {
                $goods[$k]["cover"] = tomedia($v["cover"]);
                $like = "%\"id\":\"" . $v["id"] . "\",%";
                $sql = "SELECT sum( view_goods ) AS `view_goods_sum`,openGId,update_time FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_goods = 1 && target_id = " . $v["id"] . " GROUP BY openGId";
                $groups = pdo_fetchall($sql);
                $goods[$k]["groups"] = $groups;
                $consult_count = pdo_getall("longbing_card_count", array("type" => 8, "to_uid" => $uid, "target" => $v["id"], "sign" => "copy"), array("id"));
                $consult_count = count($consult_count);
                $goods[$k]["consult_count"] = $consult_count;
                $forward_count = pdo_getall("longbing_card_forward", array("type" => 2, "staff_id" => $uid, "target_id" => $v["id"]), array("id"));
                $forward_count = count($forward_count);
                $goods[$k]["forward_count"] = $forward_count;
                $view_count = pdo_getall("longbing_card_count", array("type" => 2, "to_uid" => $uid, "target" => $v["id"], "sign" => "view"), array("id"));
                $view_count = count($view_count);
                $goods[$k]["view_count"] += $view_count;
                $goods[$k]["follow_count"] = 0;
                $goods[$k]["deal_count"] = 0;
            }
            $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $goods);
            return $this->result(0, "", $data);
        } else {
            if ($type == 2) {
                $timeline = pdo_getslice("longbing_card_timeline", array("user_id" => $uid, "uniacid" => $_W["uniacid"], "status" => 1), $limit, $count, array("id", "user_id", "title", "cover", "create_time"), "", array("id desc"));
                if (empty($timeline)) {
                    $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => array());
                    return $this->result(0, "", $data);
                }
                foreach ($timeline as $k => $v) {
                    $arr = explode(",", $v["cover"]);
                    $tmp = array();
                    foreach ($arr as $k2 => $v2) {
                        array_push($tmp, tomedia($v2));
                    }
                    $timeline[$k]["cover"] = $tmp;
                    $like = "%\"id\":\"" . $v["id"] . "\",%";
                    $sql = "SELECT sum( view_timeline ) AS `view_timeline_sum` FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_timeline = 1 && target_id = " . $v["id"] . " GROUP BY openGId";
                    $groups = pdo_fetchall($sql);
                    $timeline[$k]["groups"] = $groups;
                    $forward_count = pdo_getall("longbing_card_forward", array("type" => 3, "staff_id" => $uid, "target_id" => $v["id"]), array("id"));
                    $forward_count = count($forward_count);
                    $timeline[$k]["forward_count"] = $forward_count;
                    $view_count = pdo_getall("longbing_card_count", array("type" => 7, "to_uid" => $uid, "target" => $v["id"], "sign" => "view"), array("id"));
                    $view_count = count($view_count);
                    $timeline[$k]["view_count"] += $view_count;
                    $timeline[$k]["follow_count"] = 0;
                    $timeline[$k]["deal_count"] = 0;
                }
                $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $timeline);
                return $this->result(0, "", $data);
            } else {
                if ($type == 3) {
                    $qr = pdo_getslice("longbing_card_custom_qr", array("user_id" => $uid, "uniacid" => $_W["uniacid"], "status" => 1), $limit, $count, array("id", "user_id", "title", "create_time"), "", array("id desc"));
                    if (empty($qr) && $curr != 1) {
                        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => array());
                        return $this->result(0, "", $data);
                    }
                    if ($curr == 1) {
                        $tmp = array("id" => 0, "user_id" => $uid, "title" => "名片", "groups" => array(), "follow_count" => 0, "deal_count" => 0);
                        $list = pdo_getall("longbing_card_user_mark", array("staff_id" => $uid, "uniacid" => $_W["uniacid"], "mark >" => 0));
                        $tmp["follow_count"] = count($list);
                        foreach ($list as $k => $v) {
                            if ($v["mark"] == 2) {
                                $tmp["deal_count"] += 1;
                            }
                        }
                        $qr = array_merge(array($tmp), $qr);
                    }
                    foreach ($qr as $k => $v) {
                        if ($v["id"] == 0) {
                            continue;
                        }
                        $sql = "SELECT sum( view_custom_qr ) AS `view_custom_qr_sum` FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_custom_qr = 1 && target_id = " . $v["id"] . " GROUP BY openGId";
                        $groups = pdo_fetchall($sql);
                        $qr[$k]["groups"] = $groups;
                        $qr[$k]["follow_count"] = 0;
                        $qr[$k]["deal_count"] = 0;
                        $beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
                    }
                    $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $qr);
                    return $this->result(0, "", $data);
                } else {
                    return $this->result(-1, "", array());
                }
            }
        }
    }
    public function doPageExtensionDetailV2()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        $type = $_GPC["type"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$type) {
            return $this->result(-1, "", array());
        }
        $limit = array(1, $this->limit);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        if ($type == 1) {
            $goods = pdo_getslice("longbing_card_goods", array("uniacid" => $_W["uniacid"]), $limit, $count, array("id", "cover", "price", "name"), "", "");
            foreach ($goods as $k => $v) {
                $goods[$k]["cover"] = tomedia($v["cover"]);
                $like = "%\"id\":\"" . $v["id"] . "\",%";
                $sql = "SELECT sum( view_goods ) AS `view_goods_sum`, openGId, update_time FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_goods = 1 && target_id = " . $v["id"] . " && client_id != " . $uid . " GROUP BY openGId";
                $groups = pdo_fetchall($sql);
                $groups = $groups ? $groups : array();
                $goods[$k]["total_number"] = 0;
                $goods[$k]["attract_number"] = 0;
                $goods[$k]["chat_number"] = 0;
                $goods[$k]["follow_number"] = 0;
                $goods[$k]["deal_number"] = 0;
                foreach ($groups as $k2 => $v2) {
                    $last = pdo_fetch("SELECT update_time FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_goods = 1 && client_id != " . $uid . " && openGId = '" . $v2["openGId"] . "' && target_id = " . $v["id"] . " ORDER BY id DESC");
                    $groups[$k2]["update_time"] = $last ? $last["update_time"] : $groups["update_time"];
                    $tmpUidArr = array();
                    $number = pdo_get("longbing_card_group_number", array("openGId" => $v2["openGId"]));
                    $goods[$k]["total_number"] += $number ? $number["number"] : 0;
                    $attract = pdo_fetchall("SELECT client_id FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && target_id = " . $v["id"] . " && client_id != " . $uid . " && openGId = '" . $v2["openGId"] . "'");
                    if (!empty($attract)) {
                        $tmpArr = array();
                        foreach ($attract as $k3 => $v3) {
                            array_push($tmpArr, $v3["client_id"]);
                            array_push($tmpUidArr, $v3["client_id"]);
                        }
                        $tmpArr = array_unique($tmpArr);
                        $goods[$k]["attract_number"] += count($tmpArr);
                    }
                    $tmpArr = array_unique($tmpUidArr);
                    $tmpUidStr = implode(",", $tmpUidArr);
                    if (1 < count($tmpUidArr)) {
                        $tmpUidStr = "(" . $tmpUidStr . ")";
                        $chat_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_chat") . " WHERE user_id IN " . $tmpUidStr);
                        $goods[$k]["chat_number"] += count($chat_number);
                        $follow_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_follow") . " WHERE user_id IN " . $tmpUidStr);
                        $goods[$k]["follow_number"] += count($follow_number);
                        $deal_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE user_id IN " . $tmpUidStr . " && mark = 2");
                        $goods[$k]["deal_number"] += count($deal_number);
                    } else {
                        $chat_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_chat") . " WHERE user_id = " . $tmpUidStr);
                        $goods[$k]["chat_number"] += count($chat_number);
                        $follow_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_follow") . " WHERE user_id = " . $tmpUidStr);
                        $goods[$k]["follow_number"] += count($follow_number);
                        $deal_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE user_id = " . $tmpUidStr . " && mark = 2");
                        $goods[$k]["deal_number"] += count($deal_number);
                    }
                }
                $goods[$k]["groups"] = $groups;
            }
            $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $goods);
            return $this->result(0, "", $data);
        } else {
            if ($type == 2) {
                $timeline = pdo_getslice("longbing_card_timeline", array("user_id" => $uid, "uniacid" => $_W["uniacid"], "status" => 1), $limit, $count, array("id", "user_id", "title", "cover", "create_time"), "", array("id desc"));
                if (empty($timeline)) {
                    $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => array());
                    return $this->result(0, "", $data);
                }
                foreach ($timeline as $k => $v) {
                    $timeline[$k]["total_number"] = 0;
                    $timeline[$k]["attract_number"] = 0;
                    $timeline[$k]["chat_number"] = 0;
                    $timeline[$k]["follow_number"] = 0;
                    $timeline[$k]["deal_number"] = 0;
                    $arr = explode(",", $v["cover"]);
                    $tmp = array();
                    foreach ($arr as $k2 => $v2) {
                        array_push($tmp, tomedia($v2));
                    }
                    $timeline[$k]["cover"] = $tmp;
                    $sql = "SELECT sum( view_timeline ) AS `view_timeline_sum`, openGId, update_time FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_timeline = 1 && target_id = " . $v["id"] . " && client_id != " . $uid . " GROUP BY openGId";
                    $groups = pdo_fetchall($sql);
                    $groups = $groups ? $groups : array();
                    foreach ($groups as $k2 => $v2) {
                        $last = pdo_fetch("SELECT update_time FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_timeline = 1 && client_id != " . $uid . " && openGId = '" . $v2["openGId"] . "' && target_id = " . $v["id"] . " ORDER BY id DESC");
                        $groups[$k2]["update_time"] = $last ? $last["update_time"] : $groups["update_time"];
                        $tmpUidArr = array();
                        $number = pdo_get("longbing_card_group_number", array("openGId" => $v2["openGId"]));
                        $timeline[$k]["total_number"] += $number ? $number["number"] : 0;
                        $attract = pdo_fetchall("SELECT client_id FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && target_id = " . $v["id"] . " && client_id != " . $uid . " && openGId = '" . $v2["openGId"] . "'");
                        if (!empty($attract)) {
                            $tmpArr = array();
                            foreach ($attract as $k3 => $v3) {
                                array_push($tmpArr, $v3["client_id"]);
                                array_push($tmpUidArr, $v3["client_id"]);
                            }
                            $tmpArr = array_unique($tmpArr);
                            $timeline[$k]["attract_number"] += count($tmpArr);
                        }
                        $tmpUidArr = array_unique($tmpUidArr);
                        $tmpUidStr = implode(",", $tmpUidArr);
                        if (1 < count($tmpUidArr)) {
                            $tmpUidStr = "(" . $tmpUidStr . ")";
                            $chat_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_chat") . " WHERE user_id IN " . $tmpUidStr);
                            $timeline[$k]["chat_number"] += count($chat_number);
                            $follow_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_follow") . " WHERE user_id IN " . $tmpUidStr);
                            $timeline[$k]["follow_number"] += count($follow_number);
                            $deal_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE user_id IN " . $tmpUidStr . " && mark = 2");
                            $timeline[$k]["deal_number"] += count($deal_number);
                        } else {
                            $chat_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_chat") . " WHERE user_id = " . $tmpUidStr);
                            $timeline[$k]["chat_number"] += count($chat_number);
                            $follow_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_follow") . " WHERE user_id = " . $tmpUidStr);
                            $timeline[$k]["follow_number"] += count($follow_number);
                            $deal_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE user_id = " . $tmpUidStr . " && mark = 2");
                            $timeline[$k]["deal_number"] += count($deal_number);
                        }
                    }
                    $timeline[$k]["groups"] = $groups;
                }
                $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $timeline);
                return $this->result(0, "", $data);
            } else {
                if ($type == 3) {
                    $qr = pdo_getslice("longbing_card_custom_qr", array("user_id" => $uid, "uniacid" => $_W["uniacid"], "status" => 1), $limit, $count, array("id", "user_id", "title", "create_time"), "", array("id desc"));
                    if (empty($qr) && $curr != 1) {
                        $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => array());
                        return $this->result(0, "", $data);
                    }
                    if ($curr == 1) {
                        $sql = "SELECT sum( view_card ) AS `view_card_sum`, openGId, update_time FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_card = 1 && client_id != " . $uid . " GROUP BY openGId";
                        $groups = pdo_fetchall($sql);
                        $groups = $groups ? $groups : array();
                        $tmp = array("id" => 0, "user_id" => $uid, "title" => "名片", "groups" => $groups, "total_number" => 0, "attract_number" => 0, "chat_number" => 0, "follow_number" => 0, "deal_number" => 0);
                        foreach ($groups as $k2 => $v2) {
                            $last = pdo_fetch("SELECT update_time FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_card = 1 && client_id != " . $uid . " && openGId = '" . $v2["openGId"] . "' ORDER BY id DESC");
                            $groups[$k2]["update_time"] = $last ? $last["update_time"] : $groups["update_time"];
                            $tmpUidArr = array();
                            $number = pdo_get("longbing_card_group_number", array("openGId" => $v2["openGId"]));
                            $tmp["total_number"] += $number ? $number["number"] : 0;
                            $attract = pdo_fetchall("SELECT client_id FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && client_id != " . $uid . " && openGId = '" . $v2["openGId"] . "' && view_card = 1");
                            if (!empty($attract)) {
                                $tmpArr = array();
                                foreach ($attract as $k3 => $v3) {
                                    array_push($tmpArr, $v3["client_id"]);
                                    array_push($tmpUidArr, $v3["client_id"]);
                                }
                                $tmpArr = array_unique($tmpArr);
                                $tmp["attract_number"] += count($tmpArr);
                            }
                            $tmpUidArr = array_unique($tmpUidArr);
                            $tmpUidStr = implode(",", $tmpUidArr);
                            if (1 < count($tmpUidArr)) {
                                $tmpUidStr = "(" . $tmpUidStr . ")";
                                $chat_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_chat") . " WHERE user_id IN " . $tmpUidStr);
                                $tmp["chat_number"] += count($chat_number);
                                $follow_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_follow") . " WHERE user_id IN " . $tmpUidStr);
                                $tmp["follow_number"] += count($follow_number);
                                $deal_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE user_id IN " . $tmpUidStr . " && mark = 2");
                                $tmp["deal_number"] += count($deal_number);
                            } else {
                                $chat_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_chat") . " WHERE user_id = " . $tmpUidStr);
                                $tmp["chat_number"] += count($chat_number);
                                $follow_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_follow") . " WHERE user_id = " . $tmpUidStr);
                                $tmp["follow_number"] += count($follow_number);
                                $deal_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE user_id = " . $tmpUidStr . " && mark = 2");
                                $tmp["deal_number"] += count($deal_number);
                            }
                        }
                        $qr = array_merge(array($tmp), $qr);
                    }
                    foreach ($qr as $k => $v) {
                        if ($v["id"] == 0) {
                            continue;
                        }
                        $sql = "SELECT sum( view_custom_qr ) AS `view_custom_qr_sum`, openGId, update_time FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_custom_qr = 1 && target_id = " . $v["id"] . " && client_id != " . $uid . " GROUP BY openGId";
                        $groups = pdo_fetchall($sql);
                        $qr[$k]["total_number"] = 0;
                        $qr[$k]["attract_number"] = 0;
                        $qr[$k]["chat_number"] = 0;
                        $qr[$k]["follow_number"] = 0;
                        $qr[$k]["deal_number"] = 0;
                        foreach ($groups as $k2 => $v2) {
                            $last = pdo_fetch("SELECT update_time FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && view_custom_qr = 1 && client_id != " . $uid . " && openGId = '" . $v2["openGId"] . "' && target_id = " . $v["id"] . " ORDER BY id DESC");
                            $groups[$k2]["update_time"] = $last ? $last["update_time"] : $groups["update_time"];
                            $tmpUidArr = array();
                            $number = pdo_get("longbing_card_group_number", array("openGId" => $v2["openGId"]));
                            $qr[$k]["total_number"] += $number ? $number["number"] : 0;
                            $attract = pdo_fetchall("SELECT client_id FROM " . tablename("longbing_card_share_group") . " WHERE user_id = " . $uid . " && target_id = " . $v["id"] . " && client_id != " . $uid . " && openGId = '" . $v2["openGId"] . "'");
                            if (!empty($attract)) {
                                $tmpArr = array();
                                foreach ($attract as $k3 => $v3) {
                                    array_push($tmpArr, $v3["client_id"]);
                                    array_push($tmpUidArr, $v3["client_id"]);
                                }
                                $tmpArr = array_unique($tmpArr);
                                $qr[$k]["attract_number"] += count($tmpArr);
                            }
                            $tmpUidArr = array_unique($tmpUidArr);
                            $tmpUidStr = implode(",", $tmpUidArr);
                            if (1 < count($tmpUidArr)) {
                                $tmpUidStr = "(" . $tmpUidStr . ")";
                                $chat_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_chat") . " WHERE user_id IN " . $tmpUidStr);
                                $qr[$k]["chat_number"] += count($chat_number);
                                $follow_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_follow") . " WHERE user_id IN " . $tmpUidStr);
                                $qr[$k]["follow_number"] += count($follow_number);
                                $deal_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE user_id IN " . $tmpUidStr . " && mark = 2");
                                $qr[$k]["deal_number"] += count($deal_number);
                            } else {
                                $chat_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_chat") . " WHERE user_id = " . $tmpUidStr);
                                $qr[$k]["chat_number"] += count($chat_number);
                                $follow_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_follow") . " WHERE user_id = " . $tmpUidStr);
                                $qr[$k]["follow_number"] += count($follow_number);
                                $deal_number = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE user_id = " . $tmpUidStr . " && mark = 2");
                                $qr[$k]["deal_number"] += count($deal_number);
                            }
                        }
                        $qr[$k]["groups"] = $groups;
                    }
                    $data = array("page" => $curr, "total_page" => ceil($count / $this->limit), "list" => $qr);
                    return $this->result(0, "", $data);
                } else {
                    return $this->result(-1, "", array());
                }
            }
        }
    }
    public function doPageGroupPeople()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $openGId = $_GPC["openGId"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$openGId) {
            return $this->result(-1, "", array());
        }
        $info = pdo_getall("longbing_card_share_group", array("openGId" => $openGId), array(), "", "id asc");
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        $sql = "SELECT create_time,client_id FROM " . tablename("longbing_card_share_group") . " WHERE openGId = '" . $openGId . "' && client_id != " . $uid . " GROUP BY client_id";
        $info = pdo_fetchall($sql);
        if (empty($info)) {
            $data = array("count" => 0, "last_time" => 0);
            return $this->result(0, "", $data);
        }
        $lset_time = pdo_getall("longbing_card_share_group", array("openGId" => $openGId), array("create_time"), "", array("create_time desc"));
        $lset_time = $lset_time ? $lset_time[0]["create_time"] : 0;
        $data = array("count" => count($info), "last_time" => $lset_time);
        return $this->result(0, "", $data);
    }
    public function doPageTurnoverRate()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $openGId = $_GPC["openGId"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$openGId) {
            return $this->result(-1, "", array());
        }
        $type = $_GPC["type"];
        if (!$type) {
            $type = 1;
        }
        switch ($type) {
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            case 4:
                $beginTime = mktime(0, 0, 0, date("m"), 1, date("Y"));
                break;
            default:
                $beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        }
        $sql = "SELECT create_time,client_id FROM " . tablename("longbing_card_share_group") . " WHERE openGId = '" . $openGId . "' && client_id != " . $uid . " && create_time > " . $beginTime . " GROUP BY client_id";
        $info = pdo_fetchall($sql);
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        $data["users"] = count($info);
        if (empty($info)) {
            $data["follows"] = 0;
            $data["deals"] = 0;
        } else {
            $ids = "";
            foreach ($info as $k => $v) {
                $ids .= "," . $v["client_id"];
            }
            $ids = trim($ids, ",");
            if (1 < count($info)) {
                $ids = "(" . $ids . ")";
                $sqlF = "SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE staff_id = " . $uid . " && user_id IN " . $ids . " && create_time > " . $beginTime . " && mark = 1";
                $sqlD = "SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE staff_id = " . $uid . " && user_id IN " . $ids . " && create_time > " . $beginTime . " && mark = 2";
                $sqlC = "SELECT id FROM " . tablename("longbing_card_chat") . " WHERE target_id = " . $uid . " && user_id IN " . $ids . " && create_time > " . $beginTime;
            } else {
                $sqlF = "SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE staff_id = " . $uid . " && user_id = " . $ids . " && create_time > " . $beginTime . " && mark = 1";
                $sqlD = "SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE staff_id = " . $uid . " && user_id = " . $ids . " && create_time > " . $beginTime . " && mark = 2";
                $sqlC = "SELECT id FROM " . tablename("longbing_card_chat") . " WHERE target_id = " . $uid . " && user_id = " . $ids . " && create_time > " . $beginTime;
            }
            $follows = pdo_fetchall($sqlF);
            $data["follows"] = count($follows);
            $deals = pdo_fetchall($sqlD);
            $data["deals"] = count($deals);
            $data["follows"] += $data["deals"];
            $chats = pdo_fetchall($sqlC);
            $data["chats"] = count($chats);
        }
        $number = pdo_get("longbing_card_group_number", array("openGId" => $openGId, "staff_id" => $uid));
        $data["number"] = empty($number) ? 0 : $number["number"];
        $data["new_rate"] = 0;
        $data["chat_rate"] = 0;
        $data["deal_rate"] = 0;
        if ($data["number"]) {
            $data["new_rate"] = sprintf("%.2f", $data["users"] / $data["number"]) * 100;
            $data["chat_rate"] = sprintf("%.2f", $data["chats"] / $data["number"]) * 100;
            $data["deal_rate"] = sprintf("%.2f", $data["deals"] / $data["number"]) * 100;
        }
        return $this->result(0, "", $data);
    }
    public function doPageSetGroupNumber()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $openGId = $_GPC["openGId"];
        $number = $_GPC["number"];
        $number = intval($number);
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$openGId) {
            return $this->result(-1, "", array());
        }
        if (!$number) {
            $number = 0;
        }
        $time = time();
        $data = array("openGId" => $openGId, "staff_id" => $uid, "number" => $number, "uniacid" => $_W["uniacid"], "update_time" => $time);
        $check = pdo_get("longbing_card_group_number", array("openGId" => $openGId, "staff_id" => $uid));
        if ($check) {
            $result = pdo_update("longbing_card_group_number", $data, array("id" => $check["id"]));
        } else {
            $data["create_time"] = $time;
            $result = pdo_insert("longbing_card_group_number", $data);
        }
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageTurnoverRateTotal()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $users = pdo_getall("longbing_card_collection", array("to_uid" => $uid, "uid !=" => $uid));
        $data["users"] = count($users);
        $sqlF = "SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE staff_id = " . $uid . " && mark = 1";
        $sqlD = "SELECT id FROM " . tablename("longbing_card_user_mark") . " WHERE staff_id = " . $uid . " && mark = 2";
        $follows = pdo_fetchall($sqlF);
        $data["follows"] = count($follows);
        $deals = pdo_fetchall($sqlD);
        $data["deals"] = count($deals);
        $data["follows"] += $data["deals"];
        $sql = "SELECT id,lable_id,update_time FROM " . tablename("longbing_card_user_label") . " WHERE staff_id = " . $uid . " GROUP BY lable_id";
        $list = @pdo_fetchall($sql);
        $data["mark"] = count($list);
        $start = pdo_getall("longbing_card_start", array("staff_id" => $uid));
        $data["start"] = count($start);
        return $this->result(0, "", $data);
    }
    public function doPageInteraction()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $type = $_GPC["type"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $openGId = $_GPC["openGId"];
        if (!$openGId) {
            return $this->result(-1, "", array());
        }
        if (!$type) {
            $type = 1;
        }
        switch ($type) {
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            case 4:
                $beginTime = mktime(0, 0, 0, date("m"), 1, date("Y"));
                break;
            default:
                $beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        }
        $sql = "SELECT create_time,client_id FROM " . tablename("longbing_card_share_group") . " WHERE openGId = '" . $openGId . "' && client_id != " . $uid . " && create_time > " . $beginTime . " GROUP BY client_id";
        $info = pdo_fetchall($sql);
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        $data = array("goods" => array("count" => 0, "rate" => 0), "timeline" => array("count" => 0, "rate" => 0), "card" => array("count" => 0, "rate" => 0), "qr" => array("count" => 0, "rate" => 0), "custom_qr" => array("count" => 0, "rate" => 0));
        if (empty($info)) {
            return $this->result(0, "", $data);
        }
        $ids = "";
        foreach ($info as $k => $v) {
            $ids .= "," . $v["client_id"];
        }
        $ids = trim($ids, ",");
        if (1 < count($info)) {
            $ids = "(" . $ids . ")";
            $sql = "SELECT * FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $uid . " && type = 2 && create_time > " . $beginTime . " && user_id in " . $ids . " && sign = 'praise'";
            $cards = pdo_fetchall($sql);
            $data["card"]["count"] = count($cards);
            $sql = "SELECT * FROM " . tablename("longbing_card_custom_qr_record") . " WHERE staff_id = " . $uid . " && create_time > " . $beginTime . " && user_id in " . $ids;
            $custom_qr = pdo_fetchall($sql);
            $data["custom_qr"]["count"] = count($custom_qr);
            $sql = "SELECT * FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $uid . " && type = 2 && create_time > " . $beginTime . " && user_id in " . $ids . " && sign = 'view'";
            $goods = pdo_fetchall($sql);
            $data["goods"]["count"] = count($goods);
            $sql = "SELECT * FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $uid . " && type = 7 && create_time > " . $beginTime . " && user_id in " . $ids . " && sign = 'view'";
            $timeline = pdo_fetchall($sql);
            $data["timeline"]["count"] = count($timeline);
        } else {
            $sql = "SELECT * FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $uid . " && type = 2 && create_time > " . $beginTime . " && user_id = " . $ids . " && sign = 'praise'";
            $cards = pdo_fetchall($sql);
            $data["card"]["count"] = count($cards);
            $sql = "SELECT * FROM " . tablename("longbing_card_custom_qr_record") . " WHERE staff_id = " . $uid . " && create_time > " . $beginTime . " && user_id = " . $ids;
            $custom_qr = pdo_fetchall($sql);
            $data["custom_qr"]["count"] = count($custom_qr);
            $sql = "SELECT * FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $uid . " && type = 2 && create_time > " . $beginTime . " && user_id = " . $ids . " && sign = 'view'";
            $goods = pdo_fetchall($sql);
            $data["goods"]["count"] = count($goods);
            $sql = "SELECT * FROM " . tablename("longbing_card_count") . " WHERE to_uid = " . $uid . " && type = 7 && create_time > " . $beginTime . " && user_id = " . $ids . " && sign = 'view'";
            $timeline = pdo_fetchall($sql);
            $data["timeline"]["count"] = count($timeline);
        }
        $total = 0;
        foreach ($data as $k => $v) {
            $total += $v["count"];
        }
        if (0 < $total) {
            foreach ($data as $k => $v) {
                $data[$k]["rate"] = sprintf("%.2f", $v["count"] / $total) * 100;
            }
        }
        return $this->result(0, "", $data);
    }
    public function doPageGroupRank()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $openGId = $_GPC["openGId"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$openGId) {
            return $this->result(-1, "", array());
        }
        $info = pdo_getall("longbing_card_share_group", array("openGId" => $openGId, "user_id" => $uid), array(), "", "id asc");
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        $type = $_GPC["type"];
        $order = $_GPC["order"];
        if (!$type) {
            $type = 1;
        }
        if (!$order) {
            $order = "time";
        }
        switch ($type) {
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            case 4:
                $beginTime = mktime(0, 0, 0, date("m"), 1, date("Y"));
                break;
            default:
                $beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        }
        $sql = "SELECT sum( view_goods ) AS `view_goods_sum`, sum( view_card ) AS `view_card_sum`, sum( view_custom_qr ) AS `view_custom_qr_sum`,sum( view_timeline ) AS `view_timeline_sum`,create_time,client_id,openGId FROM " . tablename("longbing_card_share_group") . " WHERE openGId = '" . $openGId . "' && user_id = " . $uid . " && client_id != " . $uid . " && create_time > " . $beginTime . " GROUP BY client_id";
        $groups = pdo_fetchall($sql);
        if (!empty($groups)) {
            foreach ($groups as $k => $v) {
                $groups[$k]["count"] = $v["view_goods_sum"] + $v["view_card_sum"] + $v["view_custom_qr_sum"] + $v["view_timeline_sum"];
                $info = pdo_get("longbing_card_client_info", array("user_id" => $v["client_id"]));
                $groups[$k]["name"] = $info["name"];
                $user = pdo_get("longbing_card_user", array("id" => $v["client_id"]));
                $groups[$k]["name"] = $info["name"] ? $info["name"] : $user["nickName"];
                $groups[$k]["avatarUrl"] = $user["avatarUrl"];
            }
        }
        if ($order == "time") {
            array_multisort(array_column($groups, "create_time"), SORT_DESC, $groups);
        } else {
            array_multisort(array_column($groups, "count"), SORT_DESC, $groups);
        }
        return $this->result(0, "", $groups);
    }
    public function doPageClientList()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $type = $_GPC["type"];
        $staff_id = $_GPC["staff_id"];
        $uniacid = $_W["uniacid"];
        if ($staff_id) {
            $uid = $staff_id;
            $type = 1;
        }
        if (!$type) {
            $type = 1;
        }
        $limit = array(1, 15);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $len = 15;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $start = ($curr - 1) * $len;
        $ids = "";
        if ($type == 1) {
            $users = pdo_fetchall("SELECT b.* FROM " . tablename("longbing_card_collection") . " a INNER JOIN " . tablename("longbing_card_user") . " b ON a.uid = b.id WHERE a.to_uid = " . $uid . " && a.uid != " . $uid . " ORDER BY a.id DESC LIMIT " . $start . ", " . $len);
            $count = pdo_fetchall("SELECT b.* FROM " . tablename("longbing_card_collection") . " a INNER JOIN " . tablename("longbing_card_user") . " b ON a.uid = b.id WHERE a.to_uid = " . $uid . " && a.uid != " . $uid . " ORDER BY a.id DESC");
            $count = count($count);
        } else {
            if ($type == 2) {
                $list = pdo_getslice("longbing_card_user_mark", array("staff_id" => $uid, "mark" => 1), $limit, $count, array(), "", array("create_time desc"));
                if (!empty($list)) {
                    foreach ($list as $k => $v) {
                        $ids .= "," . $v["user_id"];
                    }
                    $ids = trim($ids, ",");
                }
            } else {
                if ($type == 3) {
                    $list = pdo_getslice("longbing_card_user_mark", array("staff_id" => $uid, "mark" => 2), $limit, $count, array(), "", array("create_time desc"));
                    if (!empty($list)) {
                        foreach ($list as $k => $v) {
                            $ids .= "," . $v["user_id"];
                        }
                        $ids = trim($ids, ",");
                    }
                } else {
                    return $this->result(-1, "", array());
                }
            }
        }
        if ($type != 1) {
            if (!$ids) {
                return $this->result(0, "", array());
            }
            if (strpos($ids, ",")) {
                $sql = "SELECT id,nickName,avatarUrl FROM " . tablename("longbing_card_user") . " where `id` in (" . $ids . ")";
            } else {
                $sql = "SELECT id,nickName,avatarUrl FROM " . tablename("longbing_card_user") . " where `id` = " . $ids;
            }
            $users = pdo_fetchall($sql);
        }
        foreach ($users as $k => $v) {
            $praise = pdo_getall("longbing_card_count", array("user_id" => $v["id"], "to_uid" => $uid, "sign" => "praise"), array("id", "create_time"), "", array("create_time desc"));
            $message1 = pdo_getall("longbing_card_message", array("user_id" => $v["id"], "target_id" => $uid), array("id", "create_time"), "", array("create_time desc"));
            $message2 = pdo_getall("longbing_card_message", array("user_id" => $uid, "target_id" => $v["id"]), array("id", "create_time"), "", array("create_time desc"));
            $view = pdo_getall("longbing_card_count", array("user_id" => $v["id"], "to_uid" => $uid, "sign" => "view"), array("id", "create_time"), "", array("create_time desc"));
            $copy = pdo_getall("longbing_card_count", array("user_id" => $v["id"], "to_uid" => $uid, "sign" => "copy"), array("id", "create_time"), "", array("create_time desc"));
            $users[$k]["count"] = count($praise) + count($message1) + count($message2) + count($view) + count($copy);
            $times = array();
            $times[] = $praise[0]["create_time"];
            $times[] = $message1[0]["create_time"];
            $times[] = $message2[0]["create_time"];
            $times[] = $view[0]["create_time"];
            $times[] = $copy[0]["create_time"];
            rsort($times);
            $users[$k]["last_time"] = $times[0] ? $times[0] : 0;
            $phone = pdo_get("longbing_card_user_phone", array("user_id" => $v["id"]));
            $client_info = pdo_get("longbing_card_client_info", array("user_id" => $v["id"]));
            $client_phone = "";
            if (!empty($client_info) && $client_info["phone"]) {
                $client_phone = $client_info["phone"];
            }
            $users[$k]["phone"] = !empty($phone) ? $phone["phone"] : $client_phone;
            $start = pdo_get("longbing_card_start", array("user_id" => $v["id"], "staff_id" => $uid));
            $users[$k]["start"] = !$start ? 0 : 1;
            $mark = pdo_get("longbing_card_user_mark", array("user_id" => $v["id"], "staff_id" => $uid), array("id", "create_time", "mark"));
            $users[$k]["mark"] = 0;
            if ($mark) {
                $users[$k]["mark"] = $mark["mark"];
            }
            $collection = pdo_get("ims_longbing_card_collection", array("uid" => $v["id"], "to_uid" => $uid));
            $users[$k]["share_info"] = "";
            $users[$k]["share_str"] = "来自搜索";
            if ($collection && $collection["from_uid"]) {
                $share_info = pdo_get("longbing_card_user", array("id" => $collection["from_uid"]));
                if ($share_info) {
                    $users[$k]["share_info"] = $share_info;
                    $users[$k]["share_str"] = "来自" . $share_info["nickName"];
                    if ($share_info["is_staff"] == 1) {
                        $share_info = pdo_get("longbing_card_user_info", array("fans_id" => $collection["from_uid"]));
                        $users[$k]["share_str"] = "来自" . $share_info["name"];
                    }
                    if ($collection["is_qr"] == 0 && $collection["is_group"] == 0) {
                        $users[$k]["share_str"] .= "分享的名片";
                    }
                    if ($collection["is_qr"]) {
                        $users[$k]["share_str"] .= "分享的二维码";
                    }
                    if ($collection["is_group"]) {
                        $users[$k]["share_str"] .= "分享到群//XL:的名片";
                        $users[$k]["is_group_opGId"] = $collection["openGId"];
                    }
                    if ($collection["is_group"] && $collection["is_qr"]) {
                        $users[$k]["share_str"] .= "分享到群//XL:的二维码";
                        $users[$k]["is_group_opGId"] = $collection["openGId"];
                    }
                }
            }
            if ($collection && $collection["from_uid"] == 0) {
                if ($collection["is_qr"]) {
                    $users[$k]["share_str"] = "来自二维码";
                }
                if ($collection["is_group"]) {
                    $users[$k]["share_str"] = "来自群//XL:分享";
                    $users[$k]["is_group_opGId"] = $collection["openGId"];
                }
            }
            if ($collection && $collection["hanover_name"]) {
                $users[$k]["share_str"] = "来自" . $collection["hanover_name"] . "的工作交接";
            }
        }
        if ($staff_id) {
            foreach ($users as $k => $v) {
                $client_info = pdo_get("longbing_card_client_info", array("user_id" => $v["id"], "uniacid" => $uniacid));
                $users[$k]["name"] = !$client_info ? "" : $client_info["name"];
                $rate = pdo_getall("longbing_card_rate", array("user_id" => $v["id"], "uniacid" => $uniacid), array(), "", array("rate desc"));
                $users[$k]["rate"] = !$rate ? 0 : $rate[0]["rate"];
                $date = pdo_getall("longbing_card_date", array("user_id" => $v["id"], "uniacid" => $uniacid), array(), "", array("date desc"));
                $users[$k]["date"] = !$date ? 0 : $date[0]["date"];
                $mark = @pdo_getall("longbing_card_user_mark", array("user_id" => $v["id"]), array(), "", array("status desc", "mark desc"));
                $users[$k]["mark"] = !$mark ? 0 : $mark[0]["mark"];
                $users[$k]["order"] = 0;
                $users[$k]["money"] = 0;
                $start = pdo_get("longbing_card_start", array("user_id" => $v["id"], "staff_id" => $uid));
                $users[$k]["start"] = !$start ? 0 : 1;
            }
        }
        $data = array("page" => $curr, "total_page" => ceil($count / 15), "list" => $users, "total_count" => $count);
        return $this->result(0, "", $data);
    }
    public function doPageFollowInsert()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $content = $_GPC["content"];
        $staff_id = $_GPC["staff_id"];
        $type = $_GPC["type"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$type) {
            $type = 1;
        }
        if (!$client_id || !$content) {
            return $this->result(-1, "", array());
        }
        $time = time();
        $data = array("user_id" => $client_id, "staff_id" => $uid, "content" => $content, "uniacid" => $_W["uniacid"], "type" => $type, "create_time" => $time, "update_time" => $time);
        $result = pdo_insert("longbing_card_user_follow", $data);
        $check = pdo_get("longbing_card_user_mark", array("user_id" => $client_id, "staff_id" => $uid));
        if (empty($check)) {
            pdo_insert("longbing_card_user_mark", array("user_id" => $client_id, "staff_id" => $uid, "uniacid" => $_W["uniacid"], "mark" => 1, "create_time" => time(), "update_time" => time()));
        }
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageFollowUpdate()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        $content = $_GPC["content"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$id || !$content) {
            return $this->result(-1, "", array());
        }
        $time = time();
        $data = array("content" => $content, "update_time" => $time);
        $check = pdo_get("longbing_card_user_follow", array("staff_id" => $uid, "id" => $id));
        if (empty($check)) {
            return $this->result(-1, "", array());
        }
        $result = pdo_update("longbing_card_user_follow", $data, array("id" => $id));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageFollowDelete()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$id) {
            return $this->result(-1, "", array());
        }
        $check = pdo_get("longbing_card_user_follow", array("staff_id" => $uid, "id" => $id));
        if (empty($check)) {
            return $this->result(-1, "", array());
        }
        $result = pdo_delete("longbing_card_user_follow", array("id" => $id));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageFollowList()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$staff_id && (!$uid || !$client_id)) {
            return $this->result(-1, "", array());
        }
        $uniacid = $_W["uniacid"];
        if ($staff_id && !$client_id) {
            $follow = pdo_fetchall("SELECT id,user_id,staff_id,content,create_time,`type` FROM " . tablename("longbing_card_user_follow") . " where staff_id = " . $uid . " && uniacid = " . $uniacid);
            $mark = pdo_fetchall("SELECT id,user_id,staff_id,mark,create_time FROM " . tablename("longbing_card_user_mark") . " where staff_id = " . $uid . " && uniacid = " . $uniacid);
            $label = pdo_fetchall("SELECT a.id,a.user_id,a.staff_id,a.create_time,b.name FROM " . tablename("longbing_card_user_label") . " a LEFT JOIN " . tablename("longbing_card_label") . " b ON a.lable_id = b.id where a.staff_id = " . $uid . " && a.uniacid = " . $uniacid);
        } else {
            $follow = pdo_fetchall("SELECT id,user_id,staff_id,content,create_time,`type` FROM " . tablename("longbing_card_user_follow") . " where user_id = " . $client_id . " && staff_id = " . $uid . " && uniacid = " . $uniacid);
            $mark = pdo_fetchall("SELECT id,user_id,staff_id,mark,create_time FROM " . tablename("longbing_card_user_mark") . " where user_id = " . $client_id . " && staff_id = " . $uid . " && uniacid = " . $uniacid);
            $label = pdo_fetchall("SELECT a.id,a.user_id,a.staff_id,a.create_time,b.name FROM " . tablename("longbing_card_user_label") . " a LEFT JOIN " . tablename("longbing_card_label") . " b ON a.lable_id = b.id where a.user_id = " . $client_id . " && a.staff_id = " . $uid . " && a.uniacid = " . $uniacid);
        }
        function meargeList(&$value, $key, $param)
        {
            $value[$param["key"]] = $param["val"];
            if (isset($value["count(a.uid)"])) {
                $value["count"] = $value["count(a.uid)"];
            }
        }
        array_walk($follow, "meargeList", array("key" => "sign", "val" => "follow"));
        array_walk($mark, "meargeList", array("key" => "sign", "val" => "mark"));
        array_walk($label, "meargeList", array("key" => "sign", "val" => "label"));
        $array = array_merge($follow, $mark, $label);
        array_multisort(array_column($array, "create_time"), SORT_DESC, $array);
        $limit = array(1, 10);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $offset = ($curr - 1) * 10;
        $count = count($array);
        $array = array_slice($array, $offset, 10);
        if ($staff_id) {
            foreach ($array as $index => $item) {
                if (isset($item["user_id"])) {
                    $user_info = pdo_get("longbing_card_user", array("id" => $item["user_id"]));
                    $array[$index]["avatarUrl"] = "";
                    $array[$index]["nickName"] = "";
                    if ($user_info) {
                        $array[$index]["avatarUrl"] = $user_info["avatarUrl"];
                        $array[$index]["nickName"] = $user_info["nickName"];
                    }
                }
            }
        }
        $data = array("page" => $curr, "total_page" => ceil($count / 10), "list" => $array, "total_count" => $count);
        return $this->result(0, "", $data);
    }
    public function doPageInterest()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $type = $_GPC["type"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid || !$client_id) {
            return $this->result(-1, "", array());
        }
        if (!$type) {
            $type = 3;
        }
        switch ($type) {
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            case 4:
                $beginTime = mktime(0, 0, 0, date("m"), 1, date("Y"));
                break;
            default:
                $beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        }
        $data = array();
        $total_count = 0;
        $qr_share = pdo_getall("longbing_card_forward", array("staff_id" => $uid, "user_id" => $client_id, "type" => 1, "create_time >" => $beginTime), array("id"));
        $qr_view = pdo_getall("longbing_card_count", array("to_uid" => $uid, "user_id" => $client_id, "type" => 2, "create_time >" => $beginTime, "sign" => "praise"), array("id"));
        $count = count($qr_share) + count($qr_view);
        if ($count) {
            $data["qr"] = array("count" => $count, "rate" => 0);
            $total_count += $count;
        }
        $timeline_share = pdo_getall("longbing_card_forward", array("staff_id" => $uid, "user_id" => $client_id, "type" => 3, "create_time >" => $beginTime), array("id"));
        $timeline_view = pdo_getall("longbing_card_count", array("to_uid" => $uid, "user_id" => $client_id, "type" => 7, "create_time >" => $beginTime, "sign" => "view"), array("id"));
        $count = count($timeline_share) + count($timeline_view);
        if ($count) {
            $data["timeline"] = array("count" => $count, "rate" => 0);
            $total_count += $count;
        }
        $goods_share = pdo_getall("longbing_card_forward", array("staff_id" => $uid, "user_id" => $client_id, "type" => 2, "create_time >" => $beginTime), array("id"));
        $goods_view = pdo_getall("longbing_card_count", array("to_uid" => $uid, "user_id" => $client_id, "type" => 2, "create_time >" => $beginTime, "sign" => "view"), array("id"));
        $count = count($goods_share) + count($goods_view);
        if ($count) {
            $data["goods"] = array("count" => $count, "rate" => 0);
            $total_count += $count;
        }
        $custom_qr_view = pdo_getall("longbing_card_custom_qr_record", array("staff_id" => $uid, "user_id" => $client_id, "create_time >" => $beginTime));
        $count = count($custom_qr_view);
        if ($count) {
            $data["custom_qr"] = array("count" => $count, "rate" => 0);
            $total_count += $count;
        }
        if ($total_count) {
            foreach ($data as $k => $v) {
                $data[$k]["rate"] = sprintf("%.2f", $v["count"] / $total_count * 100);
            }
        }
        return $this->result(0, "", $data);
    }
    public function doPageCustomQrRecordInsert()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["to_uid"];
        $qr_id = $_GPC["qr_id"];
        if (!$qr_id) {
            $qr_id = 0;
        }
        if (!$uid || !$staff_id) {
            return $this->result(-1, "", array());
        }
        $result = pdo_insert("longbing_card_custom_qr_record", array("user_id" => $uid, "staff_id" => $staff_id, "qr_id" => $qr_id, "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageActivity()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $type = $_GPC["type"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if ($type != 1 && $type != 2) {
            $type = 1;
        }
        if (!$uid || !$client_id) {
            return $this->result(-1, "", array());
        }
        $last = 0;
        switch ($type) {
            case 1:
                $last = 7;
                break;
            case 2:
                $last = 30;
                break;
        }
        $data = array();
        for ($i = 0; $i < $last; $i++) {
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
            $endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
            $date = date("Y-m-d", $beginTime);
            $data[$i]["date"] = $date;
            $data[$i]["beginTime"] = $beginTime;
            $data[$i]["endTime"] = $endTime;
            $count1 = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_custom_qr_record") . " WHERE user_id = " . $client_id . " && staff_id = " . $uid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime);
            $count2 = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_forward") . " WHERE user_id = " . $client_id . " && staff_id = " . $uid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime);
            $count3 = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_message") . " WHERE user_id = " . $client_id . " && target_id = " . $uid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime);
            $count4 = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " WHERE user_id = " . $client_id . " && to_uid = " . $uid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && sign = 'copy'");
            $count5 = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " WHERE user_id = " . $client_id . " && to_uid = " . $uid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && sign = 'view'");
            $count6 = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " WHERE user_id = " . $client_id . " && to_uid = " . $uid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && sign = 'praise'");
            $count = count($count1) + count($count2) + count($count3) + count($count4) + count($count5) + count($count6);
            $data[$i]["count"] = $count;
        }
        return $this->result(0, "", $data);
    }
    public function doPageClientLabels()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid || !$client_id) {
            return $this->result(-1, "", array());
        }
        $data = array();
        $label = pdo_fetchall("SELECT a.id,a.user_id,a.staff_id,a.create_time,b.name FROM " . tablename("longbing_card_user_label") . " a LEFT JOIN " . tablename("longbing_card_label") . " b ON a.lable_id = b.id where a.user_id = " . $client_id . " && a.staff_id = " . $uid . " && a.uniacid = " . $uniacid);
        return $this->result(0, "", $data);
    }
    public function doPageClientInteraction()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $type = $_GPC["type"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$type) {
            $type = 3;
        }
        if (!$uid || !$client_id) {
            return $this->result(-1, "", array());
        }
        switch ($type) {
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            case 4:
                $beginTime = mktime(0, 0, 0, date("m"), 1, date("Y"));
                break;
            case 5:
                $beginTime = 0;
                break;
            default:
                $beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        }
        $total_count = 0;
        $data = array();
        $list = pdo_getall("longbing_card_custom_qr_record", array("user_id" => $client_id, "staff_id" => $uid, "qr_id >" => 0, "create_time >" => $beginTime), array("id"));
        $count = count($list);
        if ($count) {
            $total_count += $count;
            $data["custom_qr"] = array("count" => $count, "rate" => 0, "title" => "识别自定义码");
        }
        $list = pdo_getall("longbing_card_custom_qr_record", array("user_id" => $client_id, "staff_id" => $uid, "qr_id" => 0, "create_time >" => $beginTime), array("id"));
        $count = count($list);
        if ($count) {
            $total_count += $count;
            $data["qr"] = array("count" => $count, "rate" => 0, "title" => "识别名片码");
        }
        $list = pdo_getall("longbing_card_forward", array("user_id" => $client_id, "staff_id" => $uid, "create_time >" => $beginTime), array("id", "type"));
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $total_count += 1;
                switch ($v["type"]) {
                    case 1:
                        if (isset($data["share_card"])) {
                            $data["share_card"]["count"] += 1;
                        } else {
                            $data["share_card"] = array("count" => 1, "rate" => 0, "title" => "分享名片");
                        }
                        break;
                    case 2:
                        if (isset($data["share_goods"])) {
                            $data["share_goods"]["count"] += 1;
                        } else {
                            $data["share_goods"] = array("count" => 1, "rate" => 0, "title" => "分享商品");
                        }
                        break;
                    case 3:
                        if (isset($data["share_timeline"])) {
                            $data["share_timeline"]["count"] += 1;
                        } else {
                            $data["share_timeline"] = array("count" => 1, "rate" => 0, "title" => "分享动态");
                        }
                        break;
                    case 4:
                        if (isset($data["share_web"])) {
                            $data["share_web"]["count"] += 1;
                        } else {
                            $data["share_web"] = array("count" => 1, "rate" => 0, "title" => "分享官网");
                        }
                        break;
                }
            }
        }
        $list = pdo_getall("longbing_card_message", array("user_id" => $client_id, "target_id" => $uid, "create_time >" => $beginTime), array("id"));
        $count = count($list);
        if ($count) {
            $total_count += $count;
            $data["send_message"] = array("count" => $count, "rate" => 0, "title" => "发送聊天信息");
        }
        $list = pdo_getall("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "sign" => "copy", "create_time >" => $beginTime), array("id", "type"));
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $total_count += 1;
                switch ($v["type"]) {
                    case 1:
                        if (isset($data["copy_count_1"])) {
                            $data["copy_count_1"]["count"] += 1;
                        } else {
                            $data["copy_count_1"] = array("count" => 1, "rate" => 0, "title" => "同步到通讯录");
                        }
                        break;
                    case 2:
                        if (isset($data["copy_count_2"])) {
                            $data["copy_count_2"]["count"] += 1;
                        } else {
                            $data["copy_count_2"] = array("count" => 1, "rate" => 0, "title" => "拨打手机号");
                        }
                        break;
                    case 3:
                        if (isset($data["copy_count_3"])) {
                            $data["copy_count_3"]["count"] += 1;
                        } else {
                            $data["copy_count_3"] = array("count" => 1, "rate" => 0, "title" => "拨打座机号");
                        }
                        break;
                    case 4:
                        if (isset($data["copy_count_4"])) {
                            $data["copy_count_4"]["count"] += 1;
                        } else {
                            $data["copy_count_4"] = array("count" => 1, "rate" => 0, "title" => "复制微信");
                        }
                        break;
                    case 5:
                        if (isset($data["copy_count_5"])) {
                            $data["copy_count_5"]["count"] += 1;
                        } else {
                            $data["copy_count_5"] = array("count" => 1, "rate" => 0, "title" => "复制邮箱");
                        }
                        break;
                    case 6:
                        if (isset($data["copy_count_6"])) {
                            $data["copy_count_6"]["count"] += 1;
                        } else {
                            $data["copy_count_6"] = array("count" => 1, "rate" => 0, "title" => "复制公司名");
                        }
                        break;
                    case 7:
                        if (isset($data["copy_count_7"])) {
                            $data["copy_count_7"]["count"] += 1;
                        } else {
                            $data["copy_count_7"] = array("count" => 1, "rate" => 0, "title" => "查看定位");
                        }
                        break;
                    case 8:
                        if (isset($data["copy_count_8"])) {
                            $data["copy_count_8"]["count"] += 1;
                        } else {
                            $data["copy_count_8"] = array("count" => 1, "rate" => 0, "title" => "咨询产品");
                        }
                        break;
                    case 9:
                        if (isset($data["copy_count_9"])) {
                            $data["copy_count_9"]["count"] += 1;
                        } else {
                            $data["copy_count_9"] = array("count" => 1, "rate" => 0, "title" => "播放语音");
                        }
                        break;
                }
            }
        }
        $list = pdo_getall("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "sign" => "view", "create_time >" => $beginTime), array("id", "type"));
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $total_count += 1;
                switch ($v["type"]) {
                    case 1:
                        if (isset($data["view_count_1"])) {
                            $data["view_count_1"]["count"] += 1;
                        } else {
                            $data["view_count_1"] = array("count" => 1, "rate" => 0, "title" => "浏览商城列表");
                        }
                        break;
                    case 2:
                        if (isset($data["view_count_2"])) {
                            $data["view_count_2"]["count"] += 1;
                        } else {
                            $data["view_count_2"] = array("count" => 1, "rate" => 0, "title" => "浏览商品详情");
                        }
                        break;
                    case 3:
                        if (isset($data["view_count_3"])) {
                            $data["view_count_3"]["count"] += 1;
                        } else {
                            $data["view_count_3"] = array("count" => 1, "rate" => 0, "title" => "浏览动态列表");
                        }
                        break;
                    case 4:
                        if (isset($data["view_count_4"])) {
                            $data["view_count_4"]["count"] += 1;
                        } else {
                            $data["view_count_4"] = array("count" => 1, "rate" => 0, "title" => "点赞动态");
                        }
                        break;
                    case 5:
                        if (isset($data["view_count_5"])) {
                            $data["view_count_5"]["count"] += 1;
                        } else {
                            $data["view_count_5"] = array("count" => 1, "rate" => 0, "title" => "动态留言");
                        }
                        break;
                    case 6:
                        if (isset($data["view_count_6"])) {
                            $data["view_count_6"]["count"] += 1;
                        } else {
                            $data["view_count_6"] = array("count" => 1, "rate" => 0, "title" => "浏览公司官网");
                        }
                        break;
                    case 7:
                        if (isset($data["view_count_7"])) {
                            $data["view_count_7"]["count"] += 1;
                        } else {
                            $data["view_count_7"] = array("count" => 1, "rate" => 0, "title" => "浏览动态详情");
                        }
                        break;
                }
            }
        }
        $list = pdo_getall("longbing_card_timeline_comment", array("user_id" => $client_id, "create_time >" => $beginTime), array("id"));
        $count = count($list);
        if ($count) {
            $total_count += $count;
            $data["timeline_comment"] = array("count" => $count, "rate" => 0, "title" => "评论动态");
        }
        $list = pdo_getall("longbing_card_timeline_thumbs", array("user_id" => $client_id, "create_time >" => $beginTime), array("id"));
        $count = count($list);
        if ($count) {
            $total_count += $count;
            $data["timeline_thumbs"] = array("count" => $count, "rate" => 0, "title" => "点赞动态");
        }
        $list = pdo_getall("longbing_card_goods_collection", array("user_id" => $client_id, "create_time >" => $beginTime), array("id"));
        $count = count($list);
        if ($count) {
            $total_count += $count;
            $data["goods_collection"] = array("count" => $count, "rate" => 0, "title" => "收藏商品");
        }
        $list = pdo_getall("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "sign" => "praise", "type <" => 4, "create_time >" => $beginTime), array("id", "type"));
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $total_count += 1;
                switch ($v["type"]) {
                    case 1:
                        if (isset($data["voice"])) {
                            $data["voice"]["count"] += 1;
                        } else {
                            $data["voice"] = array("count" => 1, "rate" => 0, "title" => "点赞语音");
                        }
                        break;
                    case 2:
                        if (isset($data["view_detail"])) {
                            $data["view_detail"]["count"] += 1;
                        } else {
                            $data["view_detail"] = array("count" => 1, "rate" => 0, "title" => "打开名片");
                        }
                        break;
                    case 3:
                        if (isset($data["th"])) {
                            $data["th"]["count"] += 1;
                        } else {
                            $data["th"] = array("count" => 1, "rate" => 0, "title" => "点赞名片");
                        }
                        break;
                }
            }
        }
        if ($total_count) {
            foreach ($data as $k => $v) {
                $data[$k]["rate"] = floatval(sprintf("%.4f", $v["count"] / $total_count) * 100);
            }
        }
        array_multisort(array_column($data, "rate"), SORT_DESC, $data);
        foreach ($data as $index => $item) {
            $data[$index]["rate"] = sprintf("%.2f", $item["rate"]);
        }
        return $this->result(0, "", $data);
    }
    public function doPageDeal()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid || !$client_id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_user_mark", array("user_id" => $client_id, "staff_id" => $uid));
        if (empty($info)) {
            $result = pdo_insert("longbing_card_user_mark", array("user_id" => $client_id, "staff_id" => $uid, "mark" => 2, "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
            if ($result) {
                return $this->result(0, "", array());
            }
            return $this->result(-1, "", array());
        }
        if ($info["mark"] == 2) {
            return $this->result(-1, "", array());
        }
        $result = pdo_update("longbing_card_user_mark", array("mark" => 2, "update_time" => time()), array("id" => $info["id"]));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageCancelDeal()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid || !$client_id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_user_mark", array("user_id" => $client_id, "staff_id" => $uid, "mark" => 2));
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        $result = pdo_update("longbing_card_user_mark", array("mark" => 1, "update_time" => time()), array("id" => $info["id"]));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageCheckDeal()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid || !$client_id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_user_mark", array("user_id" => $client_id, "staff_id" => $uid));
        if (!empty($info) && $info["mark"] == 2) {
            return $this->result(0, "已成交", array());
        }
        return $this->result(0, "未成交", array());
    }
    public function doPageStaff()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_user", array("id" => $uid), array("nickName", "avatarUrl", "is_staff"));
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        if ($info["is_staff"] != 1) {
            return $this->result(-1, "", array());
        }
        $user_info = pdo_get("longbing_card_user_info", array("fans_id" => $uid), array("name", "job_id"));
        if (!$user_info["job_id"]) {
            $user_info["job_id"] = 1;
        }
        $job = pdo_get("longbing_card_job", array("id" => $user_info["job_id"]));
        $user_info["job"] = $job ? $job["name"] : "暂无职称";
        $info["info"] = $user_info;
        return $this->result(0, "", $info);
    }
    public function doPageUnread()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_user", array("id" => $uid), array("nickName", "avatarUrl", "is_staff"));
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        if ($info["is_staff"] != 1) {
            return $this->result(-1, "", array());
        }
        $list = pdo_getall("longbing_card_message", array("target_id" => $uid, "status" => 1));
        $count = count($list);
        return $this->result(0, "", array("count" => $count));
    }
    public function doPageClientUnread()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_user", array("id" => $uid), array("nickName", "avatarUrl", "is_staff"));
        $data = array("user_count" => 0, "staff_count" => 0);
        if ($info) {
            if ($to_uid) {
                $list = pdo_getall("longbing_card_message", array("target_id" => $uid, "user_id" => $to_uid, "status" => 1));
            } else {
                $list = pdo_getall("longbing_card_message", array("target_id" => $uid, "status" => 1));
            }
            $count = count($list);
            $data["user_count"] = $count;
        }
        if ($info && $info["is_staff"] == 1) {
            $list = pdo_getall("longbing_card_message", array("target_id" => $uid, "status" => 1));
            $count = count($list);
            $data["staff_count"] = $count;
        }
        return $this->result(0, "", array("count" => $data));
    }
    public function doPageStaffCard()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_user", array("id" => $uid), array("nickName", "avatarUrl", "is_staff"));
        if (empty($info)) {
        }
        if ($info["is_staff"] != 1) {
        }
        $user_info = pdo_get("longbing_card_user_info", array("fans_id" => $uid));
        $user_info["avatar"] = tomedia($user_info["avatar"]);
        $user_info["voice"] = tomedia($user_info["voice"]);
        $user_info["desc2"] = str_replace("&nbsp;", " ", $user_info["desc"]);
        $arr = explode(",", $user_info["images"]);
        foreach ($arr as $k => $v) {
            $arr[$k] = tomedia($v);
        }
        $user_info["images"] = $arr;
        $job_list = pdo_getall("longbing_card_job", array("uniacid" => $_W["uniacid"], "status" => 1));
        if (!$job_list) {
            pdo_insert("longbing_card_job", array("uniacid" => $_W["uniacid"], "name" => "首席服务官", "create_time" => time(), "update_time" => time()));
            $job_list = pdo_getall("longbing_card_job", array("uniacid" => $_W["uniacid"], "status" => 1));
        }
        $job_index = 0;
        foreach ($job_list as $key => $item) {
            if ($item["id"] == $user_info["job_id"]) {
                $job_index = $key;
            }
        }
        $my_tags = pdo_getall("longbing_card_tags", array("user_id" => $uid, "uniacid" => $uniacid));
        $sys_tags = pdo_getall("longbing_card_tags", array("user_id" => 0, "uniacid" => $uniacid));
        $data["my_tags"] = $my_tags;
        $data["sys_tags"] = $sys_tags;
        return $this->result(0, "", array("count" => $user_info, "job_list" => $job_list, "job_index" => $job_index));
    }
    public function doPageEditStaff()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $uniacid = $_W["uniacid"];
        if (!$uid || !$_GPC["job_id"]) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_user", array("id" => $uid), array("nickName", "avatarUrl", "is_staff", "id"));
        if (empty($info)) {
            return $this->result(-1, "/用户", array());
        }
        if ($info["is_staff"] != 1) {
        }
        $images = $this->transImageBack($_GPC["images"]);
        $avatar = $this->transImageBack($_GPC["avatar"]);
        $voice = $this->transImageBack($_GPC["voice"]);
        $_GPC["desc"] = str_replace(" ", "&nbsp;", $_GPC["desc"]);
        $data = array("uniacid" => $uniacid, "avatar" => $avatar, "name" => $_GPC["name"], "phone" => $_GPC["phone"], "wechat" => $_GPC["wechat"], "telephone" => $_GPC["telephone"], "job_id" => $_GPC["job_id"], "email" => $_GPC["email"], "desc" => $_GPC["desc"], "company_id" => $_GPC["company_id"], "voice" => $voice, "voice_time" => $_GPC["voice_time"], "card_type" => $_GPC["card_type"], "my_url" => $_GPC["my_url"], "images" => $images, "update_time" => time());
        if ($_GPC["my_video"]) {
            $data["my_video"] = $_GPC["my_video"];
        }
        $user_info = pdo_get("longbing_card_user_info", array("fans_id" => $info["id"], "uniacid" => $uniacid), array("name", "phone", "fans_id", "id", "create_time", "avatar"));
        if (empty($user_info)) {
            $data["fans_id"] = $info["id"];
            $data["create_time"] = time();
            $result = pdo_insert("longbing_card_user_info", $data);
        } else {
            if (!$user_info["create_time"]) {
                $data["create_time"] = time();
            }
            if ($data["avatar"] != $user_info["avatar"]) {
                $destination_folder = ATTACHMENT_ROOT . "/images" . "/longbing_card/" . $_W["uniacid"];
                $image = $destination_folder . "/" . $_W["uniacid"] . "-" . $uid . "qr.png";
                if (file_exists($image)) {
                    @unlink($image);
                }
            }
            $result = pdo_update("longbing_card_user_info", $data, array("fans_id" => $info["id"]));
        }
        if ($result) {
            if ($this->redis_sup_v3) {
                $redis_key = "longbing_cardsv5_" . $uid . "_" . $_W["uniacid"];
                $this->redis_server_v3->set($redis_key, "");
                $this->redis_server_v3->EXPIRE($redis_key, 0);
            }
            return $this->result(0, "", array());
        }
        return $this->result(-1, "" . $result, array());
    }
    public function doPageFirstTime()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $uniacid = $_W["uniacid"];
        if (!$uid || !$client_id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_user", array("id" => $client_id));
        if (!empty($info)) {
            return $this->result(0, "", array("time" => $info["create_time"]));
        }
        return $this->result(0, "", array());
    }
    public function doPageSearch()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $type = $_GPC["type"];
        $keyword = $_GPC["keyword"];
        if (!$uid || !$keyword) {
            return $this->result(-1, "", array());
        }
        $keyword = "%" . $keyword . "%";
        if (!$type) {
            $type = 1;
        }
        if ($type == 1) {
            $ids = array();
            $labels = pdo_fetchall("SELECT * FROM " . tablename("longbing_card_label") . " WHERE `name` like '" . $keyword . "'");
            foreach ($labels as $k => $v) {
                $info = pdo_getall("longbing_card_user_label", array("staff_id" => $uid, "lable_id" => $v["id"]));
                foreach ($info as $k2 => $v2) {
                    array_push($ids, $v2["user_id"]);
                }
            }
            $infos = pdo_fetchall("SELECT * FROM " . tablename("longbing_card_client_info") . " WHERE `name` like '" . $keyword . "' && staff_id = " . $uid);
            foreach ($infos as $k => $v) {
                array_push($ids, $v["user_id"]);
            }
            $users2 = pdo_getall("longbing_card_collection", array("to_uid" => $uid));
            if (!empty($users2)) {
                $uids = "";
                foreach ($users2 as $k => $v) {
                    $uids .= "," . $v["uid"];
                }
                $uids = trim($uids, ",");
                if (1 < count($users2)) {
                    $uids = "(" . $uids . ")";
                    $sql = "SELECT * FROM " . tablename("longbing_card_user") . " WHERE id in " . $uids . " && nickName like '" . $keyword . "'";
                }
                $users = pdo_fetchall($sql);
                foreach ($users as $k => $v) {
                    array_push($ids, $v["id"]);
                }
            }
            $ids = array_unique($ids);
            $ids = implode(",", $ids);
            if ($ids) {
                if (strpos($ids, ",")) {
                    $ids = "(" . $ids . ")";
                    $sql = "SELECT id,nickName,avatarUrl FROM " . tablename("longbing_card_user") . " WHERE id in " . $ids;
                } else {
                    $sql = "SELECT id,nickName,avatarUrl FROM " . tablename("longbing_card_user") . " WHERE id = " . $ids;
                }
                $users = pdo_fetchall($sql);
                foreach ($users as $k => $v) {
                    $info = pdo_get("longbing_card_client_info", array("user_id" => $v["id"], "staff_id" => $uid));
                    $users[$k]["info"] = $info;
                    $praise = pdo_getall("longbing_card_praise", array("uid" => $v["id"], "to_uid" => $uid), array("id", "create_time"), "", array("create_time desc"));
                    $message1 = pdo_getall("longbing_card_message", array("user_id" => $v["id"], "target_id" => $uid), array("id", "create_time"), "", array("create_time desc"));
                    $message2 = pdo_getall("longbing_card_message", array("user_id" => $uid, "target_id" => $v["id"]), array("id", "create_time"), "", array("create_time desc"));
                    $view = pdo_getall("longbing_card_count", array("user_id" => $v["id"], "to_uid" => $uid, "sign" => "view"), array("id", "create_time"), "", array("create_time desc"));
                    $copy = pdo_getall("longbing_card_count", array("user_id" => $v["id"], "to_uid" => $uid, "sign" => "copy"), array("id", "create_time"), "", array("create_time desc"));
                    $mark = pdo_get("longbing_card_user_mark", array("user_id" => $v["id"], "staff_id" => $uid), array("id", "create_time", "mark"));
                    $users[$k]["mark"] = 0;
                    if ($mark) {
                        $users[$k]["mark"] = $mark["mark"];
                    }
                    $users[$k]["count"] = count($praise) + count($message1) + count($message2) + count($view) + count($copy);
                    $times[] = $praise[0]["create_time"];
                    $times[] = $message1[0]["create_time"];
                    $times[] = $message2[0]["create_time"];
                    $times[] = $view[0]["create_time"];
                    $times[] = $copy[0]["create_time"];
                    rsort($times);
                    $users[$k]["last_time"] = $times[0] ? $times[0] : 0;
                    $users[$k]["name"] = $users[$k]["nickName"];
                    $client_info = pdo_get("longbing_card_client_info", array("user_id" => $v["id"], "staff_id" => $uid));
                    if ($client_info && $client_info["name"]) {
                        $users[$k]["name"] = $client_info["name"];
                    }
                }
                $users = json_decode(json_encode($users), true);
                return $this->result(0, "", array("data" => $users));
            } else {
                return $this->result(0, "", array());
            }
        }
    }
    public function doPageSendTemplate()
    {
        load()->func("communication");
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        $content = $_GPC["content"];
        $client_infoz = pdo_get("longbing_card_client_info", array("user_id" => $uid, "staff_id" => $to_uid));
        if ($client_infoz && $client_infoz["is_mask"]) {
            return $this->result(-1, "1", array());
        }
        $date = $_GPC["date"];
        if (!$uid || !$to_uid) {
            return $this->result(-1, "2", array());
        }
        $appid = $_W["account"]["key"];
        $appsecret = $_W["account"]["secret"];
        $user = pdo_get("longbing_card_user", array("id" => $to_uid));
        $client = pdo_get("longbing_card_user", array("id" => $uid));
        $client_info = pdo_get("longbing_card_client_info", array("user_id" => $uid, "staff_id" => $to_uid));
        if (!empty($client_info) && $client_info["name"]) {
            $name = $client_info["name"];
        } else {
            $name = $client["nickName"];
        }
        if (empty($user)) {
            return $this->result(-1, "3", array());
        }
        if ($user["is_staff"] != 1) {
            return $this->result(-1, "4", array());
        }
        $openid = $user["openid"];
        if ($date) {
            $date = date("Y-m-d H:i", $date);
        } else {
            $date = date("Y-m-d H:i");
        }
        $config = pdo_get("longbing_card_config", array("uniacid" => $_W["uniacid"]));
        if ($config["notice_switch"] == 1) {
            if (!$config["wx_appid"]) {
                return $this->result(-1, "", array());
            }
            if (!$config["wx_tplid"]) {
                return $this->result(-1, "", array());
            }
            $ac = $this->getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=" . $ac;
            $date = date("Y-m-d H:i");
            $page = "longbing_card/chat/staffChat/staffChat?is_tpl=1&to_uid=" . $uid;
            $data = array("touser" => $user["openid"], "mp_template_msg" => array("appid" => $config["wx_appid"], "url" => "http://weixin.qq.com/download", "template_id" => $config["wx_tplid"], "miniprogram" => array("appid" => $appid, "pagepath" => $page), "data" => array("first" => array("value" => "", "color" => "#c27ba0"), "keyword1" => array("value" => $name, "color" => "#93c47d"), "keyword2" => array("value" => "你有未读消息!", "color" => "#0000ff"), "remark" => array("value" => $date, "color" => "#45818e"))));
            if ($content) {
                $data["mp_template_msg"]["data"]["keyword2"]["value"] = $content;
            }
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            $res = $this->curlPost($url, $data);
            $res = json_decode($res, true);
            if ($res["errcode"] && $res["errcode"] == 40001) {
                $appidMd5 = md5($appid);
                @unlink(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt");
            }
            return $this->result(0, "", array("e" => $res));
        }
        if ($config["notice_switch"] == 2) {
            $appid = $config["corpid"];
            $appsecret = $config["corpsecret"];
            $agentid = $config["agentid"];
            if (!$appid || !$appsecret || !$agentid) {
                return $this->result(-1, "", array());
            }
            $user_info = pdo_get("longbing_card_user_info", array("fans_id" => $to_uid));
            $touser = $user_info["ww_account"];
            if (!$touser) {
                return $this->result(-1, "", array());
            }
            $data = array("touser" => $touser, "msgtype" => "text", "agentid" => $agentid, "text" => array("content" => $name . "给你发了条消息，请前往小程序查看"));
            if ($content) {
                $data["text"]["content"] = $content;
            }
            include_once $_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/work.weixin.class.php";
            $work = new work($appid, $appsecret);
            $result = $work->send($data);
            $result = json_decode($result, true);
            if ($result["errcode"] == 0) {
                return $this->result(0, "", array());
            }
            return $this->result(-1, "" . $result["errcode"] . "-" . $result["errmsg"], array());
        }
        if (!$config["mini_template_id"]) {
            return $this->result(-1, "12", array());
        }
        $form = $this->getFormId($to_uid);
        if (!$form) {
            return $this->result(-1, "21", array());
        }
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return $this->result(-1, "1111", array($access_token));
        }
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
        $page = "longbing_card/chat/staffChat/staffChat?is_tpl=1&to_uid=" . $uid;
        $postData = array("touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $name . ":"), "keyword2" => array("value" => "你有未读消息!!"), "keyword3" => array("value" => $date)));
        if ($content) {
            $postData["data"]["keyword2"]["value"] = $content;
        }
        $postData = json_encode($postData, JSON_UNESCAPED_UNICODE);
        $response = ihttp_post($url, $postData);
        return $this->result(0, "", array());
    }
    public function sendTotal($count_id)
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $appid = $_W["account"]["key"];
        $count_info = pdo_get("longbing_card_count", array("id" => $count_id));
        if (!$count_info) {
            return false;
        }
        $check = pdo_getall("longbing_card_count", array("user_id" => $count_info["user_id"], "to_uid" => $count_info["to_uid"], "type" => $count_info["type"], "uniacid" => $count_info["uniacid"], "target" => $count_info["target"], "sign" => $count_info["sign"]));
        $count = 1;
        if ($check) {
            $count = count($check);
        }
        $client_infoz = pdo_get("longbing_card_client_info", array("user_id" => $count_info["user_id"], "staff_id" => $count_info["to_uid"]));
        $send = true;
        if ($client_infoz && $client_infoz["is_mask"]) {
            $send = false;
        }
        if (!$send) {
            return false;
        }
        $send_body = $this->getSendBody($count_info);
        if ($send_body == false) {
            return false;
        }
        if (!$count_info["sign"] == "order") {
            $send_body = "第" . $count . "次" . $send_body;
        }
        $config = pdo_get("longbing_card_config", array("uniacid" => $uniacid));
        $tabbar = pdo_get("longbing_card_tabbar", array("uniacid" => $uniacid));
        $client = pdo_get("longbing_card_user", array("id" => $count_info["user_id"]));
        $staff = pdo_get("longbing_card_user", array("id" => $count_info["to_uid"]));
        if ($config["notice_switch"] == 1) {
            if (!$config["wx_appid"]) {
                return false;
            }
            if (!$config["wx_tplid"]) {
                return false;
            }
            $ac = $this->getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=" . $ac;
            $date = date("Y-m-d H:i", $count_info["create_time"]);
            $page = "longbing_card/staff/radar/radar";
            $data = array("touser" => $staff["openid"], "mp_template_msg" => array("appid" => $config["wx_appid"], "url" => "http://weixin.qq.com/download", "template_id" => $config["wx_tplid"], "miniprogram" => array("appid" => $appid, "pagepath" => $page), "data" => array("first" => array("value" => "", "color" => "#c27ba0"), "keyword1" => array("value" => $client["nickName"], "color" => "#93c47d"), "keyword2" => array("value" => $send_body, "color" => "#0000ff"), "remark" => array("value" => $date, "color" => "#45818e"))));
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            $res = $this->curlPost($url, $data);
            if ($res) {
                $res = json_decode($res, true);
                if (isset($res["errcode"]) && $res["errcode"] != 0) {
                    $form = $this->getFormId($count_info["to_uid"]);
                    if ($form) {
                        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $ac;
                        $postData = array("touser" => $staff["openid"], "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $client["nickName"]), "keyword2" => array("value" => $send_body), "keyword3" => array("value" => $date)));
                        $postData = json_encode($postData);
                        $response = curlPost($url, $postData);
                    }
                }
            }
        }
        if ($config["notice_switch"] == 2) {
            $appid = $config["corpid"];
            $appsecret = $config["corpsecret"];
            $agentid = $config["agentid"];
            if (!$appid || !$appsecret || !$agentid) {
                return true;
            }
            $user_info = pdo_get("longbing_card_user_info", array("fans_id" => $count_info["to_uid"]));
            $touser = $user_info["ww_account"];
            if (!$touser) {
                return true;
            }
            $data = array("touser" => $touser, "msgtype" => "text", "agentid" => $agentid, "text" => array("content" => $client["nickName"] . "," . $send_body));
            if ($count_info["sign"] == "view" && $count_info["type"]) {
                $info = pdo_get("longbing_card_goods", array("id" => $count_info["target"]));
                $data = array("touser" => $touser, "msgtype" => "news", "agentid" => $agentid, "news" => array("articles" => array(array("title" => $client["nickName"], "description" => $send_body, "url" => tomedia($info["cover"]), "picurl" => tomedia($info["cover"])))));
            }
            include_once $_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/work.weixin.class.php";
            $work = new work($appid, $appsecret);
            $result = $work->send($data);
        }
        if ($config["notice_switch"] == 3 || $config["notice_switch"] == 0) {
            $openid = $staff["openid"];
            $date = date("Y-m-d H:i", $count_info["create_time"]);
            $form = $this->getFormId($count_info["to_uid"]);
            if (!$form) {
                return false;
            }
            $access_token = $this->getAccessToken();
            if (!$access_token) {
                return false;
            }
            $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
            $page = "longbing_card/pages/index/index?to_uid=" . $count_info["to_uid"] . "&currentTabBar=toCard";
            $page = "longbing_card/staff/radar/radar";
            $postData = array("touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $client["nickName"]), "keyword2" => array("value" => $send_body), "keyword3" => array("value" => $date)));
            $postData = json_encode($postData, JSON_UNESCAPED_UNICODE);
            $response = ihttp_post($url, $postData);
        }
        return true;
    }
    protected function getSendBody(array $count_info = array(), $count_id = 0)
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $tabbar = pdo_get("longbing_card_tabbar", array("uniacid" => $uniacid));
        if (empty($count_info) && $count_id == 0) {
            return false;
        }
        if (empty($count_info) && $count_id != 0) {
            $count_info = pdo_get("longbing_card_count", array("id" => $count_id));
            if (!$count_info) {
                return false;
            }
        }
        $body = "";
        if ($count_info["sign"] == "praise") {
            switch ($count_info["type"]) {
                case 2:
                    $body = "浏览你的名片";
                    break;
                case 4:
                    $body = "分享你的名片";
                    break;
            }
        }
        if ($count_info["sign"] == "view") {
            switch ($count_info["type"]) {
                case 1:
                    $body = "浏览" . $tabbar["menu2_name"] . "列表";
                    break;
                case 2:
                    $body = "浏览" . $tabbar["menu2_name"] . "详情";
                    if ($count_info["target"]) {
                        $info = pdo_get("longbing_card_goods", array("id" => $count_info["target"]));
                        $body .= ":" . $info["name"];
                    }
                    break;
                case 3:
                    $body = "浏览" . $tabbar["menu3_name"] . "列表";
                    break;
                case 4:
                    $body = "点赞" . $tabbar["menu3_name"];
                    break;
                case 5:
                    $body = $tabbar["menu3_name"] . "留言";
                    break;
                case 6:
                    $body = "浏览公司" . $tabbar["menu4_name"];
                    break;
                case 7:
                    $body = "浏览" . $tabbar["menu3_name"] . "详情";
                    if ($count_info["target"]) {
                        $info = pdo_get("longbing_card_timeline", array("id" => $count_info["target"]));
                        $body .= ":" . $info["title"];
                    }
                    break;
                case 8:
                    $body = "浏览" . $tabbar["menu3_name"] . "视频详情";
                    if ($count_info["target"]) {
                        $info = pdo_get("longbing_card_timeline", array("id" => $count_info["target"]));
                        $body .= ":" . $info["title"];
                    }
                    break;
                case 9:
                    $body = "浏览" . $tabbar["menu3_name"] . "外链详情";
                    if ($count_info["target"]) {
                        $info = pdo_get("longbing_card_timeline", array("id" => $count_info["target"]));
                        $body .= ":" . $info["title"];
                    }
                    break;
                case 10:
                    $body = "浏览" . $tabbar["menu3_name"] . "跳转小程序";
                    if ($count_info["target"]) {
                        $info = pdo_get("longbing_card_timeline", array("id" => $count_info["target"]));
                        $body .= ":" . $info["title"];
                    }
                    break;
            }
        }
        if ($count_info["sign"] == "copy") {
            switch ($count_info["type"]) {
                case 1:
                    $body = "同步到通讯录";
                    break;
                case 2:
                    $body = "拨打手机号";
                    break;
                case 3:
                    $body = "拨打座机号";
                    break;
                case 4:
                    $body = "复制微信";
                    break;
                case 5:
                    $body = "复制邮箱";
                    break;
                case 6:
                    $body = "复制公司名";
                    break;
                case 7:
                    $body = "查看定位";
                    break;
                case 8:
                    $body = "咨询产品";
                    break;
                case 9:
                    $body = "播放语音";
                    break;
                case 10:
                    $body = "保存名片海报";
                    break;
                case 11:
                    $body = "拨打400热线";
                    break;
            }
        }
        if ($count_info["sign"] == "order") {
            switch ($count_info["type"]) {
                case 1:
                    $body = "购买商品";
                    break;
                case 2:
                    $body = "参与拼团";
                    break;
            }
            if ($count_info["target"]) {
                $order_info = pdo_get("longbing_card_shop_order", array("id" => $count_info["target"]));
                if ($order_info) {
                    $body .= "，订单号：" . $order_info["transaction_id"];
                }
            }
        }
        if ($body) {
            return $body;
        }
        return false;
    }
    public function getAccessToken()
    {
        global $_GPC;
        global $_W;
        $appid = $_W["account"]["key"];
        $appsecret = $_W["account"]["secret"];
        $appidMd5 = md5($appid);
        if (!is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt") && is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/")) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
            $data = ihttp_get($url);
            $data = json_decode($data["content"], true);
            if (!isset($data["access_token"])) {
                return false;
            }
            $access_token = $data["access_token"];
            file_put_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array("at" => $access_token, "time" => time() + 6200)));
            return $access_token;
        }
        if (is_file(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt")) {
            $fileInfo = file_get_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt");
            if (!$fileInfo) {
                $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
                $data = ihttp_get($url);
                $data = json_decode($data["content"], true);
                if (!isset($data["access_token"])) {
                    return false;
                }
                $access_token = $data["access_token"];
                file_put_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array("at" => $access_token, "time" => time() + 6200)));
                return $access_token;
            }
            $fileInfo = json_decode($fileInfo, true);
            if ($fileInfo["time"] < time()) {
                $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $appsecret;
                $data = ihttp_get($url);
                $data = json_decode($data["content"], true);
                if (!isset($data["access_token"])) {
                    return false;
                }
                $access_token = $data["access_token"];
                file_put_contents(IA_ROOT . "/data/tpl/web/" . $appidMd5 . ".txt", json_encode(array("at" => $access_token, "time" => time() + 6200)));
                return $access_token;
            }
            return $fileInfo["at"];
        }
        return false;
    }
    public function doPageSendTemplateCilent()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $date = $_GPC["date"];
        if (!$uid || !$client_id || !$date) {
            return $this->result(-1, "", array());
        }
        $content = $_GPC["content"];
        $appid = $_W["account"]["key"];
        $appsecret = $_W["account"]["secret"];
        $client = pdo_get("longbing_card_user", array("id" => $client_id));
        $send_info = pdo_get("longbing_card_user_info", array("fans_id" => $uid));
        if ($client["is_staff"] == 1) {
        }
        if (empty($client) || empty($send_info)) {
            return $this->result(-1, "", array());
        }
        $name = $send_info["name"];
        $openid = $client["openid"];
        if ($date) {
            $date = date("Y-m-d H:i", $date);
        } else {
            $date = date("Y-m-d H:i");
        }
        $config = pdo_get("longbing_card_config", array("uniacid" => $_W["uniacid"]), array("mini_template_id", "notice_switch", "notice_i", "min_tmppid"));
        if ($config["notice_switch"] == 1 && false) {
            if (!$config["notice_i"]) {
                return $this->result(-1, "", array());
            }
            if (!$config["min_tmppid"]) {
                return $this->result(-1, "", array());
            }
            $url = "https://" . $_SERVER["HTTP_HOST"] . "/app/index.php?i=" . $config["notice_i"] . "&c=entry&do=sendmsg&m=longbing_tmsg&min_uid=" . $client_id . "&min_uniacid=" . $_W["uniacid"] . "&min_tmppid=" . $config["min_tmppid"];
            $data = array("first" => array("value" => $name, "color" => "#c27ba0"), "keyword1" => array("value" => "你有未读消息", "color" => "#93c47d"), "keyword2" => array("value" => $date, "color" => "#0000ff"), "remark" => array("value" => "", "color" => "#45818e"));
            if ($content) {
                $data["keyword1"]["value"] = $content;
            }
            $page = "longbing_card/pages/index/index?to_uid=" . $uid . "&currentTabBar=toCard";
            $data = array("data_content" => $data, "pagepath" => $page, "appid" => $appid);
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            $res = $this->curlPost($url, $data);
            return $this->result(0, "", array());
        }
        if (!$config["mini_template_id"]) {
            return $this->result(-1, "", array());
        }
        $form = $this->getFormId($client_id);
        if (!$form) {
            return $this->result(-1, "", array());
        }
        $access_token = $this->getAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
        $page = "longbing_card/chat/userChat/userChat?to_uid=" . $uid . "&is_tpl=1";
        $postData = array("touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $name . ":"), "keyword2" => array("value" => "你有未读消息"), "keyword3" => array("value" => $date)));
        if ($content) {
            $postData["data"]["keyword2"]["value"] = $content;
        }
        $postData = json_encode($postData, JSON_UNESCAPED_UNICODE);
        $response = ihttp_post($url, $postData);
        return $this->result(0, "", array());
    }
    public function doPageJob()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $list = pdo_getall("longbing_card_job", array("uniacid" => $_W["uniacid"], "status" => 1));
        if (empty($list)) {
            pdo_insert("longbing_card_job", array("uniacid" => $_W["uniacid"], "status" => 1, "name" => "首席服务官"));
            $list = pdo_getall("longbing_card_job", array("uniacid" => $_W["uniacid"], "status" => 1));
        }
        return $this->result(0, "", $list);
    }
    public function doPageForward()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $to_uid = $_GPC["to_uid"];
        $type = $_GPC["type"];
        $target_id = $_GPC["target_id"];
        if (!$target_id) {
            $target_id = 0;
        }
        if (!$uid || !$to_uid || !$type) {
            return $this->result(-1, "", array());
        }
        $result = pdo_insert("longbing_card_forward", array("user_id" => $uid, "staff_id" => $to_uid, "type" => $type, "target_id" => $target_id, "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageRate()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $check = pdo_get("longbing_card_rate", array("user_id" => $client_id, "staff_id" => $uid, "uniacid" => $_W["uniacid"]));
        $time = time();
        $rate = 0;
        $beginTime = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        if (!empty($check)) {
            if (86400 < $check["create_time"] - $beginTime) {
                $rate = $this->countRate($client_id, $uid, $_W["uniacid"]);
            } else {
                $rate = $check["rate"];
            }
        } else {
            $rate = $this->countRate($client_id, $uid, $_W["uniacid"]);
        }
        return $this->result(0, "", array("rate" => $rate));
    }
    public function doPageIsStaff()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $info = pdo_get("longbing_card_user", array("uniacid" => $_W["uniacid"], "id" => $uid));
        if (!empty($info) && $info["is_staff"]) {
            return $this->result(0, "", array("is_staff" => 1, "is_boss" => $info["is_boss"]));
        }
        return $this->result(0, "", array("is_staff" => 0));
    }
    public function doPageFormIds()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
        $info = pdo_getall("longbing_card_formId", array("uniacid" => $_W["uniacid"], "user_id" => $uid, "create_time >" => $beginTime));
        return $this->result(0, "", array("count" => count($info)));
    }
    public function countRate($client_id, $uid, $uniacid)
    {
        $this->cross();
        $is_deal = pdo_get("longbing_card_user_mark", array("user_id" => $client_id, "staff_id" => $uid, "uniacid" => $uniacid));
        $check = pdo_get("longbing_card_rate", array("user_id" => $client_id, "staff_id" => $uid));
        if (!empty($is_deal) && $is_deal["mark"] == 2) {
            if ($check) {
                pdo_update("longbing_card_rate", array("rate" => 100, "update_time" => time()), array("id" => $check["id"]));
            } else {
                pdo_insert("longbing_card_rate", array("user_id" => $client_id, "staff_id" => $uid, "rate" => 100, "create_time" => time(), "update_time" => time(), "uniacid" => $uniacid));
            }
            return 100;
        }
        $staff_count = 0;
        $client_count = 0;
        if (!empty($is_deal)) {
            $staff_count += 5;
        }
        $chat = pdo_fetch("SELECT id,user_id,target_id,create_time FROM " . tablename("longbing_card_chat") . " where (user_id = " . $uid . " && target_id = " . $client_id . ") OR (user_id = " . $client_id . " && target_id = " . $uid . ")");
        if (!empty($chat)) {
            $mesage = pdo_getall("longbing_card_message", array("chat_id" => $chat["id"]));
            $count = count($mesage);
            if ($count) {
                $client_count += 4;
            }
            if (15 < $count) {
                $count = 15;
            }
            $staff_count += $count;
        }
        $label = pdo_getall("longbing_card_user_label", array("user_id" => $client_id, "staff_id" => $uid, "uniacid" => $uniacid));
        $count = count($label);
        if (10 < $count) {
            $count = 10;
        }
        $staff_count += $count * 2;
        $info = pdo_get("longbing_card_user_phone", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid));
        if (!empty($info)) {
            $client_count += 6;
        }
        $info = pdo_get("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid, "sign" => "copy", "type" => 2));
        if (!empty($info)) {
            $client_count += 4;
        }
        $info = pdo_get("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid, "sign" => "copy", "type" => 1));
        if (!empty($info)) {
            $client_count += 4;
        }
        $info = pdo_get("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid, "sign" => "copy", "type" => 4));
        if (!empty($info)) {
            $client_count += 4;
        }
        $info = pdo_get("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid, "sign" => "praise", "type" => 1));
        if (!empty($info)) {
            $client_count += 1;
        }
        $info = pdo_get("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid, "sign" => "praise", "type" => 3));
        if (!empty($info)) {
            $client_count += 1;
        }
        $client_count += 2;
        $info = pdo_get("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid, "sign" => "view", "type" => 1));
        if (!empty($info)) {
            $client_count += 2;
        }
        $info = pdo_get("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid, "sign" => "view", "type" => 2));
        if (!empty($info)) {
            $client_count += 2;
        }
        $info = pdo_get("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid, "sign" => "view", "type" => 3));
        if (!empty($info)) {
            $client_count += 2;
        }
        $info = pdo_get("longbing_card_count", array("user_id" => $client_id, "to_uid" => $uid, "uniacid" => $uniacid, "sign" => "view", "type" => 6));
        if (!empty($info)) {
            $client_count += 2;
        }
        $info = pdo_get("longbing_card_user", array("id" => $client_id, "uniacid" => $uniacid));
        if (!empty($info) && $info["avatarUrl"]) {
            $client_count += 2;
        }
        $info = pdo_get("longbing_card_forward", array("user_id" => $client_id, "staff_id" => $uid, "uniacid" => $uniacid, "type" => 1));
        if (!empty($info)) {
            $client_count += 4;
        }
        $count = $staff_count + $client_count;
        if (92 < $count) {
            $count = 92;
        }
        if ($check) {
            pdo_update("longbing_card_rate", array("rate" => $count, "update_time" => time()), array("id" => $check["id"]));
        } else {
            pdo_insert("longbing_card_rate", array("user_id" => $client_id, "staff_id" => $uid, "rate" => $count, "create_time" => time(), "update_time" => time(), "uniacid" => $uniacid));
        }
        return $count;
    }
    public function doPageDealDate()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $date = $_GPC["date"];
        if (!$uid || !$client_id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_date", array("user_id" => $client_id, "staff_id" => $uid, "uniacid" => $_W["uniacid"]));
        if (!$date) {
            return $this->result(0, "", $info);
        }
        if (empty($info)) {
            $result = pdo_insert("longbing_card_date", array("user_id" => $client_id, "staff_id" => $uid, "date" => $date, "uniacid" => $_W["uniacid"], "create_time" => time(), "update_time" => time()));
        } else {
            $result = pdo_update("longbing_card_date", array("date" => $date, "update_time" => time()), array("user_id" => $client_id, "staff_id" => $uid, "uniacid" => $_W["uniacid"]));
        }
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageConfig()
    {
        global $_GPC;
        global $_W;
        $info = pdo_get("longbing_card_config", array("uniacid" => $_W["uniacid"]));
        $info["copyright"] = tomedia($info["copyright"]);
        if (!LONGBING_AUTH_COPYRIGHT) {
            $info["copyright"] = "https://retail.xiaochengxucms.com/images/12/2018/11/crDXyl3TyBRLUBch6ToqXL6e9D96hY.jpg";
        }
        return $this->result(0, "", $info);
    }
    public function doPageConfigV2()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        if ($this->redis_sup_v3) {
            $redis_key = "longbing_card_configv2_" . $_W["uniacid"];
            $data = $this->redis_server_v3->get($redis_key);
            if ($data) {
                $data = json_decode($data, true);
                $data["config"]["from_redis"] = 1;
                return $this->result(0, "", $data);
            }
        }
        $info = pdo_get("longbing_card_config", array("uniacid" => $_W["uniacid"]), array("show_card", "copyright", "mini_app_name", "allow_create", "create_text", "logo_switch", "logo_text", "logo_phone", "notice_switch", "notice_i", "min_tmppid", "order_overtime", "collage_overtime", "force_phone", "admin_account", "first_extract", "cash_mini", "plug_form"));
        $info["copyright"] = tomedia($info["copyright"]);
        if (!LONGBING_AUTH_COPYRIGHT) {
            $info["copyright"] = "https://retail.xiaochengxucms.com/images/12/2018/11/crDXyl3TyBRLUBch6ToqXL6e9D96hY.jpg";
        }
        $info["form"] = 0;
        if (defined("LONGBING_AUTH_FORM") && LONGBING_AUTH_FORM) {
            $info["form"] = 1;
        }
        $checkExists = pdo_tableexists("longbing_cardauth2_config");
        if ($checkExists) {
            $auth_info = pdo_get("longbing_cardauth2_config", array("modular_id" => $_W["uniacid"]));
            if ($auth_info && $auth_info["copyright_id"]) {
                $copyright = pdo_get("longbing_cardauth2_copyright", array("id" => $auth_info["copyright_id"]));
                if ($copyright) {
                    $info["copyright"] = tomedia($copyright["image"]);
                    $info["logo_text"] = $copyright["text"];
                    $info["logo_phone"] = $copyright["phone"];
                    $info["logo_switch"] = 2;
                }
            }
        }
        $data["config"] = $info;
        $company = pdo_getall("longbing_card_company", array("uniacid" => $_W["uniacid"], "status" => 1));
        foreach ($company as $k => $v) {
            $company[$k]["desc"] = tomedia($v["desc"]);
            $company[$k]["logo"] = $this->transImage($v["logo"]);
            $images = $v["culture"];
            $images = trim($images, ",");
            $images = explode(",", $images);
            $tmp = array();
            foreach ($images as $k2 => $v2) {
                $src = tomedia($v2);
                array_push($tmp, $src);
            }
            $company[$k]["culture"] = $tmp;
        }
        $data["company_list"] = $company;
        $user = pdo_get("longbing_card_user_info", array("fans_id" => $uid, "is_staff" => 1, "status" => 1));
        if ($user) {
            if ($user["company_id"]) {
                $company2 = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"], "id" => $user["company_id"]));
                if ($company2) {
                    $company2["logo"] = $this->transImage($company2["logo"]);
                    $data["my_company"] = $company2;
                } else {
                    $data["my_company"] = $company[0];
                }
            } else {
                $data["my_company"] = $company[0];
            }
        } else {
            $data["my_company"] = $company[0];
        }
        $info = pdo_get("longbing_card_tabbar", array("uniacid" => $_W["uniacid"]));
        if (empty($info)) {
            $time = time();
            $dataConfig = array("uniacid" => $_W["uniacid"], "status" => 1, "create_time" => $time, "update_time" => $time);
            $res = pdo_insert("longbing_card_tabbar", $dataConfig);
            if ($res) {
                $info = pdo_get("longbing_card_tabbar", array("uniacid" => $_W["uniacid"]));
            } else {
                $data["tabBar"] = array();
            }
        }
        if ($info) {
            $i = 0;
            foreach ($info as $k => $v) {
                if ($i < 5 && 0 < $i) {
                    $data["tabBar"]["menu_name"][] = $v;
                } else {
                    if ($i < 9 && 0 < $i) {
                        $data["tabBar"]["menu_is_hide"][] = $v;
                    } else {
                        if ($i < 13 && 0 < $i) {
                            $data["tabBar"]["menu_url"][] = $v;
                        } else {
                            if ($i < 17 && 0 < $i) {
                                $data["tabBar"]["menu_url_out"][] = $v;
                            } else {
                                if ($i < 21 && 0 < $i) {
                                    $data["tabBar"]["menu_url_jump_way"][] = $v;
                                } else {
                                    $data["tabBar"][$k] = $v;
                                }
                            }
                        }
                    }
                }
                $i++;
            }
        }
        if ($this->redis_sup_v3) {
            $redis_key = "longbing_card_configv2_" . $_W["uniacid"];
            $this->redis_server_v3->set($redis_key, json_encode($data));
            $this->redis_server_v3->EXPIRE($redis_key, 60 * 60);
        }
        return $this->result(0, "", $data);
    }
    public function checkEmpty()
    {
        $h = date("H");
        if ($h % 2 || true) {
            pdo_delete("longbing_card_collection", array("to_uid" => 0));
            pdo_delete("longbing_card_user_info", array("fans_id" => 0));
            pdo_query("UPDATE " . tablename("longbing_card_user_info") . " SET `card_type` = 'cardType1' WHERE card_type = 1;");
            pdo_query("UPDATE " . tablename("longbing_card_user_info") . " SET `card_type` = 'cardType1' WHERE card_type = '';");
        }
        global $_GPC;
        global $_W;
        $time = time();
        $check = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"]));
        if (!empty($check)) {
            return false;
        }
        $dataCompany = array("uniacid" => $_W["uniacid"], "name" => "某某科技有限责任公司公司", "addr" => "某某省某某市某某街100室", "logo" => "https://retail.xiaochengxucms.com/images/12/2018/11/crDXyl3TyBRLUBch6ToqXL6e9D96hY.jpg", "phone" => "17361005975", "longitude" => "104.054880", "latitude" => "30.549300", "create_time" => $time, "update_time" => $time);
        pdo_insert("longbing_card_company", $dataCompany);
        $check = pdo_get("longbing_card_job", array("uniacid" => $_W["uniacid"]));
        if (empty($check)) {
            $dataJob = array("uniacid" => $_W["uniacid"], "name" => "首席服务官", "status" => 1, "create_time" => $time, "update_time" => $time);
            pdo_insert("longbing_card_job", $dataJob);
        }
        $check = pdo_get("longbing_card_config", array("uniacid" => $_W["uniacid"]));
        if (empty($check)) {
            $dataConfig = array("uniacid" => $_W["uniacid"], "status" => 1, "create_time" => $time, "update_time" => $time);
            pdo_insert("longbing_card_config", $dataConfig);
        }
        $check = pdo_get("longbing_card_tabbar", array("uniacid" => $_W["uniacid"]));
        if (empty($check)) {
            $dataConfig = array("uniacid" => $_W["uniacid"], "status" => 1, "create_time" => $time, "update_time" => $time);
            pdo_insert("longbing_card_tabbar", $dataConfig);
        }
        return true;
    }
    public function insertMessage($data)
    {
    }
    protected function insertView($user_id, $to_uid, $type, $uniacid, $target = "")
    {
        load()->func("communication");
        global $_GPC;
        global $_W;
        $appid = $_W["account"]["key"];
        $appsecret = $_W["account"]["secret"];
        $uniacid = $_W["uniacid"];
        if ($target) {
            $check = pdo_getall("longbing_card_count", array("user_id" => $user_id, "to_uid" => $to_uid, "type" => $type, "uniacid" => $uniacid, "target" => $target, "sign" => "view"));
        } else {
            $check = pdo_getall("longbing_card_count", array("user_id" => $user_id, "to_uid" => $to_uid, "type" => $type, "uniacid" => $uniacid, "sign" => "view"));
        }
        if ($user_id == $to_uid) {
            return 1;
        }
        $time = time();
        $count = 1;
        if ($check) {
            $ten = count($check) % 10;
            $count = count($check);
        }
        $client_infoz = pdo_get("longbing_card_client_info", array("user_id" => $user_id, "staff_id" => $to_uid));
        $send = true;
        if ($client_infoz && $client_infoz["is_mask"]) {
            $send = false;
        }
        $config = pdo_get("longbing_card_config", array("uniacid" => $uniacid));
        $tabbar = pdo_get("longbing_card_tabbar", array("uniacid" => $uniacid));
        $client = pdo_get("longbing_card_user", array("id" => $user_id));
        $client2 = pdo_get("longbing_card_user", array("id" => $to_uid));
        $name = $client["nickName"];
        $data = array("user_id" => $user_id, "to_uid" => $to_uid, "type" => $type, "uniacid" => $uniacid, "target" => $target, "sign" => "view", "scene" => $_GPC["scene"], "create_time" => $time, "update_time" => $time);
        $res = pdo_insert("longbing_card_count", $data);
        if ($send) {
            if ($config["notice_switch"] == 1) {
                if (!$config["wx_appid"]) {
                    return false;
                }
                if (!$config["wx_tplid"]) {
                    return false;
                }
                switch ($type) {
                    case 1:
                        $witch = "浏览" . $tabbar["menu2_name"] . "列表";
                        break;
                    case 2:
                        $witch = "浏览" . $tabbar["menu2_name"] . "详情";
                        if ($target) {
                            $info = pdo_get("longbing_card_goods", array("id" => $target));
                            $witch .= ":" . $info["name"];
                        }
                        break;
                    case 3:
                        $witch = "浏览" . $tabbar["menu3_name"] . "列表";
                        break;
                    case 4:
                        $witch = "点赞" . $tabbar["menu3_name"];
                        break;
                    case 5:
                        $witch = $tabbar["menu3_name"] . "留言";
                        break;
                    case 6:
                        $witch = "浏览公司" . $tabbar["menu4_name"];
                        break;
                    case 7:
                        $witch = "浏览" . $tabbar["menu3_name"] . "详情";
                        if ($target) {
                            $info = pdo_get("longbing_card_timeline", array("id" => $target));
                            $witch .= ":" . $info["title"];
                        }
                        break;
                    default:
                        $witch = "浏览你的名片内容";
                }
                $ac = $this->getAccessToken();
                $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=" . $ac;
                $date = date("Y-m-d H:i");
                $page = "longbing_card/staff/radar/radar";
                $data = array("touser" => $client2["openid"], "mp_template_msg" => array("appid" => $config["wx_appid"], "url" => "http://weixin.qq.com/download", "template_id" => $config["wx_tplid"], "miniprogram" => array("appid" => $appid, "pagepath" => $page), "data" => array("first" => array("value" => "", "color" => "#c27ba0"), "keyword1" => array("value" => $name, "color" => "#93c47d"), "keyword2" => array("value" => "第" . $count . "次" . $witch, "color" => "#0000ff"), "remark" => array("value" => $date, "color" => "#45818e"))));
                $data = json_encode($data);
                $res = $this->curlPost($url, $data);
                if ($res) {
                    $res = json_decode($res, true);
                    if (isset($res["errcode"]) && $res["errcode"] != 0) {
                        $form = $this->getFormId($to_uid);
                        if ($form) {
                            $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $ac;
                            $postData = array("touser" => $client2["openid"], "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $name), "keyword2" => array("value" => "第" . $count . "次" . $witch), "keyword3" => array("value" => $date)));
                            $postData = json_encode($postData);
                            $response = curlPost($url, $postData);
                        }
                    }
                }
            } else {
                if ($config["notice_switch"] == 2) {
                    $appid = $config["corpid"];
                    $appsecret = $config["corpsecret"];
                    $agentid = $config["agentid"];
                    if (!$appid || !$appsecret || !$agentid) {
                        return true;
                    }
                    $user_info = pdo_get("longbing_card_user_info", array("fans_id" => $to_uid));
                    $touser = $user_info["ww_account"];
                    if (!$touser) {
                        return true;
                    }
                    $witch = "";
                    switch ($type) {
                        case 1:
                            $witch = "浏览" . $tabbar["menu2_name"] . "列表";
                            break;
                        case 2:
                            $witch = "浏览" . $tabbar["menu2_name"] . "详情";
                            if ($target) {
                                $info = pdo_get("longbing_card_goods", array("id" => $target));
                                $witch .= ":" . $info["name"];
                            }
                            break;
                        case 3:
                            $witch = "浏览" . $tabbar["menu3_name"] . "列表";
                            break;
                        case 4:
                            $witch = "点赞" . $tabbar["menu3_name"];
                            break;
                        case 5:
                            $witch = $tabbar["menu3_name"] . "留言";
                            break;
                        case 6:
                            $witch = "浏览公司" . $tabbar["menu4_name"];
                            break;
                        case 7:
                            $witch = "浏览" . $tabbar["menu3_name"] . "详情";
                            if ($target) {
                                $info = pdo_get("longbing_card_timeline", array("id" => $target));
                                $witch .= ":" . $info["title"];
                            }
                            break;
                        default:
                            $witch = "浏览你的名片内容";
                    }
                    $data = array("touser" => $touser, "msgtype" => "text", "agentid" => $agentid, "text" => array("content" => $name . "，第" . $count . "次" . $witch));
                    if ($type == 2) {
                        $data = array("touser" => $touser, "msgtype" => "news", "agentid" => $agentid, "news" => array("articles" => array(array("title" => $info["name"], "description" => $name . "，第" . $count . "次" . $witch, "url" => tomedia($info["cover"]), "picurl" => tomedia($info["cover"])))));
                    }
                    include_once $_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/work.weixin.class.php";
                    $work = new work($appid, $appsecret);
                    $result = $work->send($data);
                } else {
                    $openid = $client2["openid"];
                    $date = date("Y-m-d H:i");
                    if ($config["mini_template_id"]) {
                        $form = $this->getFormId($to_uid);
                        if ($form) {
                            $access_token = $this->getAccessToken();
                            if (!$access_token) {
                                return false;
                            }
                            $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
                            $page = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCard";
                            switch ($type) {
                                case 1:
                                    $witch = "浏览" . $tabbar["menu2_name"] . "列表";
                                    $page = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toShop";
                                    break;
                                case 2:
                                    $witch = "浏览" . $tabbar["menu2_name"] . "详情";
                                    if ($target) {
                                        $info = pdo_get("longbing_card_goods", array("id" => $target));
                                        $witch .= ":" . $info["name"];
                                    }
                                    $page = "longbing_card/pages/shop/detail/detail?to_uid=" . $to_uid . "&id=" . $info["id"];
                                    break;
                                case 3:
                                    $witch = "浏览" . $tabbar["menu3_name"] . "列表";
                                    $page = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toNews";
                                    break;
                                case 4:
                                    $witch = "点赞" . $tabbar["menu3_name"];
                                    $page = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toNews";
                                    break;
                                case 5:
                                    $witch = $tabbar["menu3_name"] . "留言";
                                    $page = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toNews";
                                    break;
                                case 6:
                                    $witch = "浏览公司" . $tabbar["menu4_name"];
                                    $page = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCompany";
                                    break;
                                case 7:
                                    $witch = "浏览" . $tabbar["menu3_name"] . "详情";
                                    if ($target) {
                                        $info = pdo_get("longbing_card_timeline", array("id" => $target));
                                        $witch .= ":" . $info["title"];
                                    }
                                    $page = "longbing_card/pages/news/detail/detail?to_uid=" . $to_uid . "&id=" . $info["id"];
                                    break;
                            }
                            $page = "longbing_card/staff/radar/radar";
                            $postData = array("touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $name), "keyword2" => array("value" => "第" . $count . "次" . $witch), "keyword3" => array("value" => $date)));
                            $postData = json_encode($postData);
                            $response = curlPost($url, $postData);
                        }
                    }
                }
            }
        }
        pdo_fetch("DELETE FROM " . tablename("longbing_card_count") . " where user_id = to_uid && sign != 'praise'");
        pdo_fetch("DELETE FROM " . tablename("longbing_card_count") . " where user_id = to_uid && sign = 'praise' && type = 2");
        return $res;
    }
    protected function sendTplStaff($user_id, $to_uid, $type, $uniacid, $target = "")
    {
        global $_GPC;
        global $_W;
        $appid = $_W["account"]["key"];
        $appsecret = $_W["account"]["secret"];
        $config = pdo_get("longbing_card_config", array("uniacid" => $uniacid));
        $client = pdo_get("longbing_card_user", array("id" => $user_id));
        $client2 = pdo_get("longbing_card_user", array("id" => $to_uid));
        $name = $client["nickName"];
        $check = pdo_getall("longbing_card_count", array("user_id" => $user_id, "to_uid" => $to_uid, "type" => 2, "uniacid" => $uniacid, "sign" => "praise"));
        if ($user_id == $to_uid) {
            return false;
        }
        $time = time();
        $count = 1;
        if (!empty($check)) {
            $ten = count($check) % 10;
            $count = count($check);
        }
        if ($config["notice_switch"] == 1) {
            if (!$config["wx_appid"]) {
                return false;
            }
            if (!$config["wx_tplid"]) {
                return false;
            }
            $page = "longbing_card/staff/radar/radar";
            $date = date("Y-m-d H:i");
            $ac = $this->getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=" . $ac;
            $date = date("Y-m-d H:i");
            $page = "longbing_card/staff/radar/radar";
            $data = array("touser" => $client2["openid"], "mp_template_msg" => array("appid" => $config["wx_appid"], "url" => "http://weixin.qq.com/download", "template_id" => $config["wx_tplid"], "miniprogram" => array("appid" => $appid, "pagepath" => $page), "data" => array("first" => array("value" => "", "color" => "#c27ba0"), "keyword1" => array("value" => $name, "color" => "#93c47d"), "keyword2" => array("value" => "第" . $count . "次进入你的名片", "color" => "#0000ff"), "remark" => array("value" => $date, "color" => "#45818e"))));
            $data = json_encode($data);
            $res = $this->curlPost($url, $data);
            if ($res) {
                $res = json_decode($res, true);
                if (isset($res["errcode"]) && $res["errcode"] != 0) {
                    $form = $this->getFormId($to_uid);
                    if ($form) {
                        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $ac;
                        $postData = array("touser" => $client2["openid"], "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $name), "keyword2" => array("value" => "第" . $count . "次进入你的名片"), "keyword3" => array("value" => $date)));
                        $postData = json_encode($postData);
                        $response = curlPost($url, $postData);
                    }
                }
            }
        } else {
            if ($config["notice_switch"] == 2) {
                $appid = $config["corpid"];
                $appsecret = $config["corpsecret"];
                $agentid = $config["agentid"];
                if (!$appid || !$appsecret || !$agentid) {
                    return false;
                }
                $user_info = pdo_get("longbing_card_user_info", array("fans_id" => $to_uid));
                $touser = $user_info["ww_account"];
                if (!$touser) {
                    return false;
                }
                $data = array("touser" => $touser, "msgtype" => "text", "agentid" => $agentid, "text" => array("content" => $name . "第" . $count . "次进入你的名片"));
                include_once $_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/work.weixin.class.php";
                $work = new work($appid, $appsecret);
                $result = $work->send($data);
            } else {
                $openid = $client2["openid"];
                $date = date("Y-m-d H:i");
                if ($config["mini_template_id"]) {
                    $form = $this->getFormId($to_uid);
                    if ($form) {
                        $access_token = $this->getAccessToken();
                        if (!$access_token) {
                            return false;
                        }
                        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
                        $page = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCard";
                        $page = "longbing_card/staff/radar/radar";
                        $postData = array("touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $name), "keyword2" => array("value" => "第" . $count . "次进入你的名片"), "keyword3" => array("value" => $date)));
                        $postData = json_encode($postData);
                        $response = @curlPost($url, $postData);
                    }
                }
            }
        }
        pdo_fetch("DELETE FROM " . tablename("longbing_card_count") . " where user_id = to_uid && sign != 'praise'");
        pdo_fetch("DELETE FROM " . tablename("longbing_card_count") . " where user_id = to_uid && sign = 'praise' && type = 2");
        return true;
    }
    protected function sendTplStaff2($user_id, $to_uid, $type, $uniacid, $target = "")
    {
        global $_GPC;
        global $_W;
        $appid = $_W["account"]["key"];
        $appsecret = $_W["account"]["secret"];
        $config = pdo_get("longbing_card_config", array("uniacid" => $uniacid), array("mini_template_id", "notice_switch", "notice_i", "min_tmppid"));
        $client = pdo_get("longbing_card_user", array("id" => $user_id));
        $client2 = pdo_get("longbing_card_user", array("id" => $to_uid));
        $name = $client["nickName"];
        $check = pdo_getall("longbing_card_count", array("user_id" => $user_id, "to_uid" => $to_uid, "type" => 2, "uniacid" => $uniacid, "sign" => "praise"));
        if ($user_id == $to_uid) {
            return false;
        }
        $time = time();
        $count = 1;
        if (!empty($check)) {
            $ten = count($check) % 10;
            $count = count($check);
        }
        if ($config["notice_switch"] == 1) {
            if (!$config["notice_i"]) {
                return $this->result(-1, "", array());
            }
            if (!$config["min_tmppid"]) {
                return $this->result(-1, "", array());
            }
            $page = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCard";
            $url = "http://" . $_SERVER["HTTP_HOST"] . "/app/index.php?i=" . $config["notice_i"] . "&c=entry&do=sendmsg&m=longbing_tmsg&min_uid=" . $to_uid . "&min_uniacid=" . $_W["uniacid"] . "&min_tmppid=" . $config["min_tmppid"];
            $date = date("Y-m-d H:i");
            $data = array("first" => array("value" => $name, "color" => "#c27ba0"), "keyword1" => array("value" => "第" . $count . "次进入你的名片", "color" => "#93c47d"), "keyword2" => array("value" => $date, "color" => "#0000ff"), "remark" => array("value" => "备注", "color" => "#45818e"));
            echo "<pre>";
            $data = array("data_content" => $data, "pagepath" => $page, "appid" => $appid);
            $data = json_encode($data);
            $res = @$this->curlPost($url, $data);
        } else {
            $openid = $client2["openid"];
            $date = date("Y-m-d H:i");
            if ($config["mini_template_id"]) {
                $form = $this->getFormId($to_uid);
                if ($form) {
                    $access_token = $this->getAccessToken();
                    if (!$access_token) {
                        return $this->result(-1, "", array());
                    }
                    $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
                    $page = "longbing_card/pages/index/index?to_uid=" . $to_uid . "&currentTabBar=toCard";
                    $postData = array("touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $name), "keyword2" => array("value" => "第" . $count . "次进入你的名片"), "keyword3" => array("value" => $date)));
                    $postData = json_encode($postData);
                    $response = @ihttp_post($url, $postData);
                }
            }
        }
        pdo_fetch("DELETE FROM " . tablename("longbing_card_count") . " where user_id = to_uid && sign != 'praise'");
        pdo_fetch("DELETE FROM " . tablename("longbing_card_count") . " where user_id = to_uid && sign = 'praise' && type = 2");
        return true;
    }
    protected function pp($data)
    {
        $data = json_decode(json_encode($data), true);
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        exit;
    }
    protected function getRandStr($len)
    {
        $len = intval($len);
        $a = "A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,S,Y,Z";
        $a = explode(",", $a);
        $tmp = "";
        for ($i = 0; $i < $len; $i++) {
            $rand = rand(0, count($a));
            $tmp .= $a[$rand];
        }
        return $tmp;
    }
    protected function getFormId($to_uid)
    {
        $beginTime = mktime(0, 0, 0, date("m"), date("d") - 6, date("Y"));
        pdo_delete("longbing_card_formId", array("create_time <" => $beginTime));
        $formId = pdo_getall("longbing_card_formId", array("user_id" => $to_uid), array(), "", "id asc", 1);
        if (empty($formId)) {
            return false;
        }
        if ($formId[0]["create_time"] < $beginTime) {
            pdo_delete("longbing_card_formId", array("id" => $formId[0]["id"]));
            $this->getFormId($to_uid);
        } else {
            pdo_delete("longbing_card_formId", array("id" => $formId[0]["id"]));
            return $formId[0]["formId"];
        }
    }
    public function http_file_get($url, $data = NULL)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $info = curl_exec($curl);
        curl_close($curl);
        return $info;
    }
    protected function transImage($path)
    {
        $path = tomedia($path);
        global $_GPC;
        global $_W;
        $arr = explode("/", $path);
        $fileName = "images/longbing_card/" . $_W["uniacid"] . "/" . $arr[count($arr) - 1];
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images");
        }
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card");
        }
        if (!is_dir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/")) {
            mkdir(ATTACHMENT_ROOT . "/" . "images/longbing_card/" . $_W["uniacid"] . "/");
        }
        if (file_exists(ATTACHMENT_ROOT . $fileName)) {
            $path = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $fileName;
            $path = str_replace("ttp://", "ttps://", $path);
            if (!strstr($path, "ttps://")) {
                $path = "https://" . $path;
            }
            return $path;
        }
        if (!strstr($path, $_SERVER["HTTP_HOST"])) {
            file_put_contents(ATTACHMENT_ROOT . "/" . $fileName, $this->http_file_get($path));
            $path = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $fileName;
        } else {
            if (strstr($path, "." . $_SERVER["HTTP_HOST"])) {
                file_put_contents(ATTACHMENT_ROOT . "/" . $fileName, $this->http_file_get($path));
                $path = $_W["siteroot"] . $_W["config"]["upload"]["attachdir"] . "/" . $fileName;
            } else {
                $path = str_replace("ttp://", "ttps://", $path);
                if (!strstr($path, "ttps://")) {
                    $path = "https://" . $path;
                }
            }
        }
        $path = str_replace("ttp://", "ttps://", $path);
        if (!strstr($path, "ttps://")) {
            $path = "https://" . $path;
        }
        return $path;
    }
    protected function transImageBack($path)
    {
        $pathArr = explode(",", $path);
        $tmp = array();
        foreach ($pathArr as $k => $v) {
            if (substr($v, 0, 7) == "http://" || substr($v, 0, 8) == "https://" || substr($v, 0, 2) == "//") {
                $pos = strpos($v, "/images");
                if ($pos) {
                    $url = substr($v, $pos + 1);
                    array_push($tmp, $url);
                } else {
                    array_push($tmp, $v);
                }
            } else {
                array_push($tmp, $v);
            }
        }
        $tmp = implode(",", $tmp);
        return $tmp;
    }
    public function doPageAddReply()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $content = $_GPC["content"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$uid || !$content) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_quick_reply", array("user_id" => $uid, "content" => $content, "uniacid" => $_W["uniacid"], "status" => 1));
        if (!empty($info)) {
            return $this->result(-1, "", array());
        }
        $time = time();
        $result = pdo_insert("longbing_card_quick_reply", array("user_id" => $uid, "content" => $content, "uniacid" => $_W["uniacid"], "status" => 1, "create_time" => $time, "update_time" => $time));
        if ($result) {
            $insert_id = pdo_insertid();
            return $this->result(0, "", array("id" => $insert_id));
        }
        return $this->result(-1, "", array());
    }
    public function doPageEditReply()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        $content = $_GPC["content"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$uid || !$content || !$id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_quick_reply", array("user_id" => $uid, "id" => $id, "uniacid" => $_W["uniacid"], "status" => 1));
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        $time = time();
        $result = pdo_update("longbing_card_quick_reply", array("content" => $content, "update_time" => $time), array("id" => $id));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageDelReply()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $id = $_GPC["id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$uid || !$id) {
            return $this->result(-1, "", array());
        }
        $info = pdo_get("longbing_card_quick_reply", array("user_id" => $uid, "id" => $id, "uniacid" => $_W["uniacid"], "status" => 1));
        if (empty($info)) {
            return $this->result(-1, "", array());
        }
        $time = time();
        $result = pdo_delete("longbing_card_quick_reply", array("id" => $id));
        if ($result) {
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function doPageReplyList()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $data = array();
        $info = pdo_getall("longbing_card_reply_type", array("uniacid" => $_W["uniacid"], "status" => 1), array(), "", array("top desc", "id desc"));
        $my = pdo_getall("longbing_card_quick_reply", array("uniacid" => $_W["uniacid"], "status" => 1, "user_id" => $uid), array(), "", array("top desc", "id desc"));
        $my = array("title" => "自定义话术", "list" => $my);
        $data[] = $my;
        foreach ($info as $k => $v) {
            $list = pdo_getall("longbing_card_quick_reply", array("uniacid" => $_W["uniacid"], "status" => 1, "type" => $v["id"]), array("content"), "", array("top desc", "id desc"));
            $v["list"] = $list;
            array_push($data, $v);
        }
        return $this->result(0, "", $data);
    }
    public function doPageTabBar()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $staff_id = $_GPC["staff_id"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        $info = pdo_get("longbing_card_tabbar", array("uniacid" => $_W["uniacid"]));
        if (empty($info)) {
            $time = time();
            $dataConfig = array("uniacid" => $_W["uniacid"], "status" => 1, "create_time" => $time, "update_time" => $time);
            $res = pdo_insert("longbing_card_tabbar", $dataConfig);
            if ($res) {
                $info = pdo_get("longbing_card_tabbar", array("uniacid" => $_W["uniacid"]));
            } else {
                return $this->result(0, "", array());
            }
        }
        if ($info) {
            $data = array();
            $i = 0;
            foreach ($info as $k => $v) {
                if ($i < 5 && 0 < $i) {
                    $data["menu_name"][] = $v;
                } else {
                    if ($i < 9 && 0 < $i) {
                        $data["menu_is_hide"][] = $v;
                    } else {
                        if ($i < 13 && 0 < $i) {
                            $data["menu_url"][] = $v;
                        } else {
                            if ($i < 17 && 0 < $i) {
                                $data["menu_url_out"][] = $v;
                            } else {
                                if ($i < 21 && 0 < $i) {
                                    $data["menu_url_jump_way"][] = $v;
                                } else {
                                    $data[$k] = $v;
                                }
                            }
                        }
                    }
                }
                $i++;
            }
            return $this->result(0, "", $data);
        } else {
            return $this->result(0, "", array());
        }
    }
    public function doPageSource()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $client_id = $_GPC["client_id"];
        $staff_id = $_GPC["staff_id"];
        $uniacid = $_W["uniacid"];
        if ($staff_id) {
            $uid = $staff_id;
        }
        if (!$client_id) {
            return $this->result(-1, "", array());
        }
        $user = pdo_get("longbing_card_user", array("id" => $client_id, "uniacid" => $uniacid));
        if (empty($user)) {
            return $this->result(-1, "", array());
        }
        $data = array();
        $data["user"] = array("is_qr" => $user["is_qr"], "is_group" => $user["is_group"], "type" => $user["type"], "target_id" => $user["target_id"], "scene" => $user["scene"], "openGId" => $user["openGId"]);
        if ($data["user"]["is_group"]) {
            $group = pdo_get("longbing_card_share_group", array("user_id" => $uid, "client_id" => $client_id));
            if ($group) {
                $data["user"]["openGId"] = $group["openGId"];
            }
        }
        $phone = pdo_get("longbing_card_user_phone", array("user_id" => $client_id, "status" => 1, "uniacid" => $uniacid));
        if (!$phone) {
            $phone = pdo_get("longbing_card_client_info", array("user_id" => $client_id, "staff_id" => $uid, "status" => 1, "uniacid" => $uniacid));
            if (!$phone) {
                $phone = pdo_get("longbing_card_client_info", array("user_id" => $client_id, "status" => 1, "uniacid" => $uniacid));
                $phone = !$phone ? "" : $phone["phone"];
                $data["phone"] = $phone;
            } else {
                $data["phone"] = $phone["phone"];
            }
        } else {
            $data["phone"] = $phone["phone"];
        }
        $label = pdo_get("longbing_card_user_label", array("user_id" => $client_id, "status" => 1, "uniacid" => $uniacid, "staff_id" => $uid));
        if ($label) {
            $data["is_label"] = 1;
        } else {
            $data["is_label"] = 0;
        }
        $start = pdo_get("longbing_card_start", array("user_id" => $client_id, "staff_id" => $uid, "uniacid" => $uniacid));
        $data["start"] = 0;
        if (!empty($start)) {
            $data["start"] = 1;
        }
        $collection = pdo_get("ims_longbing_card_collection", array("uid" => $client_id, "to_uid" => $uid));
        $data["share_info"] = "";
        $data["share_str"] = "来自搜索";
        if ($collection && $collection["from_uid"]) {
            $share_info = pdo_get("longbing_card_user", array("id" => $collection["from_uid"]));
            if ($share_info) {
                $data["share_info"] = $share_info;
                $data["share_str"] = "来自" . $share_info["nickName"];
                if ($share_info["is_staff"] == 1) {
                    $share_info = pdo_get("longbing_card_user_info", array("fans_id" => $collection["from_uid"]));
                    $data["share_str"] = "来自" . $share_info["name"];
                }
                if ($collection["is_qr"] == 0 && $collection["is_group"] == 0) {
                    $data["share_str"] .= "分享的名片";
                }
                if ($collection["is_qr"]) {
                    $data["share_str"] .= "分享的二维码";
                }
                if ($collection["is_group"]) {
                    $data["share_str"] .= "分享到群//XL:的名片";
                    $data["is_group_opGId"] = $collection["openGId"];
                }
                if ($collection["is_group"] && $collection["is_qr"]) {
                    $data["share_str"] .= "分享到群//XL:的二维码";
                    $data["is_group_opGId"] = $collection["openGId"];
                }
            }
        }
        if ($collection && $collection["from_uid"] == 0) {
            if ($collection["is_qr"]) {
                $data["share_str"] = "来自二维码";
            }
            if ($collection["is_group"]) {
                $data["share_str"] = "来自群//XL:分享";
                $data["is_group_opGId"] = $collection["openGId"];
            }
        }
        if ($collection && $collection["hanover_name"]) {
            $data["share_str"] = "来自" . $collection["hanover_name"] . "的工作交接";
        }
        return $this->result(0, "", $data);
    }
    protected function cross()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:GET,POST");
        header("Access-Control-Allow-Headers:x-requested-with,content-type");
    }
    public function check_is_boss($uid)
    {
        $user_info = pdo_get("longbing_card_user", array("id" => $uid));
        if (!$user_info) {
            return false;
        }
        if ($user_info["is_staff"] == 0 || $user_info["is_boss"] == 0) {
            return false;
        }
        return true;
    }
    public function doPageBossOverview()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $type = $_GPC["type"];
        $uniacid = $_W["uniacid"];
        $is_more = $_GPC["is_more"];
        $check_is_boss = $this->check_is_boss($uid);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        if (!$type) {
            $type = 0;
        }
        $beginTime = 0;
        switch ($type) {
            case 1:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"));
                break;
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            default:
                $beginTime = 0;
        }
        if ($beginTime == 0) {
            $new_client = pdo_getall("longbing_card_user", array("uniacid" => $uniacid), array("id"));
            $new_client = count($new_client);
            $view_client = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE uniacid = " . $uniacid . " && sign = 'praise' && `type` = 2 GROUP BY user_id";
            $view_client = pdo_fetchall($view_client);
            $view_client = count($view_client);
            $mark_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid), array("id"));
            $mark_client = count($mark_client);
            $chat_list = "SELECT chat_id, user_id, target_id FROM " . tablename("longbing_card_message") . " WHERE uniacid = " . $uniacid . " GROUP BY chat_id";
            $chat_list = pdo_fetchall($chat_list);
            if (!empty($chat_list)) {
                $tmp = array();
                foreach ($chat_list as $k => $v) {
                    array_push($tmp, $v["user_id"], $v["target_id"]);
                }
                $tmp = array_unique($tmp, SORT_NUMERIC);
                $tmp = implode(",", $tmp);
                if (strpos($tmp, ",")) {
                    $tmp = "(" . $tmp . ")";
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,id FROM " . tablename("longbing_card_user") . " WHERE id IN " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                } else {
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,id,create_time FROM " . tablename("longbing_card_user") . " WHERE id = " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                }
                $chat_client = $chat_list[0]["count"];
            } else {
                $chat_client = 0;
            }
            $sale_money = 0;
            $sale_order = 0;
            $orderList = pdo_getall("longbing_card_shop_order", array("uniacid" => $uniacid, "pay_status" => 1, "order_status !=" => 1));
            foreach ($orderList as $index => $item) {
                $sale_money += $item["total_price"];
            }
            $sale_money = sprintf("%.2f", $sale_money);
            $sale_order = count($orderList);
            $share_count = pdo_getall("longbing_card_forward", array("uniacid" => $uniacid, "type" => 1), array("id"));
            $share_count = count($share_count);
            $save_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 2) OR (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 1) GROUP BY user_id";
            $save_count = pdo_fetchall($save_count);
            $save_count = count($save_count);
            $thumbs_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 1) OR (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 3) OR (uniacid = " . $uniacid . " && sign = 'view' && `type` = 4) GROUP BY user_id";
            $thumbs_count = pdo_fetchall($thumbs_count);
            $thumbs_count = $thumbs_count[0]["count"];
        } else {
            $new_client = pdo_getall("longbing_card_user", array("uniacid" => $uniacid, "create_time >" => $beginTime), array("id"));
            $new_client = count($new_client);
            $view_client = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE uniacid = " . $uniacid . " && sign = 'praise' && `type` = 2 && create_time > " . $beginTime . " GROUP BY user_id";
            $view_client = pdo_fetchall($view_client);
            $view_client = count($view_client);
            $mark_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid, "create_time >" => $beginTime), array("id"));
            $mark_client = count($mark_client);
            $chat_list = "SELECT chat_id, user_id, target_id FROM " . tablename("longbing_card_message") . " WHERE uniacid = " . $uniacid . " && create_time > " . $beginTime . " GROUP BY chat_id";
            $chat_list = pdo_fetchall($chat_list);
            if (!empty($chat_list)) {
                $tmp = array();
                foreach ($chat_list as $k => $v) {
                    array_push($tmp, $v["user_id"], $v["target_id"]);
                }
                $tmp = array_unique($tmp, SORT_NUMERIC);
                $tmp = implode(",", $tmp);
                if (strpos($tmp, ",")) {
                    $tmp = "(" . $tmp . ")";
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,nickName FROM " . tablename("longbing_card_user") . " WHERE id IN " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                } else {
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,avatarUrl FROM " . tablename("longbing_card_user") . " WHERE id = " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                }
                $chat_client = $chat_list[0]["count"];
            } else {
                $chat_client = 0;
            }
            $sale_money = 0;
            $sale_order = 0;
            $orderList = pdo_getall("longbing_card_shop_order", array("uniacid" => $uniacid, "pay_status" => 1, "create_time >" => $beginTime, "order_status !=" => 1));
            foreach ($orderList as $index => $item) {
                $sale_money += $item["total_price"];
            }
            $sale_money = sprintf("%.2f", $sale_money);
            $sale_order = count($orderList);
            $share_count = pdo_getall("longbing_card_forward", array("uniacid" => $uniacid, "type" => 1, "create_time >" => $beginTime), array("id"));
            $share_count = count($share_count);
            $save_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 2 && create_time > " . $beginTime . ") OR (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 1 && create_time > " . $beginTime . ") GROUP BY user_id";
            $save_count = pdo_fetchall($save_count);
            $save_count = count($save_count);
            $thumbs_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 1 && create_time > " . $beginTime . ") OR (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 3 && create_time > " . $beginTime . ") OR (uniacid = " . $uniacid . " && sign = 'view' && `type` = 4 && create_time > " . $beginTime . ") GROUP BY user_id";
            $thumbs_count = pdo_fetchall($thumbs_count);
            $thumbs_count = $thumbs_count[0]["count"];
        }
        $data["nine"] = array("new_client" => $new_client, "view_client" => $view_client, "mark_client" => $mark_client, "chat_client" => $chat_client, "sale_money" => $sale_money, "sale_order" => $sale_order, "share_count" => $share_count, "save_count" => $save_count, "thumbs_count" => $thumbs_count);
        if ($is_more) {
            $client = pdo_getall("longbing_card_user", array("uniacid" => $uniacid), array("id"));
            $client = count($client);
            $mark_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid), array("id"));
            $mark_client = count($mark_client);
            $deal_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid, "mark" => 2), array("id"));
            $deal_client = count($deal_client);
            $data["dealRate"] = array("client" => $client, "mark_client" => $mark_client, "deal_client" => $deal_client);
            $last = 30;
            $dataOrderMoney = array();
            $dataNewClient = array();
            $dataAskClient = array();
            $dataMarkClient = array();
            $dataInterest = array();
            for ($i = 0; $i < $last; $i++) {
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
                $endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
                $md = date("m/d", $beginTime);
                $sql = "SELECT id, total_price FROM " . tablename("longbing_card_shop_order") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && pay_status = 1 && order_status != 1";
                $list = pdo_fetchall($sql);
                $sale_money = 0;
                foreach ($list as $index => $item) {
                    $sale_money += $item["total_price"];
                }
                $sale_money = sprintf("%.2f", $sale_money);
                $sale_order = count($list);
                $tmp = array("date" => $md, "time" => $beginTime, "order_number" => $sale_order, "money_number" => $sale_money);
                array_push($dataOrderMoney, $tmp);
                $sql = "SELECT id FROM " . tablename("longbing_card_user") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
                $info = pdo_fetchall($sql);
                $tmp = array("date" => $md, "time" => $beginTime, "number" => count($info));
                array_push($dataNewClient, $tmp);
                $sql = "SELECT user_id FROM " . tablename("longbing_card_message") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " GROUP BY user_id";
                $info = pdo_fetchall($sql);
                $tmp = array("date" => $md, "time" => $beginTime, "number" => count($info));
                array_push($dataAskClient, $tmp);
                $sql = "SELECT user_id FROM " . tablename("longbing_card_user_mark") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
                $info = pdo_fetchall($sql);
                $tmp = array("date" => $md, "time" => $beginTime, "number" => count($info));
                array_push($dataMarkClient, $tmp);
                $sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE sign = 'view' && type = 6 && uniacid = " . $uniacid;
                $compony = pdo_fetchall($sql);
                $compony = count($compony);
                $sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && type = 2 && uniacid = " . $uniacid . ") OR (sign = 'copy' && type = 1 && uniacid = " . $uniacid . ")";
                $goods = pdo_fetchall($sql);
                $goods = count($goods);
                $sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && uniacid = " . $uniacid . ") OR (sign != 'praise' && uniacid = " . $uniacid . ")";
                $staff = pdo_fetchall($sql);
                $staff = count($staff);
                $total = $compony + $goods + $staff;
                $dataInterest = array("compony" => array("number" => $compony, "rate" => 0), "goods" => array("number" => $goods, "rate" => 0), "staff" => array("number" => $staff, "rate" => 0));
                if ($total) {
                    foreach ($dataInterest as $k => $v) {
                        $dataInterest[$k]["rate"] = sprintf("%.2f", $v["number"] / $total) * 100;
                    }
                }
            }
            array_multisort(array_column($dataOrderMoney, "time"), SORT_ASC, $dataOrderMoney);
            array_multisort(array_column($dataNewClient, "time"), SORT_ASC, $dataNewClient);
            array_multisort(array_column($dataAskClient, "time"), SORT_ASC, $dataAskClient);
            array_multisort(array_column($dataMarkClient, "time"), SORT_ASC, $dataMarkClient);
            $data["orderMoney"] = $dataOrderMoney;
            $data["newClient"] = $dataNewClient;
            $data["askClient"] = $dataAskClient;
            $data["markClient"] = $dataMarkClient;
            $data["interest"] = $dataInterest;
            $last = 15;
            $dataActivity = array();
            for ($i = 0; $i < $last; $i++) {
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
                $endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
                $md = date("m/d", $beginTime);
                $sql = "SELECT id FROM " . tablename("longbing_card_count") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
                $count = pdo_fetchall($sql);
                $count = count($count);
                $sql = "SELECT id FROM " . tablename("longbing_card_forward") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
                $forward = pdo_fetchall($sql);
                $forward = count($forward);
                $sql = "SELECT id FROM " . tablename("longbing_card_user_phone") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
                $phone = pdo_fetchall($sql);
                $phone = count($phone);
                $tmp = array("date" => $md, "time" => $beginTime, "number" => $count + $forward + $phone);
                array_push($dataActivity, $tmp);
            }
            array_multisort(array_column($dataActivity, "time"), SORT_ASC, $dataActivity);
            $data["activity"] = $dataActivity;
            $dataActivityBarGraph = array();
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - $last, date("Y"));
            $thumbs = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where (sign = 'view' && `type` = 4 && uniacid = " . $uniacid . " && create_time > " . $beginTime . ") OR (sign = 'praise' && `type` = 1 && uniacid = " . $uniacid . " && create_time > " . $beginTime . ") OR (sign = 'praise' && `type` = 3 && uniacid = " . $uniacid . " && create_time > " . $beginTime . ")");
            $thumbs = count($thumbs);
            $dataActivityBarGraph[] = array("title" => "点赞", "number" => $thumbs, "rate" => 0);
            $save_phone = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where sign = 'copy' && `type` = 1 && uniacid = " . $uniacid . " && create_time > " . $beginTime);
            $save_phone = count($save_phone);
            $dataActivityBarGraph[] = array("title" => "保存手机", "number" => $save_phone, "rate" => 0);
            $comment = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_timeline_comment") . " where uniacid = " . $uniacid . " && create_time > " . $beginTime);
            $comment = count($comment);
            $dataActivityBarGraph[] = array("title" => "评论", "number" => $comment, "rate" => 0);
            $copy_wechat = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where sign = 'copy' && `type` = 4 && uniacid = " . $uniacid . " && create_time > " . $beginTime);
            $copy_wechat = count($copy_wechat);
            $dataActivityBarGraph[] = array("title" => "复制微信", "number" => $copy_wechat, "rate" => 0);
            $total = $thumbs + $save_phone + $comment + $copy_wechat;
            if ($total) {
                foreach ($dataActivityBarGraph as $k => $v) {
                    $dataActivityBarGraph[$k]["rate"] = sprintf("%.2f", $v["number"] / $total) * 100;
                }
            }
            $data["activityBarGraph"] = $dataActivityBarGraph;
        }
        return $this->result(0, "", $data);
    }
    public function doPageBossRadarNine()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $type = $_GPC["type"];
        $uniacid = $_W["uniacid"];
        if (!$type) {
            $type = 0;
        }
        $beginTime = 0;
        $check_is_boss = $this->check_is_boss($uid);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        switch ($type) {
            case 1:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"));
                break;
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            default:
                $beginTime = 0;
        }
        if ($beginTime == 0) {
            $new_client = pdo_getall("longbing_card_user", array("uniacid" => $uniacid), array("id"));
            $new_client = count($new_client);
            $view_client = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE uniacid = " . $uniacid . " && sign = 'praise' && `type` = 2 GROUP BY user_id";
            $view_client = pdo_fetchall($view_client);
            $view_client = count($view_client);
            $mark_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid), array("id"));
            $mark_client = count($mark_client);
            $chat_list = "SELECT chat_id, user_id, target_id FROM " . tablename("longbing_card_message") . " WHERE uniacid = " . $uniacid . " GROUP BY chat_id";
            $chat_list = pdo_fetchall($chat_list);
            if (!empty($chat_list)) {
                $tmp = array();
                foreach ($chat_list as $k => $v) {
                    array_push($tmp, $v["user_id"], $v["target_id"]);
                }
                $tmp = array_unique($tmp, SORT_NUMERIC);
                $tmp = implode(",", $tmp);
                if (strpos($tmp, ",")) {
                    $tmp = "(" . $tmp . ")";
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,id FROM " . tablename("longbing_card_user") . " WHERE id IN " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                } else {
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,id,create_time FROM " . tablename("longbing_card_user") . " WHERE id = " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                }
                $chat_client = $chat_list[0]["count"];
            } else {
                $chat_client = 0;
            }
            $sale_money = 0;
            $sale_order = 0;
            $share_count = pdo_getall("longbing_card_forward", array("uniacid" => $uniacid, "type" => 1), array("id"));
            $share_count = count($share_count);
            $save_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 2) OR (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 1) GROUP BY user_id";
            $save_count = pdo_fetchall($save_count);
            $save_count = count($save_count);
            $thumbs_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 1) OR (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 3) OR (uniacid = " . $uniacid . " && sign = 'view' && `type` = 4) GROUP BY user_id";
            $thumbs_count = pdo_fetchall($thumbs_count);
            $thumbs_count = $thumbs_count[0]["count"];
        } else {
            $new_client = pdo_getall("longbing_card_user", array("uniacid" => $uniacid, "create_time >" => $beginTime), array("id"));
            $new_client = count($new_client);
            $view_client = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE uniacid = " . $uniacid . " && sign = 'praise' && `type` = 2 && create_time > " . $beginTime . " GROUP BY user_id";
            $view_client = pdo_fetchall($view_client);
            $view_client = count($view_client);
            $mark_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid, "create_time >" => $beginTime), array("id"));
            $mark_client = count($mark_client);
            $chat_list = "SELECT chat_id, user_id, target_id FROM " . tablename("longbing_card_message") . " WHERE uniacid = " . $uniacid . " && create_time > " . $beginTime . " GROUP BY chat_id";
            $chat_list = pdo_fetchall($chat_list);
            if (!empty($chat_list)) {
                $tmp = array();
                foreach ($chat_list as $k => $v) {
                    array_push($tmp, $v["user_id"], $v["target_id"]);
                }
                $tmp = array_unique($tmp, SORT_NUMERIC);
                $tmp = implode(",", $tmp);
                if (strpos($tmp, ",")) {
                    $tmp = "(" . $tmp . ")";
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,nickName FROM " . tablename("longbing_card_user") . " WHERE id IN " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                } else {
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,avatarUrl FROM " . tablename("longbing_card_user") . " WHERE id = " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                }
                $chat_client = $chat_list[0]["count"];
            } else {
                $chat_client = 0;
            }
            $sale_money = 0;
            $sale_order = 0;
            $share_count = pdo_getall("longbing_card_forward", array("uniacid" => $uniacid, "type" => 1, "create_time >" => $beginTime), array("id"));
            $share_count = count($share_count);
            $save_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 2 && create_time > " . $beginTime . ") OR (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 1 && create_time > " . $beginTime . ") GROUP BY user_id";
            $save_count = pdo_fetchall($save_count);
            $save_count = count($save_count);
            $thumbs_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 1 && create_time > " . $beginTime . ") OR (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 3 && create_time > " . $beginTime . ") OR (uniacid = " . $uniacid . " && sign = 'view' && `type` = 4 && create_time > " . $beginTime . ") GROUP BY user_id";
            $thumbs_count = pdo_fetchall($thumbs_count);
            $thumbs_count = $thumbs_count[0]["count"];
        }
        $data = array("new_client" => $new_client, "view_client" => $view_client, "mark_client" => $mark_client, "chat_client" => $chat_client, "sale_money" => $sale_money, "sale_order" => $sale_order, "share_count" => $share_count, "save_count" => $save_count, "thumbs_count" => $thumbs_count);
        return $this->result(0, "", $data);
    }
    public function doPageBossDealRate()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        $check_is_boss = $this->check_is_boss($uid);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $client = pdo_getall("longbing_card_user", array("uniacid" => $uniacid), array("id"));
        $client = count($client);
        $mark_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid), array("id"));
        $mark_client = count($mark_client);
        $deal_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid, "mark" => 2), array("id"));
        $deal_client = count($deal_client);
        $data = array("client" => $client, "mark_client" => $mark_client, "deal_client" => $deal_client);
        return $this->result(0, "", $data);
    }
    public function doPageBossOrderMoney()
    {
        global $_GPC;
        global $_W;
        $last = 30;
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $data = array();
        for ($i = 0; $i < $last; $i++) {
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
            $endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
            $tmp = array("date" => date("Y-m-d", $beginTime), "time" => $beginTime, "order_number" => 0, "money_number" => 0);
            array_push($data, $tmp);
        }
        array_multisort(array_column($data, "time"), SORT_ASC, $data);
        return $this->result(0, "", $data);
    }
    public function doPageBossNewClient()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $last = 30;
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $data = array();
        for ($i = 0; $i < $last; $i++) {
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
            $endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
            $sql = "SELECT id FROM " . tablename("longbing_card_user") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
            $info = pdo_fetchall($sql);
            $tmp = array("date" => date("Y-m-d", $beginTime), "time" => $beginTime, "number" => count($info));
            array_push($data, $tmp);
        }
        array_multisort(array_column($data, "time"), SORT_ASC, $data);
        return $this->result(0, "", $data);
    }
    public function doPageBossAskClient()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $last = 30;
        $data = array();
        $list = pdo_getall("longbing_card_user", array("uniacid" => $uniacid, "is_staff" => 1), array("id"));
        $ids = "";
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $ids .= "," . $v["id"];
            }
            $ids = trim($ids, ",");
        }
        for ($i = 0; $i < $last; $i++) {
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
            $endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
            if (!empty($list)) {
                $sql = "SELECT user_id FROM " . tablename("longbing_card_message") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " GROUP BY user_id";
            } else {
                if (1 < count($list)) {
                    $ids = "(" . $ids . ")";
                    $sql = "SELECT user_id FROM " . tablename("longbing_card_message") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && user_id NOT IN " . $ids . " GROUP BY user_id";
                } else {
                    $sql = "SELECT user_id FROM " . tablename("longbing_card_message") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime . " && user_id != " . $ids . " GROUP BY user_id";
                }
            }
            $info = pdo_fetchall($sql);
            $tmp = array("date" => date("Y-m-d", $beginTime), "time" => $beginTime, "number" => count($info));
            array_push($data, $tmp);
        }
        array_multisort(array_column($data, "time"), SORT_ASC, $data);
        return $this->result(0, "", $data);
    }
    public function doPageBossMarkClient()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $last = 30;
        $data = array();
        for ($i = 0; $i < $last; $i++) {
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
            $endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
            $sql = "SELECT user_id FROM " . tablename("longbing_card_user_mark") . " where uniacid = " . $_W["uniacid"] . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
            $info = pdo_fetchall($sql);
            $tmp = array("date" => date("Y-m-d", $beginTime), "time" => $beginTime, "number" => count($info));
            array_push($data, $tmp);
        }
        array_multisort(array_column($data, "time"), SORT_ASC, $data);
        return $this->result(0, "", $data);
    }
    public function doPageBossInterest()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE sign = 'view' && type = 6 && uniacid = " . $uniacid;
        $compony = pdo_fetchall($sql);
        $compony = count($compony);
        $sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && type = 2 && uniacid = " . $uniacid . ") OR (sign = 'copy' && type = 1 && uniacid = " . $uniacid . ")";
        $goods = pdo_fetchall($sql);
        $goods = count($goods);
        $sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && uniacid = " . $uniacid . ") OR (sign != 'praise' && uniacid = " . $uniacid . ")";
        $staff = pdo_fetchall($sql);
        $staff = count($staff);
        $total = $compony + $goods + $staff;
        $data = array("compony" => array("number" => $compony, "rate" => 0), "goods" => array("number" => $goods, "rate" => 0), "staff" => array("number" => $staff, "rate" => 0));
        if ($total) {
            foreach ($data as $k => $v) {
                $data[$k]["rate"] = sprintf("%.2f", $v["number"] / $total) * 100;
            }
        }
        return $this->result(0, "", $data);
    }
    public function doPageBossActivity()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $last = 15;
        $data = array();
        for ($i = 0; $i < $last; $i++) {
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
            $endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
            $sql = "SELECT id FROM " . tablename("longbing_card_count") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
            $count = pdo_fetchall($sql);
            $count = count($count);
            $sql = "SELECT id FROM " . tablename("longbing_card_forward") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
            $forward = pdo_fetchall($sql);
            $forward = count($forward);
            $sql = "SELECT id FROM " . tablename("longbing_card_user_phone") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
            $phone = pdo_fetchall($sql);
            $phone = count($phone);
            $tmp = array("date" => date("Y-m-d", $beginTime), "time" => $beginTime, "number" => $count + $forward + $phone);
            array_push($data, $tmp);
        }
        array_multisort(array_column($data, "time"), SORT_ASC, $data);
        return $this->result(0, "", $data);
    }
    public function doPageBossActivityBarGraph()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $last = 15;
        $data = array();
        $beginTime = mktime(0, 0, 0, date("m"), date("d") - $last, date("Y"));
        $thumbs = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where (sign = 'view' && `type` = 4 && uniacid = " . $uniacid . " && create_time > " . $beginTime . ") OR (sign = 'praise' && `type` = 1 && uniacid = " . $uniacid . " && create_time > " . $beginTime . ") OR (sign = 'praise' && `type` = 3 && uniacid = " . $uniacid . " && create_time > " . $beginTime . ")");
        $thumbs = count($thumbs);
        $data[] = array("title" => "点赞", "number" => $thumbs, "rate" => 0);
        $save_phone = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where sign = 'copy' && `type` = 1 && uniacid = " . $uniacid . " && create_time > " . $beginTime);
        $save_phone = count($save_phone);
        $data[] = array("title" => "保存手机", "number" => $save_phone, "rate" => 0);
        $comment = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_timeline_comment") . " where uniacid = " . $uniacid . " && create_time > " . $beginTime);
        $comment = count($comment);
        $data[] = array("title" => "评论", "number" => $comment, "rate" => 0);
        $copy_wechat = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where sign = 'copy' && `type` = 4 && uniacid = " . $uniacid . " && create_time > " . $beginTime);
        $copy_wechat = count($copy_wechat);
        $data[] = array("title" => "复制微信", "number" => $copy_wechat, "rate" => 0);
        $total = $thumbs + $save_phone + $comment + $copy_wechat;
        if ($total) {
            foreach ($data as $k => $v) {
                $data[$k]["rate"] = sprintf("%.2f", $v["number"] / $total) * 100;
            }
        }
        return $this->result(0, "", $data);
    }
    public function doPageBossRankClients()
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $sign = $_GPC["sign"];
        $type = $_GPC["type"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        if (!$sign) {
            $sign = 1;
        }
        if (!$type) {
            $type = 1;
        }
        $curr = 1;
        if (isset($_GPC["page"])) {
            $curr = $_GPC["page"];
        }
        $offset = ($curr - 1) * 10;
        if ($sign == 1) {
            $sql = "SELECT count(id) as total, to_uid FROM " . tablename("longbing_card_collection") . " WHERE uid != to_uid && uniacid = " . $uniacid . " GROUP BY to_uid";
            $list = pdo_fetchall($sql);
            $staffs = pdo_fetchall("SELECT a.id,a.name,a.avatar,a.create_time,a.fans_id,b.nickName,b.avatarUrl FROM " . tablename("longbing_card_user_info") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.fans_id = b.id where a.status = 1 && b.is_staff = 1 && a.uniacid = " . $uniacid . " && a.fans_id > 0");
            foreach ($staffs as $k => $v) {
                $staffs[$k]["count"] = 0;
                $staffs[$k]["avatar"] = tomedia($v["avatar"]);
                foreach ($list as $k2 => $v2) {
                    if ($v2["to_uid"] == $v["fans_id"]) {
                        $staffs[$k]["count"] = $v2["total"];
                    }
                }
            }
            array_multisort(array_column($staffs, "count"), SORT_DESC, $staffs);
            foreach ($staffs as $k => $v) {
                $staffs[$k]["sort"] = $k + 1;
            }
            $array = array_slice($staffs, $offset, 10);
            $data = array("page" => $curr, "total_page" => ceil(count($staffs) / 10), "list" => $array, "total_count" => count($staffs));
            return $this->result(0, "", $data);
        } else {
            $beginTime = 0;
            switch ($type) {
                case 2:
                    $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                    break;
                case 3:
                    $beginTime = mktime(0, 0, 0, date("m"), date("d") - 15, date("Y"));
                    break;
                case 4:
                    $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                    break;
                default:
                    $beginTime = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"));
            }
            $sql = "SELECT count(id) as total, to_uid FROM " . tablename("longbing_card_collection") . " WHERE uid != to_uid && uniacid = " . $uniacid . " && create_time > " . $beginTime . " GROUP BY to_uid";
            $list = pdo_fetchall($sql);
            $staffs = pdo_fetchall("SELECT a.id,a.name,a.avatar,a.create_time,a.fans_id,b.nickName,b.avatarUrl FROM " . tablename("longbing_card_user_info") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.fans_id = b.id where a.status = 1 && b.is_staff = 1 && a.uniacid = " . $uniacid);
            foreach ($staffs as $k => $v) {
                $staffs[$k]["count"] = 0;
                $staffs[$k]["avatar"] = tomedia($v["avatar"]);
                foreach ($list as $k2 => $v2) {
                    if ($v2["to_uid"] == $v["fans_id"]) {
                        $staffs[$k]["count"] = $v2["total"];
                    }
                }
            }
            array_multisort(array_column($staffs, "count"), SORT_DESC, $staffs);
            foreach ($staffs as $k => $v) {
                $staffs[$k]["sort"] = $k + 1;
            }
            $array = array_slice($staffs, $offset, 10);
            $data = array("page" => $curr, "total_page" => ceil(count($staffs) / 10), "list" => $array, "total_count" => count($staffs));
            return $this->result(0, "", $data);
        }
    }
    public function doPageBossRankOrder()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $type = $_GPC["type"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        if (!$type) {
            $type = 1;
        }
        $beginTime = 0;
        switch ($type) {
            case 1:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"));
                break;
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 15, date("Y"));
                break;
            case 4:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            default:
                $beginTime = 0;
        }
        $curr = 1;
        if (isset($_GPC["page"])) {
            $curr = $_GPC["page"];
        }
        $offset = ($curr - 1) * 10;
        $staffs = pdo_fetchall("SELECT a.id,a.name,a.avatar,a.create_time,a.fans_id,b.nickName,b.avatarUrl FROM " . tablename("longbing_card_user_info") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.fans_id = b.id where a.status = 1 && b.is_staff = 1 && a.uniacid = " . $uniacid);
        foreach ($staffs as $k => $v) {
            $staffs[$k]["count"] = 0;
            $staffs[$k]["money"] = 0;
            $orderList = pdo_getall("longbing_card_shop_order", array("pay_status" => 1, "create_time >" => $beginTime, "to_uid" => $v["fans_id"], "order_status !=" => 1));
            foreach ($orderList as $index => $item) {
                $staffs[$k]["money"] += $item["total_price"];
            }
            $staffs[$k]["money"] = sprintf("%.2f", $staffs[$k]["money"]);
            $staffs[$k]["count"] = count($orderList);
            $staffs[$k]["avatar"] = tomedia($v["avatar"]);
        }
        array_multisort(array_column($staffs, "count"), SORT_DESC, $staffs);
        foreach ($staffs as $k => $v) {
            $staffs[$k]["sort"] = $k + 1;
        }
        $array = array_slice($staffs, $offset, 10);
        $data = array("page" => $curr, "total_page" => ceil(count($staffs) / 10), "list" => $array, "total_count" => count($staffs));
        return $this->result(0, "", $data);
    }
    public function doPageBossRankInteraction()
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $sign = $_GPC["sign"];
        $type = $_GPC["type"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        if (!$sign) {
            $sign = 1;
        }
        if (!$type) {
            $type = 5;
        }
        $beginTime = 0;
        switch ($type) {
            case 1:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"));
                break;
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 15, date("Y"));
                break;
            case 4:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            default:
                $beginTime = 0;
        }
        $curr = 1;
        if (isset($_GPC["page"])) {
            $curr = $_GPC["page"];
        }
        $offset = ($curr - 1) * 10;
        $staffs = pdo_fetchall("SELECT a.id,a.name,a.avatar,a.create_time,a.fans_id,b.nickName,b.avatarUrl FROM " . tablename("longbing_card_user_info") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.fans_id = b.id where a.status = 1 && b.is_staff = 1 && a.uniacid = " . $uniacid);
        foreach ($staffs as $k => $v) {
            $staffs[$k]["avatar"] = tomedia($v["avatar"]);
            $list = array();
            if ($sign == 1) {
                $list = pdo_getall("longbing_card_user_mark", array("staff_id" => $v["fans_id"], "create_time >" => $beginTime));
            } else {
                $list = pdo_getall("longbing_card_user_mark", array("mark" => 2, "staff_id" => $v["fans_id"], "create_time >" => $beginTime));
            }
            $staffs[$k]["count"] = count($list);
        }
        array_multisort(array_column($staffs, "count"), SORT_DESC, $staffs);
        foreach ($staffs as $k => $v) {
            $staffs[$k]["sort"] = $k + 1;
        }
        $array = array_slice($staffs, $offset, 10);
        $data = array("page" => $curr, "total_page" => ceil(count($staffs) / 10), "list" => $array, "total_count" => count($staffs));
        return $this->result(0, "", $data);
    }
    public function doPageBossRankRate()
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $type = $_GPC["type"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        if (!$type) {
            $type = 1;
        }
        $curr = 1;
        if (isset($_GPC["page"])) {
            $curr = $_GPC["page"];
        }
        $offset = ($curr - 1) * 10;
        $staffs = pdo_fetchall("SELECT a.id,a.name,a.avatar,a.create_time,a.fans_id,b.nickName,b.avatarUrl FROM " . tablename("longbing_card_user_info") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.fans_id = b.id where a.status = 1 && b.is_staff = 1 && a.uniacid = " . $uniacid);
        foreach ($staffs as $k => $v) {
            $staffs[$k]["avatar"] = tomedia($v["avatar"]);
            $sql = "SELECT id FROM " . tablename("longbing_card_rate") . " WHERE staff_id = " . $v["fans_id"] . " && uniacid = " . $uniacid;
            if ($type == 1) {
                $sql .= " && rate < 50";
            } else {
                if ($type == 2) {
                    $sql .= " && rate >= 50";
                }
            }
            $list = pdo_fetchall($sql);
            $staffs[$k]["count"] = count($list);
        }
        array_multisort(array_column($staffs, "count"), SORT_DESC, $staffs);
        foreach ($staffs as $k => $v) {
            $staffs[$k]["sort"] = $k + 1;
        }
        $array = array_slice($staffs, $offset, 10);
        $data = array("page" => $curr, "total_page" => ceil(count($staffs) / 10), "list" => $array, "total_count" => count($staffs));
        return $this->result(0, "", $data);
    }
    public function doPageBossClients()
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $staff_id = $_GPC["staff_id"];
        if (!$staff_id) {
            return $this->result(-1, "", array());
        }
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $where = array("uniacid" => $uniacid, "is_staff" => 0);
        $curr = 1;
        $len = 15;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $start = ($curr - 1) * $len;
        $list = pdo_fetchall("SELECT a.id,a.uid,b.nickName,b.avatarUrl FROM " . tablename("longbing_card_collection") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.uid = b.id WHERE a.uid = " . $staff_id . " ORDER BY a.id DESC LIMIT " . $start . ", " . $len);
        $tmp = array();
        foreach ($list as $k => $v) {
            $client_info = pdo_get("longbing_card_client_info", array("user_id" => $v["uid"], "uniacid" => $uniacid));
            $list[$k]["name"] = empty($client_info) ? "" : $client_info["name"];
            $rate = pdo_getall("longbing_card_rate", array("user_id" => $v["uid"], "uniacid" => $uniacid), "", array("rate desc"));
            $list[$k]["rate"] = empty($rate) ? 0 : $client_info[0]["rate"];
            $date = pdo_getall("longbing_card_date", array("user_id" => $v["uid"], "uniacid" => $uniacid), "", array("date desc"));
            $list[$k]["date"] = empty($date) ? 0 : $date[0]["date"];
            $mark = pdo_getall("longbing_card_user_mark", array("user_id" => $v["uid"], "uniacid" => $uniacid), "", array("status desc"));
            $list[$k]["mark"] = empty($mark) ? 0 : $date[0]["mark"];
            $client_orders = pdo_getall("longbing_card_shop_order", array("user_id" => $v["uid"], "pay_status" => 1, "order_status !=" => 1));
            $list[$k]["order"] = count($client_orders);
            $list[$k]["money"] = 0;
            foreach ($client_orders as $index => $item) {
                $list[$k]["money"] += $item["total_price"];
            }
        }
        $list2 = pdo_fetchall("SELECT a.id,a.uid,b.nickName,b.avatarUrl FROM " . tablename("longbing_card_collection") . " a LEFT JOIN " . tablename("longbing_card_user") . " b ON a.uid = b.id WHERE a.uid = " . $staff_id . " ORDER BY a.id DESC");
        $count = count($list2);
        $data = array("page" => $curr, "total_page" => ceil($count / 15), "list" => $list, "count" => $count);
        return $this->result(0, "", $data);
    }
    public function doPageBossAi()
    {
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $default = array("client" => 0, "charm" => 0, "interaction" => 0, "product" => 0, "website" => 0, "active" => 0);
        $max = array("client" => 0, "charm" => 0, "interaction" => 0, "product" => 0, "website" => 0, "active" => 0);
        $staff_list = pdo_getall("longbing_card_user", array("uniacid" => $uniacid, "is_staff" => 1), array("id", "nickName", "avatarUrl"));
        foreach ($staff_list as $k => $v) {
            $info = pdo_get("longbing_card_user_info", array("uniacid" => $uniacid, "fans_id" => $v["id"]), array("name", "avatar", "phone", "job_id"));
            $job = pdo_get("longbing_card_job", array("id" => $info["job_id"]));
            $info["job_name"] = !empty($job) ? $job["name"] : "";
            $total = 0;
            $value = $this->bossGetAiValue($v["id"]);
            foreach ($value as $k2 => $v2) {
                if ($max[$k2] < $v2["value"]) {
                    $max[$k2] = $v2["value"];
                }
                $total += $v2["value"];
            }
            $staff_list[$k]["value"] = $value;
            $staff_list[$k]["total"] = $total;
            $info["avatar"] = tomedia($info["avatar"]);
            $staff_list[$k]["info"] = $info;
        }
        array_multisort(array_column($staff_list, "total"), SORT_DESC, $staff_list);
        $limit = array(1, 10);
        $curr = 1;
        if (isset($_GPC["page"])) {
            $limit[0] = $_GPC["page"];
            $curr = $_GPC["page"];
        }
        $offset = ($curr - 1) * 10;
        $array = array_slice($staff_list, $offset, 10);
        $com = pdo_get("longbing_card_company", array("uniacid" => $uniacid));
        $com["logo"] = $this->transImage($com["logo"]);
        $data = array("list" => $array, "max" => $max, "com" => $com);
        return $this->result(0, "", $data);
    }
    protected function bossGetAiValue($id)
    {
        global $_GPC;
        global $_W;
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $uniacid = $_W["uniacid"];
        $value = array("client" => 0, "charm" => 0, "interaction" => 0, "product" => 0, "website" => 0, "active" => 0);
        $check = pdo_get("longbing_card_value", array("staff_id" => $id));
        if (!$check || $check && 24 * 60 * 60 < time() - $check["update_time"]) {
            $client = pdo_getall("longbing_card_collection", array("status" => 1, "to_uid" => $id));
            $client = count($client);
            if (0 < $client) {
                $client -= 1;
            }
            $value["client"] = $client;
            $list1 = pdo_getall("longbing_card_count", array("sign" => "praise", "type" => 1, "to_uid" => $id));
            $list2 = pdo_getall("longbing_card_count", array("sign" => "praise", "type" => 3, "to_uid" => $id));
            $list3 = pdo_getall("longbing_card_count", array("sign" => "copy", "to_uid" => $id));
            $count = count($list1) + count($list2) + count($list3);
            $value["charm"] = $count;
            $list1 = pdo_getall("longbing_card_message", array("user_id" => $id));
            $list2 = pdo_getall("longbing_card_message", array("target_id" => $id));
            $list3 = pdo_getall("longbing_card_count", array("sign" => "view", "to_uid" => $id));
            $count = count($list1) + count($list2) + count($list3);
            $value["interaction"] = $count;
            $list1 = pdo_getall("longbing_card_extension", array("user_id" => $id, "uniacid" => $uniacid));
            $list2 = pdo_getall("longbing_card_user_mark", array("staff_id" => $id, "uniacid" => $uniacid, "mark" => 2));
            $list3 = pdo_getall("longbing_card_forward", array("staff_id" => $id, "uniacid" => $uniacid, "type" => 2));
            $list4 = pdo_getall("longbing_card_share_group", array("user_id" => $id, "uniacid" => $uniacid, "view_goods !=" => ""));
            $count = count($list1) + count($list2) + count($list3) + count($list4);
            $value["product"] = $count;
            $list1 = pdo_getall("longbing_card_count", array("sign" => "view", "type" => 6, "to_uid" => $id));
            $list2 = pdo_getall("longbing_card_forward", array("staff_id" => $id, "uniacid" => $uniacid, "type" => 4));
            $count = count($list1) + count($list2);
            $value["website"] = $count;
            $list1 = pdo_getall("longbing_card_message", array("user_id" => $id));
            $list2 = pdo_getall("longbing_card_message", array("target_id" => $id));
            $list3 = pdo_getall("longbing_card_user_follow", array("staff_id" => $id));
            $list4 = pdo_getall("longbing_card_user_mark", array("staff_id" => $id));
            $count = count($list1) + count($list2) + count($list3) + count($list4);
            $value["active"] = $count;
            $insertData = $value;
            $insertData["staff_id"] = $id;
            $time = time();
            $insertData["update_time"] = $time;
            $insertData["uniacid"] = $uniacid;
            if (!$check) {
                $insertData["create_time"] = $time;
                pdo_insert("longbing_card_value", $insertData);
            } else {
                $updateData = $value;
                $insertData["update_time"] = $time;
                pdo_update("longbing_card_value", $insertData, array("id" => $check["id"]));
            }
        } else {
            $value = array("client" => $check["client"], "charm" => $check["charm"], "interaction" => $check["interaction"], "product" => $check["product"], "website" => $check["website"], "active" => $check["active"]);
        }
        $data = array("client" => array("titlle" => "获客能力值", "value" => $value["client"]), "charm" => array("titlle" => "个人魅力值", "value" => $value["charm"]), "interaction" => array("titlle" => "客户互动值", "value" => $value["interaction"]), "product" => array("titlle" => "产品推广值", "value" => $value["product"]), "website" => array("titlle" => "官网推广度", "value" => $value["website"]), "active" => array("titlle" => "销售主动性值", "value" => $value["active"]));
        $data = array(array("titlle" => "销售主动性值", "value" => $value["active"]), array("titlle" => "个人魅力值", "value" => $value["charm"]), array("titlle" => "获客能力值", "value" => $value["client"]), array("titlle" => "客户互动值", "value" => $value["interaction"]), array("titlle" => "产品推广值", "value" => $value["product"]), array("titlle" => "官网推广度", "value" => $value["website"]));
        return $data;
    }
    public function doPageBossStaffNumber()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uniacid = $_W["uniacid"];
        $staff_id = $_GPC["staff_id"];
        if (!$staff_id) {
            return $this->result(-1, "", array());
        }
        $check_is_boss = $this->check_is_boss($_GPC["user_id"]);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $client = pdo_getall("longbing_card_collection", array("status" => 1, "to_uid" => $staff_id, "uid !=" => $staff_id));
        $client = count($client);
        $value["client"] = $client;
        $mark = pdo_getall("longbing_card_user_mark", array("status" => 1, "staff_id" => $staff_id));
        $mark = count($mark);
        $value["mark"] = $mark;
        $chat1 = pdo_getall("longbing_card_chat", array("status" => 1, "user_id" => $staff_id));
        $chat2 = pdo_getall("longbing_card_chat", array("status" => 1, "target_id" => $staff_id));
        $chat = count($chat1) + count($chat2);
        $value["chat"] = $chat;
        $check = pdo_get("longbing_card_user_info", array("fans_id" => $staff_id, "uniacid" => $_W["uniacid"]));
        if (!$check || empty($check)) {
            return $this->result(-1, "", array());
        }
        if ($check["company_id"]) {
            $com = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"], "id" => $check["company_id"], "status" => 1));
            if (!$com) {
                $com = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"], "status" => 1));
            }
            $value["myCompany"] = $com;
        } else {
            $com = pdo_get("longbing_card_company", array("uniacid" => $_W["uniacid"], "status" => 1));
            $value["myCompany"] = $com;
        }
        return $this->result(0, "", $value);
    }
    public function doPageBossStaffRadarNine()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $type = $_GPC["type"];
        $uniacid = $_W["uniacid"];
        if (!$type) {
            $type = 0;
        }
        $beginTime = 0;
        $staff_id = $_GPC["staff_id"];
        if (!$staff_id) {
            return $this->result(-1, "", array());
        }
        $check_is_boss = $this->check_is_boss($uid);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        switch ($type) {
            case 1:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"));
                break;
            case 2:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 7, date("Y"));
                break;
            case 3:
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - 30, date("Y"));
                break;
            default:
                $beginTime = 0;
        }
        if ($beginTime == 0) {
            $new_client = pdo_getall("longbing_card_collection", array("to_uid" => $staff_id), array("id"));
            $new_client = count($new_client);
            $view_client = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE uniacid = " . $uniacid . " && sign = 'praise' && `type` = 2 && to_uid = " . $staff_id . " GROUP BY user_id";
            $view_client = pdo_fetchall($view_client);
            $view_client = $view_client[0]["count"];
            $mark_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid, "staff_id" => $staff_id), array("id"));
            $mark_client = count($mark_client);
            $chat_list = "SELECT chat_id, user_id, target_id FROM " . tablename("longbing_card_message") . " WHERE uniacid = " . $uniacid . " && target_id = " . $staff_id . " GROUP BY chat_id";
            $chat_list = pdo_fetchall($chat_list);
            if (!empty($chat_list)) {
                $tmp = array();
                foreach ($chat_list as $k => $v) {
                    array_push($tmp, $v["user_id"], $v["target_id"]);
                }
                $tmp = array_unique($tmp, SORT_NUMERIC);
                $tmp = implode(",", $tmp);
                if (strpos($tmp, ",")) {
                    $tmp = "(" . $tmp . ")";
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,id FROM " . tablename("longbing_card_user") . " WHERE id IN " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                } else {
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,id,create_time FROM " . tablename("longbing_card_user") . " WHERE id = " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                }
                $chat_list = $chat_list[0]["count"];
            } else {
                $chat_list = 0;
            }
            $sale_money = 0;
            $sale_order = 0;
            $share_count = pdo_getall("longbing_card_forward", array("uniacid" => $uniacid, "type" => 1, "staff_id" => $staff_id), array("id"));
            $share_count = count($share_count);
            $save_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 2 && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 1 && to_uid = " . $staff_id . ") GROUP BY user_id";
            $save_count = pdo_fetchall($save_count);
            $save_count = $save_count[0]["count"];
            $thumbs_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 1 && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 3 && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'view' && `type` = 4 && to_uid = " . $staff_id . ") GROUP BY user_id";
            $thumbs_count = pdo_fetchall($thumbs_count);
            $thumbs_count = $thumbs_count[0]["count"];
        } else {
            $new_client = pdo_getall("longbing_card_collection", array("to_uid" => $staff_id, "create_time >" => $beginTime), array("id"));
            $new_client = count($new_client);
            $view_client = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE uniacid = " . $uniacid . " && sign = 'praise' && `type` = 2 && create_time > " . $beginTime . " && to_uid = " . $staff_id . " GROUP BY user_id";
            $view_client = pdo_fetchall($view_client);
            $view_client = $view_client[0]["count"];
            $mark_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid, "create_time >" => $beginTime, "staff_id" => $staff_id), array("id"));
            $mark_client = count($mark_client);
            $chat_list = "SELECT chat_id, user_id, target_id FROM " . tablename("longbing_card_message") . " WHERE uniacid = " . $uniacid . " && create_time > " . $beginTime . " GROUP BY chat_id";
            $chat_list = pdo_fetchall($chat_list);
            if (!empty($chat_list)) {
                $tmp = array();
                foreach ($chat_list as $k => $v) {
                    array_push($tmp, $v["user_id"], $v["target_id"]);
                }
                $tmp = array_unique($tmp, SORT_NUMERIC);
                $tmp = implode(",", $tmp);
                if (strpos($tmp, ",")) {
                    $tmp = "(" . $tmp . ")";
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,nickName FROM " . tablename("longbing_card_user") . " WHERE id IN " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                } else {
                    $chat_list = pdo_fetchall("SELECT COUNT(id) as `count`,avatarUrl FROM " . tablename("longbing_card_user") . " WHERE id = " . $tmp . " && uniacid = " . $uniacid . " && is_staff = 0");
                }
                $chat_client = $chat_list[0]["count"];
            } else {
                $chat_client = 0;
            }
            $sale_money = 0;
            $sale_order = 0;
            $share_count = pdo_getall("longbing_card_forward", array("uniacid" => $uniacid, "type" => 1, "create_time >" => $beginTime, "staff_id" => $staff_id), array("id"));
            $share_count = count($share_count);
            $save_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 2 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'copy' && `type` = 1 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") GROUP BY user_id";
            $save_count = pdo_fetchall($save_count);
            $save_count = $save_count[0]["count"];
            $thumbs_count = "SELECT COUNT(id) as `count` FROM " . tablename("longbing_card_count") . " WHERE (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 1 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'praise' && `type` = 3 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") OR (uniacid = " . $uniacid . " && sign = 'view' && `type` = 4 && create_time > " . $beginTime . " && to_uid = " . $staff_id . ") GROUP BY user_id";
            $thumbs_count = pdo_fetchall($thumbs_count);
            $thumbs_count = $thumbs_count[0]["count"];
        }
        $data = array("new_client" => $new_client, "view_client" => $view_client, "mark_client" => $mark_client, "chat_client" => $chat_client, "sale_money" => $sale_money, "sale_order" => $sale_order, "share_count" => $share_count, "save_count" => $save_count, "thumbs_count" => $thumbs_count);
        return $this->result(0, "", $data);
    }
    public function doPageBossStaffAnalysis()
    {
        $this->cross();
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $uniacid = $_W["uniacid"];
        $staff_id = $_GPC["staff_id"];
        $data = array();
        if (!$staff_id) {
            return $this->result(-1, "", array());
        }
        $check_is_boss = $this->check_is_boss($uid);
        if (!$check_is_boss) {
            return $this->result(-1, "", array());
        }
        $client = pdo_getall("longbing_card_collection", array("to_uid" => $staff_id), array("id"));
        $client = count($client);
        $mark_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid, "staff_id" => $staff_id), array("id"));
        $mark_client = count($mark_client);
        $deal_client = pdo_getall("longbing_card_user_mark", array("uniacid" => $uniacid, "mark" => 2, "staff_id" => $staff_id), array("id"));
        $deal_client = count($deal_client);
        $data["dealRate"] = array("client" => $client, "mark_client" => $mark_client, "deal_client" => $deal_client);
        $sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE sign = 'view' && type = 6 && uniacid = " . $uniacid;
        $compony = pdo_fetchall($sql);
        $compony = count($compony);
        $sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && type = 2 && uniacid = " . $uniacid . ") OR (sign = 'copy' && type = 1 && uniacid = " . $uniacid . ")";
        $goods = pdo_fetchall($sql);
        $goods = count($goods);
        $sql = "SELECT id FROM " . tablename("longbing_card_count") . " WHERE (sign = 'copy' && uniacid = " . $uniacid . ") OR (sign != 'praise' && uniacid = " . $uniacid . ")";
        $staff = pdo_fetchall($sql);
        $staff = count($staff);
        $total = $compony + $goods + $staff;
        $data2 = array("compony" => array("number" => $compony, "rate" => 0), "goods" => array("number" => $goods, "rate" => 0), "staff" => array("number" => $staff, "rate" => 0));
        if ($total) {
            foreach ($data2 as $k => $v) {
                $data2[$k]["rate"] = sprintf("%.2f", $v["number"] / $total) * 100;
            }
        }
        $data["interest"] = $data2;
        $last = 15;
        $data2 = array();
        for ($i = 0; $i < $last; $i++) {
            $beginTime = mktime(0, 0, 0, date("m"), date("d") - $i, date("Y"));
            $endTime = mktime(0, 0, 0, date("m"), date("d") - $i + 1, date("Y")) - 1;
            $sql = "SELECT id FROM " . tablename("longbing_card_count") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
            $count = pdo_fetchall($sql);
            $count = count($count);
            $sql = "SELECT id FROM " . tablename("longbing_card_forward") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
            $forward = pdo_fetchall($sql);
            $forward = count($forward);
            $sql = "SELECT id FROM " . tablename("longbing_card_user_phone") . " where uniacid = " . $uniacid . " && create_time BETWEEN " . $beginTime . " AND " . $endTime;
            $phone = pdo_fetchall($sql);
            $phone = count($phone);
            $tmp = array("date" => date("Y-m-d", $beginTime), "time" => $beginTime, "number" => $count + $forward + $phone);
            array_push($data2, $tmp);
        }
        array_multisort(array_column($data2, "time"), SORT_ASC, $data2);
        $data["activity"] = $data2;
        $data2 = array();
        $beginTime = mktime(0, 0, 0, date("m"), date("d") - $last, date("Y"));
        $thumbs = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where (sign = 'view' && `type` = 4 && uniacid = " . $uniacid . " && create_time > " . $beginTime . ") OR (sign = 'praise' && `type` = 1 && uniacid = " . $uniacid . " && create_time > " . $beginTime . ") OR (sign = 'praise' && `type` = 3 && uniacid = " . $uniacid . " && create_time > " . $beginTime . ")");
        $thumbs = count($thumbs);
        $data2[] = array("title" => "点赞", "number" => $thumbs, "rate" => 0);
        $save_phone = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where sign = 'copy' && `type` = 1 && uniacid = " . $uniacid . " && create_time > " . $beginTime);
        $save_phone = count($save_phone);
        $data2[] = array("title" => "保存手机", "number" => $save_phone, "rate" => 0);
        $comment = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_timeline_comment") . " where uniacid = " . $uniacid . " && create_time > " . $beginTime);
        $comment = count($comment);
        $data2[] = array("title" => "评论", "number" => $comment, "rate" => 0);
        $copy_wechat = pdo_fetchall("SELECT id FROM " . tablename("longbing_card_count") . " where sign = 'copy' && `type` = 4 && uniacid = " . $uniacid . " && create_time > " . $beginTime);
        $copy_wechat = count($copy_wechat);
        $data2[] = array("title" => "复制微信", "number" => $copy_wechat, "rate" => 0);
        $total = $thumbs + $save_phone + $comment + $copy_wechat;
        if ($total) {
            foreach ($data2 as $k => $v) {
                $data2[$k]["rate"] = sprintf("%.2f", $v["number"] / $total) * 100;
            }
        }
        $data["activityBarGraph"] = $data2;
        return $this->result(0, "", $data);
    }
    public function doPagePay()
    {
        global $_GPC;
        global $_W;
        $uid = $_GPC["user_id"];
        $order_id = $_GPC["order_id"];
        $uniacid = $_W["uniacid"];
        if (!$uid || !$order_id) {
            return $this->result(-1, "fail pra", array());
        }
        $user = pdo_get("longbing_card_user", array("id" => $uid, "uniacid" => $uniacid));
        if (!$user) {
            return $this->result(-1, "fail user", array());
        }
        $order = pdo_get("longbing_card_shop_order", array("id" => $order_id, "uniacid" => $uniacid));
        if (!$order) {
            return $this->result(-1, "fail found", array());
        }
        if ($order["pay_status"] != 0) {
            return $this->result(-1, "fail order", array());
        }
        $order_items = pdo_getall("longbing_card_shop_order_item", array("order_id" => $order_id));
        if (!$order_items) {
            return $this->result(-1, "fail", array());
        }
        foreach ($order_items as $index => $item) {
            $goods_info = pdo_get("longbing_card_shop_spe_price", array("id" => $item["spe_price_id"]));
            if (!$goods_info) {
                return $this->result(-1, "fail", array($goods_info));
            }
            if ($goods_info["stock"] < $item["number"]) {
                return $this->result(-1, "fail stock", array($goods_info));
            }
        }
        $out_trade_no = "b-" . $order["id"] . "-" . date("Ymd") . uniqid();
        pdo_update("longbing_card_shop_order", array("out_trade_no" => $out_trade_no), array("id" => $order_id));
        $_W["openid"] = $user["openid"];
        $orderPay = array("tid" => $out_trade_no, "user" => $user["openid"], "fee" => floatval($order["total_price"]), "title" => "wechat");
        $pay_params = $this->pay($orderPay);
        if (is_error($pay_params)) {
            return $this->result(-1, "pay fail", $pay_params);
        }
        if ($order["type"] == 1) {
            $pay_params["collage_id"] = $order["collage_id"];
            return $this->result(0, "", $pay_params);
        }
        return $this->result(0, "", $pay_params);
    }
    public function doPageRefund()
    {
        global $_GPC;
        global $_W;
        $setting = $_W["account"]["setting"]["payment"];
        $refund_setting = $setting["wechat_refund"];
        $uid = $_GPC["user_id"];
        $order_id = $_GPC["order_id"];
        $uniacid = $_W["uniacid"];
        if (!$uid || !$order_id) {
            return $this->result(-1, "", array());
        }
        $user = pdo_get("longbing_card_user", array("id" => $uid, "uniacid" => $uniacid));
        if (!$user) {
            return $this->result(-1, "", array());
        }
        $order = pdo_get("longbing_card_shop_order", array("id" => $order_id, "uniacid" => $uniacid));
        if (!$order) {
            return $this->result(-1, "", array());
        }
        if ($order["pay_status"] != 1) {
            return $this->result(-1, "", array());
        }
        if ($order["order_status"] != 1) {
            return $this->result(-1, "", array());
        }
        load()->model("refund");
        $refund_id = refund_create_order($order["out_trade_no"], $_W["current_module"]["name"]);
        if (is_error($refund_id)) {
            return $refund_id;
        }
        $refundData = reufnd_wechat_build($refund_id);
        $url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
        $cert = authcode($refund_setting["cert"], "DECODE");
        $key = authcode($refund_setting["key"], "DECODE");
        file_put_contents(ATTACHMENT_ROOT . $_W["uniacid"] . "_wechat_refund_all.pem", $cert . $key);
        $wechat = Pay::create("wechat");
        $response = $wechat->refund($refundData);
        if (is_error($response)) {
            pdo_update("core_refundlog", array("status" => "-1"), array("id" => $refund_id));
            return $this->result(-2, $response["message"], $response);
        }
        if (isset($response["return_code"]) && isset($response["return_msg"]) && $response["return_code"] == "SUCCESS" && $response["return_msg"] == "OK") {
            $out_refund_no = $response["out_refund_no"];
            pdo_update("longbing_card_shop_order", array("out_refund_no" => $out_refund_no, "pay_status" => 2), array("id" => $order_id));
            return $this->result(0, "", array());
        }
        return $this->result(-1, "", array());
    }
    public function payResult($log)
    {
        if ($log["result"] == "success" && $log["tid"]) {
            $out_trade_no = $log["tid"];
            $uniacid = $log["uniacid"];
            $order = pdo_get("longbing_card_shop_order", array("out_trade_no" => $out_trade_no, "uniacid" => $uniacid));
            if (!$order || $order["pay_status"] != 0) {
                return false;
            }
            @file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/pay.txt", $order["out_trade_no"] . ": " . @json_encode($log));
            $result = pdo_update("longbing_card_shop_order", array("pay_status" => 1, "transaction_id" => $log["tag"]["transaction_id"]), array("id" => $order["id"]));
            if (!$result) {
                @file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/pay_refund.txt", @json_encode($log));
                return false;
            }
            if ($order["type"] == 1) {
                $collage_info = pdo_get("longbing_card_shop_collage_list", array("id" => $order["collage_id"]));
                if ($collage_info["left_number"] == 0) {
                    pdo_update("longbing_card_shop_collage_list", array("collage_status" => 2), array("id" => $order["collage_id"]));
                    pdo_update("longbing_card_shop_user_collage", array("collage_status" => 2), array("collage_id" => $order["collage_id"]));
                } else {
                    pdo_update("longbing_card_shop_collage_list", array("collage_status" => 1), array("id" => $order["collage_id"]));
                    pdo_update("longbing_card_shop_user_collage", array("collage_status" => 1), array("collage_id" => $order["collage_id"]));
                }
            }
            $items = pdo_getall("longbing_card_shop_order_item", array("order_id" => $order["id"]));
            foreach ($items as $k => $v) {
                if ($v["number"]) {
                    pdo_update("longbing_card_shop_spe_price", array("stock -=" => $v["number"]), array("id" => $v["spe_price_id"]));
                    pdo_update("longbing_card_goods", array("stock -=" => $v["number"]), array("id" => $v["goods_id"]));
                    pdo_update("longbing_card_goods", array("sale_count +=" => $v["number"]), array("id" => $v["goods_id"]));
                }
                if ($order["type"] == 1) {
                    pdo_update("longbing_card_goods", array("collage_count +=" => $v["number"]), array("id" => $v["goods_id"]));
                }
            }
            $mark = pdo_get("longbing_card_user_mark", array("user_id" => $order["user_id"], "staff_id" => $order["to_uid"]));
            if (!$mark) {
                pdo_insert("longbing_card_user_mark", array("user_id" => $order["user_id"], "staff_id" => $order["to_uid"], "mark" => 2, "status" => 1, "uniacid" => $uniacid, "create_time" => time(), "update_time" => time()));
            } else {
                pdo_update("longbing_card_user_mark", array("mark" => 2, "update_time" => time()), array("id" => $mark["id"]));
            }
            $this->updateWater($order);
            $this->payNoticeClient($order);
            $this->payNoticeStaff($order);
        }
        return false;
    }
    protected function payNoticeClient($order)
    {
        global $_GPC;
        global $_W;
        $uid = $order["user_id"];
        if (!$uid) {
            return $this->result(-1, "", array());
        }
        $appid = $_W["account"]["key"];
        $appsecret = $_W["account"]["secret"];
        $client = pdo_get("longbing_card_user", array("id" => $uid));
        if (!$client) {
            return $this->result(-1, "", array());
        }
        $openid = $client["openid"];
        $name = $client["nickName"];
        $date = date("Y-m-d H:i");
        $config = pdo_get("longbing_card_config", array("uniacid" => $_W["uniacid"]), array("mini_template_id", "notice_switch", "notice_i", "min_tmppid"));
        if ($config["notice_switch"] == 1 && false) {
        } else {
            if (!$config["mini_template_id"]) {
                return false;
            }
            $form = $this->getFormId($uid);
            if (!$form) {
                return false;
            }
            $access_token = $this->getAccessToken();
            if (!$access_token) {
                return false;
            }
            $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
            $page = "longbing_card/pages/uCenter/order/orderList/orderList?currentTab=2";
            if ($order["type"] === 1) {
                $items = pdo_get("longbing_card_shop_order_item", array("order_id" => $order["id"]));
                $page = "longbing_card/pages/shop/releaseCollage/releaseCollage?id=" . $items["goods_id"] . "&status=toShare&to_uid=" . $order["to_uid"] . "&collage_id=";
            }
            $postData = array("touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $name), "keyword2" => array("value" => "订单支付成功了"), "keyword3" => array("value" => $date)));
            $postData = json_encode($postData, JSON_UNESCAPED_UNICODE);
            $response = ihttp_post($url, $postData);
            @file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/data/tpl/1.txt", @json_encode($response));
        }
        return true;
    }
    protected function payNoticeStaff($order)
    {
        global $_GPC;
        global $_W;
        $uid = $order["user_id"];
        $staff_id = $order["to_uid"];
        $time = time();
        if ($order["type"] === 1) {
            @pdo_insert("longbing_card_count", array("user_id" => $uid, "to_uid" => $staff_id, "type" => 2, "sign" => "order", "target" => $order["id"], "uniacid" => $_W["uniacid"], "create_time" => $time, "update_time" => $time));
        } else {
            @pdo_insert("longbing_card_count", array("user_id" => $uid, "to_uid" => $staff_id, "type" => 1, "sign" => "order", "target" => $order["id"], "uniacid" => $_W["uniacid"], "create_time" => $time, "update_time" => $time));
        }
        $count_id = pdo_insertid();
        $this->sendTotal($count_id);
        return true;
    }
    protected function arr2xml($data)
    {
        $result = "<xml>";
        if (is_object($data)) {
            $_data = ObjectToArray::parse($data);
        } else {
            $_data =& $data;
        }
        foreach ($_data as $key => $value) {
            if (!is_scalar($value)) {
                if (is_object($value) && method_exists($value, "toString")) {
                    $value = $value->toString();
                    if (NULL === $value) {
                        continue;
                    }
                } else {
                    if (NULL !== $value) {
                        $value = json_encode($value);
                    } else {
                        continue;
                    }
                }
            }
            $result .= "<" . $key . ">" . $value . "</" . $key . ">";
        }
        return $result . "</xml>";
    }
    public function checkOrderTime()
    {
        $list = pdo_getall("longbing_card_shop_order", array("pay_status" => 0, "order_status !=" => 1));
        $config2 = pdo_getall("longbing_card_config");
        foreach ($config2 as $k => $v) {
            $configs[$v["uniacid"]] = $v;
        }
        $time = time();
        $order_overtime = 1800;
        $collage_overtime = 172800;
        foreach ($list as $k => $v) {
            $order_overtime = $configs[$v["uniacid"]]["order_overtime"];
            if (!$order_overtime) {
                $order_overtime = 1800;
            }
            if (!$collage_overtime) {
                $collage_overtime = 172800;
            }
            if ($order_overtime < $time - $v["create_time"]) {
                pdo_update("longbing_card_shop_order", array("order_status" => 1), array("id" => $v["id"]));
                if ($v["type"] == 1) {
                    $collage_id = $v["collage_id"];
                    $collage = pdo_getall("longbing_card_shop_collage_list", array("id" => $collage_id));
                    pdo_update("longbing_card_shop_user_collage", array("collage_status" => 4), array("collage_id" => $collage_id, "user_id" => $v["user_id"]));
                    foreach ($collage as $k2 => $v2) {
                        if ($v2["user_id"] == $v["user_id"]) {
                            pdo_update("longbing_card_shop_collage_list", array("collage_status" => 4), array("id" => $v2["id"]));
                        } else {
                            pdo_update("longbing_card_shop_collage_list", array("left_number +=" => 1), array("id" => $v2["id"]));
                        }
                    }
                }
            }
        }
        $list_collage = pdo_getall("longbing_card_shop_collage_list", array("collage_status" => 1));
        foreach ($list_collage as $k => $v) {
            $collage_overtime = $configs[$v["uniacid"]]["collage_overtime"];
            if (!$collage_overtime) {
                $collage_overtime = 172800;
            }
            if ($collage_overtime < $time - $v["create_time"]) {
                pdo_update("longbing_card_shop_collage_list", array("collage_status" => 4), array("id" => $v["id"]));
                $orders = pdo_getall("longbing_card_shop_order", array("type" => 1, "collage_id" => $v["id"]));
                foreach ($orders as $k2 => $v2) {
                    @pdo_update("longbing_card_shop_order", array("order_status" => 1), array("id" => $v2["id"]));
                    @pdo_update("longbing_card_shop_user_collage", array("collage_status" => 4), array("collage_id" => $v["id"]));
                    if ($v2["pay_status"] == 1) {
                        $time = time();
                        $list = pdo_getall("longbing_card_selling_water", array("order_id" => $v2["id"], "waiting" => 1));
                        pdo_update("longbing_card_selling_water", array("status" => 0, "update_time" => $time), array("order_id" => $v2["id"], "waiting" => 1));
                        foreach ($list as $index => $item) {
                            $money = $item["price"] * $item["extract"] / 100;
                            $money = sprintf("%.2f", $money);
                            $profit = pdo_get("longbing_card_selling_profit", array("user_id" => $item["user_id"]));
                            if ($profit && 0 < $profit["waiting"]) {
                                $waiting = $profit["waiting"] - $money;
                                $waiting = floatval($waiting);
                                if ($waiting < 0) {
                                    $waiting = 0;
                                }
                                pdo_update("longbing_card_selling_profit", array("waiting" => $waiting), array("id" => $profit["id"]));
                            }
                        }
                    }
                }
            }
        }
        $list = pdo_getall("longbing_card_shop_order", array("pay_status" => 1, "order_status" => 2));
        if ($list) {
            foreach ($list as $index => $item) {
                $receiving = $configs[$item["uniacid"]]["receiving"];
                $receiving = intval($receiving);
                if (!$receiving || $receiving < 5) {
                    $receiving = 5;
                }
                $beginTime = mktime(0, 0, 0, date("m"), date("d") - $receiving, date("Y"));
                if ($item["update_time"] < $beginTime) {
                    @pdo_update("longbing_card_shop_order", array("order_status" => 3, "update_time" => @time()), array("id" => $item["id"]));
                }
            }
        }
    }
    protected function updateWater($order)
    {
        global $_GPC;
        global $_W;
        $order_item = pdo_getall("longbing_card_shop_order_item", array("order_id" => $order["id"]));
        foreach ($order_item as $index => $item) {
            $title = $item["name"] ? $item["name"] : "";
            $img = $item["cover"] ? $item["cover"] : "";
            $price = $item["price"] ? $item["price"] : "";
            $this->insertWater($order, $price, $item, $title, $img);
        }
        return true;
    }
    protected function insertWater($order, $price, $item, $title = "", $img = "")
    {
        global $_GPC;
        global $_W;
        $user_id = $order["user_id"];
        $staff_id = $order["to_uid"];
        $uniacid = $_W["uniacid"];
        $time = time();
        $config = pdo_get("longbing_card_config", array("uniacid" => $uniacid));
        $goods = pdo_get("longbing_card_goods", array("id" => $item["goods_id"]));
        if (!$config || !$goods) {
            return false;
        }
        $extract = $goods["extract"];
        if (!$extract || $extract < 0 || 100 < $extract) {
            $extract = $config["staff_extract"];
        }
        if (!$extract || $extract < 0 || 100 < $extract) {
            return false;
        }
        if ($staff_id && $config["staff_extract"] && false) {
            $staff = pdo_get("longbing_card_user", array("id" => $staff_id));
            $check_staff = pdo_get("longbing_card_selling_profit", array("user_id" => $staff_id));
            if (!$check_staff) {
                @pdo_insert("longbing_card_selling_profit", array("user_id" => $staff_id, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time));
            }
            if ($staff) {
                $extract_money = $price * $config["staff_extract"] / 100;
                $extract_money = sprintf("%.2f", $extract_money);
                @pdo_update("longbing_card_selling_profit", array("waiting +=" => $extract_money), array("user_id" => $staff_id));
                $insert_data = array("user_id" => $staff_id, "source_id" => $user_id, "order_id" => $order["id"], "goods_id" => $item["goods_id"], "buy_number" => $item["number"], "type" => 1, "title" => $title, "img" => $img, "price" => $price, "extract" => $config["staff_extract"], "waiting" => 1, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time);
                @pdo_insert("longbing_card_selling_water", $insert_data);
            }
        }
        $user = pdo_get("longbing_card_user", array("id" => $user_id));
        if (!$user) {
            return false;
        }
        if ($user["pid"] && $extract) {
            $check_first = pdo_get("longbing_card_selling_profit", array("user_id" => $user["pid"]));
            if (!$check_first) {
                @pdo_insert("longbing_card_selling_profit", array("user_id" => $user["pid"], "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time));
            }
            $extract_money_first = $price * $extract / 100;
            $extract_money_first = sprintf("%.2f", $extract_money_first);
            @pdo_update("longbing_card_selling_profit", array("waiting +=" => $extract_money_first), array("user_id" => $user["pid"]));
            $insert_data = array("user_id" => $user["pid"], "source_id" => $user_id, "order_id" => $order["id"], "goods_id" => $item["goods_id"], "buy_number" => $item["number"], "type" => 2, "title" => $title, "img" => $img, "price" => $price, "extract" => $extract, "waiting" => 1, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time);
            @pdo_insert("longbing_card_selling_water", $insert_data);
            @$this->sendCommission($order);
        }
        if ($user["pid"] && false) {
            $user_first = pdo_get("longbing_card_user", array("id" => $user["pid"]));
            if ($user_first["pid"] && $config["sec_extract"]) {
                $check_sec = pdo_get("longbing_card_selling_profit", array("user_id" => $user_first["pid"]));
                if (!$check_sec) {
                    @pdo_insert("longbing_card_selling_profit", array("user_id" => $user_first["pid"], "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time));
                }
                $extract_money_sec = $price * $config["sec_extract"] / 100;
                $extract_money_sec = sprintf("%.2f", $extract_money_sec);
                @pdo_update("longbing_card_selling_profit", array("waiting +=" => $extract_money_sec), array("user_id" => $user_first["pid"]));
                $insert_data = array("user_id" => $user_first["pid"], "source_id" => $user_id, "order_id" => $order["id"], "goods_id" => $item["goods_id"], "buy_number" => $item["number"], "type" => 3, "title" => $title, "img" => $img, "price" => $price, "extract" => $config["sec_extract"], "waiting" => 1, "uniacid" => $uniacid, "create_time" => $time, "update_time" => $time);
                @pdo_insert("longbing_card_selling_water", $insert_data);
            }
        }
        return true;
    }
    protected function getRadarDetail($sign, $type, $target = "")
    {
        $way = "";
        $detail = "";
        if ($sign == "praise") {
            switch ($type) {
                case 1:
                    $way = "语音点赞";
                    break;
                case 2:
                    $way = "查看名片";
                    break;
                case 3:
                    $way = "靠谱";
                    break;
                case 4:
                    $way = "分享";
                    break;
            }
        } else {
            if ($sign == "view") {
                switch ($type) {
                    case 1:
                        $way = "浏览商城列表";
                        break;
                    case 2:
                        $way = "浏览商品详情";
                        $info = pdo_get("longbing_card_goods", array("id" => $target), array("name"));
                        $detail = $info["name"];
                        break;
                    case 3:
                        $way = "浏览动态列表";
                        break;
                    case 4:
                        $way = "点赞动态";
                        break;
                    case 5:
                        $way = "动态留言";
                        break;
                    case 6:
                        $way = "浏览公司官网";
                        break;
                    case 7:
                        $way = "浏览动态详情";
                        $info = pdo_get("longbing_card_timeline", array("id" => $target), array("title"));
                        $detail = $info["title"];
                        break;
                }
            } else {
                if ($sign == "copy") {
                    switch ($type) {
                        case 1:
                            $way = "同步到通讯录";
                            break;
                        case 2:
                            $way = "拨打手机号";
                            break;
                        case 3:
                            $way = "拨打座机号";
                            break;
                        case 4:
                            $way = "复制微信";
                            break;
                        case 5:
                            $way = "复制邮箱";
                            break;
                        case 6:
                            $way = "复制公司名";
                            break;
                        case 7:
                            $way = "查看定位";
                            break;
                        case 8:
                            $way = "咨询产品";
                            break;
                        case 9:
                            $way = "播放语音";
                            break;
                    }
                } else {
                    if ($sign == "order") {
                        $way = "购买商品";
                        $target_info = pdo_get("longbing_card_shop_order_item", array("order_id" => $target));
                        $detail = $target_info["name"];
                    }
                }
            }
        }
        return array($way, $detail);
    }
    public function result_self($errno, $message, $data = "")
    {
        exit(json_encode(array("errno" => $errno, "message" => $message, "data" => $data), JSON_UNESCAPED_UNICODE));
    }
    public function sendCommission($order)
    {
        global $_GPC;
        global $_W;
        $user = pdo_get("longbing_card_user", array("id" => $order["user_id"]));
        if (!$user || $user["pid"] == 0) {
            return false;
        }
        $user_top = pdo_get("longbing_card_user", array("id" => $user["pid"]));
        if (!$user_top) {
            return false;
        }
        $send_type = 1;
        $config = pdo_get("longbing_card_config", array("uniacid" => $_W["uniacid"]));
        if ($user_top["is_staff"] == 1 && $config) {
            if ($config["notice_switch"] == 1 && $config["wx_appid"] && $config["wx_tplid"]) {
                $send_type = 2;
            }
            if ($config["notice_switch"] == 2 && $config["corpid"] && $config["corpsecret"] && $config["agentid"]) {
                $send_type = 3;
            }
        }
        $send_body = "您的下线在商品消费啦";
        $page = "longbing_card/voucher/pages/user/myearning/myearning";
        if ($send_type == 1) {
            $this->sendServerMsg($config, $user, $user_top, $send_body, $page);
        }
        if ($send_type == 2) {
            $this->sendPublicMsg($config, $user, $user_top, $send_body, $page);
        }
        if ($send_type == 3) {
            $this->sendEnterpriseMsg($config, $user, $user_top, $send_body, $page);
        }
        return true;
    }
    public function sendServerMsg($config, $send, $target, $send_body, $page)
    {
        $openid = $target["openid"];
        $date = date("Y-m-d H:i");
        $form = $this->getFormId($target["id"]);
        if (!$form) {
            return false;
        }
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return false;
        }
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
        $page = "longbing_card/staff/radar/radar";
        $postData = array("touser" => $openid, "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $send["nickName"]), "keyword2" => array("value" => $send_body), "keyword3" => array("value" => $date)));
        $postData = json_encode($postData, JSON_UNESCAPED_UNICODE);
        $response = ihttp_post($url, $postData);
    }
    public function sendPublicMsg($config, $send, $target, $send_body, $page)
    {
        global $_GPC;
        global $_W;
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return false;
        }
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=" . $access_token;
        $date = date("Y-m-d H:i");
        $appid = $_W["account"]["key"];
        $data = array("touser" => $target["openid"], "mp_template_msg" => array("appid" => $config["wx_appid"], "url" => "http://weixin.qq.com/download", "template_id" => $config["wx_tplid"], "miniprogram" => array("appid" => $appid, "pagepath" => $page), "data" => array("first" => array("value" => "", "color" => "#c27ba0"), "keyword1" => array("value" => $send["nickName"], "color" => "#93c47d"), "keyword2" => array("value" => $send_body, "color" => "#0000ff"), "remark" => array("value" => $date, "color" => "#45818e"))));
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $res = $this->curlPost($url, $data);
        if ($res) {
            $res = json_decode($res, true);
            if (isset($res["errcode"]) && $res["errcode"] != 0) {
                $form = $this->getFormId($target["id"]);
                if ($form) {
                    $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $access_token;
                    $postData = array("touser" => $target["openid"], "template_id" => $config["mini_template_id"], "page" => $page, "form_id" => $form, "data" => array("keyword1" => array("value" => $send["nickName"]), "keyword2" => array("value" => $send_body), "keyword3" => array("value" => $date)));
                    $postData = json_encode($postData);
                    $response = curlPost($url, $postData);
                }
            }
        }
        return true;
    }
    public function sendEnterpriseMsg($config, $send, $target, $send_body, $page)
    {
        global $_GPC;
        global $_W;
        $appid = $config["corpid"];
        $appsecret = $config["corpsecret"];
        $agentid = $config["agentid"];
        $user_info = pdo_get("longbing_card_user_info", array("fans_id" => $target["id"]));
        $touser = $user_info["ww_account"];
        if (!$touser) {
            return true;
        }
        $data = array("touser" => $touser, "msgtype" => "text", "agentid" => $agentid, "text" => array("content" => $send_body));
        include_once $_SERVER["DOCUMENT_ROOT"] . "/addons/longbing_card/images/phpqrcode/work.weixin.class.php";
        $work = new work($appid, $appsecret);
        $result = $work->send($data);
        return true;
    }
    public function checkMyShop($uid)
    {
        global $_GPC;
        global $_W;
        if (!$uid) {
            return false;
        }
        $check = pdo_get("longbing_card_config", array("uniacid" => $_W["uniacid"]));
        if (!$check || $check["myshop_switch"] == 0) {
            return false;
        }
        $list = pdo_getall("longbing_card_user_shop", array("user_id" => $uid, "uniacid" => $_W["uniacid"]));
        if (!$list) {
            return false;
        }
        $data = array();
        foreach ($list as $index => $item) {
            array_push($data, intval($item["goods_id"]));
        }
        if (!empty($data)) {
            return $data;
        }
        return false;
    }
    //我的接口开始
    //我的支付
    public function doPageMyPay(){
        global $_W, $_GPC;
        include IA_ROOT.'/addons/longbing_card/wxpay.php';
        $account = pdo_get("account_wxapp", array( "uniacid" => $_W["uniacid"] ));
        $payconfig = pdo_get("longbing_card_configpayinfo", array( "uniacid" => $_W["uniacid"] ));
        $uid=$_GPC['uid'];
        $pid=$_GPC['pid'];
        $attch=$uid.'|'.$pid;
        //支付需要传入的参数 openid 订单id 支付金额
        $appid=$account["key"];
        //查询openid
        $openid=pdo_getcolumn('longbing_card_user', array('id' => $uid), 'openid',1);
        $mch_id=$payconfig['paynum'];
        $key=$payconfig['paykey'];
        $out_trade_no =date('YmdHis',time());//订单号
        $root=$_W['siteroot'];
        //$total_fee =$_GPC['money'];
        $total_fee=pdo_getcolumn('longbing_card_teamsetting', array("uniacid" => $_W["uniacid"]), 'yearvip',1);
        if(empty($total_fee)) //默认1分
        {
            $body ='博胜名片会员支付';
            $total_fee = floatval(1*100);
        }else{
            $body ='博胜名片会员支付';
            $total_fee = floatval($total_fee*100);
        }
        $weixinpay = new WeixinPay($appid,$openid,$mch_id,$key,$out_trade_no,$body,$total_fee,$root,$attch);
        $return=$weixinpay->pay();
        echo  $this->result(0, "支付参数",$return);
    }
    //邀请码兑换
    public function doPageYQcode(){
        global $_W, $_GPC;
        $getcode=$_GPC['yqcode'];
        $uid=$_GPC['uid'];
        $pid=$_GPC['pid'];
       if (empty($pid)){
            $pid=pdo_getcolumn('longbing_card_user', array('id' => $uid), 'pid',1);
        }
        if (empty($getcode)){
            echo $this->result(-1, "请输入邀请码",'');die();
        }else{
            $wallet=pdo_getcolumn('longbing_card_randcode', array('randcode' => $getcode), 'randcode',1);
            if ($wallet){
                //升级等级
                $uplevel=pdo_update('longbing_card_user',array('level'=>1,'jointime'=>date('Y-m-d H:i:s',time())),array('id'=>$uid));
                if ($uplevel){
                    //删除该邀请码
                    pdo_delete('longbing_card_randcode',array('randcode' => $getcode));
                    //绑定关系
                    $issave=pdo_get('longbing_card_relation', array('uid' => $uid));
                    if (empty($issave)){
                        pdo_insert('longbing_card_relation',array('uid'=>$uid,'pid'=>$pid,'addtime'=>date('Y-m-d H:i:s',time())));
                    }else{
                        if (empty($issave['pid'])){
                            pdo_update('longbing_card_relation',array('pid'=>$pid),array('uid'=>$uid));
                        }
                    }
                    echo $this->result(0, "升级成功",'');die();
                }else{
                    echo $this->result(-1, "升级失败",'');die();
                }

            }else{
                echo $this->result(-1, "邀请码不存在",$pid);die();
            }
        }
    }
    //获取年卡金额
    public function doPageGetvipprice(){
        global $_GPC, $_W;
        $getprice=pdo_get('longbing_card_teamsetting',array("uniacid" => $_W["uniacid"]));
        echo  $this->result(0, "返回年卡金额", $getprice['yearvip']);
    }
    //检查用户的使用时间是否过期
    public function doPageCheckPasstime(){
        global $_GPC, $_W;
        $uid = $_GPC["user_id"];
        $getuserinfo = pdo_get("longbing_card_user", array("id" => $uid, "uniacid" => $_W["uniacid"]));
        $timer = strtotime($getuserinfo['jointime']);
        $diff = $_SERVER['REQUEST_TIME'] - $timer;
        $day = floor($diff / 86400);
        $getuserinfo['passtime']=$day;
        if ($getuserinfo['level']==0&&$getuserinfo['leveltype']==0){
            if ($day>=7){
                //体验用户已过期
                $getuserinfo['ispasstime']=1;
            }else{
                $getuserinfo['ispasstime']=0;
            }
        }else if ($getuserinfo['level']){
            //是会员，判断是否一年
            if ($day>=365){
                //体验用户已过期
                $getuserinfo['ispasstime']=1;
            }else{
                $getuserinfo['ispasstime']=0;
            }
        }
        echo  $this->result(0, "", $getuserinfo);
    }
    //分销页信息
    public function doPageUserinfo(){
        global $_W, $_GPC;
        $uid=$_GPC['uid'];
        $useifno=pdo_get('longbing_card_user',array('id'=>$uid),array('nickName','avatarUrl','level','leveltype'));
        $symoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=0 and uid='.$uid);
        //可提现=总收益-已提现
        $txmoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=1 and uid='.$uid);
        $useifno['allmoney']=number_format($symoney['allmoney']-$txmoney['allmoney'],2);
        //检查自动更新
        $allrelation=pdo_getall('longbing_card_relation');
       $teamarr=$this->get_downline($allrelation,$uid);
        $useifno['teamsize']=sizeof($teamarr);
        $teamcount=sizeof($teamarr);
        $settinginfo=pdo_get('longbing_card_teamsetting',array("uniacid" => $_W["uniacid"]));
        switch ($useifno['leveltype']){
            case 0:
                if ($teamcount>=$settinginfo['yellowgoldnum']){
                    pdo_update('longbing_card_user',array('leveltype'=>1),array('id'=>$uid));
                }
                break;
            case 1:
                if ($teamcount>=$settinginfo['bogoldnum']){
                    pdo_update('longbing_card_user',array('leveltype'=>2),array('id'=>$uid));
                }
                break;
            case 2:
                if ($teamcount>=$settinginfo['zuangoldnum']){
                    pdo_update('longbing_card_user',array('leveltype'=>3),array('id'=>$uid));
                }
                break;
        }

        echo  $this->result(0, "分销页信息",$useifno);
    }
    //提现接口
    public function doPageTX(){
        global $_W, $_GPC;
        $uid=$_GPC['uid'];
        $zfbnum=$_GPC['zfbnum'];
        $zfbname=$_GPC['zfbname'];
        $money=$_GPC['money'];
        $addtime=date('Y-m-d H:i:s',time());
        $symoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=0 and uid='.$uid);
        //可提现=总收益-已提现
        $txmoney=pdo_fetch("SELECT sum(money) as allmoney from".tablename("longbing_card_record").' where type=1 and uid='.$uid);
        $anblemoney=number_format($symoney['allmoney']-$txmoney['allmoney'],2);
        if ($anblemoney<$money){
            echo  $this->result(-1, "余额不足！",'');
        }else if(empty($zfbname)||empty($zfbnum)){
            echo  $this->result(-1, "请填支付宝信息",'');
        }else if(empty($money)){
            echo  $this->result(-1, "提现金额太低",'');
        }else{
            $inseres=pdo_insert('longbing_card_record',array('uid'=>$uid,'type'=>1,'comment'=>'申请提现'.$money.'元',
            'addtime'=>$addtime,'zfbnum'=>$zfbnum,'zfbname'=>$zfbname,'money'=>$money));
            if ($inseres){
                echo  $this->result(0, "提交成功",'');
            }else{
                echo  $this->result(-1, "提交失败！",'');
            }
        }
    }
    public function doPageTXlist(){
        global $_W, $_GPC;
        $tilist=pdo_getall('longbing_card_record',array('uid'=>$_GPC['uid'],'type'=>1));
        echo  $this->result(0, "提现明细",$tilist);
    }
    //会员订单，两级的
    public function doPageGetteamorder(){
        global $_W, $_GPC;
        $uid=$_GPC['uid'];
        $oneteam=pdo_fetchall("select * from " . tablename("longbing_card_viporder") ." where pid={$uid}");
        $twoteamarr=array();
        foreach ($oneteam as $k=>$v){
            $getteam=pdo_fetchall("select * from " . tablename("longbing_card_viporder") ." where  pid={$v['uid']}");
            if ($getteam){
                foreach ($getteam as $k=>$v){
                    array_push($twoteamarr,$v);
                }
            }
        }
        $resdata['oneteam']=$oneteam;
        $resdata['twoteam']=$twoteamarr;
        echo  $this->result(0, "团队订单",$resdata);
    }
    //团队接口
    public function doPageGetTeam(){
        global $_W, $_GPC;
        $uid=$_GPC['uid'];
        $oneteam=pdo_fetchall("select * from " . tablename("longbing_card_relation") . " r"  . " left join " . tablename("longbing_card_user") .
            " u on r.uid=u.id"." where r.pid={$uid}");
        $twoteamarr=array();
        foreach ($oneteam as $k=>$v){
            $getteam=pdo_fetchall("select * from " . tablename("longbing_card_relation") . " r"  . " left join " . tablename("longbing_card_user") .
                " u on r.uid=u.id"." where  r.pid={$v['uid']}");
            if ($getteam){
                foreach ($getteam as $k=>$v){
                    array_push($twoteamarr,$v);
                }
            }
        }
        $resdata['oneteam']=$oneteam;
        $resdata['twoteam']=$twoteamarr;
        $resdata['allcount']=count($oneteam)+count($twoteamarr);
        echo  $this->result(0, "团队信息",$resdata);
    }
    //手册
    public function doPageUserbook(){
        global $_W, $_GPC;
        $userbook=pdo_getcolumn('longbing_card_teamsetting', array("uniacid" => $_W["uniacid"]), 'userbook',1);
        $contents=htmlspecialchars_decode($userbook);
        echo  $this->result(0, "使用手册",$contents);
    }
    public function doPageReaduserbook(){
        global $_W, $_GPC;
        $uid=$_GPC['uid'];
        pdo_update("longbing_card_user",array('issend'=>1),array('id'=>$uid));
        echo  $this->result(0, "已阅读",'');
    }
    //后端的随机生成邀请码
    public function doPageAddrandcode(){
        global $_GPC, $_W;
        for($i=0;$i<10;$i++){
            $addre=pdo_insert('longbing_card_randcode',array('randcode'=>$this->getRandomString(6),"uniacid" => $_W["uniacid"]));
        }
        if ($addre){
            echo json_encode(array('type'=>'success','code'=>1,'data'=>'添加成功'));
        }else{
            echo json_encode(array('type'=>'fail','code'=>0,'data'=>'添加失败'));
        }
    }
    //后端的更新等级
    public function doPageupdatalevel(){
        global $_GPC, $_W;
        $uid=$_GPC['id'];
        $level=$_GPC['leveltype'];
        //判断上级等级是否一致，如一致就接触推荐关系
        $relainfo=pdo_get('longbing_card_relation',array('uid'=>$uid));
        $pleveltype=pdo_getcolumn('longbing_card_user', array('id' => $relainfo['pid']), 'leveltype',1);

        $updatares=pdo_update('longbing_card_user',array('leveltype'=>$level),array('id'=>$uid));
        if ($updatares){
            if ($pleveltype==$level){
                pdo_update('longbing_card_relation',array('pid'=>0,'beforepid'=>$relainfo['pid']),array('uid'=>$uid));
            }
            echo json_encode(array('type'=>'success','code'=>1,'data'=>'修改代理成功'));
        }else{
            echo json_encode(array('type'=>'fail','code'=>0,'data'=>'修改代理失败'));
        }
    }
    //后端的删除邀请码
    public function doPageDelrandcode(){
        global $_GPC, $_W;
        $delre=pdo_delete('longbing_card_randcode',array('id'=>$_GPC['id']));
        if ($delre){
            echo json_encode(array('type'=>'success','code'=>1,'data'=>'删除成功'));
        }else{
            echo json_encode(array('type'=>'fail','code'=>0,'data'=>'删除失败'));
        }
    }
    //时间计算格式
    function formatTime($date)
    {
        $str = '';
        $timer = strtotime($date);
        $diff = $_SERVER['REQUEST_TIME'] - $timer;
        $day = floor($diff / 86400);
        $free = $diff % 86400;
        if ($day > 0) {
            return $day . "天前";
        } else {
            if ($free > 0) {
                $hour = floor($free / 3600);
                $free = $free % 3600;
                if ($hour > 0) {
                    return $hour . "小时前";
                } else {
                    if ($free > 0) {
                        $min = floor($free / 60);
                        $free = $free % 60;
                        if ($min > 0) {
                            return $min . "分钟前";
                        } else {
                            if ($free > 0) {
                                return $free . "秒前";
                            } else {
                                return '刚刚';
                            }
                        }
                    } else {
                        return '刚刚';
                    }
                }
            } else {
                return '刚刚';
            }
        }
    }
    //随机生成数字和字母
    function getRandomString($len, $chars=null)
    {
        if (empty($chars)) {
            $chars = "abdefghmnpqrtwxyABDEFGHMNPQRTWXY123456789";
        }
        mt_srand(10000000*(double)microtime());
        for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }
    //获取无限下级
    function get_downline($members,$mid,$level=0){
        $arr=array();
        foreach ($members as $key => $v) {
            if($v['pid']==$mid){  //pid为0的是顶级分类
                $v['level'] = $level+1;
                $arr[]=$v;
                $arr = array_merge($arr,$this->get_downline($members,$v['uid'],$level+1));
            }
        }
        return $arr;
    }
    //我的接口结束
}

?>