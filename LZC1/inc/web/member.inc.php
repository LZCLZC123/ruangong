<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array('display'))?$_GPC['act']:'display';
$title = '会员管理';
if ($act == 'display') {
    //搜索
    $name = trim($_GPC['nickname']);
    $filter = array(
        'uniacid' => $_W['uniacid'],
    );
    if (!empty($name)) {
        $filter['nickname LIKE'] = "%{$name}%";
    }
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 20;
    $total = pdo_getcolumn('mc_members', $filter, 'COUNT(*)');
    $orderby = 'uid DESC';
    $list = pdo_getall('mc_members', $filter, '*', '', $orderby, array($pindex, $pagesize));
    $pager = pagination($total, $pindex, $pagesize);
    if ($list) {
        foreach ($list as &$li) {
            SupermanHandModel::superman_hand2_member($li);
        }
        unset($li);
    }
}
include $this->template($this->web_template_path);