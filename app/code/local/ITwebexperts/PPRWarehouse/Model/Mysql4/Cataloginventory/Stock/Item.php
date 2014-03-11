<?php
/**
 * Innoexts
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the InnoExts Commercial License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://innoexts.com/commercial-license-agreement
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@innoexts.com so we can send you a copy immediately.
 * 
 * @category    Innoexts
 * @package     Innoexts_Warehouse
 * @copyright   Copyright (c) 2013 Innoexts (http://www.innoexts.com)
 * @license     http://innoexts.com/commercial-license-agreement  InnoExts Commercial License
 */

/**
 * Stock item resource
 *
 * @category   Innoexts
 * @package    Innoexts_Warehouse
 * @author     Innoexts Team <developers@innoexts.com>
 */
class ITwebexperts_PPRWarehouse_Model_Mysql4_Cataloginventory_Stock_Item
    extends Innoexts_Warehouse_Model_Mysql4_Cataloginventory_Stock_Item
{

    /**
     * Loading available stock item data by product
     * 
     * @param   Mage_CatalogInventory_Model_Stock_Item $item
     * @param   Mage_Catalog_Model_Product $product
     * @param   float $qty
     * 
     * @return  Innoexts_Warehouse_Model_Mysql4_Cataloginventory_Stock_Item
     */
    public function loadAvailableByProduct(Mage_CatalogInventory_Model_Stock_Item $item, $product)
    {
        $manageStock = $this->getCatalogInventoryHelper()->getManageStock();
        $adapter = $this->_getReadAdapter();
        $select = $this->_getLoadSelect('product_id', $product->getId(), $item);
        $joinConditions = array(
            $this->getMainTable().'.product_id = stock_status.product_id', 
            $this->getMainTable().'.stock_id = stock_status.stock_id', 
            'stock_status.website_id = '.$adapter->quote($product->getStore()->getWebsiteId())
        );
        $select->joinLeft(
            array('stock_status' => $this->getTable('cataloginventory/stock_status')), 
            implode(' AND ', $joinConditions), 
            array('stock_status')
        );
        $select->order(array(
            'stock_status.stock_status DESC', 
            '(IF(IF(use_config_manage_stock, '.$adapter->quote($manageStock).', manage_stock), is_in_stock, 1)) DESC'
        ));
        $select->limit(1);
        $row = $adapter->fetchRow($select);
        if($row['type_id'] != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){
            $item->setData($row);
        }else{
            $select->limit(1000);
            $rows = $adapter->fetchAll($select);

            if (Mage::registry('stock_id')) {
                $_regKey = Mage::registry('stock_id');
            }
            if(Mage::app()->getRequest()->getParam('start_date')){
                $newParams = ITwebexperts_Payperrentals_Helper_Date::saveFilteredDates(Mage::app()->getRequest()->getPost(), false);
                $_start_date = $newParams['start_date'];
                $_end_date = $newParams['end_date'];

            }

            $_productId = $product->getId();
            foreach ($rows as $_iRow) {
                $_retQty = $_iRow['qty'];
                $_stockId = $_iRow['stock_id'];
                $_minQty = 1000000;
                if(isset($_start_date) && isset($_end_date)){
                    /** @var $_pprHelper ITwebexperts_Payperrentals_Helper_Data*/
                    $_pprHelper = Mage::helper('payperrentals');
                    Mage::unregister('stock_id');
                    Mage::register('stock_id', $_stockId);
                    $bookedArray = $_pprHelper->getBookedQtyForProducts($_productId, $_start_date, $_end_date);
                    foreach ($bookedArray['booked'] as $dateFormatted => $_paramAr) {
                        if (strtotime($dateFormatted) >= strtotime($_start_date) && strtotime($dateFormatted) <= strtotime($_end_date)) {
                            if ($_minQty > ($_retQty - $_paramAr[$_productId]['qty'])) {
                                $_minQty = $_retQty - $_paramAr[$_productId]['qty'];
                            }
                        }
                    }
                    if ($_minQty == 1000000) {
                        $_minQty = $_retQty;
                    }
                }

                if($_minQty > 0){
                    $row['stock_id'] = $_stockId;
                    $row['qty'] = intval($_minQty);
                    break;
                }
            }
            if(isset($_regKey)){
                Mage::unregister('stock_id');
                Mage::register('stock_id', $_regKey);
            }
            $item->setData($row);

            //foreach stock check qty for start end dates if they are set in post?
        }
        $this->_afterLoad($item);
        return $this;
    }

}