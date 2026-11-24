<?php

class Webgriffe_Multiwarehouse_Model_System_Config_Backend_Calendar extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        $calendar = Mage::getModel('wgmulti/delivery_calendar');
        $value = trim((string)$this->getValue());
        if (substr($this->getPath(), -6) === 'cutoff') {
            if (!$calendar->validCutoff($value)) {
                Mage::throwException(Mage::helper('wgmulti')->__('Use HH:MM for the daily cutoff or leave it empty.'));
            }
        } else {
            try {
                $calendar->excludedDates($value);
            } catch (InvalidArgumentException $e) {
                Mage::throwException(Mage::helper('wgmulti')->__($e->getMessage()));
            }
        }
        $this->setValue($value);
        return parent::_beforeSave();
    }
}
