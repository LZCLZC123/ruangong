<?php

defined('IN_IA') or exit('Access Denied');
require IA_ROOT . '/addons/superman_hand2_plugin_ad/global.php';
class Superman_hand2_plugin_adModuleSite extends WeModuleSite
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