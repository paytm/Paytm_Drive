<?php
    namespace One97\Paytm\Controller\Standard;
    use Magento\Framework\App\CsrfAwareActionInterface;
    use Magento\Framework\App\Request\InvalidRequestException;
    use Magento\Framework\App\RequestInterface;
    use Magento\Framework\Controller\ResultFactory;


    class EmiTxnToken extends \One97\Paytm\Controller\Paytm  implements CsrfAwareActionInterface{

        public function createCsrfValidationException(
            RequestInterface $request
        ): ?InvalidRequestException {
            return null;
        }

        public function validateForCsrf(RequestInterface $request): ?bool {
            return true;
        }
        
        /* this funtion redirect to Paytm with proper form post */
        public function execute() {
            $dataRaw=$this->_paytmModel->generateTxnTokenEmi($_POST);
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $resultJson->setData($dataRaw);
            return $resultJson;
        
        }

    }
?>