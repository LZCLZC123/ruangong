<?php

require IA_ROOT . "/addons/superman_hand2_plugin_ad/global.php";
class SupermanHand2PluginAdUtil
{
    private static $debug = true;
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
    public static function format_time_type($type)
    {
        switch ($type) {
            case "year":
                return "年";
                break;
            case "month":
                return "月";
                break;
            case "day":
                return "天";
                break;
            default:
                return "小时";
                break;
        }
    }
    public static function get_links($type, $title = '', $cube = '')
    {
        global $_W;
        $links = array(array("title" => "首页", "url" => $type == 4 ? "/pages/index/index" : self::createMobileUrl("home")), array("title" => "发布页", "url" => $type == 4 ? "/pages/post/index" : self::createMobileUrl("item", array("act" => "post"))), array("title" => "我的", "url" => $type == 4 ? "/pages/my/index" : self::createMobileUrl("my")), array("title" => "消息列表", "url" => $type == 4 ? "/pages/message/index" : self::createMobileUrl("message", array("act" => "list"))), array("title" => "任务中心", "url" => $type == 4 ? "/pages/get_credit/index" : self::createMobileUrl("my", array("act" => "get_credit"))), array("title" => "物品列表", "url" => $type == 4 ? "/pages/list/index" : self::createMobileUrl("item", array("act" => "list"))));
        if ($type == 4) {
            $filter = array("uniacid" => $_W["uniacid"], "status" => 1);
            $orderby = "displayorder DESC";
            $notice = pdo_getall("superman_hand2_notice", $filter, "*", '', $orderby);
            if (!empty($notice)) {
                foreach ($notice as $n) {
                    $links[] = array("title" => "公告:" . $n["title"], "url" => "/pages/ad/index?id=" . $n["id"]);
                }
            }
            if ($cube) {
                $category = pdo_getall("superman_hand2_category", $filter, "*", '', $orderby);
                if (!empty($category)) {
                    foreach ($category as $c) {
                        $links[] = array("title" => "分类:" . $c["title"], "url" => "/pages/list/index?id=" . $c["id"]);
                    }
                }
                $links[] = array("title" => "物品链接", "url" => "/pages/detail/index?id=");
            }
        }
        if (!empty($title)) {
            foreach ($links as $li) {
                if ($li["title"] == $title) {
                    return $li["url"];
                }
            }
            return '';
        }
        return $links;
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
    public static function order_refund($params, $extra = array())
    {
        global $_W;
        $data = array("appid" => $params["appid"], "mch_id" => $params["mch_id"], "nonce_str" => $params["nonce_str"], "transaction_id" => $params["transaction_id"], "out_refund_no" => $params["out_refund_no"], "total_fee" => floatval($params["total_fee"]) * 100, "refund_fee" => floatval($params["refund_fee"]) * 100, "op_user_id" => $params["op_user_id"], "refund_account" => "REFUND_SOURCE_UNSETTLED_FUNDS");
        if ($params["refund_account"] == 2) {
            $data["refund_account"] = "REFUND_SOURCE_RECHARGE_FUNDS";
        }
        $xml_data = "<xml>";
        foreach ($data as $k => $v) {
            $xml_data .= "<{$k}>{$v}</{$k}>";
        }
        $sign = self::sign($data, $extra["sign_key"]);
        $xml_data .= "<sign>{$sign}</sign>";
        $xml_data .= "</xml>";
        $headers = array();
        $headers["Content-Type"] = "application/x-www-form-urlencoded";
        $headers["CURLOPT_SSL_VERIFYPEER"] = false;
        $headers["CURLOPT_SSL_VERIFYHOST"] = false;
        $cert = authcode($_W["account"]["setting"]["payment"]["wechat_refund"]["cert"], "DECODE");
        $key = authcode($_W["account"]["setting"]["payment"]["wechat_refund"]["key"], "DECODE");
        $path = MODULE_ROOT . "/data/";
        mkdirs($path);
        $cert_filename = $path . $_W["uniacid"] . "_wechat_refund_all.pem";
        file_put_contents($cert_filename, $cert . $key);
        $headers["CURLOPT_SSLCERT"] = $cert_filename;
        $refund_url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
        WeUtility::logging("debug", "[todo.inc.php:display], xml_data=" . var_export($xml_data, true) . ", headers=" . var_export($headers, true) . ", account=" . var_export($_W["account"], true));
        $response = ihttp_request($refund_url, $xml_data, $headers);
        @unlink($cert_filename);
        if (is_error($response)) {
            WeUtility::logging("fatal", "[util.calss.php, Wxpay:refund] response=" . var_export($response, true));
            return $response;
        }
        $response = $response["content"];
        if (self::$debug) {
            WeUtility::logging("trace", "[util.calss.php, wxpay-api:pay] response=" . $response);
        }
        $xml = @simplexml_load_string($response);
        if (self::$debug) {
            WeUtility::logging("trace", "[util.calss.php], xml=" . var_export($xml, true));
        }
        if (empty($xml)) {
            WeUtility::logging("fatal", "[util.calss.php, Wxpay:refund] response=" . var_export($response, true));
            return $response;
        }
        $return_code = $xml->return_code ? (string) $xml->return_code : '';
        $return_msg = $xml->return_msg ? (string) $xml->return_msg : '';
        $result_code = $xml->result_code ? (string) $xml->result_code : '';
        $err_code = $xml->err_code ? (string) $xml->err_code : '';
        $err_code_des = $xml->err_code_des ? (string) $xml->err_code_des : '';
        if ($return_code == "SUCCESS" && $result_code == "SUCCESS") {
            $ret = array("success" => true, "refund_id" => (string) $xml->refund_id, "out_refund_no" => (string) $xml->out_refund_no);
            WeUtility::logging("trace", "[util.calss.php, Wxpay:refund] \$ret=" . var_export($ret, true));
            return $ret;
        } else {
            return $return_code . ":" . $return_msg . "," . $err_code . ":" . $err_code_des;
        }
    }
    public static function sign($data, $sign_key)
    {
        ksort($data);
        $data_str = '';
        foreach ($data as $k => $v) {
            if (!($v == '' || $k == "sign")) {
                $data_str .= "{$k}={$v}&";
            }
        }
        $data_str .= "key=" . $sign_key;
        $sign = strtoupper(md5($data_str));
        return $sign;
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
    public static function location_transition($lat, $lng)
    {
        $data = array();
        $url = "https://apis.map.qq.com/ws/geocoder/v1/?location=" . $lat . "," . $lng . "&key=ZXTBZ-T5F36-V76S4-MZZLX-7DZPQ-5DFMY";
        $response = ihttp_get($url);
        if (is_error($response)) {
            WeUtility::logging("fatal", "[get address_component failed], response=" . var_export($response, true));
            return;
        }
        $result = @json_decode($response["content"], true);
        $data["province"] = $result["result"]["address_component"]["province"];
        $data["city"] = $result["result"]["address_component"]["city"];
        $ad_level_1 = $result["result"]["address_component"]["ad_level_1"];
        $ad_level_2 = $result["result"]["address_component"]["ad_level_2"];
        if ($ad_level_1) {
            $data["province"] = $ad_level_1;
            $data["city"] = $ad_level_2;
        }
        return $data;
    }
    public static function get_credit_titles()
    {
        global $_W;
        $credit_title = array();
        $uni_settings = uni_setting($_W["uniacid"]);
        if ($uni_settings && $uni_settings["creditnames"]) {
            $creditnames = iunserializer($uni_settings["creditnames"]);
            if ($creditnames) {
                foreach ($creditnames as $k => $val) {
                    if ($val["enabled"]) {
                        $credit_title[$k] = $val;
                    }
                }
            }
        }
        return $credit_title;
    }
}