<?php


class Asiapay_Pdcptb_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $this->setTemplate('asiapay/pdcptb/form.phtml');
        parent::_construct();
    }
}
