<?php

$installer = $this;
$table = $installer->getTable('wgmulti/warehouse');
foreach (array('delivery_days_min', 'delivery_days_max') as $field) {
    if (!$installer->getConnection()->tableColumnExists($table, $field)) {
        $installer->getConnection()->addColumn($table, $field, array(
            'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT, 'unsigned' => true,
            'nullable' => true, 'default' => null, 'comment' => $field,
        ));
    }
}
foreach (array('min' => 'Minimum delivery time (business days)', 'max' => 'Maximum delivery time (business days)') as $bound => $label) {
    $code = 'delivery_days_' . $bound;
    $existing = $installer->getAttribute('catalog_product', $code);
    if ($existing && ($existing['backend_type'] !== 'int' || $existing['backend_model'] !== 'wgmulti/attribute_backend_delivery')) {
        throw new RuntimeException('An unrelated delivery attribute already exists: ' . $code);
    }
    if (!$installer->getAttributeId('catalog_product', $code)) {
        $installer->addAttribute('catalog_product', $code, array(
            'type' => 'int', 'input' => 'text', 'label' => $label,
            'backend' => 'wgmulti/attribute_backend_delivery',
            'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
            'required' => false, 'user_defined' => true, 'visible' => true,
            'group' => 'Delivery estimate', 'apply_to' => 'simple',
            'frontend_class' => 'validate-digits', 'default' => null,
            'note' => 'Optional pair, 0-365 business days. Used only when this product has no warehouse rows.',
        ));
    }
}
