<?php

require IA_ROOT . "/addons/superman_hand2_plugin_wechat/global.php";
class SupermanHand2PluginWechatUtil
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
    public static function get_getcash_status_style($status)
    {
        switch ($status) {
            case -1:
                return "label label-danger";
                break;
            case 0:
                return "label label-default";
                break;
            case 1:
                return "label label-success";
                break;
            default:
                return "label label-default";
                break;
        }
    }
    public static function get_getcash_status_title($status)
    {
        switch ($status) {
            case -1:
                return "提现失败";
                break;
            case 0:
                return "未支付";
                break;
            case 1:
                return "已支付";
                break;
            default:
                return "unknown";
                break;
        }
    }
    public static function get_getcash_account_type_title($account_type)
    {
        switch ($account_type) {
            case "wechat":
                return "微信";
                break;
            case "bank":
                return "银行";
                break;
            case "alipay":
                return "支付宝";
                break;
            default:
                return "unknown";
                break;
        }
    }
    public static function uid2openid($uid)
    {
        $fans = mc_fansinfo($uid);
        return $fans && $fans["openid"] ? $fans["openid"] : '';
    }
    public static function float_format($num, $len = 2)
    {
        $multiplier = pow(10, $len);
        $arr = explode(".", $num * $multiplier);
        $result = $arr[0] / $multiplier;
        return sprintf("%." . $len . "f", $result);
    }
    public static function get_uid_formid($uid)
    {
        global $_W;
        $row = pdo_get("superman_hand2_member_formid", array("uniacid" => $_W["uniacid"], "uid" => $uid, "createtime >" => TIMESTAMP - 7 * 24 * 3600), array("id", "formid"));
        if (!$row) {
            return false;
        }
        return $row;
    }
    public static function delete_uid_formid($id)
    {
        global $_W;
        pdo_delete("superman_hand2_member_formid", array("uniacid" => $_W["uniacid"], "id" => $id));
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
    public static function pay($params, $extra = array())
    {
        global $_W;
        $data = array("mch_appid" => $params["mch_appid"], "mchid" => $params["mchid"], "nonce_str" => $params["nonce_str"], "partner_trade_no" => $params["partner_trade_no"], "openid" => $params["openid"], "check_name" => $params["check_name"], "re_user_name" => $params["re_user_name"], "amount" => $params["amount"] * 100, "desc" => $params["desc"], "spbill_create_ip" => $params["spbill_create_ip"]);
        $sign = self::sign($data, $extra["sign_key"]);
        $xml_data = "<xml><mch_appid>{$data["mch_appid"]}</mch_appid><mchid>{$data["mchid"]}</mchid><nonce_str>{$data["nonce_str"]}</nonce_str><partner_trade_no>{$data["partner_trade_no"]}</partner_trade_no><openid>{$data["openid"]}</openid><check_name>{$data["check_name"]}</check_name><re_user_name>{$data["re_user_name"]}</re_user_name><amount>{$data["amount"]}</amount><desc>{$data["desc"]}</desc><spbill_create_ip>{$data["spbill_create_ip"]}</spbill_create_ip><sign>{$sign}</sign></xml>";
        $headers = array();
        $headers["Content-Type"] = "application/x-www-form-urlencoded";
        $headers["CURLOPT_SSL_VERIFYPEER"] = false;
        $headers["CURLOPT_SSL_VERIFYHOST"] = false;
        $cert = authcode($_W["account"]["setting"]["payment"]["wechat_refund"]["cert"], "DECODE");
        $key = authcode($_W["account"]["setting"]["payment"]["wechat_refund"]["key"], "DECODE");
        $path = MODULE_ROOT . "data/";
        mkdirs($path);
        $cert_filename = $path . $_W["uniacid"] . "_wechat_pay_all.pem";
        file_put_contents($cert_filename, $cert . $key);
        $headers["CURLOPT_SSLCERT"] = $cert_filename;
        if (self::$debug) {
            WeUtility::logging("trace", "[Wxpay:pay] xml_data=" . $xml_data);
            WeUtility::logging("trace", "[Wxpay:pay] headers=" . var_export($headers, true));
        }
        $pay_url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
        $response = ihttp_request($pay_url, $xml_data, $headers);
        @unlink($cert_filename);
        if ($response == '') {
            return "[wxpay-api:pay] response NULL";
        }
        $response = $response["content"];
        if (self::$debug) {
            WeUtility::logging("trace", "[Wxpay:pay] response=" . $response);
        }
        $xml = @simplexml_load_string($response);
        if (empty($xml)) {
            return "[wxpay-api:pay] parse xml NULL";
        }
        if (self::$debug) {
            WeUtility::logging("trace", "[Wxpay:pay] xml=" . var_export($xml, true));
        }
        $return_code = $xml->return_code ? (string) $xml->return_code : '';
        $return_msg = $xml->return_msg ? (string) $xml->return_msg : '';
        $result_code = $xml->result_code ? (string) $xml->result_code : '';
        $err_code = $xml->err_code ? (string) $xml->err_code : '';
        $err_code_des = $xml->err_code_des ? (string) $xml->err_code_des : '';
        if ($return_code == "SUCCESS" && $result_code == "SUCCESS") {
            $ret = array("success" => true, "partner_trade_no" => $xml->partner_trade_no, "payment_no" => $xml->payment_no, "payment_time" => $xml->payment_time);
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
}