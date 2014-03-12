<?php
require_once 'ITwebexperts/Payperrentals/controllers/AjaxController.php';


class ITwebexperts_PPRWarehouse_AjaxController extends ITwebexperts_Payperrentals_AjaxController
{

    /**
     * Returns the array of booked days, for the quantity and selected dates
     * @return array
     */

    public function updateBookedForProductAction()
    {
        if(!$this->getRequest()->getParam('product_id')){
            $bookedHtml = array(
                    'bookedDates' => '',
                    'isDisabled' => true,
                    'partiallyBooked' => ''
            );
            $this->getResponse()->setBody(Zend_Json::encode($bookedHtml));
            return;
        }
        $product = ITwebexperts_Payperrentals_Helper_Data::initProduct($this->getRequest()->getParam('product_id'));//todo might not be necessary

        $qty = $this->getRequest()->getParam('qty')?$this->getRequest()->getParam('qty'):1;
        $isDisabled = false;
        $attributes = $this->getRequest()->getParam('super_attribute')?$this->getRequest()->getParam('super_attribute'):null;
        $bundleOptions = $this->getRequest()->getParam('bundle_option')?$this->getRequest()->getParam('bundle_option'):null;
        $bundleOptionsQty1 = $this->getRequest()->getParam('bundle_option_qty1')?$this->getRequest()->getParam('bundle_option_qty1'):null;
        $bundleOptionsQty = $this->getRequest()->getParam('bundle_option_qty')?$this->getRequest()->getParam('bundle_option_qty'):null;

        $qtyArr = ITwebexperts_Payperrentals_Helper_Data::getQuantityArrayForProduct($product, $qty, $attributes, $bundleOptions, $bundleOptionsQty1, $bundleOptionsQty, true);
        $maxQtyArr = array();
        foreach($qtyArr as $iProduct => $iQty){
            $maxQty = ITwebexperts_Payperrentals_Helper_Inventory::getQuantity($iProduct);
            $maxQtyArr[$iProduct] = $maxQty;
            if($maxQty < $iQty){
                $isDisabled = true;
            }
        }
        $bookedDates = array();
        $partiallyBookedDates = array();
        $helper = Mage::helper('pprwarehouse');
        $stockArr = $helper->getValidStockIds();

        if(!$isDisabled){
            $resProductArrIds = ITwebexperts_Payperrentals_Helper_Data::getReservationProductsArrayIds($product->getId());
            foreach ($stockArr as $stockId) {
                $tempBookedDates = array();
                Mage::unregister('stock_id');
                Mage::register('stock_id', $stockId);
                $booked = ITwebexperts_Payperrentals_Helper_Data::getBookedQtyForProducts($resProductArrIds);
                $partiallyBooked = $this->_prepareBookedArray($booked);//todo this part needs a check
                $partiallyBookedDates = $partiallyBooked['partiallyBooked'];

                foreach($booked['booked'] as $dateFormatted => $paramArr){
                    foreach($paramArr as $productId => $resArr){
                        if($maxQtyArr[$productId] - $resArr['qty'] < $qty){
                            $tempBookedDates[] = $dateFormatted;
                        }
                    }

                }
                $bookedDates = array_intersect($bookedDates, $tempBookedDates);
            }
        }
        $bookedHtml = array(
                'bookedDates' => implode(',', array_unique($bookedDates)),
                'isDisabled' => $isDisabled,
                'partiallyBooked' => $partiallyBookedDates
        );

        Mage::dispatchEvent('ppr_get_booked_qty_for_products', array('request_params' => $this->getRequest()->getParams(), 'booked_html' => &$bookedHtml, 'booked' => $booked, 'product' => $product));

        $this->getResponse()->setBody(Zend_Json::encode($bookedHtml));
    }
}