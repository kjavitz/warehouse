<?php
/**
 *
 * @author Enrique Piatti
 */
require_once 'ITwebexperts/Payperrentals/controllers/Adminhtml/AjaxController.php';


class ITwebexperts_PPRWarehouse_Adminhtml_AjaxController extends ITwebexperts_Payperrentals_Adminhtml_AjaxController
{

    public function sendSelectedAction()
    {
        $sns = $this->getRequest()->getParam('sn');
        $resids = $this->getRequest()->getParam('sendRes');
        $returnPerCustomer = array();
        foreach ($resids as $id) {
            $resOrder = Mage::getModel('payperrentals/reservationorders')->load($id);
            $product = Mage::getModel('catalog/product')->load($resOrder->getProductId());
            $sn = array();
            if ($product->getPayperrentalsUseSerials()) {
                foreach ($sns as $sid => $serialArr) {
                    if ($sid == $id) {
                        foreach ($serialArr as $serial) {
                            if ($serial != '') {
                                //todo check if serial exists and has status A
                                $sn[] = $serial;
                            }
                        }
                    }
                }
                // what is this code for?
                if (count($sn) < $resOrder->getQty()) {
                    $coll = Mage::getModel('payperrentals/serialnumbers')
                        ->getCollection()
                        ->addEntityIdFilter($resOrder->getProductId())
                        ->addSelectFilter("NOT FIND_IN_SET(sn, '" . implode(',', $sn) . "') AND status='A'");
                    $j = 0;
                    foreach ($coll as $item) {
                        $sn[] = $item->getSn();
                        if ($j >= $resOrder->getQty() - count($sn)) {
                            break;
                        }
                        $j++;
                    }

                }

                foreach ($sn as $serial) {
                    Mage::getResourceSingleton('payperrentals/serialnumbers')
                        ->updateStatusBySerial($serial, 'O');
                }
            }
            $serialNumber = implode(',', $sn);
            $sendTime = date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time()));
            $sendReturn = Mage::getModel('payperrentals/sendreturn')
                ->setOrderId($resOrder->getOrderId())
                ->setProductId($resOrder->getProductId())
                ->setResStartdate($resOrder->getStartDate())
                ->setResEnddate($resOrder->getEndDate())
                ->setSendDate($sendTime)
                ->setReturnDate('0000-00-00 00:00:00')
                ->setQty($resOrder->getQty())//here needs a check this should always be true
                ->setSn($serialNumber)
                ->setStockId($resOrder->getStockId())
                ->save();

            // this relationship should be in the opposite side (returns has a reference to reservation order and not the resOrder to the return)
            Mage::getResourceSingleton('payperrentals/reservationorders')->updateSendReturnById($id, $sendReturn->getId());

