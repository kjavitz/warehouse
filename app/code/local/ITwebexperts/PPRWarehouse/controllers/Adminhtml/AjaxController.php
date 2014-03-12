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

            $order = Mage::getModel('sales/order')->load($resOrder->getOrderId());
            $order->setSendDatetime($sendTime);
            $order->save();
            $product = Mage::getModel('catalog/product')->load($sendReturn->getProductId());
            $returnPerCustomer[$order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['name'] = $product->getName();
            $returnPerCustomer[$order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['serials'] = $sendReturn->getSn();
            $returnPerCustomer[$order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['start_date'] = $sendReturn->getResStartdate();
            $returnPerCustomer[$order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['end_date'] = $sendReturn->getResEndDate();
            $returnPerCustomer[$order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['send_date'] = $sendReturn->getSendDate();
            //$returnPerCustomer[$order->getCustomerEmail()][$resOrder->getOrderId()][$product->getId()]['return_date'] = $resOrder->getReturnDate();
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
            $stockId = $this->getRequest()->getParam('stock_id');
            if (!$stockId) {
                $helper = Mage::helper('warehouse');
                $stockId = $helper->getSessionStockId() ? : $helper->getDefaultStockId();
            }
        }else{
            $stockProducts = $this->getRequest()->getParam('stock_products')?Zend_Json::decode($this->getRequest()->getParam('stock_products')):array();
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
            $childFlag = false;
            if ($this->getRequest()->getParam('childItems')) {
                $childItemsAr = Zend_Json::decode($this->getRequest()->getParam('childItems'));
                if (array_key_exists($product->getId(), $childItemsAr)) {
                    $childItemIds = explode(',', $childItemsAr[$product->getId()]);
                    $outputAvailAr = array();
                    $outputRemainingAr = array();
                    $childProductCollection = Mage::getResourceModel('catalog/product_collection')
                        ->addFieldToFilter('entity_id', array('in' => $childItemIds));
                    /** @var $childProduct Mage_Catalog_Model_Product */
                    foreach ($childProductCollection as $childProduct) {
                        $childFlag = true;
                        Mage::register('stock_id', isset($stockProducts[$childProduct->getId()])?$stockProducts[$childProduct->getId()]:$stockId);
                        $stockAvail = ITwebexperts_Payperrentals_Helper_Inventory::getQuantity($childProduct->getId(), $startDate, $endDate);
                        $childDescription = '';
                        if ($childProductCollection->getSize() > 1) {
                            $childDescription = '<strong>' . $childProduct->getSku() . ':</strong> ';
                        }
                        $qty = (isset($qtys[$product->getId()]) ? $qtys[$product->getId()] : 1);
                        $outputAvailAr[] = $childDescription . $stockAvail;
                        $outputRemainingAr[] = $childDescription . ($stockAvail - $qty);
                    }

                    if ($childFlag) {
                        $output[$product->getId()] = array(
                            'avail' => implode('<br/>', $outputAvailAr),
                            'remaining' => implode('<br/>', $outputRemainingAr)
                        );
                    }
                }
            }
            if (!$childFlag) {
                $qty = (isset($qtys[$product->getId()]) ? $qtys[$product->getId()] : 1);
                Mage::register('stock_id', isset($stockProducts[$product->getId()])?$stockProducts[$product->getId()]:$stockId);
                $stockAvail = ITwebexperts_Payperrentals_Helper_Inventory::getQuantity($product->getId(), $startDate, $endDate);
                $output[$product->getId()] = array(
                    'avail' => $stockAvail,
                    'remaining' => ($stockAvail - $qty)
                );
            }
            Mage::unregister('stock_id');
            /*Varien_Profiler::stop('child_stock_logic');*/

        }
        $this->getResponse()->setBody(Zend_Json::encode($output));
    }

    /**
     *
     */
    public function getEventsAction()
    {

        $startDate = date('Y-m-d', urldecode($this->getRequest()->getParam('start'))) . ' 00:00:00';
        $endDate = date('Y-m-d', urldecode($this->getRequest()->getParam('end'))) . ' 23:59:59';
        $productIds = explode(',', urldecode($this->getRequest()->getParam('productsids')));
        $events = array();
        $stockIds = Mage::helper('warehouse')->getStockIds();

        foreach ($stockIds as $stockId) {
            Mage::unregister('stock_id');
            Mage::register('stock_id', $stockId);
            $bookedByIds = ITwebexperts_Payperrentals_Helper_Data::getBookedQtyForProducts($productIds, $startDate, $endDate, true);
            $newArr = array();
            $configHelper = Mage::helper('payperrentals/config');

            foreach ($bookedByIds['booked'] as $dateFormatted => $productAr) {
                foreach ($productAr as $productId => $paramAr) {

                    foreach ($paramAr['orders']['order_ids'] as $incrementId) {

                        $start = strtotime($paramAr['orders']['start_end'][$incrementId]['period_start']);
                        $end = strtotime($paramAr['orders']['start_end'][$incrementId]['period_end']);

                        if (date('H:i:s', $start) != '00:00:00' || date('H:i:s', $end) != '23:59:59') {
                            $time_increment = $configHelper->getTimeIncrement() * 60;
                        } else {
                            $time_increment = 3600 * 24;
                        }
                        $iQty = $paramAr['qty'];
                        while ($start <= $end) {
                            $df = date('Y-m-d H:i', $start);

                            if (!isset($newArr[$df][$productId])) {
                                $newArr[$df][$productId]['qty'] = $iQty;
                            }

                            foreach ($newArr as $newDf => $_prod) {
                                if (date('Y-m-d', strtotime($newDf)) == date('Y-m-d', strtotime($df))) {
                                    if (isset($newArr[$newDf][$productId]['order_id']) && !in_array($incrementId, $newArr[$newDf][$productId]['order_id'])) {
                                        $newArr[$newDf][$productId]['order_id'][] = $incrementId;
                                    }
                                }
                            }
                            if (!isset($newArr[$df][$productId]['order_id']) || !in_array($incrementId, $newArr[$df][$productId]['order_id'])) {
                                $newArr[$df][$productId]['order_id'][] = $incrementId;
                            }
                            $start += $time_increment;
                        }


                    }
                }

            }

            foreach ($newArr as $dateFormatted => $productAr) {
                foreach ($productAr as $productId => $paramAr) {
                    $maxQty = ITwebexperts_Payperrentals_Helper_Inventory::getQuantity($productId);
                    if (date('H:i', strtotime($dateFormatted)) != '00:00') {
                        $time_increment = $configHelper->getTimeIncrement() * 60;
                        $start = date('Y-m-d', strtotime($dateFormatted)) . ' ' . date('H:i', strtotime($dateFormatted)) . ':00';
                        $end = date('Y-m-d', strtotime($dateFormatted)) . ' ' . date('H:i', strtotime('+' . $time_increment . ' SECOND', strtotime($dateFormatted))) . ':00';
                        $isHour = true;
                    } else {
                        $start = date('Y-m-d', strtotime($dateFormatted)) . ' 00:00:00';
                        $end = date('Y-m-d', strtotime($dateFormatted)) . ' 23:59:59';
                        $isHour = false;
                    }

                    $evb = array(
                            'title' => $paramAr['qty'] . '/' . ($maxQty - $paramAr['qty']),
                            'url' => urlencode($dateFormatted . '||' . implode(';', $paramAr['order_id']) . '||' . $productId . '||' . $stockId),
                            'start' => $start,
                            'end' => $end,
                            'qty' => $paramAr['qty'],
                            'maxqty' => ($maxQty - $paramAr['qty']),
                            'df' => date('Y-m-d', strtotime($dateFormatted)),
                            'is_hour' => $isHour,
                            'resource' => $productId . '_' . $stockId
                    );
                    if ($maxQty - $paramAr['qty'] < 0) {
                        $evb['backgroundColor'] = '#cc0000';
                        $evb['className'] = 'overbookColor';
                    }
                    $events[] = $evb;
                }
            }
        }

        $this->getResponse()->setBody(Zend_Json::encode($events));
    }


}
