<?php

global $_W;
defined('IN_IA') or exit('Access Denied');
define('SUPERMAN_MODULE_NAME', 'superman_hand2');
if (!defined('MODULE_ROOT')) {
    define('MODULE_ROOT', IA_ROOT.'/addons/'.SUPERMAN_MODULE_NAME);
}
define('MODULE_URL', $_W['siteroot'].'addons/'.SUPERMAN_MODULE_NAME.'/');
if (file_exists(IA_ROOT.'/local.lock')) {
    define('LOCAL_DEVELOPMENT', true);
} else if (file_exists(IA_ROOT.'/online-dev.lock')) {
    define('ONLINE_DEVELOPMENT', true);
}
if (defined('LOCAL_DEVELOPMENT') || defined('ONLINE_DEVELOPMENT')) {
    define('SUPERMAN_DEVELOPMENT', true);
}
if (file_exists(IA_ROOT.'/superman_hand2_package.lock')) {
    define('LOCAL_PACKAGE', true);
}
define('SUPERMAN_FONT_MSYH', MODULE_ROOT.'/data/font/msyh.ttf');
//regular
define('SUPERMAN_REGULAR_EMAIL', '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/i');
define('SUPERMAN_REGULAR_MOBILE', '/^[1](([3][0-9])|([4][5-9])|([5][0-3,5-9])|([6][5,6])|([7][0-8])|([8][0-9])|([9][1,8,9]))[0-9]{8}$/');
define('SUPERMAN_REGULAR_USERNAME', '/^[a-z\d_]{4,16}$/i');
define('SUPERMAN_REGULAR_PASSWORD', '/^\w{6,16}$/i');
define('SUPERMAN_SITE_AUTHORIZE', 'site_authorize');
load()->func('tpl');
load()->func('file');
load()->func('communication');
load()->model('mc');
load()->model('module');

spl_autoload_register(function($class) {
    $class = str_replace('SupermanHand', '', $class);
    $class = str_replace('_', '/', $class);
    $class = strtolower($class);
    $file = MODULE_ROOT.'/class/'.$class.'.class.php';
    if (file_exists($file)) {
        require $file;
    }
});