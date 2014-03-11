<?php
/**
 *
 * @author Enrique Piatti
 */
class ITwebexperts_PPRWarehouse_Model_Observer
{
	/**
	 * total_qty_ordered is wrong when Warehouse module splits the order by Warehouse (this is a bug in Warehouse)
	 * @param $event
	 */
	public function salesOrderSaveBefore($event)
	{
		/** @var Mage_Sales_Model_Order $order */
		$order = $event->getOrder();
		$realTotalQtyOrdered = 0; // $order->getTotalQtyOrdered();
		foreach($order->getAllVisibleItems() as $visibleItem){
			/** @var $visibleItem Mage_Sales_Model_Order_Item */
			$realTotalQtyOrdered += $visibleItem->getQtyOrdered();
		}
		$order->setTotalQtyOrdered($realTotalQtyOrdered);
	}

    public function beforeFilterOrder($_observer){
        $_collection = $_observer->getEvent()->getCollection();

        if (Mage::app()->getRequest()->getParam('stock_id')) {
            $_stockId = Mage::app()->getRequest()->getParam('stock_id');
        } elseif (Mage::registry('stock_id')) {
            $_stockId = Mage::registry('stock_id');
        }
        if(isset($_stockId)){
            $_collection->addFieldToFilter('main_table.stock_id', $_stockId);
        }
    }

    public function calendarNewElements($observer){

        $returnObj = $observer->getEvent()->getResult();
        $templateNewElements = 'pprwarehouse/calendar/new_elements.phtml';
        $_jsContainerPrefix =  $observer->getEvent()->getJsContainerPrefix();
        $_jsFunctionPrefix = $observer->getEvent()->getJsFunctionPrefix();
        $isAdminGlobal = $observer->getEvent()->getIsAdminGlobal();
        $isAdmin = $observer->getEvent()->getIsAdmin();
        $quoteItemId = $observer->getEvent()->getQuoteItemId();
        $quoteItem = $observer->getEvent()->getQuoteItem();

        $return = Mage::app()->getLayout()
            ->createBlock("core/template")
            ->setData('area','frontend')
            ->setData('jsContainerPrefix', $_jsContainerPrefix)
            ->setData('jsFunctionPrefix', $_jsFunctionPrefix)
            ->setData('isAdminGlobal', $isAdminGlobal)
            ->setData('isAdmin', $isAdmin)
            ->setData('quoteItemId', $quoteItemId)
            ->setData('quoteItem', $quoteItem)
            ->setTemplate($templateNewElements)
            ->toHtml();

        $returnObj->setReturn($return);
    }

    public function optionsGridNames($observer){

        $returnObj = $observer->getEvent()->getResult();
        $return = '';
        $return .= '<th class="no-link">'. Mage::helper('pprwarehouse')->__('Warehouse'). '</th>';
        $returnObj->setReturn($return);
    }

    public function optionsGridCols($observer){

        $returnObj = $observer->getEvent()->getResult();
        $return = '';
        $return .= '<col />';
        $returnObj->setReturn($return);
    }

    public function optionsGridButtons($observer){

        $returnObj = $observer->getEvent()->getResult();
        $classCall = $observer->getEvent()->getClass();
        $return = '';
        $helper = Mage::helper('warehouse');
        $config = $helper->getConfig();
        if ($config->isMultipleMode()){
            $return .= $classCall->getButtonHtml(Mage::helper('pprwarehouse')->__('Reset Items'),'order.itemsReset()');
        }
        $returnObj->setReturn($return);
    }




    public function optionsGrid($observer){

        $returnObj = $observer->getEvent()->getResult();
        $_item = $observer->getEvent()->getItem();
        $helper = Mage::helper('warehouse');
        $config = $helper->getConfig();

        $return = '<td class="a-left v-middle">';
        if ($config->isMultipleMode()){
            $return .= '<select name="item['.$_item->getId().'][stock_id]" title="'. $helper->__('Warehouse') .'">';
            $warehouses = $_item->getInStockWarehouses();
            foreach ($warehouses as $warehouse){
                $return .= '<option value="'. $warehouse->getStockId().'" '. (($warehouse->getStockId() == $_item->getStockId())?'selected="selected"':'').'>'.$warehouse->getTitle() .'</option>';
            }
            $return .= '</select>';
        }else{
            if ($_item->getWarehouse())
                $return .= $_item->getWarehouseTitle();
            else
                $return .= $helper->__('No warehouse');
        }


        $return .= '</td>';
        $returnObj->setReturn($return);
    }

    //this function gets the max quantity from all the stocks. But not sure this is ok.
    public function getMaxQuantity($observer){

        $_product = $observer->getEvent()->getProduct();
        $_start_date = $observer->getEvent()->getStartDate();
        $_end_date = $observer->getEvent()->getEndDate();
        $_retObj = $observer->getEvent()->getResult();
        if (!is_object($_product)) {
            $_product = ITwebexperts_Payperrentals_Helper_Data::_initProduct($_product);
        }
        $_productId = $_product->getId();
        $_stockArr = array();
        if (Mage::app()->getRequest()->getParam('stock_id')) {
            $_stockArr[] = Mage::app()->getRequest()->getParam('stock_id');
        } elseif (Mage::registry('stock_id')) {
            $_stockArr[] = Mage::registry('stock_id');
            $regKey = Mage::registry('stock_id');
        } else {
            $helper = Mage::helper('pprwarehouse');
            $_stockArr = $helper->getValidStockIds();
        }
        if(ITwebexperts_Payperrentals_Helper_Data::isReservationType($_product)){
            $_minRetQty = 1000000;
            foreach ($_stockArr as $_stockId) {
                $_retQty = Mage::helper('pprwarehouse')->getQtyForProductAndStock($_product, $_stockId);

                if(!is_null($_start_date) && !is_null($_end_date)){
                    /** @var $_pprHelper ITwebexperts_Payperrentals_Helper_Data*/
                    $_pprHelper = Mage::helper('payperrentals');
                    Mage::unregister('stock_id');
                    Mage::register('stock_id', $_stockId);
                    $bookedArray = $_pprHelper->getBookedQtyForProducts($_productId, $_start_date, $_end_date);
                    $_minQty = 1000000;
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
                    $_retQty = $_minQty;
                }
                if($_minRetQty > $_retQty){
                    $_minRetQty = $_retQty;
                }
            }
            if ($_minRetQty == 1000000) {
                $_minRetQty = $_retQty;
            }
            $_retQty = $_minRetQty;
            $_retObj->setRetQty($_retQty);
        }else{
            $_minRetQty = 1000000;
            foreach ($_stockArr as $_stockId) {
                $_retQty = Mage::helper('pprwarehouse')->getQtyForProductAndStock($_product, $_stockId);
                if($_minRetQty > $_retQty){
                    $_minRetQty = $_retQty;
                }
            }
            $_retQty = $_minRetQty;
            $_retObj->setRetQty($_retQty);
        }
        if(isset($regKey)){
            Mage::unregister('stock_id');
            Mage::register('stock_id', $regKey);
        }

    }

    public function setStock($_observer){
        $_resOrder = $_observer->getEvent()->getResOrder();
        $_item = $_observer->getEvent()->getItem();
        $_resOrder->setStockId($_item->getStockId());
    }
}
