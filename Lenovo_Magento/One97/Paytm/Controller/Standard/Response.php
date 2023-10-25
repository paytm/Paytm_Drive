<?php
	namespace One97\Paytm\Controller\Standard;
	use Magento\Framework\App\CsrfAwareActionInterface;
	use Magento\Framework\App\Request\InvalidRequestException;
	use Magento\Framework\App\RequestInterface;

	class Response extends \One97\Paytm\Controller\Paytm  implements CsrfAwareActionInterface{

		public function createCsrfValidationException(
		    RequestInterface $request
		): ?InvalidRequestException {
		    return null;
		}

		public function validateForCsrf(RequestInterface $request): ?bool {
		    return true;
		}
	    
		/* this funtion handle Paytm response */
	    public function execute() {
	    	$comment = "";
	        $request = $_POST;
			if(!empty($_POST)){
				$comment .= "<b>Transaction callback response: </b><br/>";
				foreach($_POST as $key => $val){
					if($key != "CHECKSUMHASH"){
						$comment .= $key  . "=" . $val . ", \n <br />";
					}
				}
			}

			// var_dump($this->getPaytmHelper()::SAVE_PAYTM_RESPONSE);die;
			$errorMsg = '';
			$successFlag = false;
			$resMessage=$this->getRequest()->getParam('RESPMSG');
			$globalErrMass="Your payment has been failed!";
			if(trim($resMessage)!=''){
				$globalErrMass.=" Reason: ".$resMessage;
			}
			$returnUrl = $this->getPaytmHelper()->getUrl('/');
		    $orderMID = $this->getRequest()->getParam('MID');        
	        $paytmOrderId = $this->getRequest()->getParam('ORDERID');
	        $magentoOrderIdArr=explode('_', $paytmOrderId);
	        $magentoOrderId=$magentoOrderIdArr[0];
	        $orderTXNID = $this->getRequest()->getParam('TXNID');
	        $paytmResponse=$this->getRequest()->getParams();
	        $paytmJsonResponse=json_encode($paytmResponse);
	        
	        if(trim($paytmOrderId)!='' && trim($orderMID)!=''){
		        $order = $this->getOrderById($magentoOrderId);
		        $orderTotal = round($order->getGrandTotal(), 2);
		        $orderStatus = $this->getRequest()->getParam('STATUS');
				$resCode = $this->getRequest()->getParam('RESPCODE');
		        $orderTxnAmount = $this->getRequest()->getParam('TXNAMOUNT');
				$transactionResponse="";
				$paytmJsonResponseOnPending='';

				// set discount if any
				if($orderTotal != $orderTxnAmount){
					$discount = $orderTotal-$orderTxnAmount;
					// //$order->setTotalPaid($orderTxnAmount);
					$order->setGrandTotal($orderTxnAmount);
					// //$order->setTotalDue(0);
					 $order->setDiscountAmount($orderTotal-$orderTxnAmount);
				}

			    if($this->getPaytmModel()->validateResponse($request, $magentoOrderId)) {
			        if($this->getPaytmHelper()::SAVE_PAYTM_RESPONSE){
				        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
				        $objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
				        $date = $objDate->gmtDate();
				        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
				        $tableName = $resource->getTableName('paytm_order_data');
				        $sql = "UPDATE ".$tableName." SET transaction_id='".$orderTXNID."', paytm_response='".$paytmJsonResponse."', date_modified='".$date."' WHERE order_id='".$magentoOrderId."' AND paytm_order_id='".$paytmOrderId."'";
				        $this->updateTable($sql);
				    }
				    $flowType = $this->getPaytmModel()->getFlowType();
				    $environment = $this->getPaytmModel()->getEnvironment();

					if($orderStatus == "TXN_SUCCESS"){	
						if($flowType ==1){ // for pre-auth
							$requestParamList['body']['orderId'] = $paytmOrderId;
							$requestParamList['body']['mid'] = $orderMID; 
							$requestParamList['body']['txnType'] = 'PREAUTH'; 

							$StatusCheckSum  =  $this->getPaytmModel()->generateStatusChecksum(json_encode($requestParamList['body'], JSON_UNESCAPED_SLASHES));

							$requestParamList['head'] = array(
								"signature" => $StatusCheckSum,
								"clientId" => "C11"
							);
							$post_fields = json_encode($requestParamList, JSON_UNESCAPED_SLASHES);
							if($environment ==1){
								$url="https://securegw.paytm.in/v4/order/status";
							}else{
								$url="https://securegw-stage.paytm.in/v4/order/status";
							}
							$ch = curl_init($url);
							curl_setopt($ch, CURLOPT_POST, 1);
							curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
							curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
							curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
							curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 

							$res = curl_exec($ch);
							$responseParamList = json_decode($res,true);
							if(isset($responseParamList['body']['resultInfo']['resultStatus'])){
								if($responseParamList['body']['resultInfo']['resultStatus'] == "PENDING"){
									$transactionResponse="PENDING";
								}else{
									if($responseParamList['body']['resultInfo']['resultStatus']=='TXN_SUCCESS' && $responseParamList['body']['blockedAmount']==$orderTxnAmount) {
										$transactionResponse="PROCESSING";
										
									} else{
										$transactionResponse="CANCELED";
									}
								}
							}else{
								$transactionResponse="FRAUD";
							}	
						}else{	
							$requestParamList = array("MID" => $orderMID , "ORDERID" => $paytmOrderId);
							$responseParamList=$this->checkStatusCall($requestParamList);
								
							//$responseParamList=$_POST;
							if(isset($responseParamList['STATUS'])){
								if($responseParamList['STATUS'] == "PENDING"){
									$transactionResponse="PENDING";
								}else{
									if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$orderTxnAmount) {
										$transactionResponse="PROCESSING";
									} else{
										$transactionResponse="CANCELED";
									}
								}
							}else{
								$transactionResponse="FRAUD";
							}
						}
					}else{
						if($orderStatus == "PENDING"){
							if($flowType ==1){ // for pre-auth
								$requestParamList['body']['orderId'] = $paytmOrderId;
								$requestParamList['body']['mid'] = $orderMID; 
								$requestParamList['body']['txnType'] = 'PREAUTH'; 

								$StatusCheckSum  =  $this->getPaytmModel()->generateStatusChecksum(json_encode($requestParamList['body'], JSON_UNESCAPED_SLASHES));

								$requestParamList['head'] = array(
									"signature" => $StatusCheckSum,
									"clientId" => "C11"
								);
								$post_fields = json_encode($requestParamList, JSON_UNESCAPED_SLASHES);
								if($environment ==1){
									$url="https://securegw.paytm.in/v4/order/status";
								}else{
									$url="https://securegw-stage.paytm.in/v4/order/status";
								}
								$ch = curl_init($url);
								curl_setopt($ch, CURLOPT_POST, 1);
								curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
								curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
								curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
								curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 

								$res = curl_exec($ch);
								$responseParamList = json_decode($res,true);
								if(isset($responseParamList['body']['resultInfo']['resultStatus'])){
									if($responseParamList['body']['resultInfo']['resultStatus'] == "PENDING"){
										$transactionResponse="PENDING";
									}else{
										if($responseParamList['body']['resultInfo']['resultStatus']=='TXN_SUCCESS' && $responseParamList['body']['blockedAmount']==$orderTxnAmount) {
											$transactionResponse="PROCESSING";
											
										} else{
											$transactionResponse="CANCELED";
										}
									}
								}else{
									$transactionResponse="FRAUD";
								}	
							}else{
								$requestParamList = array("MID" => $orderMID , "ORDERID" => $paytmOrderId);
								$responseParamList=$this->checkStatusCall($requestParamList);
								if(isset($responseParamList['STATUS'])){
									if($responseParamList['STATUS'] == "PENDING"){
										$transactionResponse="PENDING";
									}else{
										if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$orderTxnAmount) {
											$transactionResponse="PROCESSING";
											$paytmJsonResponseOnPending=json_encode($responseParamList);
										} else{
											$transactionResponse="CANCELED";
										}
									}
								}else{
									$transactionResponse="FRAUD";
								}
							}
						}else{
							$transactionResponse="CANCELED";
						}
					}            
		        } else {
					$transactionResponse="FRAUD_CHECKSUM_MISMATCH";
		        }

		        switch ($transactionResponse) {
		        	case 'FRAUD':
		        		$errorMsg = 'It seems some issue in server to server communication. Kindly connect with administrator.';
		        		$comment .=  "Fraud Detucted";
		        		$order->setStatus($order::STATUS_FRAUD);
		        		$returnUrl = $this->getPaytmHelper()->getUrl('checkout/cart');
		        		break;
		        	case "FRAUD_CHECKSUM_MISMATCH":
    					$errorMsg = $globalErrMass." Reason: Checksum Mismatch.";
    					$comment .=  "Fraud Detected";
    		            $order->setState("canceled")->setStatus($order::STATUS_FRAUD);
    					$returnUrl = $this->getPaytmHelper()->getUrl('checkout/cart');
		        		break;
		        	case "CANCELED":
		        		if($orderStatus == "PENDING"){
		        			$errorMsg = 'Paytm Transaction Pending!';
		        			if(trim($resMessage)==''){
		        				$errorMsg.=" Reason: ".$resMessage;
		        			}
		        			$comment .=  "Pending";
		        			$order->setState("pending_payment")->setStatus("pending_payment");
		        		}else{
		        			$this->getPaytmHelper()->updateStockQty($order);
		        			$errorMsg = $globalErrMass;
		        			$comment .=  $globalErrMass;
		        			$order->setState("canceled")->setStatus($this->_paytmModel->getFailOrderStatus());
		        		}
		        		$returnUrl = $this->getPaytmHelper()->getUrl('checkout/cart');
		        		break;
		        	case "PROCESSING":

		        		$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
		        		$tableName = $resource->getTableName('paytm_order_data');
		        		$paytmJsonResponseOnPending==''?$sql="UPDATE ".$tableName." SET status='1' WHERE order_id='".$magentoOrderId."' AND paytm_order_id='".$paytmOrderId."'":$sql = "UPDATE ".$tableName." SET status='1', paytm_response='".$paytmJsonResponseOnPending."' WHERE order_id='".$magentoOrderId."' AND paytm_order_id='".$paytmOrderId."'";
		        		if($this->getPaytmHelper()::SAVE_PAYTM_RESPONSE){
		        			$this->updateTable($sql);
		        		}
	        			$autoInvoice =  $this->getPaytmModel()->autoInvoiceGen();
		        		if($autoInvoice=='authorize_capture'){
		        			$txnAmount = isset($responseParamList['TXNAMOUNT'])?$responseParamList['TXNAMOUNT']:$responseParamList['body']['blockedAmount'];
		        			$payment = $order->getPayment();
		        			$payment->setTransactionId($orderTXNID)       
		        			->setPreparedMessage(__('Paytm transaction has been successful.'))
		        			->setShouldCloseParentTransaction(true)
		        			->setIsTransactionClosed(0)
		        			->setAdditionalInformation(['One97','paytm'])	
		        			->registerCaptureNotification(
		        				$txnAmount,
		        				true 
		        			);
		        			$invoice = $payment->getCreatedInvoice();
		        		}
		        		$successFlag = true;
		        		$comment .=  "Success ";
		        		// for pre auth only
		        		if(isset($responseParamList['body']['blockedAmount'])){
		        			$comment .=  "<br/><b>Pre Auth Details: </b>";
		        			$comment .=  "<br/>Status= ".$responseParamList['body']['resultInfo']['resultStatus'];
		        			$comment .=  "<br/>Txn Date= ".$responseParamList['body']['txnDate'];
		        			$comment .=  "<br/>Blocked Amount= ".$responseParamList['body']['blockedAmount'];
		        			$comment .=  "<br/>preAuthId= ".$responseParamList['body']['preAuthId'];
		        			$comment .=  "<br/>cardPreAuthType= ".$responseParamList['body']['cardPreAuthType'];
		        		}

		        		$order->setState('processing');
		        		$order->setStatus($this->_paytmModel->getSuccessOrderStatus());
		        		$order->setExtOrderId($paytmOrderId);
		        		$this->clearCart();
		        		$returnUrl = $this->getPaytmHelper()->getUrl('checkout/onepage/success');
		        		break;
		        	case "PENDING":
		        		$errorMsg = 'Paytm Transaction Pending!';
		        		if(trim($resMessage)==''){
		        			$errorMsg.=" Reason: ".$resMessage;
		        		}
		        		$comment .=  "Pending";
		        		$order->setState("pending_payment")->setStatus("pending_payment");
		        		$this->clearCart();
		        		$returnUrl = $this->getPaytmHelper()->getUrl('checkout/onepage/failure');
		        		break;

		        	default:
		        		$errorMsg = 'Paytm Transaction Pending!';
		        		if(trim($resMessage)==''){
		        			$errorMsg.=" Reason: ".$resMessage;
		        		}
		        		$comment .=  "Pending" ;
		        		$order->setState("pending_payment")->setStatus("pending_payment");
		        		$returnUrl = $this->getPaytmHelper()->getUrl('checkout/cart');
		        		break;
		        }
				$order->addStatusToHistory($order->getStatus(), $comment);
		        $order->save();
				if($successFlag){
					$this->messageManager->addSuccess( __('Paytm transaction has been successful.') );
				}else{
					$this->messageManager->addError( __($errorMsg) );
				}
	        }
			$this->getResponse()->setRedirect($returnUrl);        
		}

		/* Paytm transaction status S2S call */
		public function checkStatusCall($requestParamList){
			$response=array();
			if(!empty($requestParamList)){
				$StatusCheckSum  =  $this->getPaytmModel()->generateStatusChecksum($requestParamList);
				$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
				$check_status_url = $this->getPaytmModel()->getNewStatusQueryUrl(); 
				$responseParamList=array();
				$retry = $this->getPaytmHelper()::MAX_RETRY_COUNT;
				do{
					$response = $this->getPaytmHelper()->executecUrl($check_status_url, $requestParamList);
					$retry++;
				} while(empty($response) && $retry < $retry);
			}
			return $response;
		}

		public function updateTable($sql){
			$updateDone=false;
			if(trim($sql)!=''){
				$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
				$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
				$connection = $resource->getConnection();
				$sql = $sql;
				$connection->query($sql);
				$updateDone=true;
			}
			return $updateDone;
		}

	public function getClearCartUrl(){
        $checkoutSession = $this->getCheckoutSession();
        $quoteId = $checkoutSession->getQuote()->getId();
        $quoteItem = $this->getQuoteModel()->load($quoteId);
        $quoteItem->delete();
        $checkoutSession->getQuote()->setIsActive(false)->save();
    }

    public function getCheckoutSession(){
        $checkoutSession = $this->_objectManager->get('Magento\Checkout\Model\Session');//checkout session
        return $checkoutSession;
    }

    public function getQuoteModel(){
        $quoteModel = $this->_objectManager->create('Magento\Quote\Model\Quote');//Quote item model to load quote
        return $quoteModel;
    }



	function clearCart(){


			 $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
             $cartObject = $objectManager->create('Magento\Checkout\Model\Cart')->truncate(); 
             $checkoutSession = $this->_objectManager->get('Magento\Checkout\Model\Session');
             $checkoutSession->getQuote()->setIsActive(false)->save();
             $quoteId = $checkoutSession->getQuote()->getId();
             $quoteItem = $this->getQuoteModel()->load($quoteId);
             $quoteItem->delete();

	}




	}
?>
