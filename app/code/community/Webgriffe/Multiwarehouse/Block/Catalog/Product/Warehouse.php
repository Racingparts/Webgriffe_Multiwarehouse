<?php

class Webgriffe_Multiwarehouse_Block_Catalog_Product_Warehouse extends Mage_Core_Block_Template
{
    public function getListLabelHtml(Mage_Catalog_Model_Product $product): string
    {
        $warehouse = $this->getFirstAvailableWarehouse($product);

        if (!$warehouse) {
            $is_salable = $product->getIsSalable();

            $label = $is_salable ? $this->__('Orderable') : $this->__('Out of stock');
            $label_css = $is_salable ? 'background-color:yellow' : 'background-color:grey;color:white';

            $warehouse = [
                'frontend_label' => $label,
                'frontend_list_label_css' => $label_css
            ];
        }

        $label = $warehouse['frontend_label'];
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
        $item = Mage::getModel('wgmulti/warehouse_product')->getCollection()
            ->addFieldToFilter('warehouse_id', $warehouse_id)
            ->addFieldToFilter('product_id', $product_id)
            ->getFirstItem();

        return $item ? $item->getQty() : 0;
    }

    public function getWarehouses(): array
    {
        $data = [];

        $collection = Mage::getModel('wgmulti/warehouse')->getCollection();
        $collection->setOrder('position','ASC');

        foreach ($collection as $warehouse) {
            $data[$warehouse->getId()] = [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
                'frontend_label' => $warehouse->getFrontendLabel(),
                'frontend_list_label_css' => $warehouse->getFrontendListLabelCss(),
                'frontend_description' => $warehouse->getFrontendDescription()
            ];
        }

        return $data;
    }
}
