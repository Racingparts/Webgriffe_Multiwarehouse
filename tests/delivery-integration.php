<?php

require dirname(__DIR__, 4) . '/public/app/Mage.php';
Mage::app('admin');
if (parse_url(Mage::app()->getStore('default')->getBaseUrl(), PHP_URL_HOST) !== 'dev.lmsperformance.eu') {
    throw new RuntimeException('Run these fixture tests only on LMS dev.');
}
Mage::app()->setIsSingleStoreModeAllowed(false)->reinitStores();
Mage::app()->setCurrentStore('default');
$resource = Mage::getSingleton('core/resource');
$db = $resource->getConnection('core_write');
$manifest = Mage::getBaseDir('var') . '/tmp/wgmulti-delivery-browser.json';
function deliveryAssert($actual, $expected, $label)
{
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . json_encode($actual));
    }
}
function deliveryProduct($suffix, $type = 'simple')
{
    return Mage::getModel('catalog/product')->setStoreId(0)->setTypeId($type)
        ->setAttributeSetId(Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getDefaultAttributeSetId())
        ->setSku('wgmulti_delivery_test_' . $suffix)->setName('Delivery estimate test ' . $suffix)
        ->setWebsiteIds(array(Mage::app()->getStore('default')->getWebsiteId()))
        ->setStatus(1)->setVisibility(4)->setTaxClassId(0)->setPrice(1000)->setWeight(1)
        ->setStockData(array('use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100))
        ->setDeliveryDaysMin($type === 'simple' ? 3 : null)->setDeliveryDaysMax($type === 'simple' ? 5 : null);
}
if (in_array('--cleanup-browser', $argv, true)) {
    Mage::app()->setCurrentStore('admin');
    if (!is_file($manifest)) {
        echo "No browser fixtures remain.\n";
        exit;
    }
    $data = json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
    foreach (array_reverse($data['products']) as $id) {
        $p = Mage::getModel('catalog/product')->load($id);
        if ($p->getId()) {
            if (strpos($p->getSku(), 'wgmulti_delivery_test_') !== 0) { throw new RuntimeException('Refusing to delete an unrelated product.'); }
            $p->delete();
        }
    }
    foreach ($data['warehouses'] as $id) {
        $w = Mage::getModel('wgmulti/warehouse')->load($id);
        if ($w->getId()) {
            if (strpos($w->getCode(), 'wgmulti_delivery_test_') !== 0) { throw new RuntimeException('Refusing to delete an unrelated warehouse.'); }
            $w->delete();
        }
    }
    unlink($manifest);
    echo "Browser fixtures removed.\n";
    exit;
}
$prepare = in_array('--prepare-browser', $argv, true);
if ($prepare && is_file($manifest)) { throw new RuntimeException('Clean up existing browser fixtures first.'); }
$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(5));
    $p = deliveryProduct($suffix)->save();
    Mage::getSingleton('cataloginventory/stock_status')->updateStatus($p->getId());
    $load = function ($id, $storeId = 1) {
        // Magento only tracks original EAV values when loaded in the admin area.
        Mage::app()->setCurrentStore('admin');
        try {
            return Mage::getModel('catalog/product')->setStoreId($storeId)->load($id);
        } finally {
            Mage::app()->setCurrentStore('default');
        }
    };
    $service = Mage::getModel('wgmulti/delivery_estimate');
    $r = $service->estimate($load($p->getId()), 1);
    if ($r === null) {
        $debug = $load($p->getId());
        throw new RuntimeException('Fixture unavailable: ' . json_encode(array('status' => $debug->getStatus(),
            'salable' => $debug->isSalable(), 'websites' => $debug->getWebsiteIds(), 'store' => $debug->getStoreId(),
            'min' => $debug->getDeliveryDaysMin(), 'max' => $debug->getDeliveryDaysMax(),
            'stock' => $debug->getStockItem()->getId(), 'manage' => $debug->getStockItem()->getManageStock())));
    }
    deliveryAssert(array($r['min_days'], $r['max_days']), array(3, 5), 'Attribute fallback');
    deliveryAssert($service->estimate($load($p->getId()), 0), null, 'Invalid quantity');
    deliveryAssert($service->estimate($load($p->getId()), 1.5), null, 'Fractional quantity');
    $clear = $load($p->getId(), 0)->setDeliveryDaysMin('')->setDeliveryDaysMax('');
    deliveryAssert(is_array($clear->getOrigData()), true, 'Loaded fixture original data');
    $clear->save();
    $empty = $load($p->getId());
    deliveryAssert(array($empty->getDeliveryDaysMin(), $empty->getDeliveryDaysMax()), array(null, null), 'Clearing an interval must not create 0/0');
    $empty->setDeliveryDaysMin(false)->setDeliveryDaysMax(false)->save();
    deliveryAssert($service->estimate($load($p->getId()), 1), null, 'Inheriting blank defaults');
    $load($p->getId(), 0)->setDeliveryDaysMin(3)->setDeliveryDaysMax(5)->save();
    $load($p->getId())->setDeliveryDaysMin(false)->setDeliveryDaysMax(7)->save();
    $inherited = $load($p->getId());
    deliveryAssert(array((int)$inherited->getDeliveryDaysMin(), (int)$inherited->getDeliveryDaysMax()), array(3, 7), 'Mixed default/store interval');
    $defaultMax = $p->getResource()->getAttributeRawValue($p->getId(), 'delivery_days_max', 0);
    deliveryAssert((int)$defaultMax, 5, 'Store override must preserve the default');
    $inherited->setDeliveryDaysMin(false)->setDeliveryDaysMax(false)->save();
    $r = $service->estimate($load($p->getId()), 1);
    deliveryAssert(array($r['min_days'], $r['max_days']), array(3, 5), 'Both bounds inherit defaults');
    $warehouses = array();
    foreach (array(array(1, 2), array(5, 7)) as $i => $interval) {
        $w = Mage::getModel('wgmulti/warehouse')->setCode('wgmulti_delivery_test_' . $suffix . '_' . $i)
            ->setName('Delivery test warehouse ' . $i)->setPosition($i)
            ->setDeliveryDaysMin($interval[0])->setDeliveryDaysMax($interval[1])->save();
        $warehouses[] = $w->getId();
        Mage::getModel('wgmulti/warehouse_product')->setWarehouseId($w->getId())->setProductId($p->getId())->setQty($i ? 10 : 2)->save();
    }
    foreach (array(1 => array(1, 2), 3 => array(5, 7)) as $qty => $expected) {
        $r = $service->estimate($load($p->getId()), $qty);
        deliveryAssert(array($r['min_days'], $r['max_days']), $expected, 'Warehouse priority and split allocation');
    }
    deliveryAssert($service->estimate($load($p->getId()), 13), null, 'No fallback on insufficient warehouse stock');
    $w = Mage::getModel('wgmulti/warehouse')->load($warehouses[0]);
    $w->setDeliveryDaysMin(null)->setDeliveryDaysMax(null)->save();
    deliveryAssert($service->estimate($load($p->getId()), 1), null, 'Unknown higher priority lead time');
    $w->setDeliveryDaysMin(1)->setDeliveryDaysMax(2)->save();
    try {
        $w->setDeliveryDaysMin(8)->save();
        throw new RuntimeException('Invalid warehouse interval saved.');
    } catch (Mage_Core_Exception $e) { }
    $w->setDeliveryDaysMin(1)->save();
    try {
        $load($p->getId())->setDeliveryDaysMin(9)->save();
        throw new RuntimeException('Invalid product interval saved.');
    } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) { }
    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($p);
    $stock->setManageStock(1)->setUseConfigManageStock(0)->setQty(1)->setIsInStock(1)->save();
    $checkStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($p);
    deliveryAssert(array((float)$checkStock->getQty(), (int)$checkStock->getManageStock()), array(1.0, 1), 'Saved stock fixture');
    deliveryAssert($service->estimate($load($p->getId()), 2), null, 'Core stock shortfall');
    $stock->setManageStock(0)->setQty(100)->save();

    $child = deliveryProduct($suffix . '_child')->setVisibility(1)->setColor(34231)->save();
    $parent = deliveryProduct($suffix . '_parent', 'configurable')->save();
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'color');
    Mage::getModel('catalog/product_type_configurable_attribute')->setProductId($parent->getId())
        ->setAttributeId($attribute->getId())->setPosition(0)->setUseDefault(1)->save();
    $db->insert($resource->getTableName('catalog/product_super_link'), array('product_id' => $child->getId(), 'parent_id' => $parent->getId()));
    $db->insert($resource->getTableName('catalog/product_relation'), array('parent_id' => $parent->getId(), 'child_id' => $child->getId()));
    Mage::getSingleton('cataloginventory/stock_status')->updateStatus($child->getId());
    Mage::getSingleton('cataloginventory/stock_status')->updateStatus($parent->getId());
    $r = $service->estimate($load($parent->getId()), 1);
    deliveryAssert($r['status'] ?? null, 'choose_options', 'Configurable waits for selection');
    $r = $service->estimate($load($parent->getId()), 1, array($attribute->getId() => '34231'));
    deliveryAssert(array($r['min_days'] ?? null, $r['max_days'] ?? null), array(3, 5), 'Selected child interval');
    deliveryAssert($service->estimate($load($parent->getId()), 1, array($attribute->getId() => '999999999')), null, 'Invalid combination');
    $p->setStatus(2);
    deliveryAssert($service->estimate($p, 1), null, 'Disabled product');

    if ($prepare) {
        $data = array('products' => array((int)$p->getId(), (int)$child->getId(), (int)$parent->getId()),
            'warehouses' => array_map('intval', $warehouses), 'color_attribute' => (int)$attribute->getId());
        file_put_contents($manifest, json_encode($data, JSON_THROW_ON_ERROR));
        $db->commit();
        echo json_encode($data) . "\n";
    } else {
        $db->rollBack();
    }
    echo "PASS: product attributes, warehouse validation/allocation, core stock, configurable selection and disabled product checks. No orders or emails.\n";
} catch (Throwable $e) {
    $db->rollBack();
    if ($prepare && is_file($manifest)) { unlink($manifest); }
    throw $e;
}
