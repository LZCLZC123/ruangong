<?php

require IA_ROOT . "/addons/superman_hand2_plugin_notice/global.php";
class SupermanHand2PluginNoticeUtil
{
    public static function weiqing_polyfill()
    {
        global $_GPC, $do;
        if (empty($_GPC["do"])) {
            if (isset($_GPC["eid"]) && $_GPC["eid"]) {
                $eid = intval($_GPC["eid"]);
                $row = pdo_get("modules_bindings", array("eid" => $eid));
                if (!empty($row)) {
                    $_GPC["do"] = $row["do"];
                    $_GPC["m"] = $row["module"];
                    $do = $_GPC["do"];
                }
            }
        } else {
            if (empty($do)) {
                $do = $_GPC["do"];
            }
        }
    }
    public static function redirect($url)
    {
        ob_end_clean();
        @header("Location: " . $url);
        exit;
    }
    public static function send_wxapp_msg($data, $openid, $tmpl_id, $url, $form_id)
    {
        global $_W;
        if (empty($tmpl_id)) {
            WeUtility::logging("fatal", "[send_wxapp_msg] 模板消息发送失败：未配置模版消息");
            return false;
        }
        if (empty($openid)) {
            WeUtility::logging("fatal", "[send_wxapp_msg] 模板消息发送失败：openid为空");
            return false;
        }
        $account = WeAccount::create($_W["account"]);
        if (empty($account)) {
            WeUtility::logging("fatal", "create account failed: account=" . var_export($_W["account"], true));
            return false;
        }
        $token = $account->getAccessToken();
        if (is_error($token)) {
            WeUtility::logging("fatal", "getAccessToken failed: token=" . var_export($token, true));
            return false;
        }
        $post = array("touser" => $openid, "template_id" => $tmpl_id, "page" => $url, "form_id" => $form_id, "data" => $data);
        $post_url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=" . $token;
        $result = self::superman_hand2_request($post_url, json_encode($post));
        $uid = mc_openid2uid($openid);
        if (is_error($result)) {
            WeUtility::logging("info", "发送失败：uid={$uid}, openid={$openid}, result=" . var_export($result, true) . ", post=" . var_export($post, true));
            return false;
        } else {
            WeUtility::logging("info", "发送成功：uid={$uid}, openid={$openid}");
        }
        return true;
    }
    public static function uid2openid($uid)
    {
        $fans = mc_fansinfo($uid);
        return $fans && $fans["openid"] ? $fans["openid"] : '';
    }
    public static function createMobileUrl($do, $query = array(), $noredirect = true, $modulename = "superman_hand2")
    {
        $query["do"] = $do;
        $query["m"] = strtolower($modulename);
        return murl("entry", $query, $noredirect);
    }
}