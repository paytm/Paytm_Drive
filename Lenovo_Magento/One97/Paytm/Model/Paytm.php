<?php
    namespace One97\Paytm\Model;
    use One97\Paytm\Helper\Data as DataHelper;

    class Paytm extends \Magento\Payment\Model\Method\AbstractMethod {
        const CODE = 'paytm';
        protected $_code = self::CODE;
        protected $_isInitializeNeeded = true;
        protected $_isGateway = true;
        protected $_isOffline = true;
        protected $helper;
        protected $_minAmount = null;
        protected $_maxAmount = null;
        protected $_supportedCurrencyCodes = array('INR');
        protected $_formBlockType = 'One97\Paytm\Block\Form\Paytm';
        protected $_infoBlockType = 'One97\Paytm\Block\Info\Paytm';
        protected $urlBuilder;

        public function __construct(
            \Magento\Framework\Model\Context $context,
            \Magento\Framework\Registry $registry,
            \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
            \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
            \Magento\Payment\Helper\Data $paymentData,
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
            \Magento\Payment\Model\Method\Logger $logger,
            \Magento\Framework\UrlInterface $urlBuilder,
            \One97\Paytm\Helper\Data $helper
        ) {
            $this->helper = $helper;
            parent::__construct(
                $context,
                $registry,
                $extensionFactory,
                $customAttributeFactory,
                $paymentData,
                $scopeConfig,
                $logger
            );
            $this->_minAmount = "0.50";
            $this->_maxAmount = "1000000";
            $this->urlBuilder = $urlBuilder;
        }

        public function initialize($paymentAction, $stateObject) {
            $payment = $this->getInfoInstance();
            $order = $payment->getOrder();
            $order->setCanSendNewEmailFlag(false);
            $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $stateObject->setStatus('pending_payment');
            $stateObject->setIsNotified(false);
        }

        /* this function check ammount limit */
        public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
            if ($quote && (
                $quote->getBaseGrandTotal() < $this->_minAmount || ($this->_maxAmount && $quote->getBaseGrandTotal() > $this->_maxAmount))
            ) {
                return false;
            }
            return parent::isAvailable($quote);
        }

        /* this function for currency check*/
        public function canUseForCurrency($currencyCode) {
            if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
                return false;
            }
            return true;
        }
        /* this function return Paytm redirect form post */
        public function generateTxnTokenEmi($data) {
            $callBackUrl=$this->urlBuilder->getUrl('paytm/Standard/Response', ['_secure' => true]);
            if($this->helper::CUSTOM_CALLBACK_URL!=''){
                $callBackUrl=$this->helper::CUSTOM_CALLBACK_URL;
            }
            if($this->getConfigData("custom_callbackurl")=='1'){
                $callBackUrl=$this->getConfigData("callback_url")!=''?$this->getConfigData("callback_url"):$callBackUrl;
            }

            $params = array(
                'MID' => trim($this->getConfigData("MID")),               
                'TXN_AMOUNT' => round($data['amount'], 2),
                'PAYABLE_AMOUNT' => round($data['payableAmount'], 2),
                'CHANNEL_ID' => $this->helper::CHANNEL_ID,
                'INDUSTRY_TYPE_ID' => trim($this->getConfigData("Industry_id")),
                'WEBSITE' => trim($this->getConfigData("Website")),
                'CUST_ID' => $data['email'],
                'ORDER_ID' => $data['orderid'],                     
                'EMAIL' => $data['email'],
                'CALLBACK_URL' => trim($callBackUrl)
            );

            if($this->getConfigData("environment") ==1){
                $paytmDmain = $this->helper::PRODUCTION_HOST;
            }else{
                $paytmDmain = $this->helper::STAGING_HOST;
            }

            $paytmParams = array(); 
            $paytmParams['body']['mid']=$params["MID"];
            $paytmParams['body']['orderId']=$params["ORDER_ID"];
            $paytmParams['body']['txnAmount']=$params["TXN_AMOUNT"];
            //$paramListArr['body']['paytmSsoToken']="5578fb42-fb5e-49d5-87dc-76fb65d63000";
            $paytmParams['body']['preAuthBlockSeconds']="3000";
            $paytmParams['body']['cardPreAuthType']="";
            $paytmParams['body']['paymentMode']="";
            $paytmParams['body']['websiteName']=$params["WEBSITE"];
            $paytmParams['body']['blockType']="PAYCONFIRM";
            $paytmParams['body']['expiryAction']="RELEASE";
            $paytmParams['body']['txnTokenRequired']=true;

            $paytmParams['body']['callbackUrl']=$params["CALLBACK_URL"];
            if(isset($data['emiSubventionToken']) && $data['emiSubventionToken'] != ""){
                $paytmParams["body"]["emiSubventionToken"] = $data['emiSubventionToken'];
            }
            $paytmParams['body']['payableAmount']=$params["PAYABLE_AMOUNT"];
            $paytmParams['body']['simplifiedPaymentOffers']['applyAvailablePromo']="true"; 
		    $paytmParams['body']['simplifiedPaymentOffers']['validatePromo']="false";

            $offerItems[0]["id"] = $cartId+$loop_id;
            $offerItems[0]["amount"] = ($item->getPrice())*($item->getQtyOrdered()); 
            $offerItems[0]["productDetail"]["id"] = $item->getSku();
            $offerItems[0]["productDetail"]["brandId"] = $this->getConfigData('brandId');
            $offerItems[0]["productDetail"]["categoryIds"][0] = $this->getConfigData('categoryId');

            $paytmParams["body"]["simplifiedPaymentOffers"]["cartDetails"]["items"] = $offerItems; 

            $paytmParams['body']['userInfo']['custId'] = $params["CUST_ID"];

            $generateSignature = $this->helper->generateSignature(json_encode($paytmParams['body'], JSON_UNESCAPED_SLASHES), $this->getConfigData("merchant_key"));
            
            $paytmParams['head'] = array(
                "clientId" => "C11", // optional
                // "version" => "v1", // optional
                // "requestTimestamp" => time(), // optional
                "channelId" => "WEB",
                "signature" => $generateSignature
                );

            $url = $paytmDmain."order/v2/preAuth";
            $post_data_string = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
            $headers = array("Content-Type: application/json");
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
            $response = curl_exec($ch);
            $response_array = json_decode($response, TRUE);
            $txnToken = $response_array['body']['txnToken'];
            return $txnToken;
        }

        /* this function return Paytm redirect form post */
        public function buildPaytmRequest($order) {
            $paytmOrderId=$magentoOrderId=$order->getRealOrderId();
            if($this->helper::APPEND_TIMESTAMP){
                $paytmOrderId=$magentoOrderId.'_'.time();
            }
            
            if($this->helper::SAVE_PAYTM_RESPONSE){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                
                $objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
                $date = $objDate->gmtDate();

                $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
                $connection = $resource->getConnection();

                $tableName = $resource->getTableName('paytm_order_data');
                if(!$connection->isTableExists($tableName)){
                    $sql = "CREATE TABLE ".$tableName."(id INT(11) PRIMARY KEY AUTO_INCREMENT, order_id TEXT NOT NULL, paytm_order_id TEXT NOT NULL, transaction_id TEXT, status TINYINT(1)  DEFAULT '0', paytm_response TEXT, date_added DATETIME, date_modified DATETIME )";
                    $connection->query($sql);
                }
                $tableName = $resource->getTableName('paytm_order_data');
                $sql = "INSERT INTO ".$tableName."(order_id, paytm_order_id, date_added, date_modified) VALUES ('".$magentoOrderId."', '".$paytmOrderId."', '".$date."', '".$date."')";
                $connection->query($sql);
            }

            $callBackUrl=$this->urlBuilder->getUrl('paytm/Standard/Response', ['_secure' => true]);
            if($this->helper::CUSTOM_CALLBACK_URL!=''){
                $callBackUrl=$this->helper::CUSTOM_CALLBACK_URL;
            }
            if($this->getConfigData("custom_callbackurl")=='1'){
                $callBackUrl=$this->getConfigData("callback_url")!=''?$this->getConfigData("callback_url"):$callBackUrl;
            }
            $params = array(
                'MID' => trim($this->getConfigData("MID")),               
                'TXN_AMOUNT' => round($order->getGrandTotal(), 2),
                'CHANNEL_ID' => $this->helper::CHANNEL_ID,
                //'INDUSTRY_TYPE_ID' => trim($this->getConfigData("Industry_id")),
                'WEBSITE' => trim($this->getConfigData("Website")),
                'CUST_ID' => $order->getCustomerEmail(),
                'ORDER_ID' => $paytmOrderId,                     
                'EMAIL' => $order->getCustomerEmail(),
                'CALLBACK_URL' => trim($callBackUrl)
            );    
            if(isset($order->paytmPromoCode)){
                $params['PROMO_CAMP_ID']=$order->paytmPromoCode;
            }
            if($this->getConfigData("environment") ==1){
                $paytmDmain = 'https://securegw.paytm.in/';
                $url = $this->helper::TRANSACTION_TOKEN_URL_PRODUCTION.$params["MID"] . "&orderId=" . $params["ORDER_ID"];
            }else{
                $paytmDmain = 'https://securegw-stage.paytm.in/';
                $url = $this->helper::TRANSACTION_TOKEN_URL_STAGING.$params["MID"] . "&orderId=" . $params["ORDER_ID"];
            }
            $checksum = $this->helper->generateSignature($params, $this->getConfigData("merchant_key"));
            $params['CHECKSUMHASH'] = $checksum;

            // emi code starts here
            if($this->getConfigData("flowType") ==1){  // 1 is for PREAUTH FLOW
                $paytmParams = array(); 
                $paytmParams["body"] = array(
                    "mid" => $params["MID"],
                    "orderId" => $params["ORDER_ID"],
                    "txnAmount" => $params["TXN_AMOUNT"],
                    "preAuthBlockSeconds" => "3000",
                    "cardPreAuthType" => "",
                    "paymentMode" => "",
                    "websiteName" => $params["WEBSITE"],
                    "blockType" => "PAYCONFIRM",
                    "expiryAction" => "RELEASE",
                    "txnTokenRequired" => true,
                    "callbackUrl" => $params["CALLBACK_URL"],
                    "userInfo" => array(
                        "custId" => $params["CUST_ID"],
                    ),
                );
             // for bank offers  
             if($this->getConfigData('bankoffer') ==1){
                // SKU based offer code start
                $items = $order->getAllItems();   
                $cartId = time();  
                if($this->getConfigData('offertype')=="1"){
                    $loop_id=0;
                    foreach($items as $item) { 
                        if($this->getConfigData('brandId') !="" && $this->getConfigData('categoryId') !="" && $item->getSku() !=""){ 
                            $offerItems[$loop_id]["id"] = $cartId+$loop_id;
                            $offerItems[$loop_id]["amount"] = ($item->getPrice())*($item->getQtyOrdered());
                            $offerItems[$loop_id]["productDetail"]["id"] = $item->getSku();
                            $offerItems[$loop_id]["productDetail"]["brandId"] = $this->getConfigData('brandId');
                            $offerItems[$loop_id]["productDetail"]["categoryIds"][0] = $this->getConfigData('categoryId');
                            $loop_id++;   
                        }
                    } 
                    // SKU based offer code end 
                    $paytmParams["body"]["simplifiedPaymentOffers"]["cartDetails"]["items"] = $offerItems;  
                } 
                $paytmParams["body"]["simplifiedPaymentOffers"]["applyAvailablePromo"]= "true";
            } 
            // for emi subvention
            if($this->getConfigData('emisubvention') ==1){ 
                $paytmParams["body"]["simplifiedSubvention"]["customerId"]= $params['CUST_ID'];
                $paytmParams["body"]["simplifiedSubvention"]["selectPlanOnCashierPage"]= "true";
                if($this->getConfigData('emisubventiontype')=="1"){
                    $items = $order->getAllItems();  
                    $cartId = time(); 
                    $loop_id=0;
                    foreach($items as $item) { 
                        if($this->getConfigData('brandId') !="" && $this->getConfigData('categoryId') !="" && $item->getSku() !=""){ 
                            $emiOfferItems[$loop_id]["id"] = $cartId+$loop_id;
                            $emiOfferItems[$loop_id]["productId"] = rand(100000,900000);
                            $emiOfferItems[$loop_id]["brandId"] = $this->getConfigData('brandId');
                            $emiOfferItems[$loop_id]["model"] =  $item->getSku();
                            $emiOfferItems[$loop_id]["price"] = ($item->getPrice())*($item->getQtyOrdered());
                            $emiOfferItems[$loop_id]["quantity"] = round($item->getQtyOrdered());
                            $emiOfferItems[$loop_id]["categoryList"][0] = $this->getConfigData('categoryId');
                            $loop_id++;   
                        }
                     } 
                  $paytmParams["body"]["simplifiedSubvention"]["items"] = $emiOfferItems;
                }else{
                    $paytmParams["body"]["simplifiedSubvention"]["subventionAmount"]= $params['TXN_AMOUNT'];
                }
            }
    // new code end
              $generateSignature = $this->helper->generateSignature(json_encode($paytmParams['body'], JSON_UNESCAPED_SLASHES), $this->getConfigData("merchant_key"));
                $paytmParams["head"] = array(
                    "clientId" => "C11",
                    "channelId" => "WEB", 
                    "signature"	=> $generateSignature
                );
                if($this->getConfigData("environment") ==1){
                    $url = $this->helper::PRODUCTION_HOST."/order/v2/preAuth";
                }else{
                    $url = $this->helper::STAGING_HOST."/order/v2/preAuth";
                }
               
                $post_data_string = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
                $headers = array("Content-Type: application/json");
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
                $response = curl_exec($ch);
                $response_array = json_decode($response, TRUE);
                if(isset($response_array['body']['txnToken'])&& $response_array['body']['txnToken']!=""){
                    $txnToken = $response_array['body']['txnToken'];
                }else{
                    $txnToken = "";
                }
                $version = $this->getLastUpdate();
                $params['X-REQUEST-ID']=$this->helper::X_REQUEST_ID.str_replace('|', '_', str_replace(' ', '-', $version));
                $inputForm='<script type="application/javascript" crossorigin="anonymous" src="'.$paytmDmain.'"merchantpgpui/checkoutjs/merchants/'.$params['MID'].'.js"></script><input type="hidden" value="'.$txnToken.'"  name="txn_token" id="txn_token" /> <input id="OrderID" value="'.$params['ORDER_ID'].'"  type="hidden" /><input id="amount" value="'.$params['TXN_AMOUNT'].'"  type="hidden" />';
                $datareturn['inputForm'] = $inputForm;
                $datareturn['ORDER_ID'] = $params['ORDER_ID'];
                $datareturn['txnToken'] = $txnToken;
                $datareturn['TXN_AMOUNT'] = $params['TXN_AMOUNT'];
                $datareturn['MAGENTO_VERSION'] = $this->getMagentoVerionInPlugin();
                $datareturn['PLUGIN_VERSION'] = $this->getpluginversion();
                $datareturn['flowType'] = "PREAUTH";
                return array('response'=>$datareturn);
            }else{                        // initiate transaction flow 
                $paytmParams = array();
                $paytmParams["body"] = array(
                    "requestType" => "Payment",
                    "mid" => $params["MID"],
                    "websiteName" => $params["WEBSITE"],
                    "orderId" => $params["ORDER_ID"],
                    "callbackUrl" => $params["CALLBACK_URL"],
                    "txnAmount" => array(
                        "value" => $params["TXN_AMOUNT"],
                        "currency" => "INR",
                    ),
                    "userInfo" => array(
                        "custId" => $params["CUST_ID"],
                    ),
                );
                if($this->getConfigData('bankoffer') ==1){
                    // SKU based offer code start
                    $items = $order->getAllItems();   
                    $cartId = time();  
                    if($this->getConfigData('offertype')=="1"){
                        $loop_id=0;
                        foreach($items as $item) { 
                            if($this->getConfigData('brandId') !="" && $this->getConfigData('categoryId') !="" && $item->getSku() !=""){ 
                                $offerItems[$loop_id]["id"] = $cartId+$loop_id;
                                $offerItems[$loop_id]["amount"] = ($item->getPrice())*($item->getQtyOrdered());
                                $offerItems[$loop_id]["productDetail"]["id"] = $item->getSku();
                                $offerItems[$loop_id]["productDetail"]["brandId"] = $this->getConfigData('brandId');
                                $offerItems[$loop_id]["productDetail"]["categoryIds"][0] = $this->getConfigData('categoryId');
                                $loop_id++;   
                            }
                        } 
                        // SKU based offer code end 
                        $paytmParams["body"]["simplifiedPaymentOffers"]["cartDetails"]["items"] = $offerItems;  
                    } 
                    $paytmParams["body"]["simplifiedPaymentOffers"]["applyAvailablePromo"]= "true";
                } 
                // for emi subvention
                if($this->getConfigData('emisubvention') ==1){ 
                    $paytmParams["body"]["simplifiedSubvention"]["customerId"]= $params['CUST_ID'];
                    $paytmParams["body"]["simplifiedSubvention"]["selectPlanOnCashierPage"]= "true";
                    if($this->getConfigData('emisubventiontype')=="1"){
                        $items = $order->getAllItems();  
                        $cartId = time(); 
                        $loop_id=0;
                        foreach($items as $item) { 
                            if($this->getConfigData('brandId') !="" && $this->getConfigData('categoryId') !="" && $item->getSku() !=""){ 
                                $emiOfferItems[$loop_id]["id"] = $cartId+$loop_id;
                                $emiOfferItems[$loop_id]["productId"] = rand(100000,900000);
                                $emiOfferItems[$loop_id]["brandId"] = $this->getConfigData('brandId');
                                $emiOfferItems[$loop_id]["model"] =  $item->getSku();
                                $emiOfferItems[$loop_id]["price"] = ($item->getPrice())*($item->getQtyOrdered());
                                //$emiOfferItems[$loop_id]["quantity"] = $item->getQtyOrdered();
                                $emiOfferItems[$loop_id]["categoryList"][0] = $this->getConfigData('categoryId');
                                $loop_id++;   
                            }
                         } 
                      $paytmParams["body"]["simplifiedSubvention"]["items"] = $emiOfferItems;
                    }else{
                        $paytmParams["body"]["simplifiedSubvention"]["subventionAmount"]= $params['TXN_AMOUNT'];
                    }
                }
                $generateSignature = $this->helper->generateSignature(json_encode($paytmParams['body'], JSON_UNESCAPED_SLASHES), $this->getConfigData("merchant_key"));

                $paytmParams["head"] = array(
                    "signature" => $generateSignature
                );
                
                $post_data_string = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
                $headers = array("Content-Type: application/json");
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
                $response = curl_exec($ch);
                $response_array = json_decode($response, TRUE);
               // $txnToken = $response_array['body']['txnToken'];
                if(isset($response_array['body']['txnToken'])&& $response_array['body']['txnToken']!=""){
                    $txnToken = $response_array['body']['txnToken'];
                }else{
                    $txnToken = "";
                }
                $version = $this->getLastUpdate();
                $params['X-REQUEST-ID']=$this->helper::X_REQUEST_ID.str_replace('|', '_', str_replace(' ', '-', $version));
                $inputForm='<script type="application/javascript" crossorigin="anonymous" src="'.$paytmDmain.'"merchantpgpui/checkoutjs/merchants/'.$params['MID'].'.js"></script><input type="hidden" value="'.$txnToken.'"  name="txn_token" id="txn_token" /> <input id="OrderID" value="'.$params['ORDER_ID'].'"  type="hidden" /><input id="amount" value="'.$params['TXN_AMOUNT'].'"  type="hidden" />';
                $datareturn['inputForm'] = $inputForm;
                $datareturn['ORDER_ID'] = $params['ORDER_ID'];
                $datareturn['txnToken'] = $txnToken;
                $datareturn['TXN_AMOUNT'] = $params['TXN_AMOUNT'];
                $datareturn['MAGENTO_VERSION'] = $this->getMagentoVerionInPlugin();
                $datareturn['PLUGIN_VERSION'] = $this->getpluginversion();
                return array('response'=>$datareturn);
            }
        }

        /* this function for checksum validation */
        public function validateResponse($res,$order_id) {
            $checksum = @$res["CHECKSUMHASH"];
            unset($res["CHECKSUMHASH"]);
            if ($this->helper->verifySignature($res,$this->getConfigData("merchant_key"),$checksum)) {
                $result = true;
            } else {
                $result = false;
            }
            return $result;
        }

        /* this function for php curl version */
        public function getcURLversion() {
            return $this->helper->getcURLversion();
        }

        /* this function for php curl version */
        public function getpluginversion() {
            return $this->helper::PLUGIN_VERSION;
        }
        
        /* this function for genrating checksum */
        public function generateStatusChecksum($requestParamList) {
            $result = $this->helper->generateSignature($requestParamList,$this->getConfigData("merchant_key"));            
            return $result;
        }

        /* this function for return MID */
        public function getMID() {            
            return $this->getConfigData("MID");
        }


         /* this function for return checkoutjs url */
        public function getcheckoutjsurl() {  
           return str_replace('MID', $this->getConfigData("MID"), $this->helper->getPaytmURL($this->helper::CHECKOUT_JS_URL,$this->getConfigData('environment')));
        }


        public function autoInvoiceGen() {
            $result = $this->getConfigData("payment_action");            
            return $result;
        }

        /* this function return transaction URL from admin config */
        public function getRedirectUrl() {
            return $this->helper->getTransactionURL($this->getConfigData('environment'));
        }
        
        /* this function return transaction status URL from admin config */
        public function getNewStatusQueryUrl() {
            return $this->helper->getTransactionStatusURL($this->getConfigData('environment'));
        }

        /* this function return success order status */
        public function getSuccessOrderStatus() {
            return $this->getConfigData("success_order_status");
        }
        
        /* this function return fail order status */
        public function getFailOrderStatus() {
            return $this->getConfigData("fail_order_status");
        }


        public function getMagentoVerionInPlugin() {
            $objectManagerVs = \Magento\Framework\App\ObjectManager::getInstance();
            $productMetadata = $objectManagerVs->get('Magento\Framework\App\ProductMetadataInterface');
            return $productMetadata->getVersion();

        }

        /* this function return Magento version and last update date of plugin */
        public function getLastUpdate(){
            $objectManagerVs = \Magento\Framework\App\ObjectManager::getInstance();
            $productMetadata = $objectManagerVs->get('Magento\Framework\App\ProductMetadataInterface');
            $version = $productMetadata->getVersion();

            $lastUpdated=date('d M Y',strtotime($this->helper::LAST_UPDATED));
            return $version."|".$lastUpdated;
        }

        /* this function for return Flow Type */
        public function getFlowType() {            
            return $this->getConfigData("flowType");
        }

        /* this function for return Environment */
        public function getEnvironment() {            
            return $this->getConfigData("environment");
        }
    }
?>
