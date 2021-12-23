<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array(
    'display'
))?$_GPC['act']:'display';
if ($act == 'display') {
    //每天登录赠送积分
    $credit_setting = $this->module['config']['credit'];
    $day_login = day_get_credit($credit_setting);
    $uid = $_GPC['uid'] ? $_GPC['uid'] : $_W['member']['uid'];
    //积分设置
    $member_log = pdo_get('superman_hand2_member_log', array(
        'uniacid' => $_W['uniacid'],
        'uid' => $uid,
    ));
    $first_login = 0;
    if (empty($member_log) || $member_log['login'] == 0) {
        if (SupermanHandUtil::credit_uplimit($credit_setting, $credit_setting['login'])) {
            $credit_log = array(
                $uid,
                '首次登录赠送积分',
                'superman_hand2',
            );
            $ret = mc_credit_update($uid, 'credit1', $credit_setting['login'], $credit_log);
            if (is_error($ret)) {
                WeUtility::logging('fatal', '[home.inc.php: get_credit], ret='.var_export($ret, true));
            }
            if ($member_log) {
                $ret = pdo_update('superman_hand2_member_log', array(
                    'login' => 1,
                ), array(
                    'id' => $member_log['id']
                ));
            } else {
                $data = array(
                    'uniacid' => $_W['uniacid'],
                    'uid' => $uid,
                    'login' => 1,
                    'createtime' => TIMESTAMP,
                );
                pdo_insert('superman_hand2_member_log', $data);
            }
            $first_login = 1;
        }
    }
    $result['credit_setting'] = array(
        'open' => $credit_setting['open'],
        'login_credit' => $credit_setting['login'],
        'first_login' => $first_login,
        'day_login' => $day_login ? 0 : 1,
        'day' => $credit_setting['day'],
        'credit_title' => SupermanHandUtil::get_credit_titles(),
    );
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
}
//每天登录送积分
function day_get_credit($credit_setting) {
    global $_W;
    $member_login = pdo_get('superman_hand2_member_login', array(
        'uniacid' => $_W['uniacid'],
        'uid' => $_W['member']['uid'],
    ));
    $starttime = strtotime(date('Y-m-d 00:00:00', TIMESTAMP));
    if ($member_login['dateline'] > $starttime) {
        return false;
    }
    if ($credit_setting['open'] == 1 && $credit_setting['day']) {
        $credit_log = array(
            $_W['member']['uid'],
            '每天登录赠送积分',
            'superman_hand2',
        );
        $ret = mc_credit_update($_W['member']['uid'], 'credit1', $credit_setting['day'], $credit_log);
        if (is_error($ret)) {
            WeUtility::logging('fatal', '[home.inc.php: mc_credit_update], ret=' . var_export($ret, true));
            return false;
        }
    }
    if ($member_login) {
        $ret1 = pdo_update('superman_hand2_member_login', array(
            'dateline' => TIMESTAMP
        ), array(
            'id' => $member_login['id']
        ));
        if ($ret1 === false) {
            SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '数据库更新失败');
        }
    } else {
        $data = array(
            'uniacid' => $_W['uniacid'],
            'uid' => $_W['member']['uid'],
            'dateline' => TIMESTAMP,
        );
        pdo_insert('superman_hand2_member_login', $data);
        $new_id = pdo_insertid();
        if (empty($new_id)) {
            SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '数据库更新失败');
        }
    }
    return true;
}
