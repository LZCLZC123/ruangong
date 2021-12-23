<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array(
    'list',
    'detail',  //物品详情
    'get',     //获取分类、小区列表信息
    'edit',    //编辑的物品信息
    'post',    //表单提交
    'upload',  //上传文件
    'status',  //物品切换状态
    'audit',
    'submit',  //提交物品订单,
    'report',  //举报
    'get_phone_number', //获取微信手机号
    'post_pay', // 发布付费
    'poster'    // 商品海报
))?$_GPC['act']:'list';
if (isset($_GPC['state']) && in_array($_GPC['state'], array('list', 'post'))) {
    $act = $_GPC['state'];
}
if ($act == 'list') {
    $category = pdo_getall('superman_hand2_category', array(
        'uniacid' => $_W['uniacid'],
        'status' => 1
    ), '*', '', 'displayorder DESC');
    if ($category) {
        foreach ($category as &$li) {
            $li['cover'] = $li['cover'] ? tomedia($li['cover']) : '';
        }
        unset($li);
    }
    //取列表
    $kw = trim($_GPC['kw']);
    $cid = intval($_GPC['cid']);
    $city = trim($_GPC['city']);
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 10;
    $start = ($pindex - 1) * $pagesize;
    $params = array(
        ':uniacid' => $_W['uniacid'],
        ':cid' => $cid
    );
    if ($_GPC['op'] == 'location') {//距离排序
        $latitude = $_GPC['lat'];
        $longitude = $_GPC['lng'];
        $sql = "SELECT *,(ROUND(6378.137 * 2 * ASIN(SQRT(POW(SIN(((lat * PI()) / 180 - (:latitude * PI()) / 180) / 2), 2) + COS((:latitude * PI()) / 180) * COS((lat * PI()) / 180) * POW(SIN(((lng * PI()) / 180 - (:longitude * PI()) / 180) / 2), 2))), 2)) AS distance FROM ".tablename('superman_hand2_item')." WHERE uniacid=:uniacid AND cid=:cid";
        $orderby = " ORDER BY distance ASC LIMIT {$start},{$pagesize}";
        $params[':latitude'] = $latitude;
        $params[':longitude'] = $longitude;
    } else if ($_GPC['op'] == 'popular') {//人气排序
        $sql = "SELECT * FROM ".tablename('superman_hand2_item')." WHERE uniacid=:uniacid AND cid=:cid";
        $orderby = " ORDER BY page_view DESC, createtime DESC LIMIT {$start},{$pagesize}";
    } else {
        $sql = "SELECT * FROM " . tablename('superman_hand2_item') . " WHERE uniacid=:uniacid AND cid=:cid";
        $orderby = " ORDER BY id DESC LIMIT {$start},{$pagesize}";
    }
    if ($this->module['config']['base']['hide_sold']) {
        $sql .= " AND status=1";
    } else {
        $sql .= " AND status IN (1,2)";
    }
    $sql .= " AND (expiretime = 0 OR expiretime >".TIMESTAMP.")";
    $list = pdo_fetchall($sql.$orderby, $params);
    if (!empty($list)) {
        foreach ($list as &$li) {
            SupermanHandModel::superman_hand2_item($li);
        }
        unset($li);
        // 筛选后台发布的不在指定区域的物品
        if($_GPC['lng'] && $_GPC['lat']) {
            foreach ($list as $key => $li) {
                if ($li['item_type'] == -1) {
                    if ($li['expiretime'] > 0 && $li['expiretime'] < TIMESTAMP) {
                        unset($list[$key]);
                        continue;
                    }
                    if (empty($li['area_points'])) {
                        continue;
                    }
                    $tencent_points = array();
                    $area_points = $li['area_points'] ? iunserializer($li['area_points']) : array();
                    foreach ($area_points as $ap) {
                        $tencent_points[] = SupermanHandUtil::Convert_BD09_To_GCJ02($ap['lat'], $ap['lng']);
                    }
                    $point = array(
                        'lng' => $_GPC['lng'],
                        'lat' => $_GPC['lat']
                    );
                    $ret = SupermanHandUtil::is_point_in_polygon($point, $tencent_points);
                    if (!$ret) {
                        unset($list[$key]);
                        $list = array_values($list);
                    }
                }
            }
        }
        // 删除list中的置顶物品
        if ($_GPC['op'] != 'location') {
            foreach ($list as $key => $li) {
                if ($li['pay_position'] == 1) {
                    $fields = $li['set_top_fields'];
                    foreach ($fields as $fl) {
                        if ($fl['district'] == $_GPC['district']) {
                            if ($_GPC['op'] == 'new' && $fl['position'] != 2) {
                                unset($list[$key]);
                                break;
                            }
                            if ($_GPC['op'] == 'popular' && $fl['position'] != 1) {
                                unset($list[$key]);
                                break;
                            }
                        }
                    }
                }
            }
            $list = array_values($list);
        }
    }
    // 筛选出置顶物品
    $top_list = pdo_getall('superman_hand2_item', array(
        'uniacid' => $_W['uniacid'],
        'status' => 1,
        'pay_position' => 1,
        'cid' => $cid
    ));
    if (!empty($top_list)) {
        shuffle($top_list); // 置顶物品随机排序
        foreach ($top_list as &$li) {
            SupermanHandModel::superman_hand2_item($li);
        }
        unset($li);
    }
    $result = array(
        'category' => $category,
        'thumb' => 1, //默认开启缩略图
        'list' => $list,
        'top_items' => $top_list, // 置顶物品
        'hide_tab' => $this->module['config']['base']['hide_tab'] ? $this->module['config']['base']['hide_tab'] : 0,
        'item_view' => $this->module['config']['base']['item_view'] ? $this->module['config']['base']['item_view'] : 0,
    );
    //banner图
    if ($this->plugin_module['plugin_ad']['module'] && !$this->plugin_module['plugin_ad']['module']['is_delete']) {
        //幻灯图
        $slide = pdo_getall('superman_hand2_banner', array('uniacid' => $_W['uniacid'], 'position' => 2), '*', '', 'displayorder DESC');
        if ($slide) {
            //限制幻灯图地区
            if($_GPC['lng'] && $_GPC['lat']) {
                foreach ($slide as $key => $ia) {
                    if (($ia['endtime'] > 0 && $ia['endtime'] < TIMESTAMP) || $ia['starttime'] > TIMESTAMP) {
                        unset($slide[$key]);
                        continue;
                    }
                    if (empty($ia['area_points'])) {
                        continue;
                    }
                    $tencent_points = array();
                    $area_points = $ia['area_points'] ? iunserializer($ia['area_points']) : array();
                    foreach ($area_points as $ap) {
                        $tencent_points[] = SupermanHandUtil::Convert_BD09_To_GCJ02($ap['lat'], $ap['lng']);
                    }
                    $point = array(
                        'lng' => $_GPC['lng'],
                        'lat' => $_GPC['lat']
                    );
                    $ret = SupermanHandUtil::is_point_in_polygon($point, $tencent_points);
                    if (!$ret) {
                        unset($slide[$key]);
                    }
                }
                $slide = array_values($slide);
            }
            if ($this->module['config']['banner']['random'] == 1) {
                shuffle($slide); //随机排列
            }
            foreach ($slide as &$item) {
                $item['img'] = tomedia($item['thumb']);
            }
            unset($item);
            $result['banner'] = $slide;
        }
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'detail') {
    $id = $_GPC['id'];
    if (empty($id)) {
        SupermanHandUtil::json(SupermanHandErrno::PARAM_ERROR, '');
    }
    $detail = pdo_get('superman_hand2_item', array(
        'uniacid' => $_W['uniacid'],
        'id' => $id
    ));
    SupermanHandModel::superman_hand2_item($detail);
    //发表留言
    if ($_GPC['comment']) {
        $comment_type = $this->module['config']['base']['comment'];
        $data = array(
            'uniacid' => $_W['uniacid'],
            'item_id' => $id,
            'uid' => $_W['member']['uid'],
            'message' => $_GPC['comment'],
            'createtime' => TIMESTAMP
        );
        if ($comment_type == 0) {
            $data['status'] = 1;
            $msg = '留言发布成功';
        } else {
            $data['status'] = 0;
            $msg = '留言提交成功，请等待管理员审核';
        }
        pdo_insert('superman_hand2_comment', $data);
        $new_id = pdo_insertid();
        if (empty($new_id)) {
            SupermanHandUtil::json(SupermanHandErrno::INSERT_FAIL, '留言发布失败');
        }
        if ($comment_type == 0) {
            $openid = SupermanHandUtil::uid2openid($detail['seller_uid']);
            $uni_tpl_id = $this->module['config']['tmpl']['chat_remind']['tmpl_id'];
            $gzh_appid = $this->module['config']['minipg']['bind_gzh']['appid'];
            if (!empty($uni_tpl_id) && !empty($gzh_appid)) {
                $message_data = array(
                    'first' => array(
                        'value' => '您收到了新的留言',
                        'color' => '#173177'
                    ),
                    'keyword1' => array(
                        'value' => $detail['title'],
                    ),
                    'keyword2' => array(
                        'value' => $member['nickname'],
                    ),
                    'remark' => array(
                        'value' => '留言内容：'.$_GPC['comment'],
                        'color' => '#173177'
                    ),
                );
                SupermanHandUtil::send_uniform_msg($message_data, $openid, $uni_tpl_id, $gzh_appid, $url);
            }
        }
        SupermanHandUtil::json(SupermanHandErrno::OK, $msg);
    }
    //回复留言
    if ($_GPC['reply']) {
        $filter = array(
            'uniacid' => $_W['uniacid'],
            'id' => $_GPC['msg_id'],
        );
        $message = pdo_get('superman_hand2_comment', $filter);
        $ret = pdo_update('superman_hand2_comment', array('reply' => $_GPC['reply']), $filter);
        if ($ret === false) {
            SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '');
        }
        $openid = SupermanHandUtil::uid2openid($message['uid']);
        $uni_tpl_id = $this->module['config']['tmpl']['msg_remind']['tmpl_id'];
        $gzh_appid = $this->module['config']['minipg']['bind_gzh']['appid'];
        if (!empty($uni_tpl_id) && !empty($gzh_appid)) {
            $message_data = array(
                'first' => array(
                    'value' => '您的留言有了新的回复',
                    'color' => '#173177'
                ),
                'keyword1' => array(
                    'value' => $member['nickname'],
                ),
                'keyword2' => array(
                    'value' => date('Y-m-d h:i:s', TIMESTAMP),
                ),
                'keyword3' => array(
                    'value' => $_GPC['reply'],
                ),
                'remark' => array(
                    'value' => '请进入小程序查看',
                    'color' => '#173177'
                ),
            );
            SupermanHandUtil::send_uniform_msg($message_data, $openid, $uni_tpl_id, $gzh_appid, $url);
        }
        SupermanHandUtil::json(SupermanHandErrno::OK, '');
    }
    //点赞或收藏
    if ($_GPC['type']) {
        if ($_GPC['status']) {
            $filter = array(
                'uniacid' => $_W['uniacid'],
                'item_id' => $id,
                'uid' => $_W['member']['uid'],
                'type' => $_GPC['type']
            );
            $ret = pdo_delete('superman_hand2_action', $filter);
            if ($ret === false) {
                SupermanHandUtil::json(SupermanHandErrno::DELETE_FAIL);
            }
        } else {
            $data = array(
                'uniacid' => $_W['uniacid'],
                'item_id' => $id,
                'uid' => $_W['member']['uid'],
                'type' => $_GPC['type'],
                'is_check' => 0,
                'createtime' => TIMESTAMP
            );
            pdo_insert('superman_hand2_action', $data);
            $new_id = pdo_insertid();
            if (empty($new_id)) {
                SupermanHandUtil::json(SupermanHandErrno::INSERT_FAIL);
            }
        }
        SupermanHandUtil::json(SupermanHandErrno::OK, '');
    }
    //和ta聊聊
    if ($_GPC['chat']) {
        $chat_filter = array(
            'uniacid' => intval($_W['uniacid']),
            'itemid' => intval($id),
            'uid' => intval($_GPC['from_uid']),
            'from_uid' => intval($detail['seller_uid'])
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
                'itemid' => intval($_GPC['id']),
                'uid' => intval($_GPC['from_uid']),
                'from_uid' => intval($detail['seller_uid']),
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
    }
    //切换物品状态
    if ($_GPC['status']) {
        $data = array(
            'status' => $_GPC['status']
        );
        $ret = pdo_update('superman_hand2_item', $data, array('uniacid' => $_W['uniacid'], 'id' => $id));
        if ($ret === false) {
            SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '更改物品状态失败');
        }
        $config_credit = $this->module['config']['credit'];
        $credit_uplimit = SupermanHandUtil::credit_uplimit($config_credit, $config_credit['category'][$detail['cid']]);
        //下架扣除积分
        if ($_GPC['status'] == -1
            && $config_credit['open'] == 1) {
            $credit_log = array(
                $detail['seller_uid'],
                '下架物品'.$detail['title'],
                'superman_hand2',
            );
            $ret1 = mc_credit_update($detail['seller_uid'], 'credit1', -$config_credit['category'][$detail['cid']], $credit_log);
            if (is_error($ret1)) {
                WeUtility::logging('fatal', '[[item.inc.php: post] update seller_uid credit fail], ret1='.var_export($ret, true));
            }
        } else if ($_GPC['status'] == 1
            && $detail['status'] == -1
            && $credit_uplimit) {
            $credit_log = array(
                $detail['seller_uid'],
                '发布物品'.$detail['title'],
                'superman_hand2',
            );
            $ret1 = mc_credit_update($detail['seller_uid'], 'credit1', $config_credit['category'][$detail['cid']], $credit_log);
            if (is_error($ret1)) {
                WeUtility::logging('fatal', '[[item.inc.php: post] update seller_uid credit fail], ret1='.var_export($ret, true));
            }
        }
        if ($_GPC['status'] == 2) {
            //统计日成交量
            SupermanHandUtil::stat_day_item_trade();
            //创建订单
            $item = pdo_get('superman_hand2_item', array(
                'id' => $id
            ));
            $data = array(
                'uniacid' => $_W['uniacid'],
                'itemid' => $item['id'],
                'title' => $item['title'],
                'seller_uid' => $_W['member']['uid'],
                'buyer_uid' => 0,
                'price' => $item['price'],
                'status' => 3,
                'createtime' => TIMESTAMP,
            );
            pdo_insert('superman_hand2_order', $data);
            $new_id = pdo_insertid();
            if (empty($new_id)) {
                SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '订单插入失败');
            }
        }
        SupermanHandUtil::json(SupermanHandErrno::OK, '更改物品状态成功');
    }
    //物品浏览量+1
    pdo_update('superman_hand2_item', array(
        'page_view +=' => 1
    ), array('id' => $id));
    //物品兑换订单
    if ($_GPC['orderid'] > 0) {
        $detail['order'] = pdo_get('superman_hand2_order', array(
            'uniacid' => $_W['uniacid'],
            'id' => $_GPC['orderid'],
        ), array('name', 'mobile', 'address', 'reply', 'reason'));
    }
    //查询此uid是否点赞或收藏
    $detail['is_favour'] = pdo_getcolumn('superman_hand2_action', array(
        'uniacid' => $_W['uniacid'],
        'uid' => $_W['member']['uid'],
        'item_id' => $id,
        'type' => 1
    ), 'COUNT(*)');
    $detail['is_collect'] = pdo_getcolumn('superman_hand2_action', array(
        'uniacid' => $_W['uniacid'],
        'uid' => $_W['member']['uid'],
        'item_id' => $id,
        'type' => 2
    ), 'COUNT(*)');
    //和他聊聊是否隐藏
    $detail['chat'] = $this->module['config']['base']['chat'] ? 0 : 1;
    $detail['chat_text'] = $this->module['config']['base']['chatText'] ? $this->module['config']['base']['chatText'] : '和他聊聊';
    //查询留言条数
    $com_filter = array(
        'uniacid' => $_W['uniacid'],
        'item_id' => $id,
        'status' => 1
    );
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 10;
    $start = ($pindex - 1) * $pagesize;
    $total = pdo_getcolumn('superman_hand2_comment', $filter, 'COUNT(*)');
    $orderby = 'createtime DESC';
    $list = pdo_getall('superman_hand2_comment', $com_filter, '*', '', $orderby, array($pindex, $pagesize));
    if (!empty($list)) {
        foreach ($list as &$li) {
            SupermanHandModel::superman_hand2_comment($li);
        }
        unset($li);
    }
    //共卖出多少物品及评价数量
    $detail['sell_count'] = pdo_getcolumn('superman_hand2_item', array(
        'uniacid' => $_W['uniacid'],
        'seller_uid' => $detail['seller_uid'],
        'status' => 2,
    ), 'COUNT(*)');
    $detail['level_one'] = pdo_getcolumn('superman_hand2_grade', array(
        'uniacid' => $_W['uniacid'],
        'seller_uid' => $detail['seller_uid'],
        'level' => 1,
    ), 'COUNT(*)');
    $detail['level_two'] = pdo_getcolumn('superman_hand2_grade', array(
        'uniacid' => $_W['uniacid'],
        'seller_uid' => $detail['seller_uid'],
        'level' => 2,
    ), 'COUNT(*)');
    $detail['level_three'] = pdo_getcolumn('superman_hand2_grade', array(
        'uniacid' => $_W['uniacid'],
        'seller_uid' => $detail['seller_uid'],
        'level' => 3,
    ), 'COUNT(*)');
    //公告
    $notice = pdo_getall('superman_hand2_notice', array(
        'uniacid' => $_W['uniacid'],
        'status' => 1,
        'position LIKE' => "%detail%",
        'starttime <' => TIMESTAMP,
        'endtime >' => TIMESTAMP,
    ), '*', '', 'displayorder DESC');
    //限制公告地区
    if($_GPC['lng'] && $_GPC['lat'] && $notice) {
        foreach ($notice as $key => $ia) {
            if (empty($ia['area_points'])) {
                continue;
            }
            $tencent_points = array();
            $area_points = $ia['area_points'] ? iunserializer($ia['area_points']) : array();
            foreach ($area_points as $ap) {
                $tencent_points[] = SupermanHandUtil::Convert_BD09_To_GCJ02($ap['lat'], $ap['lng']);
            }
            $point = array(
                'lng' => $_GPC['lng'],
                'lat' => $_GPC['lat']
            );
            $ret = SupermanHandUtil::is_point_in_polygon($point, $tencent_points);
            if (!$ret) {
                unset($notice[$key]);
                $notice = array_values($notice);
            }
        }
    }
    $result = array(
        'item' => $detail,
        'message' => $list,
        'set_top' => $this->module['config']['set_top']['open'] == 0 ? 0 : 1,
        'notice' => $notice,
        'notice_type' => $this->module['config']['base']['notice_type']?$this->module['config']['base']['notice_type']:0,
        'item_view' => $this->module['config']['base']['item_view'] ? $this->module['config']['base']['item_view'] : 0,
        'tmpl_id' => $this->module['config']['minipg']['buy']['tmpl_id'], // 物品购买订阅消息模版ID
        'poster' => isset($this->module['config']['poster']['open']) && $this->module['config']['poster']['open'] == 1 ? 1 : 0
    );
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'get') {
    $category = pdo_getall('superman_hand2_category', array('uniacid' => $_W['uniacid'], 'status' => 1), '*', '', 'displayorder DESC');
    if ($category) {
        foreach ($category as &$li) {
            $li['cover'] = tomedia($li['cover']);
        }
        unset($li);
    }
    $district = pdo_getall('superman_hand2_district', array('uniacid' => $_W['uniacid'], 'status' => 1), '*', '', 'displayorder DESC');
    $base = $this->module['config']['base'];
    $rule = htmlspecialchars_decode($base['rule']);
    $notice = htmlspecialchars_decode($base['notice']);  //发布须知
    $video_switch = $base['video']?1:0;
    $result = array(
        'category' => $category,
        'district' => $district,
        'rule' => $rule,
        'video_switch' => $video_switch,
        'notice' => $notice,
        'default_unit' => $this->module['config']['base']['default_unit']?$this->module['config']['base']['default_unit']:0,
        'post_notice' => $this->module['config']['base']['notice_open']?$this->module['config']['base']['notice_open']:0,
        'show_trade' => $this->module['config']['base']['show_trade']?$this->module['config']['base']['show_trade']:0,
        'audit_type' => $this->module['config']['base']['audit'],
        'add_fields' => $this->module['config']['post']['fields_on'] ? $this->module['config']['post']['form_fields'] : '',
        'set_top' => $this->module['config']['set_top']['open'] == 0 ? 0 : 1,
        'book_status' => $this->module['config']['base']['book'] ? $this->module['config']['base']['book'] : 0,
        'unit_list' => $this->module['config']['currency']?$this->module['config']['currency']:'',
        'credit_open' => $this->module['config']['credit']['open'] ? $this->module['config']['credit']['open'] : 0,
        'post_pay' => $this->module['config']['getcash']['post_pay'] && $this->module['config']['getcash']['post_money'] ? 1 : 0,
        'post_money' => $this->module['config']['getcash']['post_money'] ? $this->module['config']['getcash']['post_money'] : 0
    );
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'edit') {
    $id = $_GPC['id'];
    if ($id) {
        $detail = pdo_get('superman_hand2_item', array('uniacid' => $_W['uniacid'], 'id' => $_GPC['id']));
        SupermanHandModel::superman_hand2_item($detail);
        SupermanHandUtil::json(SupermanHandErrno::OK, '', $detail);
    } else {
        SupermanHandUtil::json(SupermanHandErrno::PARAM_ERROR, '');
    }
} else if ($act == 'post') {
    $result = array();
    $blacklist = SupermanHandUtil::check_blacklist();
    if ($blacklist) {
        SupermanHandModel::superman_hand2_blacklist($blacklist);
        SupermanHandUtil::json(SupermanHandErrno::ACCOUNT_BLOCK, '账号已封禁, 封禁截止时间:'.$blacklist['blocktime']);
    }
    //检查图书
    if (!empty($_GPC['isbn'])) {
        $filter = array(
            'uniacid' => $_W['uniacid'],
            'seller_uid' => $_W['member']['uid'],
            'status' => 1,
            'isbn' => $_GPC['isbn']
        );
        $row = pdo_get('superman_hand2_item', $filter);
        if ($row) {
            SupermanHandUtil::json(SupermanHandErrno::BOOK_EXIST, '');
        }
    }
    $album = array();
    if ($_GPC['album']) {
        $album = json_decode(base64_decode($_GPC['album']), true);
        if (count($album) > 9) {
            SupermanHandUtil::json(SupermanHandErrno::UPLOAD_MAX, '');
        }
    }
    $thumb = array();
    if ($_GPC['thumb']) {
        $thumb = json_decode(base64_decode($_GPC['thumb']), true);
    }
    $video = array();
    if ($_GPC['video']) {
        $video = json_decode(base64_decode($_GPC['video']), true);
        if (count($video) > 9) {
            SupermanHandUtil::json(SupermanHandErrno::UPLOAD_MAX, '');
        }
    }
    $cid = intval($_GPC['cid']);
    $data = array(
        'title' => $_GPC['title'],
        'cid' => $cid,
        'isbn' => $_GPC['isbn'],
        'description' => $_GPC['description'],
        'tags' => $_GPC['tags'],
        'summary' => $_GPC['summary'],
        'album' => iserializer($album),
        'thumb' => iserializer($thumb),
        'video' => iserializer($video),
        'video_thumb' => $_GPC['video_thumb'],
        'price' => $_GPC['price'],
        'address' => $_GPC['address']!='undefined'?$_GPC['address']:'',
        'city' => $_GPC['city'],
        'stock' => $_GPC['stock'],
        'buy_type' => intval($_GPC['buy_type']),
        'wechatpay' => intval($_GPC['wechatpay']),
        'unit' => intval($_GPC['unit_type']),
        'unit_title' => $_GPC['unit_title'],
        'credit' => $_GPC['credit'],
        'trade_type' => $_GPC['trade_type'] ? $_GPC['trade_type'] : 0,
        'fetch_address' => $_GPC['fetch_address']!='undefined'?$_GPC['fetch_address']:'',
    );
    //获取自定义字段数据
    $fields = array();
    $form_field = base64_decode($_GPC['add_field']);
    $fields_value = json_decode(urldecode($form_field), true);

    $add_fields = $this->module['config']['post']['form_fields'];
    if (!empty($add_fields) && !empty($fields_value)) {
        foreach ($add_fields as $key => $val) {
            if ($val['required'] == 1) { //判断是否必填
                if (!isset($fields_value[$key]) || $fields_value[$key] == '') {
                    SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST, '请输入'.$val['title']);
                }
            }
            if (strpos($fields_value[$key], ',') !== false) {
                $fields_value[$key] = explode(',', $fields_value[$key]);
            }
            $fields[$key] = array(
                'title' => $val['title'],
                'type' => $val['type'],
                'required' => $val['required'],
                'value' => $fields_value[$key],
                'extra' => $val['extra'],
            );
        }
        $data['add_fields'] = iserializer($fields);
    }
    //获取图书信息
    if (!empty($_GPC['book_field'])) {
        $book_field = base64_decode($_GPC['book_field']);
        $book_field = json_decode(urldecode($book_field), true);
        $data['book_fields'] = iserializer($book_field);
    }
    $audit_type = $this->module['config']['base']['audit'];
    if ($audit_type == 0) {
        $data['status'] = 1;
    } else if ($audit_type == 2) { // 人工智能自动审核
        // 默认审核通过
        $data['status'] = 1;

        $account = WeAccount::create();
        $access_token = $account->getAccessToken();
        if (is_error($access_token)) {
            WeUtility::logging('fatal', 'getAccessToken failed: '.$access_token);
            $data['status'] = 0;
        } else {
            // 检查文本
            if ($data['status'] != 0
                && (!empty($_GPC['description']) || !empty($_GPC['title']))) {
                $content = $_GPC['title'].$_GPC['description'];
                $url = "https://api.weixin.qq.com/wxa/msg_sec_check?access_token=" . $access_token;
                $post_data = array(
                    'content' => $content,
                );
                $response = ihttp_post($url, json_encode($post_data));
                if (is_error($response)) {
                    WeUtility::logging('fatal', "msg_sec_check failed: content={$content}, response=".var_export($response, true).", access_token=$access_token, url=$url, data=".var_export($post_data, true));
                    $data['status'] = 0;
                } else {
                    $result = json_decode($response['content'], true);
                    WeUtility::logging('debug', "msg_sec_check: content={$content}, url=$url, post_data=".var_export($post_data, true).", response=".var_export($response, true).", result=".var_export($result, true));
                    if ($result['errcode'] != 0) {
                        $data['status'] = 0; // 待审核
                        SupermanHandUtil::json(SupermanHandErrno::TEXT_ILLEGAL, '');
                    }
                }
            }
        }
    } else {
        $data['status'] = 0;
    }
    if ($_GPC['id']) {
        $data['updatetime'] = TIMESTAMP;
        $ret = pdo_update('superman_hand2_item', $data, array('id' => $_GPC['id'], 'uniacid' => $_W['uniacid']));
        if ($ret === false) {
            SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '物品更新失败');
        }
        $url = 'pages/audit/index?id='.$_GPC['id'];
    } else {
        $data['lng'] = $_GPC['lng']!='undefined'?$_GPC['lng']:'';
        $data['lat'] = $_GPC['lat']!='undefined'?$_GPC['lat']:'';
        $data['createtime'] = TIMESTAMP;
        $data['uniacid'] = $_W['uniacid'];
        $data['seller_uid'] = $_W['member']['uid'];

        //转换坐标
        $location = SupermanHandUtil::location_transition($data['lat'], $data['lng'], $this->module['config']['base']['lbs_key']);
        if ($location) {
            $data['province'] =  $location['province'];
            $data['city'] =  $location['city'];
        }
        pdo_insert('superman_hand2_item', $data);
        $new_id = pdo_insertid();
        if (empty($new_id)) {
            SupermanHandUtil::json(SupermanHandErrno::INSERT_FAIL, '物品发布失败');
        }
        $url = 'pages/audit/index?id='.$new_id;

        //累计用户每天发布次数
        $post_count = pdo_get('superman_hand2_member_post_count', array(
            'uniacid' => $_W['uniacid'],
            'openid' => $_W['openid'],
            'daytime' => date('Ymd', TIMESTAMP),
        ));
        if ($post_count) {
            pdo_update('superman_hand2_member_post_count', array(
                'count +='=> 1,
                'itemids'=> $post_count['itemids'].','.$new_id,
            ), array(
                'id' => $post_count['id']
            ));
        } else {
            pdo_insert('superman_hand2_member_post_count', array(
                'uniacid' => $_W['uniacid'],
                'openid' => $_W['openid'],
                'itemids' => $new_id,
                'count' => 1,
                'daytime' => date('Ymd', TIMESTAMP),
            ));
        }
    }
    $result = array(
        'itemid' => $_GPC['id']?$_GPC['id']:$new_id,
    );

    $config_credit = $this->module['config']['credit'];
    //检查赠送积分上限
    $credit_uplimit = SupermanHandUtil::credit_uplimit($config_credit, $config_credit['category'][$cid]);
    //物品分类赠送积分
    if ($data['status'] == 1
        && empty($_GPC['id'])
        && $credit_uplimit) {
        $category = pdo_get('superman_hand2_category', array('uniacid' => $_W['uniacid'], 'id' => $cid));
        $credit_log = array(
            $_W['member']['uid'],
            '发布物品分类为'.$category['title'].'的商品：'.$_GPC['title'],
            'superman_hand2',
        );
        $ret = mc_credit_update($_W['member']['uid'], 'credit1', $config_credit['category'][$cid], $credit_log);
        if (is_error($ret)) {
            WeUtility::logging('fatal', '[item.inc.php: post credit update fail], ret='.var_export($ret, true));
        }
        pdo_update('superman_hand2_item', array(
            'credit_tip' => 1,
        ), array(
            'id' => $new_id,
            'uniacid' => $_W['uniacid']
        ));
        $result['category'] = array(
            'title' => '发布物品分类',
            'credit' => $config_credit['category'][$cid],
        );
    }

    if ($data['status'] == 0) {
        $msg = '已提交，请等待管理员审核';
    } else {
        $msg = '发布成功';
    }
    //日发布数量统计
    $sql = "UPDATE ".tablename('superman_hand2_stat').' SET item_submit=item_submit+1 WHERE ';
    $sql .= " uniacid={$_W['uniacid']} AND daytime=".date('Ymd');
    pdo_query($sql);

    //首次发布赠送积分
    $member_log = pdo_get('superman_hand2_member_log', array(
        'uniacid' => $_W['uniacid'],
        'uid' => $_W['member']['uid'],
    ));
    $credit_uplimit = SupermanHandUtil::credit_uplimit($config_credit, $config_credit['upload']);
    if ($data['status'] == 1
        && $member_log['upload'] == 0
        && $credit_uplimit) {
        $credit_log = array(
            $_W['member']['uid'],
            '首次发布商品'.$_GPC['title'],
            'superman_hand2',
        );
        $ret = mc_credit_update($_W['member']['uid'], 'credit1', $config_credit['upload'], $credit_log);
        if (is_error($ret)) {
            WeUtility::logging('fatal', '[item.inc.php: post], ret='.var_export($ret, true));
        }
        $data = array(
            'upload' => 1
        );
        pdo_update('superman_hand2_member_log', $data, array(
            'id' => $member_log['id'],
        ));
        $result['upload'] = array(
            'title' => '首次发布',
            'credit' => $config_credit['upload'],
        );
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, $msg, $result);
} else if ($act == 'upload') {
    if ($_FILES['imgData']) {
        $files = $_FILES['imgData'];
        if ($files['error'] > 0) {
            WeUtility::logging("trace", "上传失败：files=" . var_export($files, true));
            SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL);
        }
        if ($this->module['config']['base']['audit'] == 2) { //AI审核
            $account = WeAccount::create();
            $access_token = $account->getAccessToken();
            if (is_error($access_token)) {
                SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL, 'getAccessToken fail');
            }
            $media = $files["tmp_name"];
            $url = "https://api.weixin.qq.com/wxa/img_sec_check?access_token=" . $access_token;
            $post_data = array(
                'media' => "@{$media}",
            );
            $headers = array(
                'Content-Type' => 'multipart/form-data',
            );
            $response = ihttp_request($url, $post_data, $headers);
            if (is_error($response)) {
                WeUtility::logging('fatal', "img_sec_check failed: media={$media}, response=" . var_export($response, true) . ", access_token=$access_token, post_data=" . var_export($post_data, true));
                SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL, 'img_sec_check fail');
            }
            $result = json_decode($response['content'], true);
            //WeUtility::logging('debug', "img_sec_check: media={$media}, url=$url, post_data=" . var_export($post_data, true) . ", response=" . var_export($response, true) . ", result=" . var_export($result, true));
            if ($result['errcode'] != 0) {
                SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL, $result['errMsg']);
            }
        }
        $path = "images/{$_W['uniacid']}/" . date('Y/m') . '/';
        $allpath = IA_ROOT . '/attachment/' . $path;
        $ext = strtolower(pathinfo($files['name'], PATHINFO_EXTENSION));
        $rand = random(32);
        $filename = $rand.'.'.$ext;
        $thumbname = $rand.'_thumb.'.$ext; //缩略图
        $thumbfile = $allpath . $thumbname;
        $orignfile = $allpath . $filename;
        mkdirs($allpath);
        $ret = move_uploaded_file($files["tmp_name"], $orignfile);
        if (!$ret) {
            WeUtility::logging("trace", "上传失败：path=" . $orignfile . ", ret=" . var_export($ret, true));
            SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL);
        }
        $ret = file_image_thumb($orignfile, $thumbfile, 100);
        if (is_error($ret)) {
            WeUtility::logging("trace", "生成缩略图失败：thumbfile=$thumbfile, ret=".var_export($ret, true));
        }
        $img = $path . $filename;
        $thumb = $path . $thumbname;
        //上传至远程附件
        SupermanHandUtil::sync_remote_file(array(
            $img, $thumb
        ));
        $list = array(
            'orignal' => tomedia($img),
            'thumb' => tomedia($thumb)
        );
        SupermanHandUtil::json(SupermanHandErrno::OK, '上传成功', $list);
    } else if ($_FILES['videoData']) {
        $files = $_FILES['videoData'];
        if ($files['error'] > 0) {
            SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL);
        } else {
            $path = "videos/{$_W['uniacid']}/" . date('Y/m') . '/';
            $allpath = IA_ROOT . '/attachment/' . $path;
            $type = substr($files['name'], strripos($files['name'], '.'));
            $filename = md5($files['name']) . $type;
            $orignfile = $allpath . $filename;
            mkdirs($allpath);
            $ret = move_uploaded_file($files["tmp_name"], $orignfile);
            if (!$ret) {
                WeUtility::logging("trace", "上传失败：path=" . $allpath . ", ret=" . var_dump($ret));
                SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL);
            }
            $video = $path . $filename;
            //上传至远程附件
            SupermanHandUtil::sync_remote_file(array($video));
            $video = tomedia($video);
            SupermanHandUtil::json(SupermanHandErrno::OK, '视频上传成功', $video);
        }
    } else if ($_FILES['videoThumb']) {
        $file = $_FILES['videoThumb'];
        if ($file['error'] > 0) {
            WeUtility::logging("trace", "上传失败：file=".var_export($file, true));
            SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL);
        }
        if ($this->module['config']['base']['audit'] == 2) {
            $account = WeAccount::create();
            $access_token = $account->getAccessToken();
            if (is_error($access_token)) {
                WeUtility::logging('fatal', 'getAccessToken failed: '.$access_token);
                SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL, '智能审核失败，请切换到人工审核');
            } else {
                $media = $file["tmp_name"];
                $url = "https://api.weixin.qq.com/wxa/img_sec_check?access_token=".$access_token;
                $post_data = array(
                    'media' => "@{$media}",
                );
                $headers = array('Content-Type' => 'multipart/form-data');
                $response = ihttp_request($url, $post_data, $headers);
                if (is_error($response)) {
                    WeUtility::logging('fatal', "img_sec_check failed: media={$media}, response=".var_export($response,
                            true).", access_token=$access_token, post_data=".var_export($post_data, true));
                }
                $result = json_decode($response['content'], true);
                WeUtility::logging('debug', "img_sec_check: media={$media}, url=$url, post_data=".var_export($post_data,
                        true).", response=".var_export($response, true).", result=".var_export($result, true));
                if ($result['errcode'] != 0) {
                    SupermanHandUtil::json(SupermanHandErrno::UPLOAD_ILLEGAL);
                }
            }
        }
        $path = "images/{$_W['uniacid']}/".date('Y/m').'/';
        $allpath = IA_ROOT.'/attachment/'.$path;
        mkdirs($allpath);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $rand = random(32);
        $filename = $rand.'.'.$ext;
        $filepath = $allpath.$filename;
        $ret = move_uploaded_file($file["tmp_name"], $filepath);
        if (!$ret) {
            WeUtility::logging("trace", "上传失败：path=".$filepath.", ret=".var_export($ret, true));
            SupermanHandUtil::json(SupermanHandErrno::UPLOAD_FAIL);
        }
        $img = $path.$filename;
        SupermanHandUtil::sync_remote_file(array($img));
        $video_thumb = tomedia($img);
        SupermanHandUtil::json(SupermanHandErrno::OK, '上传成功', array(
            'video_thumb' => $video_thumb,
        ));
    }
} else if ($act == 'status') {
    $status = intval($_GPC['status']);
    if ($status == 2) {
        //日交易量统计
        $sql = "UPDATE ".tablename('superman_hand2_stat').' SET item_trade=item_trade+1 WHERE ';
        $sql .= " uniacid={$_W['uniacid']} AND daytime=".date('Ymd');
        pdo_query($sql);
    }
} else if ($act == 'audit') {
    $id = $_GPC['id'];
    if (empty($id)) {
        SupermanHandUtil::json(SupermanHandErrno::PARAM_ERROR, '');
    }
    $filter = array(
        'uniacid' => $_W['uniacid'],
        'id' => $id
    );
    $ret = pdo_update('superman_hand2_item', array('status' => $_GPC['status'], 'reason' => $_GPC['reason']), $filter);
    if ($ret === false) {
        SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '数据库更新失败');
    }
    SupermanHandUtil::json(SupermanHandErrno::OK);
} else if ($act == 'submit') {
    $itemid = intval($_GPC['itemid']);
    $payType = $_GPC['payType'];
    $count = intval($_GPC['count']);
    if (!in_array($payType, array('credit', 'wechat'))) {
        SupermanHandUtil::json(SupermanHandErrno::ORDER_NOT_FOUND_PAYTYPE);
    }
    $item = pdo_get('superman_hand2_item', array(
        'uniacid' => $_W['uniacid'],
        'id' => $itemid,
    ));
    if (empty($item)) {
        SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST, '物品不存在');
    }
    if ($item['status'] != 1) {
        SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST, '物品状态未上架或已交易');
    }
    if ($item['stock'] == 0) {
        SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST, '物品已售罄');
    }
    if ($item['stock'] < $count) {
        SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST, '购买数量已超出物品库存数量');
    }
    if ($payType == 'credit') {  //积分兑换
        if ($item['seller_uid'] == $_W['member']['uid']) {
            SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST, '发布者不可以兑换自己的物品');
        }
        $sql = 'SELECT SUM(credit) AS credit FROM '.tablename('superman_hand2_member_block_credit').'WHERE uniacid=:uniacid AND uid=:uid';
        $params = array(
            ':uniacid' => $_W['uniacid'],
            ':uid' => $_W['member']['uid']
        );
        $block_credit = pdo_fetch($sql, $params);
        $credit1 = $_W['member']['credit1'] - $block_credit['credit'];
        $total_credit = SupermanHandUtil::float_format($item['credit'] * $count);
        if ($total_credit > $credit1) {
            SupermanHandUtil::json(SupermanHandErrno::CREDIT_NOT_ENOUGH);
        }
    }

    //待支付订单删除
    $filter = array(
        'uniacid' => $_W['uniacid'],
        'itemid' => $itemid,
        'buyer_uid' => $_W['member']['uid'],
        'status' => 0,
    );
    $row = pdo_get('superman_hand2_order', $filter);
    if ($row) {
        pdo_delete('superman_hand2_order', array('id' => $row['id']));
    }
    //创建兑换订单
    $ordersn = SupermanHandUtil::create_ordersn();
    $data = array(
        'uniacid' => $_W['uniacid'],
        'itemid' => $itemid,
        'title' => $item['title'],
        'ordersn' => $ordersn,
        'seller_uid' => $item['seller_uid'],
        'buyer_uid' => $_W['member']['uid'],
        'total' => $count,
        'credit' => SupermanHandUtil::float_format($item['credit'] * $count),
        'price' => SupermanHandUtil::float_format($item['price'] * $count),
        'paytype' => $payType == 'credit' ? 1 : 2,
        'name' => $_GPC['name'],
        'mobile' => $_GPC['mobile'],
        'address' => $_GPC['address'],
        'status' => 0,
        'reply' => $_GPC['reply'],
        'createtime' => TIMESTAMP,
    );
    pdo_insert('superman_hand2_order', $data);
    $orderid = pdo_insertid();
    if (empty($orderid)) {
        SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '订单插入失败');
    }

    if ($payType == 'credit') {  //积分兑换
        //更新物品状态
        $status = $item['stock'] - $count > 0 ? 1 : 2;
        $ret = pdo_update('superman_hand2_item', array(
            'stock -=' => $count,
            'status' => $status
        ), array(
            'id' => $item['id'],
        ));
        if ($ret === false) {
            SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '物品状态更新失败');
        }
        //冻结买家积分
        $block_data = array(
            'uniacid' => $_W['uniacid'],
            'itemid' => $itemid,
            'credit' => SupermanHandUtil::float_format($item['credit'] * $count),
            'uid' => $_W['member']['uid'],
            'remark' => '兑换商品'.$item['title'],
            'createtime' => TIMESTAMP,
        );
        pdo_insert('superman_hand2_member_block_credit', $block_data);
        $block_id = pdo_insertid();
        if (empty($block_id)) {
            SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '数据库更新失败');
        }
        pdo_update('superman_hand2_order', array(
            'status' => 1,
            'paytype' => 1,
        ), array(
            'id' => $orderid,
        ));
        $openid = SupermanHandUtil::uid2openid($item['seller_uid']);
        $url = 'pages/my_order/index?type=sell';
        if ($_GPC['send_subscribe']) {
            $message_data = array(
                'character_string1' => array(
                    'value' => $ordersn
                ),
                'thing3' => array(
                    'value' => $item['title']
                ),
                'time4' => array(
                    'value' => date('Y-m-d H:i:s', TIMESTAMP)
                ),
                'amount5' => array(
                    'value' => $data['credit']
                ),
            );
            SupermanHandUtil::send_subscribe_tmplmsg($openid, $this->module['config']['minipg']['buy']['tmpl_id'], $url, $message_data);
        } else {
            $uni_tpl_id = $this->module['config']['tmpl']['buy']['tmpl_id'];
            $gzh_appid = $this->module['config']['minipg']['bind_gzh']['appid'];
            if (!empty($uni_tpl_id) && !empty($gzh_appid)) {
                $user = pdo_get('mc_members', array('uid' => $_W['member']['uid']), array('nickname'));
                $message_data = array(
                    'first' => array(
                        'value' => '您发布的' . $item['title'] . '物品已被购买',
                        'color' => '#173177'
                    ),
                    'keyword1' => array(
                        'value' => $ordersn,
                    ),
                    'keyword2' => array(
                        'value' => $item['price'] > 0 ? $item['price'].'元' : $item['credit'].'积分',
                    ),
                    'keyword3' => array(
                        'value' => date('Y-m-d H:i:s', $item['createtime']),
                    ),
                    'remark' => array(
                        'value' => '请尽快发货或联系客户自提',
                        'color' => '#173177'
                    ),
                );
                SupermanHandUtil::send_uniform_msg($message_data, $openid, $uni_tpl_id, $gzh_appid, $url);
            }
        }
    } else if ($payType == 'wechat') {  //微信支付
        if ($this->plugin_module['plugin_wechat']['module'] && !$this->plugin_module['plugin_wechat']['module']['is_delete']) {
            //微信支付
            $params = array(
                'tid' => 'superman_hand2_wechat:'.$orderid,
                'user' => $_W['openid'],
                'fee' => SupermanHandUtil::float_format($item['price'] * $count),
                'title' => '购买物品订单('.$ordersn.')支付',
            );
            $site = WeUtility::createModuleWxapp($this->plugin_module['plugin_wechat']['module']['name']);
            $result = $site->pay($params);
            if (is_error($result)) {
                WeUtility::logging('fatal', '[item.inc.php], result='.var_export($result, true));
                SupermanHandUtil::json(-1, '支付失败，请重试');
            }
        }
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'report') {
    $filter = array(
        'uniacid' => $_W['uniacid'],
        'report_uid' => $_W['member']['uid'],
        'itemid' => intval($_GPC['itemid']),
    );
    $row = pdo_get('superman_hand2_report', $filter);
    if ($row) {
        SupermanHandUtil::json(SupermanHandErrno::INVALID_REQUEST, '该物品已举报过');
    }
    $filter = array(
        'uniacid' => $_W['uniacid'],
        'id' => $_GPC['itemid'],
    );
    $item = pdo_get('superman_hand2_item', $filter);
    $data = array(
        'uniacid' => $_W['uniacid'],
        'itemid' => $_GPC['itemid'],
        'seller_uid' => $item['seller_uid'],
        'report_uid' => $_W['member']['uid'],
        'reason' => $_GPC['content'],
        'createtime' => TIMESTAMP,
    );
    pdo_insert('superman_hand2_report', $data);
    $new_id = pdo_insertid();
    if (empty($new_id)) {
        SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '数据库更新失败');
    }
    SupermanHandUtil::json(SupermanHandErrno::OK);
} else if ($act == 'get_phone_number') {
    $account = WeAccount::create();
    $encrypt_data = $_GPC['encryptedData'];
    $iv = $_GPC['iv'];
    $phoneInfo = $account->pkcs7Encode($encrypt_data, $iv);
    if (is_error($phoneInfo)) {
        WeUtility::logging('fatal', '[wxapp:member]手机号解密失败，result='.var_export($phoneInfo, true).',session_key='.$_W['session_key']);
        SupermanHandUtil::json(SupermanHandErrno::DECODE_FAIL);
    }
    $phoneNumber = $phoneInfo['purePhoneNumber'];
    $ret = pdo_update('mc_members', array(
        'mobile' => $phoneNumber,
    ), array('uid' => $_W['member']['uid'], 'uniacid' => $_W['uniacid']));
    if ($ret === false) {
        SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL, '更新数据库失败');
    } else {
        SupermanHandUtil::json(SupermanHandErrno::OK, '', $phoneInfo);
    }
} else if ($act == 'post_pay') {
    $result = array();
    if ($this->plugin_module['plugin_wechat']['module'] && !$this->plugin_module['plugin_wechat']['module']['is_delete']) {
        //微信支付
        $params = array(
            'tid' => 'superman_hand2_post_pay:'.TIMESTAMP,
            'user' => $_W['openid'],
            'fee' => SupermanHandUtil::float_format($_GPC['money']),
            'title' => '物品('.$_GPC['title'].')发布付费',
        );
        $site = WeUtility::createModuleWxapp($this->plugin_module['plugin_wechat']['module']['name']);
        $result = $site->pay($params);
        if (is_error($result)) {
            WeUtility::logging('fatal', '[item.inc.php], post_pay result='.var_export($result, true));
            SupermanHandUtil::json(-1, '支付失败，请重试');
        }
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'poster') {
    if (!isset($this->module['config']['poster']['open']) || $this->module['config']['poster']['open'] == 0) {
        SupermanHandUtil::json(SupermanHandErrno::PARAM_ERROR, '海报功能未开启');
    }
    if (empty($_GPC['itemid'])) {
        SupermanHandUtil::json(SupermanHandErrno::PARAM_ERROR, '缺少itemid参数');
    }
    $item = pdo_get('superman_hand2_item', array(
        'uniacid' => $_W['uniacid'],
        'id' => $_GPC['itemid']
    ));
    $path = 'data/' . $_W['uniacid'] . '/poster';
    mkdirs(MODULE_ROOT . '/' . $path);
    $poster_filename = md5('superman_hand2_poster:' . $item['id']) . '-' . $_W['member']['uid'] . '.png';
    $poster_path = $path . '/' . $poster_filename;
    if (!file_exists(MODULE_ROOT.'/'.$poster_path)) {
        //初始化背景图
        if (!empty($this->module['config']['poster']['bgimg'])) {
            $imgpath = SupermanHandUtil::get_bgimg_localpath($this->module['config']['poster']['bgimg']);
            if (!file_exists($imgpath)) {
                SupermanHandUtil::json(SupermanHandErrno::PARAM_ERROR, '海报背景参数未设置');
            }
            $ext = pathinfo($imgpath, PATHINFO_EXTENSION);
            $ext = strtolower($ext);
            $ext = $ext == 'jpg' ? 'jpeg' : $ext;
            $method = "imagecreatefrom{$ext}";
            if (!function_exists($method)) {
                WeUtility::logging('fatal', '[create_image] failed, function no exist, function=' . $method);
            }
            $bgimg = $method($imgpath);
        } else {
            $bgimg = imagecreatetruecolor(640, 1008);
            $white = imagecolorallocate($bgimg, 255, 255, 255);
            imagefill($bgimg, 0, 0, $white);
        }
        imagealphablending($bgimg, true); //混色模式
        //初始化组件
        $widgets = $this->module['config']['poster']['widgets'] ? iunserializer($this->module['config']['poster']['widgets']) : array();
        if ($widgets) {
            $member = pdo_get('mc_members', array('uid' => $_W['member']['uid']), array('nickname', 'avatar'));
            foreach ($widgets as $v) {
                $type = $v['type'];
                if ($type == 'avatar') {
                    $w = intval($v['width'])?intval($v['width'])*2:'48';
                    $h = intval($v['height'])?intval($v['height'])*2:'48';
                } else {
                    $w = intval($v['width'])?intval($v['width'])*2:'200';
                    $h = intval($v['height'])?intval($v['height'])*2:'200';
                }
                $x = $v['left'] * 2;
                $y = $v['top'] * 2;
                $rgb = SupermanHandUtil::hex2rgb($v['color']);
                $fontsize = $v['fontsize'];
                $imgpath = $v['imgpath'];
                if ($type == 'image') {
                    $album = $item['album'] ? unserialize($item['album']) : array();
                    $imgpath = $album ? $album[0] : '';
                    if ($imgpath == '') {
                        WeUtility::logging('warning', '[poster:'.$type.'] not found image, widget='.var_export($v, true));
                        continue;
                    }
                    $imgpath = SupermanHandUtil::get_bgimg_localpath($imgpath);
                    if (!file_exists($imgpath)) {
                        WeUtility::logging('warning', '[poster:'.$type.'] image not exist, imgpath='.$imgpath);
                        continue;
                    }
                    SupermanHandUtil::create_image($bgimg, $imgpath, $x, $y, $w, $h);
                } else if ($type == 'avatar') {
                    $imgpath = SupermanHandUtil::get_avatar_localpath($member['avatar']);
                    if (!file_exists($imgpath)) {
                        WeUtility::logging('warning', '[poster:'.$type.'] avatar image not exist, member='.var_export($_W['member'], true));
                        continue;
                    }
                    $imgpath = SupermanHandUtil::resize_img($imgpath);
                    $imgpath = SupermanHandUtil::circle_avatar($imgpath, MODULE_ROOT.'/data/'.$_W['uniacid'].'/avatar/');
                    SupermanHandUtil::create_image($bgimg, $imgpath, $x, $y, $w, $h);
                } else if ($type == 'qrcode') {
                    $mpcode = SupermanHandUtil::get_wxapp_code('pages/detail/index', $_GPC['itemid'], true);
                    SupermanHandUtil::create_image($bgimg, $mpcode, $x, $y, $w, $h);
                } else { //文字
                    if ($type == 'nickname') {
                        $text = $member['nickname'];
                    } else if ($type == 'title') {
                        $text = $item['title'];
                    } else if ($type == 'detail') {
                        if (!empty($item['description'])) {
                            $text = mb_substr($item['description'], 0, 20, 'utf-8');
                        } else {
                            $text = '';
                        }
                    }
                    _create_text($bgimg, $text, $x, $y, $rgb, SUPERMAN_FONT_MSYH, $fontsize*2);//字号需要等比放大一倍
                }
            }
        }
        //保存海报文件
        SupermanHandUtil::save_image($bgimg, MODULE_ROOT.'/'.$poster_path);
        imagedestroy($bgimg);
    }
    $poster = MODULE_URL.$poster_path;
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $poster);
}
function _create_text(&$bgimg, $text, $x, $y, $rgb, $font, $fonsize = 14) {
    $color = imagecolorallocate($bgimg, $rgb['r'], $rgb['g'], $rgb['b']);
    //根据gd库判断size单位
    $gdv = SupermanHandUtil::gdVersion();
    $size = $gdv >= 2 ? $fonsize * 3 / 4 : $fonsize;//磅值/像素
    //文字定位点在左下角
    imagettftext($bgimg, $size, 0, $x, $y + $fonsize, $color, $font, $text);
}
function ffmpeg_thumbnail($video_file, $time = 1, $width = 300, $height = 200) {
    $url_prefix = strstr($video_file, 'video', -1);
    $url_suffix = substr($video_file, strpos($video_file, 'video'));
    $output_file = $url_prefix.$url_suffix.'.jpg';
    //$output_file = strstr($video_file, '.', -1).'.jpg';
    $cmd = "ffmpeg -i " . $video_file . " -f image2 -ss 1 -vframes {$time} -s $width*$height $output_file";

    $programs = array('exec', 'system', 'passthru');
    $status = 0;
    $data = null;
    $res = -1;
    foreach ($programs as $p) {
        if (function_exists($p)) {
            if ($p == 'exec') {
                $p($cmd, $data, $res);
            } else {
                $p($cmd, $res);
            }
        } else {
            $status++;
        }
        if ($res == 0) {
            break; // success
        }
    }
    if ($status == 3) {
        WeUtility::logging('fatal', 'exec、system、passthru方法都被禁用了，无法生成缩略图！');
        return false;
    }
    if ($res == 1) {
        WeUtility::logging('fatal', '图片截取失败: video='.$video_file.', thumb='.$output_file);
        return false;
    }
    WeUtility::logging('trace', '图片截取成功: video='.$video_file.', thumb='.$output_file);
    return true;
}
