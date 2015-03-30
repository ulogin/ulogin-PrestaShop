<?php

class uloginProfileModuleFrontController extends ModuleFrontController
{
    public $errors = array();
    public $ssl = true;
    public $display_column_left = false;
    public $auth = true;

    public function initContent()
    {
        parent::initContent();
        $this->setTemplate('profile.tpl');
        if (Tools::getValue('err')) {
            $this->context->smarty->assign('ulogin_message', Tools::getValue('err'));
        }
    }
}