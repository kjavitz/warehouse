<?php
/**
 *
 * @author Enrique Piatti
 */
/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = $this;

$installer->startSetup();
$catalogEntity = Mage_Catalog_Model_Product::ENTITY;
// hide this attribute, we are using the Inventory Tab
$installer->updateAttribute($catalogEntity, 'payperrentals_quantity', 'is_visible', false);
$installer->updateAttribute($catalogEntity, 'payperrentals_use_serials', 'is_visible', false);
$installer->updateAttribute($catalogEntity, 'res_serialnumbers', 'is_visible', false);

// add stock_id column to serial number table
$tableSerialNumber = $installer->getTable('payperrentals/serialnumbers');
$tableStock = $installer->getTable('cataloginventory/stock');
$sql = "
ALTER TABLE $tableSerialNumber ADD COLUMN `stock_id` SMALLINT(5) UNSIGNED NOT NULL  AFTER `status` ,
  ADD CONSTRAINT `FK_SERIAL_NUMBER_STOCK_ID`
  FOREIGN KEY (`stock_id` )
  REFERENCES $tableStock (`stock_id` )
  ON DELETE CASCADE
  ON UPDATE CASCADE
, ADD INDEX `FK_SERIAL_NUMBER_STOCK_ID_idx` (`stock_id` ASC) ;
";
$installer->run($sql);

$tableSendReturn = $installer->getTable('payperrentals/sendreturn');
$sql = "
ALTER TABLE $tableSendReturn ADD COLUMN `stock_id` SMALLINT(5) UNSIGNED NOT NULL,
  ADD CONSTRAINT `FK_SEND_RETURN_STOCK_ID`
  FOREIGN KEY (`stock_id` )
  REFERENCES $tableStock (`stock_id` )
  ON DELETE CASCADE
  ON UPDATE CASCADE
, ADD INDEX `FK_SEND_RETURN_STOCK_ID_idx` (`stock_id` ASC) ;
";
$installer->run($sql);

$tableReservationOrders = $installer->getTable('payperrentals/reservationorders');
$sql = "
ALTER TABLE $tableReservationOrders ADD COLUMN `stock_id` SMALLINT(5) UNSIGNED NOT NULL,
  ADD CONSTRAINT `FK_RESERVATION_ORDER_STOCK_ID`
  FOREIGN KEY (`stock_id` )
  REFERENCES $tableStock (`stock_id` )
  ON DELETE CASCADE
  ON UPDATE CASCADE
, ADD INDEX `FK_RESERVATION_ORDER_STOCK_ID_idx` (`stock_id` ASC) ;
";
$installer->run($sql);

// I'm not sure if we need this
//$tableRentalQueue = $installer->getTable('payperrentals/rentalqueue');
//$sql = "
//ALTER TABLE $tableRentalQueue ADD COLUMN `stock_id` SMALLINT(5) UNSIGNED NOT NULL,
//  ADD CONSTRAINT `FK_RENTAL_QUEUE_STOCK_ID`
//  FOREIGN KEY (`stock_id` )
//  REFERENCES $tableStock (`stock_id` )
//  ON DELETE CASCADE
//  ON UPDATE CASCADE
//, ADD INDEX `FK_RENTAL_QUEUE_STOCK_ID_idx` (`stock_id` ASC) ;
//";
//$installer->run($sql);

$installer->endSetup();