            $_order = Mage::getModel('sales/order')->load($resOrder->getOrderId());
            $_order->setSendDatetime($sendTime);
            $_order->save();
            $product = Mage::getModel('catalog/product')->load($sendReturn->getProductId());
            $returnPerCustomer[$_order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['name'] = $product->getName();
            $returnPerCustomer[$_order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['serials'] = $sendReturn->getSn();
            $returnPerCustomer[$_order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['start_date'] = $sendReturn->getResStartdate();
            $returnPerCustomer[$_order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['end_date'] = $sendReturn->getResEndDate();
            $returnPerCustomer[$_order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['send_date'] = $sendReturn->getSendDate();
            //$returnPerCustomer[$_order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['return_date'] = $resOrder->getReturnDate();
        }

        ITwebexperts_Payperrentals_Helper_Data::sendEmail('send', $returnPerCustomer);

        $error = '';

        $results = array(
            'error' => $error
        );
        $this->getResponse()->setBody(Zend_Json::encode($results));
    }

    public function getSerialNumbersbyItemIdAction()
    {
        $query = $this->getRequest()->getParam('value');
        $oId = $this->getRequest()->getParam('oId');
        $oitem = Mage::getModel('sales/order_item')->load($oId);
        $productId = $oitem->getProductId();

        $results = array();

        $coll = Mage::getModel('payperrentals/serialnumbers')
            ->getCollection()
            ->addEntityIdFilter($productId)
            ->addSelectFilter("sn like '%" . $query . "%' AND status='A'");

        $coll->addFieldToFilter('stock_id', $oitem->getStockId());

        foreach ($coll as $item) {
            $results[] = $item->getSn();
        }

        $this->getResponse()->setBody(Zend_Json::encode($results));
    }

    public function getProductsPricesAction()
    {
        if (!$this->getRequest()->getParam('products')) {
            return;
        }
        if (!$this->getRequest()->getParam('stock_products')) {
            $_stockId = $this->getRequest()->getParam('stock_id');
            if (!$_stockId) {
                $helper = Mage::helper('warehouse');
                $_stockId = $helper->getSessionStockId() ? : $helper->getDefaultStockId();
            }
        }else{
            $_stockProducts = $this->getRequest()->getParam('stock_products');
        }

        $output = array();

        $productIds = $this->getRequest()->getParam('products')?Zend_Json::decode($this->getRequest()->getParam('products')):array();
        $dates = $this->getRequest()->getParam('dates')?Zend_Json::decode($this->getRequest()->getParam('dates')):array();
        $startDate = Mage::getSingleton('core/session')->getData('startDateInitial')?Mage::getSingleton('core/session')->getData('startDateInitial'):date('Y-m-d H:i:s');
        $endDate = Mage::getSingleton('core/session')->getData('endDateInitial')?Mage::getSingleton('core/session')->getData('endDateInitial'):date('Y-m-d H:i:s');
        $qtys = $this->getRequest()->getParam('qtys')?Zend_Json::decode($this->getRequest()->getParam('qtys')):array();

        $productCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addFieldToFilter('entity_id', array('in' => $productIds));

        /** @var $product Mage_Catalog_Model_Product */
        foreach ($productCollection AS $product) {
            if (isset($dates[$product->getId()])) {
                $arrDates = explode(',', $dates[$product->getId()]);
                $startDate = $arrDates[0];
                $endDate = $arrDates[1];
            }

            /*Varien_Profiler::start('child_stock_logic');*/
            $_childFlag = false;
            if ($this->getRequest()->getParam('childItems')) {
                $_childItemsAr = Zend_Json::decode($this->getRequest()->getParam('childItems'));
                if (array_key_exists($product->getId(), $_childItemsAr)) {
                    $_childItemIds = explode(',', $_childItemsAr[$product->getId()]);
                    $_outputAvailAr = array();
                    $_outputRemainingAr = array();
                    $_childProductCollection = Mage::getResourceModel('catalog/product_collection')
                        ->addFieldToFilter('entity_id', array('in' => $_childItemIds));
                    /** @var $_childProduct Mage_Catalog_Model_Product */
                    foreach ($_childProductCollection as $_childProduct) {
                        $_childFlag = true;
                        Mage::register('stock_id', isset($_stockProducts[$_childProduct->getId()])?$_stockProducts[$_childProduct->getId()]:$_stockId);
                        $_stockAvail = ITwebexperts_Payperrentals_Helper_Inventory::getQuantity($_childProduct->getId(), $startDate, $endDate);
                        $_childDescription = '';
                        if ($_childProductCollection->getSize() > 1) {
                            $_childDescription = '<strong>' . $_childProduct->getSku() . ':</strong> ';
                        }
                        $_qty = (isset($qtys[$product->getId()]) ? $qtys[$product->getId()] : 1);
                        $_outputAvailAr[] = $_childDescription . $_stockAvail;
                        $_outputRemainingAr[] = $_childDescription . ($_stockAvail - $_qty);
                    }

                    if ($_childFlag) {
                        $output[$product->getId()] = array(
                            'avail' => implode('<br/>', $_outputAvailAr),
                            'remaining' => implode('<br/>', $_outputRemainingAr)
                        );
                    }
                }
            }
            if (!$_childFlag) {
                $_qty = (isset($qtys[$product->getId()]) ? $qtys[$product->getId()] : 1);
                Mage::register('stock_id', isset($_stockProducts[$product->getId()])?$_stockProducts[$product->getId()]:$_stockId);
                $_stockAvail = ITwebexperts_Payperrentals_Helper_Inventory::getQuantity($product->getId(), $startDate, $endDate);
                $output[$product->getId()] = array(
                    'avail' => $_stockAvail,
                    'remaining' => ($_stockAvail - $_qty)
                );
            }
            /*Varien_Profiler::stop('child_stock_logic');*/

        }
        $this->getResponse()->setBody(Zend_Json::encode($output));
    }


}
