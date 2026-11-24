<?php

class Webgriffe_Multiwarehouse_Block_Catalog_Product_Delivery extends Mage_Core_Block_Template
{
    public function getProduct()
    {
        return Mage::registry('current_product');
    }

    public function getEstimateUrl(): string
    {
        return $this->getUrl('wgmulti/delivery/estimate', array('_nosid' => true, '_query' => array('___store' => Mage::app()->getStore()->getCode()), '_secure' => Mage::app()->getStore()->isCurrentlySecure()));
    }

    protected function _toHtml()
    {
        $product = $this->getProduct();
        if (!Mage::getStoreConfigFlag('catalog/wgmulti_delivery/enabled') || !$product
            || !in_array($product->getTypeId(), array('simple', 'configurable'), true)) {
            return '';
        }
        return parent::_toHtml();
    }
}
