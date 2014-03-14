<?php
class ITwebexperts_PPRWarehouse_Helper_Payperrentals_Inventory extends ITwebexperts_Payperrentals_Helper_Inventory {

    /*
     * Function to get maximum quantity for product
     * @param Mage_Catalog_Product|int $product
     * @param $startDate
     * @param $endDate
     * @return int
     */

    public static function getQuantityForProductAndStock($product, $stockId, $origQty = -1)
    {
        if (!is_object($product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION))) {
            $source = unserialize($product->getCustomOption('info_buyRequest')->getValue());
            if (isset($source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION])) {
                $startDateval = $source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION];
                $endDateVal = $source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION];
                if (isset($source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL])) {
                    $nonSequential = $source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL];
                }
            }
        } else {
            $startDateval = $product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION)->getValue();
            $endDateVal = $product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION)->getValue();
            if (is_object($product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL))) {
                $nonSequential = $product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL)->getValue();
            }
        }

        if ($nonSequential == 1) {
            $startDateArr = explode(',', $startDateval);
            $endDateArr = explode(',', $startDateval);
        } else {
            $startDateArr = array($startDateval);
            $endDateArr = array($endDateVal);
        }
        $maxRetQty = 10000;
        foreach ($startDateArr as $count => $startDate) {
            $endDate = $endDateArr[$count];
            if($startDate && $endDate){
                if(Mage::registry('stock_id')){
                    $_regKey = Mage::registry('stock_id');
                    Mage::unregister('stock_id');
                }
                Mage::register('stock_id', $stockId);
                Mage::register('no_quote', 1);

                $maxQty = self::getQuantity($product, $startDate, $endDate);
                Mage::unregister('no_quote');
               if($maxRetQty > $maxQty){
                   $maxRetQty = $maxQty;
               }
            }
        }
        Mage::unregister('no_quote');
        if(isset($_regKey)){
            Mage::unregister('stock_id');
            Mage::register('stock_id', $_regKey);
        }

        if ($origQty > -1 && $maxRetQty > $origQty) {
            $maxRetQty = $origQty;
        }

        return $maxRetQty;
    }

}