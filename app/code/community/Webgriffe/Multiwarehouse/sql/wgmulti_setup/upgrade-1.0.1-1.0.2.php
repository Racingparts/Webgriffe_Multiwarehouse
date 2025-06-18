<?php

$installer = $this;
$installer->startSetup();

$installer->getConnection()
    ->addColumn($installer->getTable('wgmulti/warehouse'), 'frontend_label', [
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable'  => true,
        'length'    => 255,
        'after'     => 'position',
        'comment'   => 'Frontend label'
    ]);

$installer->getConnection()
    ->addColumn($installer->getTable('wgmulti/warehouse'), 'frontend_list_label_css', [
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable'  => true,
        'length'    => 255,
        'after'     => 'frontend_label',
        'comment'   => 'Frontend list label css'
    ]);

$installer->getConnection()
    ->addColumn($installer->getTable('wgmulti/warehouse'), 'frontend_description', [
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable'  => true,
        'length'    => 255,
        'after'     => 'frontend_list_label_css',
        'comment'   => 'Frontend description'
    ]);

$installer->endSetup();
