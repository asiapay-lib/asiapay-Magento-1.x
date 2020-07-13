<?php


/**
 * Pdcptb payment model
 *
 */
class Asiapay_Pdcptb_Model_Pdcptb extends Mage_Payment_Model_Method_Abstract
{
    const CGI_URL = 'https://www.paydollar.com/b2c2/eng/payment/payForm.jsp';
    const CGI_URL_TEST = 'https://test.paydollar.com/b2cDemo/eng/payment/payForm.jsp';
    const REQUEST_AMOUNT_EDITABLE = 'N';

    protected $_code  = 'pdcptb';
    protected $_formBlockType = 'asiapay_pdcptb_block_form';
    protected $_allowCurrencyCode = array('HKD','USD','SGD','CNY','JPY','TWD','AUD','EUR','GBP','CAD','MOP','PHP','THB','MYR','IDR','KRW','SAR','NZD','AED','BND');
    
    public function getUrl()
    {
    	$url = $this->getConfigData('cgi_url');
    	
    	if(!$url)
    	{
    		$url = self::CGI_URL_TEST;
    	}
    	
    	return $url;
    }
    
    /**
     * Get session namespace
     *
     */
    public function getSession()
    {
        return Mage::getSingleton('pdcptb/pdcptb_session');
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }
    
	public function getCheckoutFormFields()
	{
		//for Magento v1.3.x series
		/*
		$a = $this->getQuote()->getShippingAddress();
		$b = $this->getQuote()->getBillingAddress();
		$currency_code = $this->getQuote()->getBaseCurrencyCode();
		$cost = $a->getBaseSubtotal() - $a->getBaseDiscountAmount();
		$shipping = $a->getBaseShippingAmount();

		$_shippingTax = $this->getQuote()->getShippingAddress()->getBaseTaxAmount();
		$_billingTax = $this->getQuote()->getBillingAddress()->getBaseTaxAmount();
		$tax = sprintf('%.2f', $_shippingTax + $_billingTax);
		$cost = sprintf('%.2f', $cost + $tax);
		*/
		
		//for Magento v1.5.x
		/*$order = $this->getOrder();*/
		
		//for Magento v1.6.x series
		$order = Mage::getSingleton('sales/order');
		$order->loadByIncrementId($this->getCheckout()->getLastRealOrderId());
        
        $currency_code = $order->getBaseCurrencyCode();
		$cur = $this->getIsoCurrCode($currency_code);
		
		$grandTotalAmount = sprintf('%.2f', $order->getBaseGrandTotal());  
		
		$gatewayLanguage = substr($this->getConfigData('gateway_language'), 0, 1);
		
		if (preg_match("/^[CEXKJcexkj]/", $gatewayLanguage, $matches)){
			$lang = strtoupper($matches[0]);
		}else{
			$lang = 'C';
		}
		$orderReferencePrefix = trim($this->getConfigData('order_reference_no_prefix'));
		
		if (is_null($orderReferencePrefix) || $orderReferencePrefix == ''){
			$orderReferenceValue = $this->getCheckout()->getLastRealOrderId();
		}else{
			$orderReferenceValue = $this->getConfigData('order_reference_no_prefix') . "-" . $this->getCheckout()->getLastRealOrderId();
		}
		
		$merchantId = $this->getConfigData('merchant_id');
		$paymentType = $this->getConfigData('pay_type');
		$secureHashSecret = $this->getConfigData('secure_hash_secret');
		
		/* memberpay start */
		$memberpay_service = $this->getConfigData('memberpay');
		$memberpay_memberid = '';
		$memberPay_email = '';
		if (Mage::app()->isInstalled() && Mage::getSingleton('customer/session')->isLoggedIn()) {            
			$memberpay_memberid = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
			$memberPay_email = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
        }
		/* memberpay end */
		
		$fields = array(
			'merchantId'				=> $merchantId,
			//for Magento v1.3.x series
			//'amount'					=> sprintf('%.2f', $cost + $shipping),
			'amount'					=> $grandTotalAmount, 
			'currCode'					=> $cur,
			'orderRef'					=> $orderReferenceValue,
			'successUrl'				=> Mage::getUrl('pdcptb/pdcptb/success'),
			'cancelUrl'					=> Mage::getUrl('pdcptb/pdcptb/cancel'),
			'failUrl'					=> Mage::getUrl('pdcptb/pdcptb/failure'),
			'lang'						=> $lang,
			'payMethod'					=> 'ALL',
			'payType'					=> $paymentType,
			'secureHash'				=> $this->generatePaymentSecureHash($merchantId, $orderReferenceValue, $cur, $grandTotalAmount, $paymentType, $secureHashSecret),
			'memberPay_service'			=> $memberpay_service,
			'memberPay_memberId'		=> $memberpay_memberid,
			'memberPay_email'			=> $memberPay_email,
			'failRetry'					=> 'no'
				
		);

		// Run through fields and replace any occurrences of & with the word 
		// 'and', as having an ampersand present will conflict with the HTTP
		// request.
		$filtered_fields = array();
        foreach ($fields as $k=>$v) {
            $value = str_replace("&","and",$v);
            $filtered_fields[$k] =  $value;
        }
        
        return $filtered_fields;
	}
	
