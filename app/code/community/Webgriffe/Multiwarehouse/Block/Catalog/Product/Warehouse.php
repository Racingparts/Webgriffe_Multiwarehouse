<?php

class Webgriffe_Multiwarehouse_Block_Catalog_Product_Warehouse extends Mage_Core_Block_Template
{
    protected static $_warehouses;

    protected static $_productQuantities = [];

    public function getListLabelHtml(Mage_Catalog_Model_Product $product): string
    {
        $warehouse = $this->getFirstAvailableWarehouse($product);

        if (!$warehouse) {
            $is_salable = $product->getIsSalable();

            $label = $is_salable ? $this->__('Orderable') : $this->__('Out of stock');
            $label_css = $is_salable ? 'background-color:yellow' : 'background-color:grey;color:white';

            $warehouse = [
                'frontend_list_label' => $label,
                'frontend_list_label_css' => $label_css
            ];
        }

        $label = $warehouse['frontend_list_label'];
        $label_css = $warehouse['frontend_list_label_css'];

        $html = "<span style='position:absolute;z-index:3;padding:7px;border-radius:8px;font-weight:bold;$label_css'>";
        $html .= $label;
        $html .= '</span>';

        return $html;
    }

    public function getFirstAvailableWarehouse(Mage_Catalog_Model_Product $product): ?array
    {
        $warehouse = null;

        foreach ($this->getWarehouses() as $warehouse_id => $warehouse_data) {
            $warehouse_qty = $this->getProductWarehouseQty($warehouse_id, $product->getId());

            if ($warehouse_qty > 0) {
                $warehouse = $warehouse_data;
                break;
            }
        }

        return $warehouse;
    }

    public function getProductWarehouseQty(int $warehouse_id, int $product_id)
    {
        $this->loadProductQuantities($product_id);

        return self::$_productQuantities[$product_id][$warehouse_id] ?? 0;
    }

    public function getWarehouses(): array
    {
        if (self::$_warehouses !== null) {
            return self::$_warehouses;
        }

        $data = [];

        $collection = Mage::getModel('wgmulti/warehouse')->getCollection();
        $collection->setOrder('position','ASC');

        foreach ($collection as $warehouse) {
            $data[$warehouse->getId()] = [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
                'frontend_label' => $warehouse->getFrontendLabel() ?? $warehouse->getName(),
                'frontend_label_css' => $warehouse->getFrontendLabelCss() ?? '',
                'frontend_list_label' => $warehouse->getFrontendListLabel() ?? $warehouse->getName(),
                'frontend_list_label_css' => $warehouse->getFrontendListLabelCss() ?? '',
                'frontend_description' => $warehouse->getFrontendDescription() ?? ''
            ];
        }

        self::$_warehouses = $data;

        return self::$_warehouses;
    }

    protected function loadProductQuantities(int $fallbackProductId): void
    {
        if (array_key_exists($fallbackProductId, self::$_productQuantities)) {
            return;
        }

        $productIds = [$fallbackProductId];
        $productCollection = $this->getProductCollection();
        if ($productCollection instanceof Varien_Data_Collection && $productCollection->isLoaded()) {
            $productIds = array_merge($productIds, $productCollection->getColumnValues('entity_id'));
        }

        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $productIds = array_values(array_diff($productIds, array_keys(self::$_productQuantities)));
        if (!$productIds) {
            return;
        }

        foreach ($productIds as $productId) {
            self::$_productQuantities[$productId] = [];
        }

        $collection = Mage::getResourceModel('wgmulti/warehouse_product_collection')
            ->addFieldToFilter('product_id', ['in' => $productIds]);

        foreach ($collection as $item) {
            self::$_productQuantities[(int) $item->getProductId()][(int) $item->getWarehouseId()] = $item->getQty();
        }
    }
}
