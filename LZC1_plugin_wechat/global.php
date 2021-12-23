<?php

global $_W;
defined('IN_IA') or exit('Access Denied');
define('SUPERMAN_MODULE_NAME', 'superman_hand2_plugin_wechat');
if (!defined('MODULE_ROOT')) {
    define('MODULE_ROOT', IA_ROOT.'/addons/'.SUPERMAN_MODULE_NAME.'/');
}
if (file_exists(IA_ROOT.'/local.lock')) {
    define('LOCAL_DEVELOPMENT', true);
} else if (file_exists(IA_ROOT.'/online-dev.lock')) {
    define('ONLINE_DEVELOPMENT', true);
}
if (defined('LOCAL_DEVELOPMENT') || defined('ONLINE_DEVELOPMENT')) {
    define('SUPERMAN_DEVELOPMENT', true);
}
define('MODULE_URL', $_W['siteroot'].'addons/'.SUPERMAN_MODULE_NAME.'/');
define('ATTACHMENT_ROOT', IA_ROOT .'./attachment/');
define('SUPERMAN_SKEY_BANNER_SLIDE', 'banner_slide');

load()->func('tpl');
load()->func('file');
load()->func('communication');
load()->model('mc');
load()->model('module');
load()->model('account');

spl_autoload_register(function($class) {
    $class = str_replace('SupermanHand2PluginWechat', '', $class);
    $class = str_replace('_', '/', $class);
    $class = strtolower($class);
    $file = IA_ROOT.'/addons/superman_hand2_plugin_wechat/class/'.$class.'.class.php';
    if (file_exists($file)) {
        require $file;
    }
});

SupermanHand2PluginWechatUtil::weiqing_polyfill();