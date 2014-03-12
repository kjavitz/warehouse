<?php
class ITwebexperts_PPRWarehouse_Helper_Payperrentals_Inventory extends ITwebexperts_Payperrentals_Helper_Inventory {

    /**
     * Function to get the available quantity between dates
     * @param $productId
     * @param int $qty
     * @param $start_date
     * @param $end_date
     * @return bool
     */
    public static function availableQty($productId, $start_date, $end_date, $quoteItem = false)
    {
        /*$oldQty = 0;
        if($quoteItem && is_numeric($quoteItem->getId())){
            $collQuotes = Mage::getModel('payperrentals/reservationquotes')
                    ->getCollection()
                    ->addProductIdFilter($productId)
                    ->addSelectFilter("start_date = '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date) . "' AND end_date = '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($end_date) . "' AND quote_item_id = '" . $quoteItem->getId() . "'");

            foreach ($collQuotes as $oldQuote) {
                $oldQty = $oldQuote->getQty();
            }

            if (Mage::app()->getRequest()->getParam('qty')) {
                $oldQty = 0;
            }
        }*/
        //this part should only be needed if is single warehouse
       /* if($quoteItem && $quoteItem->getStockId()){
            if(Mage::registry('stock_id')){
                $regKey = Mage::registry('stock_id');
                Mage::unregister('stock_id');
            }
            Mage::register('stock_id', $quoteItem->getStockId());
        }*/

        if (self::isAllowedOverbook($productId)) {
           /* if(isset($regKey)){
                Mage::unregister('stock_id');
                Mage::register('stock_id',$regKey);
            }*/
            return 1000;//this should be somehow modified
        }
        Mage::register('no_quote', 1);
        $maxQty = self::getQuantity($productId, $start_date, $end_date);
        Mage::unregister('no_quote');

        /*if(isset($regKey)){
            Mage::unregister('stock_id');
            Mage::register('stock_id',$regKey);
        }*/
        return ($maxQty );
    }

    /**
     * Function to get if product is available between dates
     * @param $productId
     * @param int $qty
     * @param $start_date
     * @param $end_date
     * @return bool
     */
    public function isAvailable($productId, $start_date, $end_date, $qty, $quoteItem = false)
    {
        if($quoteItem){
            $_coll5 = Mage::getModel('payperrentals/reservationquotes')
                ->getCollection()
                ->addProductIdFilter($productId)
                ->addSelectFilter("start_date = '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date) . "' AND end_date = '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($end_date) . "' AND quote_item_id = '" . $quoteItem->getId() . "'");

            $_oldQty = 0;
            foreach ($_coll5 as $oldQuote) {
                $_oldQty = $oldQuote->getQty();
            }

            if (Mage::app()->getRequest()->getParam('qty')) {
                $_oldQty = 0;
            }
            $qty = $qty - $_oldQty;
        }
        //this part should only be needed if is single warehouse
        if($quoteItem && $quoteItem->getStockId()){
            if(Mage::registry('stock_id')){
                $_regKey = Mage::registry('stock_id');
                Mage::unregister('stock_id');
            }
            Mage::register('stock_id', $quoteItem->getStockId());
        }
        if (self::isAllowedOverbook($productId)) {
            if(isset($_regKey)){
                Mage::unregister('stock_id');
                Mage::register('stock_id',$_regKey);
            }
            return true;
        }
        $maxQty = self::getQuantity($productId, $start_date, $end_date);

        if ($maxQty < $qty) {
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