<?php

defined('IN_IA') or exit('Access Denied');
require IA_ROOT . '/addons/superman_hand2_plugin_notice/global.php';
class superman_hand2_plugin_noticeModule extends WeModule
{
    public $module;
    private $_data = array();
    public function settingsDisplay($settings)
    {
        global $_W, $_GPC;
        if (!empty($_GPC['load_field'])) {
            include $this->template('web/distance');
            exit;
        }
        if (checksubmit('submit')) {
            $this->_setting_base();
            $this->_setting_tmpl();
            $this->_setting_distance();
            $this->saveSettings($this->_data);
            itoast('更新成功！', referer(), 'success');
        }
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
        $url = $this->createWebUrl('ask_item');
        SupermanHand2PluginNoticeUtil::redirect($url);
    }
    private function _setting_base()
    {
        global $_GPC;
        $this->_data['base'] = $_GPC['base'];
    }
    private function _setting_tmpl()
    {
        global $_GPC;
        $this->_data['tmpl'] = $_GPC['tmpl'];
    }
    private function _setting_distance()
    {
        global $_GPC;
        $this->_data['distance'] = $_GPC['distance'];
    }
}