<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array(
    'init', 'list', 'chat', 'delete', 'red_dot',
))?$_GPC['act']:'init';
if (!defined('SUPERMAN_HAND2_CHAT_PORT')) {
    SupermanHandUtil::json(SupermanHandErrno::CHATROOM_NOT_OPEN);
}
if (empty($_W['member']['uid'])) {
    SupermanHandUtil::json(SupermanHandErrno::NOT_LOGIN);
}
if ($act == 'init') {
    $result = array(
        'url' => '',
        'sid' => '',
    );
    $domain = SupermanHandUtil::get_domain($_W['siteroot']);
    $scheme = 'wss://';
    if (defined('LOCAL_DEVELOPMENT')) {
        $scheme = 'ws://';
    }
    $result['url'] = "{$scheme}{$domain}/websocket";
    $result['sid'] = md5('SupermanHand2:'.$_W['member']['uid'].':'.date('Ymd').':'.$_W['config']['setting']['authkey']);
    $result['expiretime'] = date('Y-m-d H:i:s', strtotime('+1 hour'));
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'list') {
    $result = array(
        'message_list' => array(),
        'post_notice' => $this->module['config']['base']['notice_open']?$this->module['config']['base']['notice_open']:0,
    );
    $filter = array(
        'uid' => $_W['member']['uid'],
    );
    $result['message_list'] = SupermanHandModel::getMessgeList($filter, 20);
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'chat') {
    $result = array(
        'from_member' => array(),
        'to_member' => mc_fetch($_W['member']['uid'], array('nickname', 'avatar')),
        'item_post' => array(),
        'messages' => array(),
    );
    $from_uid = intval($_GPC['from_uid']);
    $item_id = intval($_GPC['item_id']);
    $from_member = mc_fetch($from_uid, array('nickname', 'avatar'));
    if (empty($from_member)) {
        SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST);
    }
    $result['from_member'] = $from_member;
    $item_post = SupermanHandModel::getItem($item_id);
    if (empty($item_post)) {
        SupermanHandUtil::json(SupermanHandErrno::NO_DATA);
    }
    $result['item_post'] = $item_post;
    pdo_update('superman_hand2_message_list', array(
        'status' => 0,
    ), array(
        'uid' => $_W['member']['uid'],
        'from_uid' => $from_uid,
        'itemid' => $item_id,
    ));
    $to_uid = $_W['member']['uid'];
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 10;
    $condition = "itemid='$item_id' AND ((from_uid='$from_uid' AND to_uid='$to_uid')";
    $condition .= " OR (from_uid='$to_uid' AND to_uid='$from_uid'))";
    $orderby = 'id DESC';
    $limit = array($pindex, $pagesize);
    $list = pdo_getall('superman_hand2_message', $condition, '', '', $orderby, $limit);
    if (!empty($list)) {
        foreach ($list as &$li) {
            $li['createtime'] = $li['createtime']?date('Y-m-d H:i:s', $li['createtime']):'';
        }
        $result['messages'] = $pindex>1 ? $list : array_reverse($list); //首页逆序
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
}
