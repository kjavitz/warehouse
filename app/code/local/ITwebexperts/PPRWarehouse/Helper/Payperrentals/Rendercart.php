<?php
/**
 * Created by PhpStorm.
 * User: cristian
 * Date: 09/01/14
 * Time: 14:38
 */
class ITwebexperts_PPRWarehouse_Helper_Payperrentals_Rendercart extends ITwebexperts_Payperrentals_Helper_Rendercart
{
    public function checkAvailability($Product, $start_date, $end_date, $quoteID, $qty, $maxQty, $quoteItemId = 0, $stockId){
        $bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForProducts($Product->getId(), $start_date, $end_date, $quoteID, false, $stockId);

        $coll5 = Mage::getModel('payperrentals/reservationquotes')
            ->getCollection()
            ->addProductIdFilter($Product->getId())
            ->addSelectFilter("start_date = '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date)."' AND end_date = '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($end_date)."' AND quote_item_id = '".$quoteItemId."'");

        $oldQty = 0;
        foreach($coll5 as $oldQuote){
            $oldQty = $oldQuote->getQty();
        }

        if (Mage::app()->getRequest()->getParam('qty')) {
            $oldQty = 0;
        }

        //this function needs a better check.
        //after that remains only to save the event and gate into observer and in the function reserveOrder -done
        //then on checkout add the setting to remove shipping -done
        //then add the pprbox
        //then check admin for nonsequential and the settings for hiding address

        foreach ($bookedArray['booked'] as $dateFormatted => $_paramAr) {
            if ($maxQty - $_paramAr[$Product->getId()]['qty']  < $qty - $oldQty) {
                return false;
            }

        }
        return true;
    }

    public function prepareForCartAdvanced(Varien_Object $buyRequest, $product = null, $processMode = null, $productType = 'simple'){
        if (!ITwebexperts_Payperrentals_Helper_Data::isAllowedRenting()) {
            return Mage::helper('payperrentals')->__('You are not allowed renting. Please login on CNH');
        }
        if($productType != 'simple' && $product->getIsReservation() == ITwebexperts_Payperrentals_Model_Product_Isreservation::STATUS_DISABLED){
            return 'call_parent';
        }else{
            if ($buyRequest->getIsReservation() == ITwebexperts_Payperrentals_Model_Product_Isreservation::STATUS_RENTAL) {
                return ITwebexperts_Payperrentals_Helper_Data::addProductToQueue($product, $buyRequest);
            } else {
                if( ! $buyRequest->getStartDate() /*|| ($buyRequest->getGlobalDatesNot())*/)
                {
                    if(ITwebexperts_Payperrentals_Helper_Data::isUsingGlobalDates($product))
                    {
                        $globalDates = ITwebexperts_Payperrentals_Helper_Data::getCurrentGlobalDates();
                        if($globalDates){
                            $buyRequest->setStartDate($globalDates['start_date']);
                            $buyRequest->setEndDate($globalDates['end_date']);
                        }
                        elseif(Mage::getSingleton('core/session')->getData('startDateInitial')){
                            $buyRequest->setStartDate(Mage::getSingleton('core/session')->getData('startDateInitial'));
                            if(Mage::getSingleton('core/session')->getData('endDateInitial')){
                                $buyRequest->setEndDate(Mage::getSingleton('core/session')->getData('endDateInitial'));
                            }
                        }else{
                            return 'call_parent';
                        }
                    }
                }
                //TODO how should comport when no dates were previously selected. Should add to cart with min days difference? or should ask for the dates. Maybe check if global dates is enabled?

                Mage::dispatchEvent('prepare_advanced_before',array('buy_request' => $buyRequest, 'productType' => $productType, 'product' => $product));

                if ($buyRequest->getStartDate() && $buyRequest->getStartDate() != $buyRequest->getEndDate()) {
                    $_useNonsequential = ITwebexperts_Payperrentals_Helper_Data::useNonSequential();

                    if (!$buyRequest->getEndDate()) {
                        $buyRequest->setEndDate($buyRequest->getStartDate());
                    }
                    if(!$_useNonsequential){
                        if(!$buyRequest->getIsFiltered()){
                            $params = array('start_date' => $buyRequest->getStartDate(), 'end_date' => $buyRequest->getEndDate());
                            $params = ITwebexperts_Payperrentals_Helper_Data::filterDates($params, true);
                            $startingDateFiltered = $params['start_date'];
                            $endingDateFiltered = $params['end_date'];
                            $buyRequest->setStartDate($startingDateFiltered);
                            $buyRequest->setEndDate($endingDateFiltered);
                            $buyRequest->setIsFiltered(true);
                        }else{
                            $params = array('start_date' => $buyRequest->getStartDate(), 'end_date' => $buyRequest->getEndDate());
                            $startingDateFiltered = $params['start_date'];
                            $endingDateFiltered = $params['end_date'];
                        }
                    }elseif(!$buyRequest->getIsFiltered()){
                        $params = array('start_date' => $buyRequest->getStartDate(), 'end_date' => $buyRequest->getEndDate());
                        $startingDateFiltered = '';
                        $allDates = explode(',', $buyRequest->getStartDate());
                        $nrVal = count($allDates) - 1;
                        foreach($allDates as $key => $iDate){
                            $paramsArr = array('idate' => $iDate);
                            $paramsArr = ITwebexperts_Payperrentals_Helper_Data::filterDates($paramsArr, true);
                            if($key != $nrVal){
                                $startingDateFiltered .= $paramsArr['idate'].',';
                            }else{
                                $startingDateFiltered .= $paramsArr['idate'];
                            }
                        }
                        $endingDateFiltered = $startingDateFiltered;
                        $buyRequest->setStartDate($startingDateFiltered);
                        $buyRequest->setEndDate($endingDateFiltered);
                        $buyRequest->setIsFiltered(true);
                    }else{
                        $params = array('start_date' => $buyRequest->getStartDate(), 'end_date' => $buyRequest->getEndDate());
                        $startingDateFiltered = $params['start_date'];
                        $endingDateFiltered = $params['end_date'];
                    }

                    $product->addCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION, $startingDateFiltered, $product);
                    $product->addCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION, $endingDateFiltered, $product);


                    if($_useNonsequential){
                        $product->addCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL, 1, $product);
                        $buyRequest->setNonSequential(1);
                    }else{
                        $product->addCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL, 0, $product);
                        $buyRequest->setNonSequential(0);
                    }

