<?php

use Mage_Adminhtml_Block_Widget_Grid_Massaction_Abstract as MassAction;

class Webgriffe_Multiwarehouse_Block_Adminhtml_Stock_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected $_stock_item_attributes = [
        'is_in_stock',
        'manage_stock',
        'use_config_manage_stock',
        'backorders',
        'use_config_backorders',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->setId('multiwarehouse_stock_grid');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('product_filter');
    }

    protected function _getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        return Mage::app()->getStore($storeId);
    }

    protected function _prepareCollection()
    {
        $store = $this->_getStore();
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('attribute_set_id')
            ->addAttributeToSelect('type_id');

        if ($this->isModuleEnabled('Mage_CatalogInventory', 'catalog')) {
            $collection->joinField(
                'qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left',
            );
        }

        if ($store->getId()) {
            //$collection->setStoreId($store->getId());
            $adminStore = Mage_Core_Model_App::ADMIN_STORE_ID;
            $collection->addStoreFilter($store);
            $collection->joinAttribute(
                'name',
                'catalog_product/name',
                'entity_id',
                null,
                'inner',
                $adminStore,
            );
            $collection->joinAttribute(
                'custom_name',
                'catalog_product/name',
                'entity_id',
                null,
                'inner',
                $store->getId(),
            );
            $collection->joinAttribute(
                'status',
                'catalog_product/status',
                'entity_id',
                null,
                'inner',
                $store->getId(),
            );
            $collection->joinAttribute(
                'visibility',
                'catalog_product/visibility',
                'entity_id',
                null,
                'inner',
                $store->getId(),
            );
            $collection->joinAttribute(
                'price',
                'catalog_product/price',
                'entity_id',
                null,
                'left',
                $store->getId(),
            );
        } else {
            $collection->addAttributeToSelect('price');
            $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
        }

        $this->joinStockItemAttributes($collection);
        $this->joinWarehouses($collection);

        $this->setCollection($collection);

        parent::_prepareCollection();
        $this->getCollection()->addWebsiteNamesToResult();
        return $this;
    }

    protected function _addColumnFilterToCollection($column)
    {
        if ($this->getCollection()) {
            if ($column->getId() === 'websites') {
                $this->getCollection()->joinField(
                    'websites',
                    'catalog/product_website',
                    'website_id',
                    'product_id=entity_id',
                    null,
                    'left',
                );
            }
        }
        return parent::_addColumnFilterToCollection($column);
    }

    /**
     * @inheritDoc
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'entity_id',
            [
                'header' => Mage::helper('catalog')->__('ID'),
                'index' => 'entity_id',
            ],
        );

        $this->addColumn(
            'sku',
            [
                'header' => Mage::helper('catalog')->__('SKU'),
                'width' => '150px',
                'index' => 'sku',
            ],
        );

        $this->addColumn(
            'name',
            [
                'header' => Mage::helper('catalog')->__('Name'),
                'width' => '300px',
                'index' => 'name',
            ],
        );

        $store = $this->_getStore();
        if ($store->getId()) {
            $this->addColumn(
                'custom_name',
                [
                    'header' => Mage::helper('catalog')->__('Name in %s', $this->escapeHtml($store->getName())),
                    'index' => 'custom_name',
                ],
            );
        }

        if ($this->isModuleEnabled('Mage_CatalogInventory', 'catalog')) {
            $this->addColumn(
                'qty',
                [
                    'header' => Mage::helper('catalog')->__('Qty'),
                    'width' => '100px',
                    'type'  => 'number',
                    'index' => 'qty',
                ],
            );
        }

        foreach ($this->getWarehouses() as $warehouse) {
            $this->addColumn(
                'warehouse_' . $warehouse['id'],
                [
                    'header' => Mage::helper('catalog')->__($warehouse['name']),
                    'type'  => 'number',
                    'index' => 'warehouse_' . $warehouse['id'],
                    'filter_condition_callback' => [$this, 'warehouseQtyFilterCallback'],
                ],
            );
        }

        foreach ($this->_stock_item_attributes as $stock_item_attribute) {
            $this->addColumn(
                $stock_item_attribute,
                [
                    'header' => Mage::helper('catalog')->__($stock_item_attribute),
                    'index' => $stock_item_attribute,
                    'filter_condition_callback' => [$this, 'stockItemAttributeCallback'],
                ],
            );
        }

        $this->addColumn(
            'status',
            [
                'header' => Mage::helper('catalog')->__('Status'),
                'width' => '70px',
                'index' => 'status',
                'type'  => 'options',
                'options' => Mage::getSingleton('catalog/product_status')->getOptionArray(),
            ],
        );

        $this->addColumn(
            'visibility',
            [
                'header' => Mage::helper('catalog')->__('Visibility'),
                'width' => '150px',
                'index' => 'visibility',
                'type'  => 'options',
                'options' => Mage::getModel('catalog/product_visibility')->getOptionArray(),
            ],
        );

        $this->addColumn(
            'type',
            [
                'header' => Mage::helper('catalog')->__('Type'),
                'width' => '150px',
                'index' => 'type_id',
                'type'  => 'options',
                'options' => Mage::getSingleton('catalog/product_type')->getOptionArray(),
            ],
        );

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn(
                'websites',
                [
                    'header' => Mage::helper('catalog')->__('Websites'),
                    'width' => '100px',
                    'sortable'  => false,
                    'index'     => 'websites',
                    'type'      => 'options',
                    'options'   => Mage::getModel('core/website')->getCollection()->toOptionHash(),
                ],
            );
        }

        $this->addColumn(
            'action',
            [
                'type'      => 'action',
                'getter'     => 'getId',
                'actions'   => [
                    [
                        'caption' => Mage::helper('catalog')->__('Edit'),
                        'url'     => [
                            'base' => 'adminhtml/catalog_product/edit',
                            'params' => ['store' => $this->getRequest()->getParam('store')],
                        ],
                        'field'   => 'id',
                    ],
                ],

                'index'     => 'stores',
            ],
        );

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('product');

        $this->getMassactionBlock()->addItem(MassAction::DELETE, [
            'label' => Mage::helper('catalog')->__('Delete'),
            'url'  => $this->getUrl('adminhtml/catalog_product/massDelete'),
        ]);

        $statuses = Mage::getSingleton('catalog/product_status')->getOptionArray();

        array_unshift($statuses, ['label' => '', 'value' => '']);
        $this->getMassactionBlock()->addItem(MassAction::STATUS, [
            'label' => Mage::helper('catalog')->__('Change status'),
            'url'  => $this->getUrl('adminhtml/catalog_product/massStatus', ['_current' => true]),
            'additional' => [
                'visibility' => [
                    'name' => 'status',
                    'type' => 'select',
                    'class' => 'required-entry',
                    'label' => Mage::helper('catalog')->__('Status'),
                    'values' => $statuses,
                ],
            ],
        ]);

        if (Mage::getSingleton('admin/session')->isAllowed('catalog/update_attributes')) {
            $this->getMassactionBlock()->addItem(MassAction::ATTRIBUTES, [
                'label' => Mage::helper('catalog')->__('Update Attributes'),
                'url'   => $this->getUrl('adminhtml/catalog_product_action_attribute/edit', ['_current' => true]),
            ]);
        }

        Mage::dispatchEvent('adminhtml_catalog_product_grid_prepare_massaction', ['block' => $this]);
        return $this;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    public function getRowUrl($row)
    {
        return null;
    }

    protected function warehouseQtyFilterCallback($collection, $column)
    {
        if (($value = $column->getFilter()->getValue()) === null) {
            return $collection;
        }

        $index = $column->getIndex();
        $table = $collection->getResource()->getTable('wgmulti/warehouse_product');

        $value_from = $value['from'] ?? null;
        $value_to = $value['to'] ?? null;

        //$this->joinWarehouses($collection);

        if ($value_from !== null && !$value_to) {
            $sql = "{$index}.qty >= {$value_from}";
        }

        if (!$value_from && $value_to !== null) {
            $sql = "{$index}.qty <= {$value_to}";
        }

        if ($value_from !== null && $value_to !== null) {
            $sql = "{$index}.qty >= {$value_from} AND {$index}.qty <= {$value_to} ";
        }

        $collection->getSelect()->where($sql);

        return $collection;
    }

    protected function joinWarehouses($collection)
    {
        // check if already added
        $added = false;
        $from_part = $collection->getSelect()->getPart(Zend_Db_Select::FROM);

        foreach ($from_part as $key => $value) {
            if (stripos($key, 'warehouse_') !== false) {
                $added = true;
                break;
            }
        }

        if ($added) {
            return;
        }

        $warehouses = $this->getWarehouses();

        foreach ($warehouses as $warehouse) {
            $warehouse_id = $warehouse['id'];
            $alias = 'warehouse_' . $warehouse_id;

            $collection->getSelect()->joinLeft(
                [$alias => $collection->getResource()->getTable('wgmulti/warehouse_product')],
                "$alias.product_id = e.entity_id AND $alias.warehouse_id = $warehouse_id",
                [
                    "warehouse_{$warehouse_id}" => "$alias.qty",
                ],
            );
        }
    }

    protected function stockItemAttributeCallback($collection, $column)
    {
        if (($value = $column->getFilter()->getValue()) === null) {
            return $collection;
        }

        $index = $column->getIndex();

        $collection->getSelect()->where("stock_item.{$index} = {$value}");

        return $collection;
    }

    protected function joinStockItemAttributes($collection)
    {
        $table = $collection->getResource()->getTable('cataloginventory/stock_item');

        $collection->getSelect()->joinLeft(
            ['stock_item' => $table],
            "stock_item.product_id = e.entity_id AND stock_item.stock_id=1",
            $this->_stock_item_attributes,
        );
    }

    protected function getWarehouses(): array
    {
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

        return $data;
    }
}