	public function getIsoCurrCode($magento_currency_code) {
		switch($magento_currency_code){
		case 'HKD':
			$cur = '344';
			break;
		case 'USD':
			$cur = '840';
			break;
		case 'SGD':
			$cur = '702';
			break;
		case 'CNY':
			$cur = '156';
			break;
		case 'JPY':
			$cur = '392';
			break;		
		case 'TWD':
			$cur = '901';
			break;
		case 'AUD':
			$cur = '036';
			break;
		case 'EUR':
			$cur = '978';
			break;
		case 'GBP':
			$cur = '826';
			break;
		case 'CAD':
			$cur = '124';
			break;
		case 'MOP':
			$cur = '446';
			break;
		case 'PHP':
			$cur = '608';
			break;
		case 'THB':
			$cur = '764';
			break;
		case 'MYR':
			$cur = '458';
			break;
		case 'IDR':
			$cur = '360';
			break;
		case 'KRW':
			$cur = '410';
			break;
		case 'SAR':
			$cur = '682';
			break;
		case 'NZD':
			$cur = '554';
			break;
		case 'AED':
			$cur = '784';
			break;
		case 'BND':
			$cur = '096';
			break;
		case 'VND':
			$cur = '704';
			break;
		case 'INR':
			$cur = '356';
			break;
		default:
			$cur = '344';
		}		
		return $cur;
	}
	
	public function generatePaymentSecureHash($merchantId, $merchantReferenceNumber, $currencyCode, $amount, $paymentType, $secureHashSecret) {

		$buffer = $merchantId . '|' . $merchantReferenceNumber . '|' . $currencyCode . '|' . $amount . '|' . $paymentType . '|' . $secureHashSecret;
		//echo $buffer;
		return sha1($buffer);

	}
	
    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('pdcptb/pdcptb_form', $name)
            ->setMethod('pdcptb')
            ->setPayment($this->getPayment())
            ->setTemplate('asiapay/pdcptb/form.phtml');

        return $block;
    }
	
    
    public function validate()
    {
        parent::validate();
        $currency_code = $this->getQuote()->getBaseCurrencyCode();
        if($currency_code == ""){
        }else{
	        if (!in_array($currency_code,$this->_allowCurrencyCode)) {
	            Mage::throwException(Mage::helper('pdcptb')->__('Selected currency code ('.$currency_code.') is not compatabile with PayDollar'));
	        }
        }
        return $this;
    }
	
    public function onOrderValidate(Mage_Sales_Model_Order_Payment $payment)
    {
       return $this;
    }

    public function onInvoiceCreate(Mage_Sales_Model_Invoice_Payment $payment)
    {

    }
	
    public function getOrderPlaceRedirectUrl()
    {
          return Mage::getUrl('pdcptb/pdcptb/redirect');
    }
	
}
