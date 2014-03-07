<?php
class ITwebexperts_PPRWarehouse_Helper_Payperrentals_Inventory extends ITwebexperts_Payperrentals_Helper_Inventory {

    /**
     * @param $productId
     * @param $start_date
     * @param $end_date
     * @param $stockId
     * @return int
     */
   public static function getStockOnly($productId, $start_date, $end_date, $stockId)
    {
        $helper = Mage::helper('pprwarehouse');
        $Product = ITwebexperts_Payperrentals_Helper_Data::_initProduct($productId);
        if(ITwebexperts_Payperrentals_Helper_Data::isReservationType($productId)){
            $maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
            $bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForProducts($productId, $start_date, $end_date, 0, false, $stockId);
            $minQty = 1000000;
            foreach ($bookedArray['booked'] as $dateFormatted => $_paramAr) {
                if (strtotime($dateFormatted) >= strtotime($start_date) && strtotime($dateFormatted) <= strtotime($end_date)) {
                    if ($minQty > ($maxQty - $_paramAr[$productId]['qty'])) {
                        $minQty = $maxQty - $_paramAr[$productId]['qty'];
                    }
                }
            }

            if ($minQty == 1000000) {
                $minQty = $helper->getQtyForProductAndStock($Product, $stockId);
            }

            return $minQty;
        }else{
            return $helper->getQtyForProductAndStock($Product, $stockId);
        }
    }

}