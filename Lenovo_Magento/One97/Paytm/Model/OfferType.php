<?php
    namespace One97\Paytm\Model;

    class OfferType implements \Magento\Framework\Option\ArrayInterface {
        public function toOptionArray() {
            return [['value' => '0', 'label' => __('Amount Based Offer')], ['value' => '1', 'label' => __('Item/SKU Based Offer')]];
        }

        public function toArray() {
            return [0 => __('Amt'), 1 => __('Item')];
        }
    }
?>