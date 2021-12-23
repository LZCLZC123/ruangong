<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array(
    'detail',
))?$_GPC['act']:'detail';
if ($act == 'detail') {
    $id = $_GPC['id'];
    if (empty($id)) {
        SupermanHandUtil::json(SupermanHandErrno::PARAM_ERROR, '');
    }
    $filter = array(
        'uniacid' => $_W['uniacid'],
        'id' => $id
    );
    $detail = pdo_get('superman_hand2_notice', $filter);
    $detail['content'] = htmlspecialchars_decode($detail['content']);
    $detail['createtime'] = $detail['createtime']?date('Y-m-d H:i:s', $detail['createtime']):'';
    //获取点击量
    pdo_update('superman_hand2_notice', array(
        'count +=' => 1,
    ), array('id' => $id));
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $detail);
}
