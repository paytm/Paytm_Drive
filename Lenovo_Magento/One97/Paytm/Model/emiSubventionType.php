<?php
    namespace One97\Paytm\Model;

    class EmiSubventionType implements \Magento\Framework\Option\ArrayInterface {
        public function toOptionArray() {
            return [['value' => '0', 'label' => __('Amount Based Subvention')], ['value' => '1', 'label' => __('Item/SKU Based Subvention')]];
        }

        public function toArray() {
            return [0 => __('Amt'), 1 => __('Item')];
        }
    }
?>