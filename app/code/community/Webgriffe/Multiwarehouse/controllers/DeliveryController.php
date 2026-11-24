<?php

class Webgriffe_Multiwarehouse_DeliveryController extends Mage_Core_Controller_Front_Action
{
    public function preDispatch()
    {
        Mage::register('amfpc_ignorelist', true, true);
        $this->getResponse()->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0', true)
            ->setHeader('Pragma', 'no-cache', true)->setHeader('Expires', '0', true)
            ->setHeader('Content-Type', 'application/json; charset=UTF-8', true);
        return parent::preDispatch();
    }

    public function estimateAction()
    {
        $result = array('status' => 'unavailable');
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setHttpResponseCode(405)->setHeader('Allow', 'POST', true);
        } elseif (Mage::getStoreConfigFlag('catalog/wgmulti_delivery/enabled')) {
            try {
                $id = $this->getRequest()->getPost('product');
                $qty = $this->getRequest()->getPost('qty', '1');
                $attributes = $this->getRequest()->getPost('super_attribute', array());
                if (is_scalar($id) && ctype_digit((string)$id) && is_scalar($qty) && is_numeric($qty)
                    && is_array($attributes) && count($attributes) <= 30) {
                    $product = Mage::getModel('catalog/product')->setStoreId(Mage::app()->getStore()->getId())->load((int)$id);
                    if ($product->getVisibility() != Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE) {
                        $result = Mage::getModel('wgmulti/delivery_estimate')->estimate($product, (float)$qty, $attributes) ?: $result;
                    }
                }
                if ($result['status'] === 'estimate') {
                    $locale = Mage::app()->getLocale();
                    $min = $locale->date($result['min_date'], 'yyyy-MM-dd', null, false)->toString('yyyy. MMMM d.');
                    $max = $locale->date($result['max_date'], 'yyyy-MM-dd', null, false)->toString('yyyy. MMMM d.');
                    $result['label'] = Mage::helper('wgmulti')->__('Estimated delivery: %s', $min === $max ? $min : $min . ' - ' . $max);
                    $days = $result['min_days'] === $result['max_days'] ? (string)$result['min_days'] : $result['min_days'] . '-' . $result['max_days'];
                    $result['detail'] = Mage::helper('wgmulti')->__('With home delivery, usually %s business days.', $days);
                } elseif ($result['status'] === 'choose_options') {
                    $result['label'] = Mage::helper('wgmulti')->__('Select a product option to see the estimated delivery.');
                }
            } catch (Throwable $e) {
                Mage::logException($e);
                $result = array('status' => 'unavailable');
            }
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}
