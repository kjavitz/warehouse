<?php
class ITwebexperts_PPRWarehouse_Helper_Payperrentals_Inventory extends ITwebexperts_Payperrentals_Helper_Inventory {

    /**
     * Function to get if product is available between dates
     * @param $_productId
     * @param int $_qty
     * @param $_start_date
     * @param $_end_date
     * @return bool
     */
    public function isAvailable($_productId, $_start_date, $_end_date, $_qty = 1, $_quoteItem = false)
    {
        return true;
        if($_quoteItem){
            $_coll5 = Mage::getModel('payperrentals/reservationquotes')
                ->getCollection()
                ->addProductIdFilter($_productId)
                ->addSelectFilter("start_date = '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_start_date) . "' AND end_date = '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_end_date) . "' AND quote_item_id = '" . $_quoteItem->getId() . "'");

            $_oldQty = 0;
            foreach ($_coll5 as $oldQuote) {
                $_oldQty = $oldQuote->getQty();
            }

            if (Mage::app()->getRequest()->getParam('qty')) {
                $_oldQty = 0;
            }
            $_qty = $_qty - $_oldQty;
        }
        if($_quoteItem && $_quoteItem->getStockId()){
            if(Mage::registry('stock_id')){
                $_regKey = Mage::registry('stock_id');
                Mage::unregister('stock_id');
            }
            Mage::register('stock_id', $_quoteItem->getStockId());
        }
        if (self::isAllowedOverbook($_productId)) {
            if(isset($_regKey)){
                Mage::unregister('stock_id');
                Mage::register('stock_id',$_regKey);
            }
            return true;
        }
        $maxQty = self::getQuantity($_productId, $_start_date, $_end_date);

        if ($maxQty < $_qty) {
            if(isset($_regKey)){
                Mage::unregister('stock_id');
                Mage::register('stock_id',$_regKey);
            }
            return false;
        }
        if(isset($_regKey)){
            Mage::unregister('stock_id');
            Mage::register('stock_id',$_regKey);
        }
        return true;
    }

}