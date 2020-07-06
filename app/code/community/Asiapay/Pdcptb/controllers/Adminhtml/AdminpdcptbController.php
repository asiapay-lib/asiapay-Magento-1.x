<?php
/**
 * Adminpdcptb Controller
 *
 */

class Asiapay_Pdcptb_Adminhtml_AdminpdcptbController extends Mage_Adminhtml_Controller_Action
{
	
public function __construct(Zend_Controller_Request_Abstract $request, 
  Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
    {
     
     //print_r($this->getLayout());
        $this->_request = $request;
        $this->_response= $response;
        
        //$this->loadLayout();
        //$this->loadLayout(null,false,false); 
        $arrayUrl = explode("/", $this->getRequest()->getRequestUri());
        $key = array_search('adminhtml_adminpdcptb', $arrayUrl);
        $key2 = array_search('order_id', $arrayUrl);
        $action = $arrayUrl[$key+1];
        $orderid = $arrayUrl[$key2+1];
        //$this->indexAction();
        switch ($action){
         case "cancel" :
          //echo "cancel";
          $this->cancelAction($orderid);
          break;
         case "update" : 
          //echo "update";
          $this->updateAction($orderid);
          break;
   case "query" : 
          //echo "update";
          $this->queryAction($orderid);
          break;
         default :
          //echo "query".$orderid;
          $this->indexAction();
          break;
          
        }
  $this->loadLayout();
  //var_dump($this->getLayout());
  //print_r($this->renderLayout()->getAllBlocks());
        //$this->loadLayout(null,false,false)->renderLayout(null,false,false);
        
    }

    public function indexAction(){
    	
    }
	public function updateAction($orderid)
	{
		//retrieve order details
		$order_id = $orderid;//$this->getRequest()->getParam('order_id');
		$order_object = Mage::getSingleton('sales/order');
		$order_object->load($order_id);
		$increment_id = $order_object->getIncrementId()	;
		$store_id = $order_object->getData('store_id');
		$payment_method = $order_object->getPayment()->getMethodInstance();
		
		//retrieve plugin parameter values
		$merchant_id = $payment_method->getConfigData('merchant_id',$store_id);
		$api_url = $payment_method->getConfigData('api_url',$store_id);
		$api_username = $payment_method->getConfigData('api_username',$store_id);
		$api_password = $payment_method->getConfigData('api_password',$store_id);
		$order_reference_no_prefix = $payment_method->getConfigData('order_reference_no_prefix',$store_id);
		
		//order prefix handler
		$order_ref = $increment_id;
		if($order_reference_no_prefix != '') $order_ref = $order_reference_no_prefix . '-' . $increment_id;
		
		//validate
		$error_msg = '';
		if($merchant_id == '')	$error_msg .= '- Merchant Id is not set. <br/>';
		if($api_url == '')		$error_msg .= '- API URL is not set. <br/>';
		if($api_username == '')	$error_msg .= '- API Username is not set. <br/>';
		if($api_password == '')	$error_msg .= '- API Password is not set. <br/>';
		
		if($error_msg != ''){
			//display module parameter errors
			echo '<b>MODULE SETUP ERROR:</b><br/>' . $error_msg ;
			echo '<br/>';
		}else{
			//call the query api
			$postUrl = $api_url;
			$postData = 'merchantId=' . $merchant_id . '&loginId=' . $api_username . '&password=' . $api_password . '&orderRef=' . $order_ref . '&actionType=Query';
			$response = $this->_httpPost($postUrl, $postData);
				
			if($response == ''){
				//display error
				echo 'QUERY ORDER REF: <b>' . $order_ref . '</b><br/>';
				echo 'QUERY URL: ' . $postUrl . '<br/>';
				echo 'QUERY DATA: ' . $postData . '<br/>';
				echo 'QUERY RESPONSE: No response recieved.<br/><br/>';
			}else{
				if(strpos($response,'resultCode') === 0){
					//display api error response
					parse_str($response,$responseArray);
					echo 'QUERY ORDER REF: <b>' . $order_ref . '</b><br/>';
					echo 'QUERY URL: ' . $postUrl . '<br/>';
					echo 'QUERY DATA: ' . $postData . '<br/>';
					echo 'QUERY RESPONSE: ' . $responseArray['errMsg'] . '<br/><br/>';
				}else{
					//display api response
					$xmlObj = simplexml_load_string($response);
					$recordsFound = count($xmlObj->children());
					
					$hasAtleastOneApproved = false;
					$payRef = '';
						
					if($recordsFound > 0){
						foreach($xmlObj->children() as $record) {
							if($record->orderStatus == 'Accepted' || $record->orderStatus == 'Authorized'){
								$hasAtleastOneApproved = true;
								if($pay_ref == ''){
									$payRef = $record->payRef;
								}
							}
						}
					}
					
					if($hasAtleastOneApproved){
						$error;
						try {	
							//update order status to processing
							$comment = "Payment was Accepted. Payment Ref: " . $payRef ;
							$order_object->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $comment, 1)->save(); 		
							$order_object->sendNewOrderEmail();
							$order_object->sendOrderUpdateEmail(true, $comment);	//for sending order email update to customer
						
							//add payment record for the order
							$payment = Mage::getSingleton('sales/order_payment')
									->setMethod('ppcptb')
									->setTransactionId($payRef)
									->setIsTransactionClosed(true);
				        	$order_object->setPayment($payment);
							$payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT);
							$order_object->save();
							
							//create invoice
							//Instantiate Mage_Sales_Model_Service_Order class and prepare the invoice
							$invoice_object = Mage::getSingleton('sales/service_order', $order_object)->prepareInvoice();
					 
							if (!$invoice_object->getTotalQty()) {
								Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
							}
					 					
							$invoice_object->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
							$invoice_object->register();
							
							$invoice_object->setEmailSent(true);
							$invoice_object->getOrder()->setIsInProcess(true);
										
							//Instantiate Mage_Core_Model_Resource_Transaction and perform a transaction
							$transaction_object = Mage::getSingleton('core/resource_transaction')
									->addObject($invoice_object)->addObject($invoice_object->getOrder());	 
							$transaction_object->save();
										
							$invoice_object->sendEmail(true, $comment);///
							
						}
						catch (Mage_Core_Exception $e) {
							$error = $e;
							//print_r($e);
							Mage::log($error);
							Mage::logException($e);
						}
						
						if (!$error){
							echo 'Order State Has Been Updated To Processing. <br/><br/>';
						}
					}
		
				}
			}
		}
			
