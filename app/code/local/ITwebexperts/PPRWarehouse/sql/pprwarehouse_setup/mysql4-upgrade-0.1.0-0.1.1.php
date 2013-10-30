<?php

$installer = $this;

$installer->startSetup();

$installer->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'allow_overbooking', 'is_visible', false);
/*somehow I had to execute this manually, not sure why needs more testing*/
$installer->endSetup();
