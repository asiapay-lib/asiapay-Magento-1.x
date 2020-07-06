<?php


class Asiapay_Pdcptb_Block_Redirect extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $pdcptb = Mage::getModel('pdcptb/pdcptb');

        $form = new Varien_Data_Form();
        $form->setAction($pdcptb->getUrl())
            ->setId('pdcptb_checkout')
            ->setName('pdcptb_checkout')
            ->setMethod('post')
            ->setUseContainer(true);
        foreach ($pdcptb->getCheckoutFormFields() as $field=>$value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }
        $html = '<html><body>';
        $html.= $this->__('You will be redirected to the payment gateway in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("pdcptb_checkout").submit();</script>';
        $html.= '</body></html>';

        return $html;
    }
}
