<?php

class Webgriffe_Multiwarehouse_Adminhtml_Multiwarehouse_StockController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/multiwarehouse/stock');
    }

    public function indexAction()
    {
        $this->_title($this->__('Catalog'))->_title($this->__('Multiwarehouse'))->_title($this->__('Stock'));

        $this->loadLayout()
            ->_setActiveMenu('catalog/multiwarehouse/stock')
            ->_addBreadcrumb($this->__('Catalog'), $this->__('Catalog'))
            ->_addBreadcrumb($this->__('Multiwarehouse'), $this->__('Multiwarehouse'))
            ->_addBreadcrumb($this->__('Stock'), $this->__('Stock'));

        $block = $this->getLayout()->createBlock('wgmulti/adminhtml_stock');
        $this->_addContent($block);
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('wgmulti/adminhtml_stock_grid')->toHtml()
        );
    }
}
