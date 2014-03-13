<?php
class ITwebexperts_PPRWarehouse_Helper_Payperrentals_Inventory extends ITwebexperts_Payperrentals_Helper_Inventory {


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
        //return true;
        //if(!Mage::registry('no_quote')){
            if($quoteItem && is_numeric($quoteItem->getId())){
                if(Mage::registry('stock_id')){
                    $regKey = Mage::registry('stock_id');
                }elseif($quoteItem->getStockId()){
                    $regKey = $quoteItem->getStockId();
                    Mage::register('stock_id',$regKey);
                }

                $collQuotes = Mage::getModel('payperrentals/reservationquotes')
                    ->getCollection()
                    ->addProductIdFilter($productId)
                    ->addSelectFilter("start_date = '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date) . "' AND end_date = '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($end_date) . "' AND quote_item_id = '" . $quoteItem->getId() . "'");

                //if(isset($regKey)){
                  //  $collQuotes->addFieldToFilter('stock_id', $regKey);
                //}

                $oldQty = 0;
                foreach ($collQuotes as $oldQuote) {
                    if(isset($regKey) && $oldQuote->getStockId() && $oldQuote->getStockId() != $regKey){
                        $oldQty += $oldQuote->getQty();
                    }
                }

                //if (Mage::app()->getRequest()->getParam('qty')) {
                  //  $oldQty = 0;
                //}
                $qty = $qty - $oldQty;
            }
            //this part should only be needed if is single warehouse

        //}
        if (self::isAllowedOverbook($productId)) {
            return true;
        }
        if(Mage::app()->getRequest()->getParam('update_cart_action') == 'update_qty' && !Mage::registry('no_quote')){
            Mage::register('no_quote', 1);
        }
        $maxQty = self::getQuantity($productId, $start_date, $end_date);
        Mage::unregister('no_quote');
        if ($maxQty < $qty) {
            return false;
        }

        return true;
    }

}