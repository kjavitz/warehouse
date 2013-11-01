<?php

$installer = $this;

$installer->startSetup();

$tableReservationQuotes = $installer->getTable('payperrentals/reservationquotes');
$tableStock = $installer->getTable('cataloginventory/stock');
$sql = "
ALTER TABLE $tableReservationQuotes ADD COLUMN `stock_id` SMALLINT(5) UNSIGNED NOT NULL,
  ADD CONSTRAINT `FK_RESERVATION_QUOTE_STOCK_ID`
  FOREIGN KEY (`stock_id` )
  REFERENCES $tableStock (`stock_id` )
  ON DELETE CASCADE
  ON UPDATE CASCADE
, ADD INDEX `FK_RESERVATION_QUOTE_STOCK_ID_idx` (`stock_id` ASC) ;
";
$installer->run($sql);

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
$setup->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'allow_overbooking', 'is_visible', false);
/*somehow I had to execute this manually, not sure why needs more testing*/
$installer->endSetup();
