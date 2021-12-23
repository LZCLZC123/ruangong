<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array('list', 'chat', 'delete'))?$_GPC['act']:'list';
checkauth();
if (!defined('SUPERMAN_HAND2_CHAT_PORT')) {
    message('未开启聊天服务，请联系管理员开启', $this->createMobileUrl('home'), 'info');
}
if ($act == 'list') {
    $title = '消息列表';
    $filter = array(
        'uniacid' => $_W['uniacid'],
        'uid' => $_W['member']['uid'],
    );
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 20;
    $orderby = 'updatetime DESC';
    $list = pdo_getall('superman_hand2_message_list', $filter, '*', '', $orderby, array($pindex, $pagesize));
    //从详情页或模板消息发起的聊天
    $from = $_GPC['from'];
    $id = $_GPC['id'];
    if (!empty($list)) {
        foreach ($list as &$li) {
            SupermanHandModel::superman_hand2_message_list($li);
            if (!empty($from) && !empty($id)) {
                if ($li['id'] == $id) {
                    $li['from'] = $from;
                }
            }

        }
        unset($li);
    }
    $sid = md5('SupermanHand2:'.$_W['member']['uid'].':'.date('Ymd').':'.$_W['config']['setting']['authkey']);
    include $this->template('message/list');
} else if ($act == 'chat') {
    $member = pdo_get('mc_members', array('uid' => $_W['member']['uid']));
    $result = array(
        'to' => array(
            'uid' => $member['uid'],
            'nickname' => $member['nickname'],
            'avatar' => $member['avatar'],
        ),
        'from' => array(),
        'item' => array(),
        'message' => array(),
    );
    $itemid = intval($_GPC['itemid']);
    $fromuid = intval($_GPC['fromuid']);
    if (empty($itemid) || empty($fromuid)) {
        SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST);
    }
    $result['item'] = pdo_get('superman_hand2_item', array('id' => $itemid));
    if (empty($result['item'])) {
        SupermanHandUtil::json(SupermanHandErrno::MESSAGE_INVALID);
    }
    SupermanHandModel::superman_hand2_item($result['item']);
    $uid = $_W['member']['uid'];
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 20;
    $start = ($pindex - 1) * $pagesize;
    $condition = "itemid=$itemid AND ((from_uid=$fromuid AND to_uid=$uid) OR (from_uid=$uid AND to_uid=$fromuid))";
    $orderby = 'id DESC';
    $limit = array($pindex, $pagesize);
    $list = pdo_getall('superman_hand2_message', $condition, '', '', $orderby, $limit);
    if (!empty($list)) {
        $list = array_reverse($list);
        $result['message'] = $list;
    }
    $from_member = pdo_get('mc_members', array('uid' => $fromuid), array('nickname', 'avatar'));
    $result['from'] = array(
        'uid' => $fromuid,
        'nickname' => $from_member['nickname'],
        'avatar' => $from_member['avatar'],
    );
    //移除红点
    pdo_update('superman_hand2_message_list', array(
        'status' => 0,
    ), array(
        'uid' => $_W['member']['uid'],
        'from_uid' => $fromuid,
        'itemid' => $itemid,
    ));
    //--end
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'delete') {
    if (empty($_GPC['id'])) {
        SupermanHandUtil::json(SupermanHandErrno::PARAM_ERROR);
    }
    $filter = array(
        'uniacid' => $_W['uniacid'],
        'id' => $_GPC['id']
    );
    $ret = pdo_delete('superman_hand2_message_list', $filter);
    if ($ret === false) {
        SupermanHandUtil::json(SupermanHandErrno::DELETE_FAIL);
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '删除成功');
}