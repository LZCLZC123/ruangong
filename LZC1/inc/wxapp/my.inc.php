<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array(
    'display',
    'item_list',
    'delete',
    'blacklist',   //黑名单
    'post_count',  //每日发布数量
    'init_chat', // 点赞收藏页面和他聊聊
    'check_action', // 点赞收藏已读
))?$_GPC['act']:'display';
if ($act == 'display') {
    $result = array(
        'member' => array(),
        'credit' => array(
            'open' => $this->module['config']['credit']['open'] ? true : false,
            'title' => $_W['account']['setting']['creditnames']['credit1']['title'],
            'block' => 0.00,
            'balance' => 0.00,
        ),
        'praise_total' => 0,
        'favor_total' => 0,
        'balance' => 0.00,
        'sell_total' => 0,
        'wxapps' => array(),
        'recycle' => array(
            'open' => $this->module['config']['recycle']['open']?true:false,
        ),
        'post_notice' => $this->module['config']['base']['notice_open']?$this->module['config']['base']['notice_open']:0,
    );
    if ($_W['member']['uid']) {
        //更新微擎缓存，防止小程序端用户头像不显示
        cache_build_memberinfo($_W['member']['uid']);

        $result['member'] = mc_fetch($_W['member']['uid'], array(
            'nickname', 'avatar', 'mobile',
        ));
        if (!empty($result['member']['mobile'])) {
            $result['member']['mobile'] = SupermanHandUtil::hide_mobile($result['member']['mobile']);
        }
        $result['praise_total'] = pdo_count('superman_hand2_action', array(
            'uid' => $_W['member']['uid'],
            'type' => 1,
        ));
        $result['favor_total'] = pdo_count('superman_hand2_action', array(
            'uid' => $_W['member']['uid'],
            'type' => 2,
        ));
        $result['sell_total'] = pdo_count('superman_hand2_order', array(
            'seller_uid' => $_W['member']['uid'],
            'status' => 1,
        ));
        if ($this->plugin_module['plugin_wechat']['module']
            && !$this->plugin_module['plugin_wechat']['module']['is_delete']) {
            $result['balance'] = pdo_getcolumn('superman_hand2_member', array(
                'uniacid' => $_W['uniacid'],
                'uid' => $_W['member']['uid']
            ), array('balance'));
        }
        //冻结积分
        $sql = 'SELECT SUM(credit) AS credit FROM '.tablename('superman_hand2_member_block_credit').'WHERE uniacid=:uniacid AND uid=:uid';
        $params = array(
            ':uniacid' => $_W['uniacid'],
            ':uid' => $_W['member']['uid']
        );
        $block_credit = pdo_fetchcolumn($sql, $params, 'credit');
        $result['credit']['block'] = SupermanHandUtil::float_format($block_credit);
        $result['credit']['balance'] = $_W['member']['credit1'] - $result['credit']['block'];
    }
    if ($this->module['config']['my']['wxapp']) {
        $wxapps = array();
        foreach ($this->module['config']['my']['wxapp'] as $li) {
            $li['img'] = tomedia($li['img']);
            $wxapp[] = $li;
        }
        $result['wxapps'] = $wxapp;
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'item_list') {
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 20;
    $orderby = 'createtime DESC';
    $action = $_GPC['action'];
    $result = array();
    if (!empty($action)) {
        if ($action == 1) {
            $filter = array(
                'uniacid' => $_W['uniacid'],
                'seller_uid' => $_W['member']['uid'],
                'status' => array(1, 2)
            );
            $id_list = pdo_getall('superman_hand2_item', $filter, array('id'));
            if (!empty($id_list)) {
                $ids = array();
                for ($i = 0; $i < count($id_list); $i++) {
                    $ids[$i] = $id_list[$i]['id'];
                }
                $list = pdo_getall('superman_hand2_action', array(
                    'uniacid' => $_W['uniacid'],
                    'item_id' => $ids,
                    'type' => 1
                ), '', '', $orderby);
            }
        } else {
            $list = pdo_getall('superman_hand2_action', array(
                'uniacid' => $_W['uniacid'],
                'uid' => $_W['member']['uid'],
                'type' => 2
            ), '', '', $orderby);
        }
        if (!empty($list)) {
            foreach ($list as &$li) {
                SupermanHandModel::superman_hand2_action($li);
            }
            unset($li);
        }
    }
    if ($_GPC['type']) {
        $filter = array(
            'uniacid' => $_W['uniacid'],
            'seller_uid' => $_GPC['uid'] ? $_GPC['uid'] : $_W['member']['uid'],
            'status' => array(-1, 0, 1, 2)
        );
        $total = pdo_getcolumn('superman_hand2_item', $filter, 'COUNT(*)');
        $list = pdo_getall('superman_hand2_item', $filter, '*', '', $orderby, array($pindex, $pagesize));
        if (!empty($list)) {
            foreach ($list as &$li) {
                SupermanHandModel::superman_hand2_item($li);
                if ($this->plugin_module['plugin_ad']['module'] && !$this->plugin_module['plugin_ad']['module']['is_delete']) {
                    $item_top = pdo_getall('superman_hand2_position_order_log', array(
                        'uniacid' => $_W['uniacid'],
                        'itemid' => $li['id'],
                    ));
                    if (!empty($item_top)) {
                        $li['item_top'] = $item_top;
                    }
                }
            }
            unset($li);
        }
    }
    $result = array (
        'item' => $list,
        'total' => $total ? $total : 0
    );
    //广告插件
    if ($this->plugin_module['plugin_ad']['module'] && !$this->plugin_module['plugin_ad']['module']['is_delete']) {
        $result['pay_item'] = 1;
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'delete') {
    $filter = array(
        'uniacid' => $_W['uniacid'],
        'id' => $_GPC['id']
    );
    $item = pdo_get('superman_hand2_item', $filter);
    if ($item['status'] == 1
        && $item['credit_tip'] == 1
        && $this->module['config']['credit']['open'] == 1) {
        $credit = $this->module['config']['credit']['category'][$item['cid']];
        $credit_log = array(
            $item['seller_uid'],
            '删除物品'.$item['title'],
            'superman_hand2',
        );
        $ret1 = mc_credit_update($item['seller_uid'], 'credit1', -$credit, $credit_log);
        if (is_error($ret1)) {
            WeUtility::logging('fatal', '[my.inc.php: delete, update seller_uid credit fail], ret1='.var_export($ret1, true));
        }
    }
    $ret2 = pdo_update('superman_hand2_item', array(
        'status' => -2,
        'pay_position' => 0,
    ), array('id' => $_GPC['id']));
    $ret3 = pdo_delete('superman_hand2_action', array('item_id' => $_GPC['id']));
    if ($ret2 === false || $ret3 === false) {
        SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '数据库更新失败');
    }
    if ($this->plugin_module['plugin_ad']['module'] && !$this->plugin_module['plugin_ad']['module']['is_delete']) {
        pdo_delete('superman_hand2_position_order_log', array(
            'uniacid' => $_W['uniacid'],
            'itemid' => $_GPC['id']
        ));
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '删除成功');
} else if ($act == 'blacklist') {
    //检查是否为黑名单用户
    $blacklist = SupermanHandUtil::check_blacklist();
    if ($blacklist) {
        SupermanHandModel::superman_hand2_blacklist($blacklist);
        SupermanHandUtil::json(SupermanHandErrno::ACCOUNT_BLOCK, '账号已封禁, 封禁截止时间:'.$blacklist['blocktime']);
    }
    SupermanHandUtil::json(SupermanHandErrno::OK);
} else if ($act == 'post_count') {
    //检查每日发布物品的次数限制
    $base_post_count = $this->module['config']['base']['post_count'];
    $limit_tips = $this->module['config']['base']['limit_tips'];
    if ($base_post_count > 0) {
        $post_count = pdo_get('superman_hand2_member_post_count', array(
            'uniacid' => $_W['uniacid'],
            'openid' => $_W['openid'],
            'daytime' => date('Ymd', TIMESTAMP),
        ));
        if ($post_count
            && $base_post_count == $post_count['count']) {
            $msg = '每天仅可发布'.$base_post_count.'次物品，明日再来发布吧';
            $msg = $limit_tips ? $limit_tips : $msg;
            SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST, $msg);
        }
    }
    SupermanHandUtil::json(SupermanHandErrno::OK);
} else if ($act == 'init_chat') {
    $chat_filter = array(
        'uniacid' => $_W['uniacid'],
        'itemid' => $_GPC['item_id'],
        'uid' => intval($_GPC['from_uid']),
        'from_uid' => intval($_W['member']['uid'])
    );
    $message = pdo_get('superman_hand2_message_list', $chat_filter);
    if (!empty($message)) {
        $data = array(
            'status' => 1,
            'updatetime' => TIMESTAMP
        );
        $ret = pdo_update('superman_hand2_message_list', $data, array('id' => $message['id']));
        if ($ret === false) {
            SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '');
        }
    } else {
        $data = array(
            'uniacid' => intval($_W['uniacid']),
            'itemid' => intval($_GPC['item_id']),
            'uid' => intval($_GPC['from_uid']),
            'from_uid' => intval($_W['member']['uid']),
            'status' => 0,
            'updatetime' => TIMESTAMP
        );
        pdo_insert('superman_hand2_message_list', $data);
        $new_id = pdo_insertid();
        if (empty($new_id)) {
            SupermanHandUtil::json(SupermanHandErrno::INSERT_FAIL, '');
        }
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '');
} else if ($act == 'check_action') {
    $filter = array(
        'uniacid' => $_W['uniacid'],
        'item_id' => $_GPC['itemid'],
        'uid' => $_GPC['uid'],
        'type' => $_GPC['action']
    );
    $ret = pdo_update('superman_hand2_action', array(
        'is_check' => 1
    ), $filter);
    if ($ret === false) {
        SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '');
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '');
}