                    if($productType != 'bundle'){
                        if($productType == 'simple'){
                            if ($product->getSelectionQty() && $product->getParentProductId()) {
                                $bqty = $buyRequest->getQty() * $product->getSelectionQty();
                            } else {
                                $bqty = $buyRequest->getQty();
                            }

                            $_bundleAllowOverbooking = false;
                            if ($buyRequest->getBundleOption()) {
                                $_bundleProduct = Mage::getModel('catalog/product')->load($buyRequest->getProduct());
                                $_bundleAllowOverbooking = ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($_bundleProduct);
                            }
                            $isAvail = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailableWithQuote($product, $bqty, $_bundleAllowOverbooking);
                        }else{
                            $isAvail = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailableWithQuote($product, $buyRequest->getQty(), false, $buyRequest->getSuperAttribute());
                        }
                        if (!$isAvail) {
                            if (!Mage::app()->getStore()->isAdmin()) {
                                Mage::throwException(
                                    Mage::helper('payperrentals')->__("Chosen quantity is not available")
                                );
                            } else {
                                Mage::getSingleton('core/session')->addError(Mage::helper('payperrentals')->__("Chosen quantity is not available"));
                                $urlRequest = Mage::app()->getFrontController()->getRequest();
                                Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl('adminhtml/sales_order/view/', array('order_id' => $urlRequest->getParam('order_id'), 'key' => Mage::getSingleton('adminhtml/url')->getSecretKey('sales_order', 'view'))))->sendResponse();
                                die();
                                //redirect
                            }

                        }
                    }
                    if (Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_USE_GLOBAL_DAYS) == 1) {
                        if(!$_useNonsequential){
                            Mage::getSingleton('core/session')->setData('startDateInitial', $startingDateFiltered);
                            Mage::getSingleton('core/session')->setData('endDateInitial', $endingDateFiltered);
                        }else{
                            Mage::getSingleton('core/session')->setData('startDateInitial', $params['start_date']);
                        }
                    }

                    return 'call_parent';
                }
                return Mage::helper('payperrentals')->__('Please specify reservation information');
            }
        }
    }

}