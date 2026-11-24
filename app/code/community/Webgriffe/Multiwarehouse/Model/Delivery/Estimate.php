<?php

class Webgriffe_Multiwarehouse_Model_Delivery_Estimate
{
    public function estimate(Mage_Catalog_Model_Product $product, float $qty, array $attributes = array()): ?array
    {
        if (!is_finite($qty) || $qty <= 0 || $qty > 1000000 || !$this->available($product)) {
            return null;
        }
        if ($product->getTypeId() === 'configurable') {
            $type = $product->getTypeInstance(true);
            $ids = $type->getUsedProductAttributeIds($product);
            if (!$ids || count($attributes) !== count($ids)) {
                return array('status' => 'choose_options');
            }
            foreach ($ids as $id) {
                if (!isset($attributes[$id]) || !is_scalar($attributes[$id]) || !ctype_digit((string)$attributes[$id]) || (int)$attributes[$id] <= 0) {
                    return array('status' => 'choose_options');
                }
            }
            $child = $type->getProductByAttributes($attributes, $product);
            if (!$child || !$child->getId()) {
                return null;
            }
            $product = Mage::getModel('catalog/product')->setStoreId($product->getStoreId())->load($child->getId());
            if (!$this->available($product)) {
                return null;
            }
        }
        if ($product->getTypeId() !== 'simple') {
            return null;
        }
        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        if (!$stock->getId() || (!$stock->getIsQtyDecimal() && floor($qty) !== $qty)
            || $qty < (float)$stock->getMinSaleQty() || ((float)$stock->getMaxSaleQty() > 0 && $qty > (float)$stock->getMaxSaleQty())
            || $stock->checkQtyIncrements($qty)->getHasError()
            || ($stock->getManageStock() && (!$stock->getIsInStock()
            || (float)$stock->getQty() - (float)$stock->getMinQty() + 0.00001 < $qty))) {
            return null;
        }
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_read');
        $rows = $db->fetchAll($db->select()->from(array('p' => $resource->getTableName('wgmulti/warehouse_product')), array('qty'))
            ->join(array('w' => $resource->getTableName('wgmulti/warehouse')), 'w.id = p.warehouse_id', array('delivery_days_min', 'delivery_days_max'))
            ->where('p.product_id = ?', (int)$product->getId())->order(array('w.position ASC', 'w.id ASC', 'p.id ASC')));
        $calendar = Mage::getModel('wgmulti/delivery_calendar');
        $interval = $rows ? $this->allocate($rows, $qty) : $calendar->interval($product->getDeliveryDaysMin(), $product->getDeliveryDaysMax());
        if ($interval === null) {
            return null;
        }
        $storeId = $product->getStoreId();
        $now = new DateTimeImmutable('now', new DateTimeZone(Mage::getStoreConfig('general/locale/timezone', $storeId)));
        $dates = $calendar->dates($interval, $now, trim((string)Mage::getStoreConfig('catalog/wgmulti_delivery/cutoff', $storeId)),
            $calendar->excludedDates((string)Mage::getStoreConfig('catalog/wgmulti_delivery/excluded_dates', $storeId)));
        $refresh = $now->modify('tomorrow')->setTime(0, 0);
        $cutoff = trim((string)Mage::getStoreConfig('catalog/wgmulti_delivery/cutoff', $storeId));
        if ($cutoff !== '' && $now->format('H:i') < $cutoff) {
            list($hour, $minute) = array_map('intval', explode(':', $cutoff));
            $refresh = $now->setTime($hour, $minute);
        }
        return array('status' => 'estimate', 'min_days' => $interval[0], 'max_days' => $interval[1],
            'min_date' => $dates[0], 'max_date' => $dates[1],
            'refresh_after' => max(1, min(300, $refresh->getTimestamp() - $now->getTimestamp() + 1)));
    }

    protected function available(Mage_Catalog_Model_Product $product): bool
    {
        return $product->getId() && (int)$product->getStatus() === Mage_Catalog_Model_Product_Status::STATUS_ENABLED
            && in_array((int)Mage::app()->getStore($product->getStoreId())->getWebsiteId(), array_map('intval', $product->getWebsiteIds()), true)
            && $product->isSalable();
    }

    public function allocate(array $rows, float $qty): ?array
    {
        $min = $max = 0;
        $calendar = Mage::getModel('wgmulti/delivery_calendar');
        foreach ($rows as $row) {
            if ((float)$row['qty'] <= 0) {
                continue;
            }
            $interval = $calendar->interval($row['delivery_days_min'], $row['delivery_days_max']);
            if ($interval === null) {
                return null;
            }
            $min = max($min, $interval[0]);
            $max = max($max, $interval[1]);
            $qty -= (float)$row['qty'];
            if ($qty <= 0.00001) {
                return array($min, $max);
            }
        }
        return null;
    }
}
