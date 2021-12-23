<?php

global $_W;
defined('IN_IA') or exit('Access Denied');
define('SUPERMAN_MODULE_NAME', 'superman_hand2_plugin_notice');
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

load()->func('tpl');
load()->func('file');
load()->func('communication');
load()->model('mc');
load()->model('module');
load()->model('account');

spl_autoload_register(function($class) {
    $class = str_replace('SupermanHand2PluginNotice', '', $class);
    $class = str_replace('_', '/', $class);
    $class = strtolower($class);
    $file = MODULE_ROOT.'class/'.$class.'.class.php';
    if (file_exists($file)) {
        require $file;
    }
});

//SupermanHand2PluginAdUtil::weiqing_polyfill();