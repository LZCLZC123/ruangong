<?php

defined('IN_IA') or exit('Access Denied');
require IA_ROOT . '/addons/superman_hand2_plugin_wechat/global.php';
class Superman_hand2_plugin_wechatModuleSite extends WeModuleSite
{
    public $module;
    public function __construct()
    {
        global $_W, $_GPC, $do;
        $modules = uni_modules(false);
        $this->module = $modules['superman_hand2'];
        if (defined('IN_SYS')) {
            $this->init_web();
        }
    }
    private function init_web()
    {
    }
    public function wechat_pay($params)
    {
        global $_W;
        load()->model('payment');
        load()->model('account');
        $modules = uni_modules(false);
        if (empty($params) || !array_key_exists('superman_hand2', $modules)) {
            return error(1, '模块不存在');
        }
        $modules = $modules['superman_hand2'];
        $moduleid = empty($modules['mid']) ? '000000' : sprintf('%06d', $modules['mid']);
        $uniontid = date('YmdHis') . $moduleid . random(8, 1);
        $wxapp_uniacid = $params['uniacid'];
        $paylog = pdo_get('core_paylog', array("uniacid" => $wxapp_uniacid, "module" => $modules['name'], "tid" => $params['tid']));
        if (empty($paylog)) {
            $paylog = array("uniacid" => $wxapp_uniacid, "acid" => $wxapp_uniacid, "type" => "wechat", "openid" => $params['user'], "module" => $modules['name'], "tid" => $params['tid'], "uniontid" => $uniontid, "fee" => floatval($params['fee']), "card_fee" => floatval($params['fee']), "status" => "0", "is_usecard" => "0", "tag" => iserializer(array("acid" => $_W['acid'], "uid" => $_W['member']['uid'])));
            pdo_insert('core_paylog', $paylog);
            $paylog['plid'] = pdo_insertid();
        }
        if (!empty($paylog) && $paylog['status'] != '0') {
            return error(1, '这个订单已经支付成功, 不需要重复支付.');
        }
        if (!empty($paylog) && empty($paylog['uniontid'])) {
            pdo_update('core_paylog', array("uniontid" => $uniontid), array("plid" => $paylog['plid']));
            $paylog['uniontid'] = $uniontid;
        }
        $_W['openid'] = $paylog['openid'];
        $data = array("tid" => $paylog['tid'], "fee" => $paylog['card_fee'], "user" => $paylog['openid'], "uniontid" => $paylog['uniontid'], "title" => $params['title']);
        $setting = uni_setting($wxapp_uniacid, array("payment"));
        $wechat_payment = array("appid" => $params['appid'], "signkey" => $setting['payment']['wechat']['signkey'], "mchid" => $setting['payment']['wechat']['mchid'], "version" => 2);
        $result = wechat_build($data, $wechat_payment);
        if (is_error($result)) {
            WeUtility::logging('fatal', '[superman_wxpay_build] failed, result=' . var_export($result, true) . ', data=' . var_export($data, true) . ', wechat=' . var_export($wechat_payment, true));
        }
        return $result;
    }
}