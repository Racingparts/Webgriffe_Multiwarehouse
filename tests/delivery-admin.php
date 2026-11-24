<?php

require dirname(__DIR__, 4) . '/public/app/Mage.php';
Mage::app('admin');
if (parse_url(Mage::app()->getStore('default')->getBaseUrl(), PHP_URL_HOST) !== 'dev.lmsperformance.eu') {
    throw new RuntimeException('Run this read-only rendering test on LMS dev.');
}
Mage::app()->loadArea('adminhtml');
Mage::getDesign()->setArea('adminhtml');
class Wgmulti_Delivery_Test_Tabs extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs
{
    protected function _prepareLayout() { return $this; }
}
function adminDeliveryAssert($value, $message)
{
    if (!$value) { throw new RuntimeException($message); }
}
$layout = Mage::app()->getLayout();
$layout->createBlock('adminhtml/page_head', 'head');
$resource = Mage::getSingleton('core/resource');
$read = $resource->getConnection('core_read');
$setId = (int)Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getDefaultAttributeSetId();
$groups = Mage::getResourceModel('eav/entity_attribute_group_collection')->setAttributeSetFilter($setId);
$deliveryGroup = null;
foreach ($groups as $group) {
    if ($group->getAttributeGroupName() === 'Delivery estimate') { $deliveryGroup = $group; break; }
}
adminDeliveryAssert($deliveryGroup !== null, 'Missing dedicated attribute group.');
$groupId = (int)$deliveryGroup->getId();
$before = $read->fetchAll($read->select()->from($resource->getTableName('eav/entity_attribute'))
    ->where('attribute_group_id = ?', $groupId));
$original = Mage::registry('product');
try {
    foreach (array(0, 1) as $storeId) {
        $product = Mage::getModel('catalog/product')->setId(-990001)->setTypeId('simple')->setAttributeSetId($setId)
            ->setStoreId($storeId)->setDeliveryDaysMin(5)->setDeliveryDaysMax(7);
        $product->setStockItem(Mage::getModel('cataloginventory/stock_item')->setManageStock(1)->setQty(15)->setIsInStock(1));
        Mage::unregister('product');
        Mage::register('product', $product);
        $attributes = $product->getAttributes($groupId, true);
        foreach (array(false, true) as $locked) {
            $product->unlockAttributes();
            if ($locked) { $product->lockAttribute('delivery_days_min')->lockAttribute('delivery_days_max'); }
            $fields = $layout->createBlock('adminhtml/catalog_product_edit_tab_attributes')
                ->setGroup($deliveryGroup)->setGroupAttributes($attributes)->toHtml();
            $tabs = new Wgmulti_Delivery_Test_Tabs();
            $stockHtml = $layout->createBlock('adminhtml/catalog_product_edit_tab_inventory')->toHtml();
            $tabs->setLayout($layout)->addTab('inventory', array('label' => 'Inventory', 'content' => $stockHtml));
            $tabs->addTab('group_' . $groupId, array('label' => 'Delivery estimate', 'content' => $fields));
            $tabs->addTab('untouched', array('label' => 'Other', 'content' => '<div>Keep me</div>'));
            Mage::dispatchEvent('adminhtml_block_widget_tabs_html_before', array('block' => $tabs));
            adminDeliveryAssert($tabs->getTab('group_' . $groupId) === null, 'Standalone tab remains.');
            $html = $tabs->getTab('inventory')->getContent();
            adminDeliveryAssert(strpos($html, 'table_cataloginventory') !== false, 'Stock fields lost.');
            adminDeliveryAssert($tabs->getTab('untouched')->getContent() === '<div>Keep me</div>', 'Unrelated tab changed.');
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($dom);
            foreach (array('min' => '5', 'max' => '7') as $bound => $value) {
                $nodes = $xpath->query('//input[@name="product[delivery_days_' . $bound . ']"]');
                adminDeliveryAssert($nodes->length === 1 && $nodes->item(0)->getAttribute('value') === $value, 'Duplicate, missing or changed native field.');
                if ($locked) { adminDeliveryAssert($nodes->item(0)->hasAttribute('readonly') || $nodes->item(0)->hasAttribute('disabled'), 'Locked attribute became editable.'); }
                if ($storeId) {
                    adminDeliveryAssert($xpath->query('//input[@name="use_default[]" and @value="delivery_days_' . $bound . '"]')->length === 1, 'Native store inheritance checkbox missing.');
                }
            }
            Mage::dispatchEvent('adminhtml_block_widget_tabs_html_before', array('block' => $tabs));
            adminDeliveryAssert($html === $tabs->getTab('inventory')->getContent(), 'Repeated event duplicated fields.');
            if (!$storeId && !$locked) {
                file_put_contents('/tmp/wgmulti-delivery-admin-fields.html', '<!doctype html><html><head><meta charset="utf-8"></head><body><form id="product_edit_form">' . $html . '</form></body></html>');
            }
        }
        $product->setTypeId('configurable');
        $tabs = new Wgmulti_Delivery_Test_Tabs();
        $tabs->setLayout($layout)->addTab('inventory', array('label' => 'Inventory', 'content' => 'untouched'));
        $tabs->addTab('group_' . $groupId, array('label' => 'Delivery estimate', 'content' => 'not applicable'));
        Mage::dispatchEvent('adminhtml_block_widget_tabs_html_before', array('block' => $tabs));
        adminDeliveryAssert($tabs->getTab('inventory')->getContent() === 'untouched', 'Unsupported type modified.');
    }
    adminDeliveryAssert($before === $read->fetchAll($read->select()->from($resource->getTableName('eav/entity_attribute'))->where('attribute_group_id = ?', $groupId)), 'Attribute assignments changed.');
    echo "PASS: native delivery fields moved to Inventory, values/locks/inheritance preserved, no duplicates or unrelated tab changes; no database writes.\n";
} finally {
    Mage::unregister('product');
    if ($original) { Mage::register('product', $original); }
}
