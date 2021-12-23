<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array(
    'display',
    'search',
    'get_base_info', //获取加载动画和定位开关
    'page_view',
    'banner'
))?$_GPC['act']:'display';
if ($act == 'display') {
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 10;
    $start = ($pindex - 1) * $pagesize;
    $latitude = $_GPC['lat'];
    $longitude = $_GPC['lng'];
    //排除黑名单用户
    $sql = "SELECT * FROM " . tablename('superman_hand2_member_blacklist') . " WHERE uniacid=".$_W['uniacid'];
    $sql .= " AND (blocktime = 1 OR blocktime >".TIMESTAMP.")";
    $blacklist = pdo_fetchall($sql);
    if (!empty($blacklist)) {
        $arr = array();
        foreach ($blacklist as $black) {
            $arr[] = $black['uid'];
        }
        $black_uid = implode(',', $arr);
    }
    //广告插件
    if ($this->plugin_module['plugin_ad']['module']
        && !$this->plugin_module['plugin_ad']['module']['is_delete']) {
        // 橱窗广告
        $cube_ad_switch = $this->module['config']['cube_ad']['open'] ? $this->module['config']['cube_ad']['open'] : 0;
        $cube_ad_type = $this->module['config']['cube_ad']['show_type'] ? $this->module['config']['cube_ad']['show_type'] : 0;
        $cube_ad_list = array();
        if ($cube_ad_switch == 1) {
            $orderby = 'displayorder ASC, id ASC';
            $ad_list = pdo_getall('superman_hand2_cube_ad', array('uniacid' => $_W['uniacid']), '*', '', $orderby);
            if ($ad_list) {
                foreach ($ad_list as $key => $ia) {
                    if (strtotime($ia['starttime']) > TIMESTAMP
                        || ($ia['endtime'] != '0000-00-00 00:00:00' && strtotime($ia['endtime']) < TIMESTAMP) ) {
                        unset($ad_list[$key]);
                        continue;
                    }
                    if (!empty($ia['area_points'])) {
                        $tencent_points = array();
                        $area_points = $ia['area_points'] ? iunserializer($ia['area_points']) : array();
                        foreach ($area_points as $ap) {
                            $tencent_points[] = SupermanHandUtil::Convert_BD09_To_GCJ02($ap['lat'], $ap['lng']);
                        }
                        $point = array(
                            'lng' => $longitude,
                            'lat' => $latitude
                        );
                        $ret = SupermanHandUtil::is_point_in_polygon($point, $tencent_points);
                        if (!$ret) {
                            unset($ad_list[$key]);
                        }
                    }
                }
                $ad_list = array_values($ad_list);
                foreach ($ad_list as &$item) {
                    $item['thumb'] = tomedia($item['thumb']);
                }
                unset($item);
                $cube_ad_list = $ad_list;
            }
        }
        // 悬浮按钮
        $post_btn_switch = $this->module['config']['post_img']['open']?$this->module['config']['post_img']['open']:0;
        $post_btn = array(
            'thumb' => $this->module['config']['post_img']['thumb']?tomedia($this->module['config']['post_img']['thumb']):'',
            'url' => $this->module['config']['post_img']['url']?$this->module['config']['post_img']['url']:'',
            'appid' => $this->module['config']['post_img']['appid']?$this->module['config']['post_img']['appid']:'',
            'starttime' => $this->module['config']['post_img']['show_time'] ? $this->module['config']['post_img']['show_time']['start'] : '',
            'endtime' => $this->module['config']['post_img']['show_time'] ? $this->module['config']['post_img']['show_time']['end'] : ''
        );
        if ($post_btn['endtime']) {
            if (strtotime($post_btn['endtime']) <= TIMESTAMP || strtotime($post_btn['starttime']) > TIMESTAMP) {
                $post_btn_switch = 0;
            }
        }
        //限制物品广告地区
        if ($latitude && $longitude) {
            $item_ad = pdo_getall('superman_hand2_item', array(
                'uniacid' => $_W['uniacid'],
                'item_type' => 1,
                'status' => 1
            ));
            if ($item_ad) {
                $item_ad_ids = array();
                foreach ($item_ad as $ia) {
                    if (empty($ia['area_points'])) {
                        continue;
                    }
                    if ($ia['expiretime'] > 0 && $ia['expiretime'] < TIMESTAMP) {
                        $item_ad_ids[] = $ia['id'];
                        continue;
                    }
                    $tencent_points = array();
                    $area_points = $ia['area_points'] ? iunserializer($ia['area_points']) : array();
                    foreach ($area_points as $ap) {
                        $tencent_points[] = SupermanHandUtil::Convert_BD09_To_GCJ02($ap['lat'], $ap['lng']);
                    }
                    $point = array(
                        'lng' => $longitude,
                        'lat' => $latitude
                    );
                    $ret = SupermanHandUtil::is_point_in_polygon($point, $tencent_points);
                    if (!$ret) {
                        $item_ad_ids[] = $ia['id'];
                    }
                }
                $item_ad_ids = implode(',', $item_ad_ids);
            }
        }
    }
    $params = array(
        ':uniacid' => $_W['uniacid'],
    );
    //距离排序
    if ($_GPC['op'] == 'location') {
        $sql = "SELECT *,(ROUND(6378.137 * 2 * ASIN(SQRT(POW(SIN(((lat * PI()) / 180 - (:latitude * PI()) / 180) / 2), 2) + COS((:latitude * PI()) / 180) * COS((lat * PI()) / 180) * POW(SIN(((lng * PI()) / 180 - (:longitude * PI()) / 180) / 2), 2))), 2)) AS distance FROM ".tablename('superman_hand2_item')." WHERE uniacid=:uniacid";
        $orderby = " ORDER BY distance ASC, createtime DESC LIMIT {$start},{$pagesize}";
        $params[':latitude'] = $latitude;
        $params[':longitude'] = $longitude;
    } else if ($_GPC['op'] == 'popular') {//人气排序
        $sql = "SELECT * FROM ".tablename('superman_hand2_item')." WHERE uniacid=:uniacid";
        $orderby = " ORDER BY page_view DESC, createtime DESC LIMIT {$start},{$pagesize}";
    } else {
        $sql = "SELECT * FROM " . tablename('superman_hand2_item') . " WHERE uniacid=:uniacid";
        $orderby = " ORDER BY createtime DESC LIMIT {$start},{$pagesize}";
    }
    if ($this->module['config']['base']['hide_sold']) {
        $sql .= " AND status=1";
    } else {
        $sql .= " AND status IN (1,2)";
    }
    if ($item_ad_ids) {
        $sql .= " AND id NOT IN (".$item_ad_ids.")";
    }
    if ($black_uid) {
        $sql .= " AND seller_uid NOT IN (".$black_uid.")";
    }
    $sql .= " AND (expiretime = 0 OR expiretime >".TIMESTAMP.")";
    $list = pdo_fetchall($sql.$orderby, $params);
    if (!empty($list)) {
        foreach ($list as &$li) {
            SupermanHandModel::superman_hand2_item($li);
        }
        unset($li);
        // 筛选后台发布的不在指定区域的物品
        if ($latitude && $longitude) {
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
                        'lng' => $longitude,
                        'lat' => $latitude
                    );
                    $ret = SupermanHandUtil::is_point_in_polygon($point, $tencent_points);
                    if (!$ret) {
                        unset($list[$key]);
                    }
                }
            }
            $list = array_values($list);
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
    // 筛选出所有的置顶物品
    $top_list = pdo_getall('superman_hand2_item', array(
        'uniacid' => $_W['uniacid'],
        'status' => 1,
        'pay_position' => 1
    ));
    if (!empty($top_list)) {
        shuffle($top_list); // 置顶物品随机排序
        foreach ($top_list as &$li) {
            SupermanHandModel::superman_hand2_item($li);
        }
        unset($li);
    }
    //幻灯图
    $slide = pdo_getall('superman_hand2_banner', array('uniacid' => $_W['uniacid'], 'position' => 1), '*', '', 'displayorder DESC');
    if ($slide) {
        //限制幻灯图地区
        if ($latitude && $longitude) {
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
                    'lng' => $longitude,
                    'lat' => $latitude
                );
                $ret = SupermanHandUtil::is_point_in_polygon($point, $tencent_points);
                if (!$ret) {
                    unset($slide[$key]);
                }
            }
            $slide = array_values($slide);
        }
        if ($this->module['config']['base']['random'] == 1) {
            shuffle($slide); //随机排列
        }
        foreach ($slide as &$item) {
            $item['img'] = tomedia($item['thumb']);
        }
        unset($item);
    }
    //分类
    $category = pdo_getall('superman_hand2_category', array('uniacid' => $_W['uniacid'], 'status' => 1), '*', '', 'displayorder DESC');
    if ($category) {
        foreach ($category as &$li) {
            $li['cover'] = tomedia($li['cover']);
        }
        unset($li);
    }
    //分类开关
    $switch = $this->module['config']['base']['category'];
    if (empty($switch)) {
        $switch = 1;
    }
    //公告
    $notice = pdo_getall('superman_hand2_notice', array(
        'uniacid' => $_W['uniacid'],
        'status' => 1,
        'position LIKE' => "%home%",
        'starttime <' => TIMESTAMP,
        'endtime >' => TIMESTAMP,
    ), '*', '', 'displayorder DESC');
    //限制公告地区
    if ($latitude && $longitude && $notice) {
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
                'lng' => $longitude,
                'lat' => $latitude
            );
            $ret = SupermanHandUtil::is_point_in_polygon($point, $tencent_points);
            if (!$ret) {
                unset($notice[$key]);
            }
        }
        $notice = array_values($notice);
    }
    if (!empty($this->module['config']['index_banner']['image'])) {
        $this->module['config']['index_banner']['image'] = tomedia($this->module['config']['index_banner']['image']);
    }
    $result = array(
        'title' => $this->module['config']['base']['title']?$this->module['config']['base']['title']:'',
        'notice_type' => $this->module['config']['base']['notice_type']?$this->module['config']['base']['notice_type']:0,
        'thumb' => 1, //默认开启缩略图
        'slide' => $slide,
        'cate_switch' => $switch,
        'category' => $category,
        'notice' => $notice,
        'items' => $list,
        'top_items' => $top_list, // 置顶物品
        'recycle' => array(
            'open' => $this->module['config']['recycle']['open']?true:false,
        ),
        'cube' => array(
            'open' => $cube_ad_switch,
            'data' => array(
                'list' => $cube_ad_list,
                'type' => $cube_ad_type,
            )
        ),
        'post_btn' => array(
            'open' => $post_btn_switch,
            'data' => $post_btn
        ),
        'plugin' => array(
            'ad' => false,
            'notice' => false,
            'wechat' => false,
        ),
        'index_banner' => $this->module['config']['index_banner'],
    );
    if ($this->plugin_module) {
        foreach ($this->plugin_module as $plugin) {
            $name = str_replace('superman_hand2_plugin_', '', $plugin['module']['name']);
            if ($plugin['module'] && !$plugin['module']['is_delete']) {
                $result['plugin'][$name] = true;
            }
        }
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'search') {
    $pindex = max(1, intval($_GPC['page']));
    $pagesize = 10;
    $start = ($pindex - 1) * $pagesize;
    $kw = trim($_GPC['kw']);
    //排除黑名单用户
    $sql = "SELECT * FROM " . tablename('superman_hand2_member_blacklist') . " WHERE uniacid=".$_W['uniacid'];
    $sql .= " AND (blocktime = 1 OR blocktime >".TIMESTAMP.")";
    $blacklist = pdo_fetchall($sql);
    if (!empty($blacklist)) {
        $arr = array();
        foreach ($blacklist as $black) {
            $arr[] = $black['uid'];
        }
        $black_uid = implode(',', $arr);
    }

    //距离排序
    if ($_GPC['op'] == 'location') {
        $latitude = $_GPC['lat'];
        $longitude = $_GPC['lng'];
        $sql = "SELECT *,(ROUND(6378.137 * 2 * ASIN(SQRT(POW(SIN(((lat * PI()) / 180 - (:latitude * PI()) / 180) / 2), 2) + COS((:latitude * PI()) / 180) * COS((lat * PI()) / 180) * POW(SIN(((lng * PI()) / 180 - (:longitude * PI()) / 180) / 2), 2))), 2)) AS distance FROM ".tablename('superman_hand2_item');
        if ($this->module['config']['base']['hide_sold']) {
            $sql .= " WHERE uniacid=:uniacid AND status=1";
        } else {
            $sql .= " WHERE uniacid=:uniacid AND status IN (1,2)";
        }
        if ($black_uid) {
            $sql .= " AND seller_uid NOT IN (".$black_uid.")";
        }
        if (!empty($kw)) {
            $sql .= " AND (title LIKE '%{$kw}%' OR description LIKE '%{$kw}%')";
        }
        $sql .= " AND (expiretime = 0 OR expiretime >".TIMESTAMP.")";
        $sql .= " ORDER BY distance ASC, createtime DESC LIMIT {$start},{$pagesize}";
        $params = array(
            ':uniacid' => $_W['uniacid'],
            ':latitude' => $latitude,
            ':longitude' => $longitude,
        );
    } else if ($_GPC['op'] == 'popular') {//人气排序
        $sql = "SELECT * FROM ".tablename('superman_hand2_item');
        if ($this->module['config']['base']['hide_sold']) {
            $sql .= " WHERE uniacid=:uniacid AND status=1";
        } else {
            $sql .= " WHERE uniacid=:uniacid AND status IN (1,2)";
        }
        if ($black_uid) {
            $sql .= " AND seller_uid NOT IN (".$black_uid.")";
        }
        if (!empty($kw)) {
            $sql .= " AND (title LIKE '%{$kw}%' OR description LIKE '%{$kw}%')";
        }
        $sql .= " AND (expiretime = 0 OR expiretime >".TIMESTAMP.")";
        $sql .= " ORDER BY page_view DESC, createtime DESC LIMIT {$start},{$pagesize}";
        $params = array(
            ':uniacid' => $_W['uniacid'],
        );
    } else {
        $params = array(
            ':uniacid' => $_W['uniacid'],
        );
        $sql = "SELECT * FROM " . tablename('superman_hand2_item') . " WHERE uniacid=:uniacid";
        if ($this->module['config']['base']['hide_sold']) {
            $sql .= " AND status=1";
        } else {
            $sql .= " AND status IN (1,2)";
        }
        if ($black_uid) {
            $sql .= " AND seller_uid NOT IN (".$black_uid.")";
        }
        if (!empty($kw)) {
            $sql .= " AND (title LIKE '%{$kw}%' OR description LIKE '%{$kw}%')";
        }
        $sql .= " AND (expiretime = 0 OR expiretime >".TIMESTAMP.")";
        $sql .= " ORDER BY createtime DESC LIMIT {$start},{$pagesize}";
    }
    $list = pdo_fetchall($sql, $params);
    if ($list) {
        foreach ($list as &$li) {
            SupermanHandModel::superman_hand2_item($li);
        }
        unset($li);
    }
    $result = array(
        'list' => $list
    );
    //上架通知插件
    if ($this->plugin_module['plugin_notice']['module']
        && !$this->plugin_module['plugin_notice']['module']['is_delete']) {
        $askItem = pdo_get('superman_hand2_ask_item', array(
            'uniacid' => $_W['uniacid'],
            'uid' => $_W['member']['uid'],
        ));
        $notice_config = $this->plugin_module['plugin_notice']['module']['config'];
        $result['plugin_notice'] = array(
            'switch' => $notice_config['base']['switch'] ? 1 : 0,
            'askid' => $askItem ? $askItem['id'] : 0,
        );
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'get_base_info') {
    $result = array(
        'credit_title' => SupermanHandUtil::get_credit_titles(),
        'loading_img' => $this->module['config']['base']['loading_img'] ? tomedia($this->module['config']['base']['loading_img']) : '',
        'post_time' => $this->module['config']['base']['post_time'] ? $this->module['config']['base']['post_time'] : 0,
        'sold_img' => $this->module['config']['base']['sold_img'] ? tomedia($this->module['config']['base']['sold_img']) : '',
        'tab_display' => $this->module['config']['base']['tab_display'] ? $this->module['config']['base']['tab_display'] : 'new',
        'hide_tab' => $this->module['config']['base']['hide_tab'] ? $this->module['config']['base']['hide_tab'] : 0,
        'hide_address' => $this->module['config']['base']['hide_address'] ? $this->module['config']['base']['hide_address'] : 0,
        'item_view' => $this->module['config']['base']['item_view'] ? $this->module['config']['base']['item_view'] : 0,
        'post_notice' => $this->module['config']['base']['notice_open']?$this->module['config']['base']['notice_open']:0,
    );
    if ($this->module['config']['base']['audit_switch']) {
        $result['audit_switch'] = 1;
        $result['audit_version'] = $this->module['config']['base']['audit_version'];
        $list = pdo_getall('superman_hand2_item', array(
            'uniacid' => $_W['uniacid'],
            'status' => 1
        ), '*', '', 'createtime DESC', array(1, 2));
        $result['audit_item'] = SupermanHandModel::superman_hand2_item($list[0]);
    }
    SupermanHandUtil::json(SupermanHandErrno::OK, '', $result);
} else if ($act == 'page_view') { // 橱窗广告点击计数
    $ret = pdo_update('superman_hand2_cube_ad', array(
        'page_view +=' => 1
    ), array('id' => $_GPC['id']));
    if ($ret === false) {
        SupermanHandUtil::json(SupermanHandErrno::UPDATE_FAIL);
    } else {
        SupermanHandUtil::json(SupermanHandErrno::OK);
    }
}

