<?php

defined('IN_IA') or exit('Access Denied');
class Superman_hand2_plugin_wechatModuleWxapp extends WeModuleWxapp {
    public function __construct() {
    }
    /*
     * 支付notify回调函数，无返回值
     */
    public function payResult($params) {
    }
    public function pay($order) {
        global $_W;
        load()->model('account');
        $paytype = !empty($order['paytype']) ? $order['paytype'] : 'wechat';
        $moduels = uni_modules();
        if (empty($order) || !array_key_exists('superman_hand2', $moduels)) {
            return error(1, '模块不存在');
        }
        $this->module = $moduels['superman_hand2'];
        $moduleid = empty($this->module['mid']) ? '000000' : sprintf("%06d", $this->module['mid']);
        $uniontid = date('YmdHis') . $moduleid . random(8, 1);
        $paylog = pdo_get('core_paylog', array('uniacid' => $_W['uniacid'], 'module' => $this->module['name'], 'tid' => $order['tid']));
        if (empty($paylog)) {
            $paylog = array(
                'uniacid' => $_W['uniacid'],
                'acid' => $_W['acid'],
                'type' => 'wxapp',
                'openid' => $_W['openid'],
                'module' => $this->module['name'],
                'tid' => $order['tid'],
                'uniontid' => $uniontid,
                'fee' => floatval($order['fee']),
                'card_fee' => floatval($order['fee']),
                'status' => '0',
                'is_usecard' => '0',
                'tag' => iserializer(array('acid' => $_W['acid'], 'uid' => $_W['member']['uid']))
            );
            pdo_insert('core_paylog', $paylog);
            $paylog['plid'] = pdo_insertid();
        }
        if (!empty($paylog) && $paylog['status'] != '0') {
            return error(1, '这个订单已经支付成功, 不需要重复支付.');
        }
        if (!empty($paylog) && empty($paylog['uniontid'])) {
            pdo_update('core_paylog', array(
                'uniontid' => $uniontid,
            ), array('plid' => $paylog['plid']));
            $paylog['uniontid'] = $uniontid;
        }
        $_W['openid'] = $paylog['openid'];
        $params = array(
            'tid' => $paylog['tid'],
            'fee' => $paylog['card_fee'],
            'user' => $paylog['openid'],
            'uniontid' => $paylog['uniontid'],
            'title' => $order['title'],
        );
        if ($paytype == 'wechat') {
            return $this->wechatExtend($params);
        } elseif ($paytype == 'credit') {
            return $this->creditExtend($params);
        }
    }
    public function wechatExtend($params) {
        global $_W;
        load()->model('payment');
        $wxapp_uniacid = intval($_W['account']['uniacid']);
        $setting = uni_setting($wxapp_uniacid, array('payment'));
        $wechat_payment = array(
            'appid' => $_W['account']['key'],
            'signkey' => $setting['payment']['wechat']['signkey'],
            'mchid' => $setting['payment']['wechat']['mchid'],
            'version' => 2,
        );
        return wechat_build($params, $wechat_payment);
    }
    public function creditExtend($params) {
        global $_W;
        $credtis = mc_credit_fetch($_W['member']['uid']);
        $paylog = pdo_get('core_paylog', array('uniacid' => $_W['uniacid'], 'module' => $this->module['name'], 'tid' => $params['tid']));
        if (empty($_GPC['notify'])) {
            if (!empty($paylog) && $paylog['status'] != '0') {
                return error(-1, '该订单已支付');
            }
            if ($credtis['credit2'] < $params['fee']) {
                return error(-1, '余额不足');
            }
            $fee = floatval($params['fee']);
            $result = mc_credit_update($_W['member']['uid'], 'credit2', -$fee, array($_W['member']['uid'], '消费credit2:' . $fee));
            if (is_error($result)) {
                return error(-1, $result['message']);
            }
            pdo_update('core_paylog', array('status' => '1'), array('plid' => $paylog['plid']));
            $site = WeUtility::createModuleWxapp($paylog['module']);
            if (is_error($site)) {
                return error(-1, '参数错误');
            }
            $site->weid = $_W['weid'];
            $site->uniacid = $_W['uniacid'];
            $site->inMobile = true;
            $method = 'doPagePayResult';
            if (method_exists($site, $method)) {
                $ret = array();
                $ret['result'] = 'success';
                $ret['type'] = $paylog['type'];
                $ret['from'] = 'return';
                $ret['tid'] = $paylog['tid'];
                $ret['user'] = $paylog['openid'];
                $ret['fee'] = $paylog['fee'];
                $ret['weid'] = $paylog['weid'];
                $ret['uniacid'] = $paylog['uniacid'];
                $ret['acid'] = $paylog['acid'];
                $ret['is_usecard'] = $paylog['is_usecard'];
                $ret['card_type'] = $paylog['card_type'];
                $ret['card_fee'] = $paylog['card_fee'];
                $ret['card_id'] = $paylog['card_id'];
                $site->$method($ret);
            }
        } else {
            $site = WeUtility::createModuleWxapp($paylog['module']);
            if (is_error($site)) {
                return error(-1, '参数错误');
            }
            $site->weid = $_W['weid'];
            $site->uniacid = $_W['uniacid'];
            $site->inMobile = true;
            $method = 'doPagePayResult';
            if (method_exists($site, $method)) {
                $ret = array();
                $ret['result'] = 'success';
                $ret['type'] = $paylog['type'];
                $ret['from'] = 'notify';
                $ret['tid'] = $paylog['tid'];
                $ret['user'] = $paylog['openid'];
                $ret['fee'] = $paylog['fee'];
                $ret['weid'] = $paylog['weid'];
                $ret['uniacid'] = $paylog['uniacid'];
                $ret['acid'] = $paylog['acid'];
                $ret['is_usecard'] = $paylog['is_usecard'];
                $ret['card_type'] = $paylog['card_type'];
                $ret['card_fee'] = $paylog['card_fee'];
                $ret['card_id'] = $paylog['card_id'];
                $site->$method($ret);
            }
        }
    }
}