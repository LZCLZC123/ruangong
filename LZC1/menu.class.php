<?php

defined('IN_IA') or exit('Access Denied');
class SupermanHand2Menu {
    public static function web_menus() {
        global $_W, $_GPC;
        $navs = array(
            array(
                'title' => '物品管理',
                'icon' => 'fa fa-gift',
                'items' => array(
                    array(
                        'title' => '列表',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'item',
                            'act' => 'display',
                        ),
                    ),
                    array(
                        'title' => '添加',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'item',
                            'act' => 'add',
                        ),
                    ),
                    array(
                        'title' => '已成交',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'item',
                            'act' => 'sold',
                        ),
                    ),
                ),
            ),
            array(
                'title' => '留言列表',
                'icon' => 'fa fa-comment',
                'querystring' => array(
                    'do' => 'comment',
                    'act' => 'display',
                ),
                'items' => array(),
            ),
            array(
                'title' => '评论列表',
                'icon' => 'fa fa-comment',
                'querystring' => array(
                    'do' => 'grade',
                    'act' => 'display',
                ),
                'items' => array(),
            ),
            array(
                'title' => '会员管理',
                'icon' => 'fa fa-user',
                'querystring' => array(
                    'do' => 'member',
                    'act' => 'display',
                ),
                'items' => array(),
            ),
            array(
                'title' => '订单管理',
                'icon' => 'fa fa-list',
                'querystring' => array(
                    'do' => 'order',
                    'act' => 'display',
                ),
                'items' => array(
                    array(
                        'title' => '列表',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'order',
                            'act' => 'display',
                        ),
                    ),
                    array(
                        'title' => '退款申请',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'order',
                            'act' => 'refund_list',
                        ),
                    ),
                ),
            ),
            array(
                'title' => '分类管理',
                'icon' => 'fa fa-tasks',
                'items' => array(
                    array(
                        'title' => '列表',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'category',
                            'act' => 'display',
                        ),
                    ),
                    array(
                        'title' => '添加',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'category',
                            'act' => 'post',
                        ),
                    ),
                ),
            ),
            array(
                'title' => '公告管理',
                'icon' => 'fa fa-list-ul',
                'items' => array(
                    array(
                        'title' => '列表',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'notice',
                            'act' => 'display',
                        ),
                    ),
                    array(
                        'title' => '添加',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'notice',
                            'act' => 'post',
                        ),
                    ),

                ),
            ),
            array(
                'title' => '首页轮播图',
                'icon' => 'fa fa-list-ul',
                'items' => array(
                    array(
                        'title' => '列表',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'banner',
                            'act' => 'display',
                        ),
                    ),
                    array(
                        'title' => '添加',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'banner',
                            'act' => 'post',
                        ),
                    ),

                ),
            ),
            array(
                'title' => '数据统计',
                'icon' => 'fa fa-line-chart',
                'querystring' => array(
                    'do' => 'stat',
                    'act' => 'display',
                ),
                'items' => array(),
            ),
            array(
                'title' => '举报管理',
                'icon' => 'fa fa-hand-paper-o',
                'items' => array(
                    array(
                        'title' => '列表',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'report',
                            'act' => 'display',
                        ),
                    ),
                    array(
                        'title' => '黑名单',
                        'icon' => 'fa fa-circle',
                        'querystring' => array(
                            'do' => 'report',
                            'act' => 'black',
                        ),
                    ),
                ),
            ),
            array(
                'title' => '系统设置',
                'icon' => 'fa fa-cog',
                'items' => array(
                    array(
                        'title' => '应用入口',
                        'icon' => 'fa fa-circle',
                        'target' => '_blank',
                        'querystring' => array(
                            'c' => 'platform',
                            'a' => 'cover',
                        ),
                    ),
                    array(
                        'title' => '参数设置',
                        'icon' => 'fa fa-circle',
                        'target' => '_blank',
                        'querystring' => array(
                            'c' => 'module',
                            'a' => 'manage-account',
                            'do' => 'setting',
                        ),
                    ),
                    array(
                        'title' => '权限设置',
                        'icon' => 'fa fa-circle',
                        'target' => '_blank',
                        'querystring' => array(
                            'c' => 'module',
                            'a' => 'permission',
                        ),
                    ),
                ),
            ),
        );
        $menu_active = 0;
        foreach ($navs as &$nav) {
            //nav active
            $nav['do'] = $nav['querystring']['do'];
            $nav['act'] = $nav['querystring']['act'];
            $segment = 'site/entry/' . $nav['do'];
            //unset($nav['querystring']['do']);
            $nav['querystring']['m'] = SUPERMAN_MODULE_NAME;
            $nav['url'] = wurl($segment, $nav['querystring']);
            $nav['active'] = '';
            if ($nav['querystring']
                && $nav['querystring']['do'] == $_GPC['do']
                && $nav['querystring']['act'] == $_GPC['act']
            ) {
                $nav['active'] = 'active';
            }
            $nav['max_matching_count'] = 0;
            foreach ($nav['items'] as $k => &$item) {
                if (isset($item['isfounder']) && $item['isfounder']) {
                    if (!$_W['isfounder']) {
                        unset($nav['items'][$k]);
                        continue;
                    }
                }
                $item['querystring']['m'] = SUPERMAN_MODULE_NAME;
                $item['querystring']['version_id'] = intval($_GPC['version_id']);
                $item['url'] = wurl($segment, $item['querystring']);
                $item['active'] = '';
                $query = parse_url($item['url'], PHP_URL_QUERY);
                parse_str($query, $menu_urls);
                ksort($menu_urls);
                $query = parse_url($_W['siteurl'], PHP_URL_QUERY);
                parse_str($query, $querystring);
                ksort($querystring);

                $item['querystring_matching_count'] = count(array_intersect_assoc($querystring, $menu_urls));
                if ($item['querystring_matching_count'] > $nav['max_matching_count']) {
                    $nav['max_matching_count'] = $item['querystring_matching_count'];
                }
                $item['querystring_total'] = count($querystring);
                if ($item['querystring_matching_count'] == count($querystring)
                    && $item['querystring_matching_count'] == count($menu_urls)
                ) {
                    $item['active'] = 'active';
                    $nav['active'] = 'active';
                    $menu_active = 1;
                } else {
                    $item['active'] = '';
                }
            }
        }
        //recheck menu active
        if (!$menu_active) {
            foreach ($navs as &$nav) {
                foreach ($nav['items'] as &$item) {
                    if ($item['querystring_matching_count'] == $nav['max_matching_count']
                        && $item['do'] == $_GPC['do']) {
                        $item['active'] = 'active';
                        $nav['active'] = 'active';
                        break;
                    }
                }
            }
        }
        unset($nav);
        unset($menu);
        unset($item);
        return $navs;
    }
}
