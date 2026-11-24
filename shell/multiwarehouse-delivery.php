<?php

require_once __DIR__ . '/abstract.php';

class Webgriffe_Multiwarehouse_Delivery_Shell extends Mage_Shell_Abstract
{
    public function run()
    {
        if (!$this->getArg('install')) {
            echo "Usage: php shell/multiwarehouse-delivery.php --install\n";
            return;
        }
        $setup = new Mage_Catalog_Model_Resource_Setup('wgmulti_setup');
        $setup->applyUpdates();
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_read');
        $db->resetDdlCache($resource->getTableName('wgmulti/warehouse'));
        Mage::getSingleton('eav/config')->clear();
        foreach (array('delivery_days_min', 'delivery_days_max') as $field) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $field);
            if (!$db->tableColumnExists($resource->getTableName('wgmulti/warehouse'), $field)
                || !$attribute->getId() || $attribute->getBackendType() !== 'int'
                || $attribute->getBackendModel() !== 'wgmulti/attribute_backend_delivery') {
                Mage::throwException('Delivery schema validation failed.');
            }
        }
        echo "Multiwarehouse delivery schema installed. No delivery intervals or store configuration were changed.\n";
    }
}

try {
    (new Webgriffe_Multiwarehouse_Delivery_Shell())->run();
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
