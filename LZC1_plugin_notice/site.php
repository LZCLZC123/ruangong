<?php

defined('IN_IA') or exit('Access Denied');
require IA_ROOT . '/addons/superman_hand2_plugin_notice/global.php';
class superman_hand2_plugin_noticeModuleSite extends WeModuleSite
{
    public $module;
    public function __construct()
    {
        global $_W, $_GPC, $do;
        $modules = uni_modules(false);
        $this->module = $modules['superman_hand2'];
        if (defined('IN_SYS')) {
            $this->init_web();
        }
    }
    private function init_web()
    {
    }
}