		echo '<a href="' . Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/view/', array('order_id'=>$order_object->getId())) . '">[ Go Back To Order Page ]</a>';
		exit();
	}
	
	public function cancelAction($orderid)
	{
		//retrieve order details
		$order_id = $orderid;//$this->getRequest()->getParam('order_id');
		$order_object = Mage::getSingleton('sales/order');
		$order_object->load($order_id);
		$increment_id = $order_object->getIncrementId()	;
		$store_id = $order_object->getData('store_id');
		$payment_method = $order_object->getPayment()->getMethodInstance();
		
		$order_object->cancel()->save();
		$comment = "Your Order Has Been Canceled" ;
		$order_object->sendOrderUpdateEmail(true, $comment);	//for sending order email update to customer
		
		echo 'Order Has Been Cancelled. <br/><br/>';
			
		echo '<a href="' . Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/view/', array('order_id'=>$order_object->getId())) . '">[ Go Back To Order Page ]</a>';
	exit();
	}
	
    public function queryAction($orderid)
    {
    	//retrieve order details
    try {
    	Mage::app()->getRequest()->getControllerModule();
    	//$this->loadLayout();
    	//print_r($this->getRequest()->getParams());
    	$order_id = $orderid;//$this->getRequest()->getParam('order_id');
    	//echo "order id = " . $order_id;
    	
    	$order_object = Mage::getModel('sales/order');
    	$order_object->load($order_id);
    	$increment_id = $order_object->getIncrementId()	;
    	$store_id = $order_object->getData('store_id');
    	$payment_method = $order_object->getPayment()->getMethodInstance();
    	
    	//retrieve plugin parameter values
    	$merchant_id = $payment_method->getConfigData('merchant_id',$store_id);
    	$api_url = $payment_method->getConfigData('api_url',$store_id);
    	$api_username = $payment_method->getConfigData('api_username',$store_id);
    	$api_password = $payment_method->getConfigData('api_password',$store_id);
    	$order_reference_no_prefix = $payment_method->getConfigData('order_reference_no_prefix',$store_id);
    	
    	//order prefix handler
    	$order_ref = $increment_id;
    	if($order_reference_no_prefix != '') $order_ref = $order_reference_no_prefix . '-' . $increment_id;
    	
    	//validate
    	$error_msg = '';
    	if($merchant_id == '')	$error_msg .= '- Merchant Id is not set. <br/>';
    	if($api_url == '')		$error_msg .= '- API URL is not set. <br/>';
    	if($api_username == '')	$error_msg .= '- API Username is not set. <br/>';
    	if($api_password == '')	$error_msg .= '- API Password is not set. <br/>';
    	
    	if($error_msg != ''){
    		//display module parameter errors
    		echo '<b>MODULE SETUP ERROR:</b><br/>' . $error_msg ;
    		echo '<br/>';
    	}else{
    		//call the query api
    		$postUrl = $api_url;
    		$postData = 'merchantId=' . $merchant_id . '&loginId=' . $api_username . '&password=' . $api_password . '&orderRef=' . $order_ref . '&actionType=Query';
    		$response = $this->_httpPost($postUrl, $postData);
    			
    		if($response == ''){
    			//display error
    			echo 'QUERY ORDER REF: <b>' . $order_ref . '</b><br/>';
    			echo 'QUERY URL: ' . $postUrl . '<br/>';
    			echo 'QUERY DATA: ' . $postData . '<br/>';
    			echo 'QUERY RESPONSE: No response recieved.<br/><br/>';
    		}else{
    			if(strpos($response,'resultCode') === 0){
    				//display api error response
    				parse_str($response,$responseArray);
    				echo 'QUERY ORDER REF: <b>' . $order_ref . '</b><br/>';
    				echo 'QUERY URL: ' . $postUrl . '<br/>';
    				echo 'QUERY DATA: ' . $postData . '<br/>';
    				echo 'QUERY RESPONSE: ' . $responseArray['errMsg'] . '<br/><br/>';
    			}else{
    				//display api response
    				$xmlObj = simplexml_load_string($response);
    				$recordsFound = count($xmlObj->children());
    				echo 'QUERY ORDER REF: <b>' . $order_ref . '</b><br/>';
    				echo 'QUERY RESPONSE: ' . $recordsFound . ' Record(s) Found.<br/>';
    	
    				$hasAtleastOneApproved = false;
    					
    				if($recordsFound > 0){
    					echo '<br/><table border="1"><thead>
								<tr>
									<th>txTime</th>
									<th>ref</th>
									<th>payRef</th>
									<th>orderStatus</th>
								</tr>
							</thead><tbody>';
    					foreach($xmlObj->children() as $record) {
    						if($record->orderStatus == 'Accepted' || $record->orderStatus == 'Authorized'){
    							$hasAtleastOneApproved = true;
    						}
    						echo '<tr>
									<td>' . $record->txTime . '</td>
									<td>' . $record->ref . '</td>
									<td>' . $record->payRef . '</td>
									<td><b>' . $record->orderStatus . '</b></td>';
    					}
    					echo '</tbody></table>';
    				}
    				echo '<br/>Does order ref <b>"' . $order_ref . '"</b> have any Accepted/Authorized payment? <b>' . ($hasAtleastOneApproved?'Yes':'None') . '</b><br/>';
    					
    				//ask admin for action
    				if($hasAtleastOneApproved){
    					echo '<br/>Are you sure you want to <b>update the state of the order to Processing</b>? <br/><br/> <a href="' . Mage::helper('adminhtml')->getUrl('pdcptb/adminhtml_adminpdcptb/update/',array('order_id'=>$order_object->getId())) . '">[ Update State ]</a> OR ';
    				}else{
    					echo '<br/>Are you sure you want to <b>cancel the order</b>? <br/><br/> <a href="' . Mage::helper('adminhtml')->getUrl('pdcptb/adminhtml_adminpdcptb/cancel/',array('order_id'=>$order_object->getId())) . '">[ Cancel Order ]</a> OR ';
    				}
    	
    			}
    		}
    	}
    		
    	echo '<a href="' . Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/view/', array('order_id'=>$order_object->getId())) . '">[ Go Back To Order Page ]</a>';
    	exit();
    } catch (Exception $e) {
    	Mage::log($e);
    }
    }
    
    protected function _httpPost($postUrl, $postData , $isRequestHeader=false)
    {    
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $postUrl);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
	    curl_setopt($ch, CURLOPT_HEADER, (($isRequestHeader) ? 1 : 0));
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    $response = curl_exec($ch);	
	    curl_close($ch);
	    return $response;
	}
}

