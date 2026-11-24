<?php

class Webgriffe_Multiwarehouse_Model_Attribute_Backend_Delivery extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    public function beforeSave($object)
    {
        $values = array();
        foreach (array('delivery_days_min', 'delivery_days_max') as $field) {
            $value = $object->getData($field);
            if ($value === '') {
                $object->setData($field, null);
                $value = null;
            } elseif ($value === false) {
                // False is Magento's store-view "Use Default Value" sentinel.
                $value = $object->getStoreId() && $object->getId()
                    ? $object->getResource()->getAttributeRawValue($object->getId(), $field, 0) : null;
                if ($value === false || $value === array()) {
                    $value = null;
                }
            }
            $values[] = $value;
        }
        list($min, $max) = $values;
        if (!Mage::getModel('wgmulti/delivery_calendar')->validateInterval($min, $max)) {
            Mage::throwException(Mage::helper('wgmulti')->__('Enter both delivery limits as whole numbers from 0 to 365, with minimum not greater than maximum.'));
        }
        return parent::beforeSave($object);
    }
}
