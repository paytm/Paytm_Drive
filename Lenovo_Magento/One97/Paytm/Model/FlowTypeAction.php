<?php
    namespace One97\Paytm\Model;

    class FlowTypeAction implements \Magento\Framework\Option\ArrayInterface {
        public function toOptionArray() {
            return [['value' => '0', 'label' => __('Default')], ['value' => '1', 'label' => __('Pre Auth EMI Subvention')]];
        }

        public function toArray() {
            return [0 => __('No'), 1 => __('Yes')];
        }
    }
?>