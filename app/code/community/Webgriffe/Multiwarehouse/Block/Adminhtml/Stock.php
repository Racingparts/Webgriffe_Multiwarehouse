<?php

class Webgriffe_Multiwarehouse_Block_Adminhtml_Stock extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'wgmulti';
        $this->_controller = 'adminhtml_stock';
        $this->_headerText = Mage::helper('adminhtml')->__('Product stocks');

        parent::__construct();
    }
}
