<?php

class Webgriffe_Multiwarehouse_Model_Adminhtml_Observer
{
    public function moveDeliveryFieldsToInventory(Varien_Event_Observer $observer)
    {
        $tabs = $observer->getEvent()->getBlock();
        if (!$tabs instanceof Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs) {
            return;
        }
        $product = $tabs->getProduct();
        $inventory = $tabs->getTab('inventory');
        if (!$product || $product->getTypeId() !== 'simple' || !$inventory) {
            return;
        }
        $codes = array('delivery_days_max', 'delivery_days_min');
        foreach ($tabs->getTabsIds() as $tabId) {
            if (strpos($tabId, 'group_') !== 0) {
                continue;
            }
            $attributes = $product->getAttributes((int)substr($tabId, 6), true);
            $visibleCodes = array();
            foreach ($attributes as $attribute) {
                if ($attribute->getIsVisible()) {
                    $visibleCodes[] = $attribute->getAttributeCode();
                }
            }
            sort($visibleCodes);
            // Move only the dedicated pair; do not displace unrelated custom attributes.
            if ($visibleCodes !== $codes) {
                continue;
            }
            $tab = $tabs->getTab($tabId);
            $inventory->setContent($inventory->getContent() . $tab->getContent());
            $tabs->removeTab($tabId);
        }
    }
}
