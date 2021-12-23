<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array('display', 'delete'))?$_GPC['act']:'display';
$title = '评价管理';
if ($act == 'display') {
    //搜索
    $name = trim($_GPC['nickname']);
    $ordersn = trim($_GPC['ordersn']);
    $filter = array(
        'uniacid' => $_W['uniacid'],
    );
    if (!empty($name)) {
        $users = pdo_getall('mc_members', array('nickname LIKE' => "%{$name}%"));
        if (!empty($users)) {
            $arr = array();
            foreach ($users as $li) {
                $arr[] = $li['uid'];
            }
            $filter['uid'] = $arr;
        } else {
            $filter['uid'] = 0;
        }
    }
    if (!empty($ordersn)) {
        $order = pdo_get('superman_hand2_order', array('uniacid' => $_W['uniacid'], 'ordersn' => $ordersn));
        if (!empty($order)) {
            $filter['orderid'] = $order['id'];
        } else {
            $filter['orderid'] = 0;
        }
    }
    $level = in_array($_GPC['level'], array('1', '2', '3'))?$_GPC['level']:'all';
    if ($level != 'all') {
        $filter['level'] = $level;
    }
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 20;
    $total = pdo_getcolumn('superman_hand2_grade', $filter, 'COUNT(*)');
    if ($total > 0) {
        $list = pdo_getall('superman_hand2_grade', $filter, '*', '', ' dateline DESC', array($pindex, $pagesize));
        $pager = pagination($total, $pindex, $pagesize);
        if ($list) {
            foreach ($list as &$li) {
                SupermanHandModel::superman_hand2_grade($li);
            }
            unset($li);
        }
    }
    if (checksubmit('batch_submit')) {
        if (empty($_GPC['ids'])) {
            itoast('未选择条目', referer(), 'error');
        }
        $ret = pdo_delete('superman_hand2_grade', array('id' => $_GPC['ids']));
        if ($ret === false) {
            itoast('数据库删除失败！', '', 'error');
        }
        $url = $this->createWebUrl('grade');
        itoast('操作成功！', $url, 'success');
    }
} else if ($act == 'delete') {
    $id = intval($_GPC['id']);
    if (empty($id)) {
        itoast('非法请求！', '', 'error');
    }
    $ret = pdo_delete('superman_hand2_grade', array('id' => $id));
    if ($ret === false) {
        itoast('数据库删除失败！', '', 'error');
    }
    itoast('操作成功！', 'referer', 'success');
}
include $this->template($this->web_template_path);