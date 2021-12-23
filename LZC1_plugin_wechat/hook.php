<?php

defined('IN_IA') or exit('Access Denied');

class Superman_hand2_plugin_wechatModuleHook extends WeModuleHook {
    public function hookWebNav($hook){
        global $_W, $_GPC;
        // 将调用 template/test.html
        include $this->template('nav');
    }
    //mobile端
    public function hookMobileTest($hook) {
        global $_W,$_GPC;
        // 将调用 template/mobile/test.html
    }
}

