<?php

defined('IN_IA') or exit('Access Denied');
require IA_ROOT . '/addons/superman_hand2_plugin_wechat/global.php';
class Superman_hand2_plugin_wechatModule extends WeModule
{
    public $module;
    private $_data = array();
    public function settingsDisplay($settings)
    {
        global $_W, $_GPC;
        ob_end_clean();
        $url = murl('module/manage-account/setting', array("m" => "superman_hand2", "version_id" => $_GPC['version_id']));
        $url .= '#setting_getcash';
        @header('Location: ' . $url);
        exit;
        $this->module = $this->uni_modules_fetch('superman_hand2');
        include $this->template('web/setting');
    }
    public function fieldsFormDisplay($rid = 0)
    {
    }
    public function fieldsFormValidate($rid = 0)
    {
    }
    public function fieldsFormSubmit($rid)
    {
    }
    public function ruleDeleted($rid)
    {
    }
    public function welcomeDisplay()
    {
        $url = $this->createWebUrl('finance');
        SupermanHand2PluginWechatUtil::redirect($url);
    }
    private function _setting_getcash()
    {
        global $_GPC;
        $this->_data['getcash'] = $_GPC['getcash'];
    }
    private function uni_modules_fetch($name)
    {
        load()->model('account');
        $modules = uni_modules(false);
        return $modules[$name];
    }
}