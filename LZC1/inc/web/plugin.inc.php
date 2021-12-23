<?php

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$do = $_GPC['do'];
$act = in_array($_GPC['act'], array('display'))?$_GPC['act']:'display';
$title = '发布管理';
if ($act == 'display') {
    if ($this->plugin_module) {
        foreach ($this->plugin_module as $plugin) {
            if ($plugin['module'] && !$plugin['module']['is_delete']) {
                $plugin_module[] = array(
                    'title' => $plugin['module']['title'],
                    'name' => $plugin['module']['name'],
                );
            }
        }
    }
}
include $this->template($this->web_template_path);
