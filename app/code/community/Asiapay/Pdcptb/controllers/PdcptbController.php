<?php


/**
 * Pdcptb Checkout Controller
 *
 */
class Asiapay_Pdcptb_PdcptbController extends Mage_Core_Controller_Front_Action
{
	const PARAM_NAME_REJECT_URL = 'reject_url';
	
    protected function _expireAjax()
    {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    /**
     * When a customer chooses Pdcptb on Checkout/Payment page
     */
    public function redirectAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setPdcptbQuoteId($session->getQuoteId());
        $this->getResponse()->setBody($this->getLayout()->createBlock('pdcptb/redirect')->toHtml());
        $session->unsQuoteId();
    }

    /**
     * When a customer cancels payment from Pdcptb.
     */
    public function cancelAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getPdcptbQuoteId(true));
        
    	// cancel order
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();                
                $comment = "You have canceled your order" ;
				$order->sendOrderUpdateEmail(true, $comment);	//for sending order email update to customer                
            }
        }
        $this->_redirect('checkout/cart');
     }

    /**
     * Where Pdcptb returns.
     * Pdcptb currently always returns the same code so there is little point
     * in attempting to process it.
     */
    public function successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getPdcptbQuoteId(true));
        
        // Set the quote as inactive after returning from Pdcptb
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
		
        $order = Mage::getModel('sales/order');
        $order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
    	
        //Either datafeed or this successAction will set the state from Pending to Processing
        //$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);	
	    //$order->save();
        
	    // Send a confirmation email to customer
        //if($order->getId()){
            //$order->sendNewOrderEmail();
        //}

        Mage::getSingleton('checkout/session')->unsQuoteId();
		
    	
        $this->_redirect('checkout/onepage/success');
    }
    
    public function failureAction()
    {
    	$session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getPdcptbQuoteId(true));
        
    	// cancel order
        //if ($session->getLastRealOrderId()) {
            //$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            //if ($order->getId()) {
                //$order->cancel()->save();
            //}
        //}
        $this->_redirect('checkout/onepage/failure');
    }

}
