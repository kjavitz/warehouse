<?php
/**
 *
 * @author Enrique Piatti
 */
require_once 'ITwebexperts/Payperrentals/controllers/Adminhtml/AjaxController.php';


class ITwebexperts_PPRWarehouse_Adminhtml_AjaxController extends ITwebexperts_Payperrentals_Adminhtml_AjaxController
{

    /**
     * override for setting the stock_id (we won't need this after refactoring PPR)
     * I think this action is not used anymore
     */
    /*updated*/
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
    /*updated*/
    public function getPriceandavailabilityAction()
    {
        if (!$this->getRequest()->getParam('product_id')) {
            $price = array(
                'amount' => -1,
                'available' => false,
                'stockAvail' => 0,
                'stockRest' => 0,
                'stockAvailText' => Mage::helper('payperrentals')->__('This product not available in store'),
                'stockRestText' => Mage::helper('payperrentals')->__('This product not available in store'),
                'maxqty' => 0,
                'formatAmount' => -1
            );

            $this->getResponse()->setBody(Zend_Json::encode($price));
            return;
        }

        $stockId = $this->getRequest()->getParam('stock_id');
        if (!$stockId) {
            /** @var Innoexts_Warehouse_Helper_Data $helper */
            $helper = Mage::helper('warehouse');
            $stockId = $helper->getSessionStockId() ? : $helper->getDefaultStockId();
        }

        $Product = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id'));
        if ($Product->isConfigurable()) {
            $Product = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($this->getRequest()->getParam('super_attribute'), $Product);
        }
        if (!is_object($Product)) {
            $price = array(
                'amount' => -1,
                'available' => false,
                'stockAvail' => 0,
                'stockRest' => 0,
                'stockAvailText' => Mage::helper('payperrentals')->__('Please select configuration'),
                'stockRestText' => Mage::helper('payperrentals')->__('Please select configuration'),
                'maxqty' => 0,
                'formatAmount' => -1
            );

            $this->getResponse()->setBody(Zend_Json::encode($price));
            return;
        }
        $qty = urldecode($this->getRequest()->getParam('qty'));
        if (!$qty) {
            $qty = 1;
        }
        $qty1 = $qty;
        $customerGroup = ITwebexperts_Payperrentals_Helper_Data::getCustomerGroup();

        $startingDate = urldecode($this->getRequest()->getParam('start_date'));
        $endingDate = urldecode($this->getRequest()->getParam('end_date'));
        $stockAvail = 0;
        $_bundleOverbooking = false;
        if ($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE || $Product->getBundlePricingtype() == ITwebexperts_Payperrentals_Model_Product_Bundlepricingtype::PRICING_BUNDLE_FORALL) {
            /*if (is_object($Product)) {*/
            $Product = Mage::getModel('catalog/product')->load($Product->getId());
            $priceAmount = ITwebexperts_Payperrentals_Helper_Price::calculatePrice($Product, $startingDate, $endingDate, $qty, $customerGroup);
            /*} else {
                $priceAmount = -1;
            }*/

            /** Not used part of code*/
            /*if ($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE) {
                $isAvailableArr = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($Product, $qty, $startingDate, $endingDate);
                $isAvailable = $isAvailableArr['avail'];
                $maxQty = $isAvailableArr['maxqty'];

            } else*/
            if ($this->getRequest()->getParam('bundle_option')) {
                $selectionIds = $this->getRequest()->getParam('bundle_option');
                $selectedQtys = $this->getRequest()->getParam('bundle_option_qty');
                foreach ($selectedQtys as $i1 => $j1) {
                    if (is_array($j1)) {
                        foreach ($j1 as $k1 => $p1) {
                            $selectedQtys[$i1][$k1] = $qty * ($p1 == 0 ? 1 : $p1);
                        }
                    } else {
                        $selectedQtys[$i1] = $qty * ($j1 == 0 ? 1 : $j1);
                    }
                }
                $selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);
                $isAvailable = true;
                $maxQty = 100000;
                $qty1 = $qty;
                foreach ($selections->getItems() as $selection) {
                    $Product = Mage::getModel('catalog/product')->load($selection->getProductId());
                    if (isset($selectedQtys[$selection->getOptionId()][$selection->getSelectionId()])) {
                        $qty = $selectedQtys[$selection->getOptionId()][$selection->getSelectionId()];
                    } elseif (isset($selectedQtys[$selection->getOptionId()])) {
                        $qty = $selectedQtys[$selection->getOptionId()];
                    } else {
                        $qty = $qty1;
                    }

                    if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {
                        $isAvailableArr = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($Product, $qty, $startingDate, $endingDate);
                        $isAvailable = $isAvailable && $isAvailableArr['avail'];
                        if ($maxQty > intval($isAvailableArr['maxqty'] / ($qty / $qty1))) {
                            $maxQty = intval($isAvailableArr['maxqty'] / ($qty / $qty1));
                        }
                    }
                }
                $qty = $qty1;
            }
        } elseif ($this->getRequest()->getParam('bundle_option')) {
            $selectionIds = $this->getRequest()->getParam('bundle_option');
            $selectedQtys = $this->getRequest()->getParam('bundle_option_qty');
            foreach ($selectedQtys as $i1 => $j1) {
                if (is_array($j1)) {
                    foreach ($j1 as $k1 => $p1) {
                        $selectedQtys[$i1][$k1] = $qty * (($p1 == 0) ? 1 : $p1);
                    }
                } else {
                    $selectedQtys[$i1] = $qty * (($j1 == 0) ? 1 : $j1);
                }

            }
            $selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);
            $priceVal = 0;
            $isAvailable = true;
            $maxQty = 100000;
            $qty1 = $qty;
            if ($this->getRequest()->getParam('configurate-product-id')) {
                $_parentProduct = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('configurate-product-id'));
                if ($_parentProduct->getTypeId() == 'bundle') {
                    $_bundleOverbooking = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($_parentProduct, $qty, $startingDate, $endingDate);
                }
            }
            foreach ($selections->getItems() as $selection) {
                $Product = Mage::getModel('catalog/product')->load($selection->getProductId());
                if (isset($selectedQtys[$selection->getOptionId()][$selection->getSelectionId()])) {
                    $qty = $selectedQtys[$selection->getOptionId()][$selection->getSelectionId()];
                } elseif (isset($selectedQtys[$selection->getOptionId()])) {
                    $qty = $selectedQtys[$selection->getOptionId()];
                } else {
                    $qty = $qty1;
                }

                if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {
                    $priceAmount = ITwebexperts_Payperrentals_Helper_Price::calculatePrice($Product, $startingDate, $endingDate, $qty, $customerGroup);
                    if ($priceAmount == -1) {
                        $priceVal = -1;
                        break;
                    }
                    $priceVal = $priceVal + $qty * $priceAmount;
                    if (!$_bundleOverbooking) {
                        $isAvailableArr = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($Product, $qty, $startingDate, $endingDate);
                    } else {
                        $isAvailableArr = $_bundleOverbooking;
                    }
                    $isAvailable = $isAvailable && $isAvailableArr['avail'];
                    //$maxQty = $isAvailableArr['maxqty'] / $qty1;
                    if ($maxQty > intval($isAvailableArr['maxqty'] / ($qty / $qty1))) {
                        $maxQty = intval($isAvailableArr['maxqty'] / $qty / $qty1);
                    }
                } else {
                    $priceVal = $priceVal + $qty * $Product->getPrice();
                }
            }
            $qty = $qty1;
            $priceAmount = $priceVal;

        }
        if ((isset($priceAmount)) && $priceAmount != -1) {
            if ($Product->getHasmultiply() == ITwebexperts_Payperrentals_Model_Product_Hasmultiply::STATUS_ENABLED && !is_null($qty)) {
                $priceAmount += ITwebexperts_Payperrentals_Helper_Data::getOptionsPrice($Product, $priceAmount) * $qty;
            } else {
                $priceAmount += ITwebexperts_Payperrentals_Helper_Data::getOptionsPrice($Product, $priceAmount);
            }

            if ($this->getRequest()->getParam('saveDates') && Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_USE_GLOBAL_DAYS) == 1) {
                Mage::getSingleton('core/session')->setData('startDateInitial', date('Y-m-d H:i:s', strtotime($startingDate)));
                Mage::getSingleton('core/session')->setData('endDateInitial', date('Y-m-d H:i:s', strtotime($endingDate)));
            }
        }

        $stockArr = array();
        /*$Product = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id'));*/

        if ($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE) {
            if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE ||
                ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_CONFIGURABLE
                    && $Product->getIsReservation() != ITwebexperts_Payperrentals_Model_Product_Isreservation::STATUS_DISABLED)
            ) {
                $stockArr[$Product->getId()] = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getStock($Product->getId(), $startingDate, $endingDate, $qty, $stockId);
            } else {
                $_product1 = Mage::getModel('catalog/product')->load($Product->getId());
                $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product1)->getQty();
                $stockArr[$Product->getId()]['avail'] = $qtyStock;
                $stockArr[$Product->getId()]['remaining'] = $stockArr[$Product->getId()]['avail'] - $qty;
            }


        } elseif ($this->getRequest()->getParam('bundle_option')) {
            $selectionIds = $this->getRequest()->getParam('bundle_option');
            $selectedQtys = $this->getRequest()->getParam('bundle_option_qty');
            foreach ($selectedQtys as $i1 => $j1) {
                if (is_array($j1)) {
                    foreach ($j1 as $k1 => $p1) {
                        $selectedQtys[$i1][$k1] = $qty * ($p1 == 0 ? 1 : $p1);
                    }
                } else {
                    $selectedQtys[$i1] = $qty * ($j1 == 0 ? 1 : $j1);
                }
            }
            $selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);

            $qty1 = $qty;
            foreach ($selections->getItems() as $selection) {
                $Product = Mage::getModel('catalog/product')->load($selection->getProductId());
                if (isset($selectedQtys[$selection->getOptionId()][$selection->getSelectionId()])) {
                    $qty = $selectedQtys[$selection->getOptionId()][$selection->getSelectionId()];
                } elseif (isset($selectedQtys[$selection->getOptionId()])) {
                    $qty = $selectedQtys[$selection->getOptionId()];
                } else {
                    $qty = $qty1;
                }


                if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {
                    if (!isset($stockArr[$selection->getProductId()])) {
                        $stockArr[$selection->getProductId()] = ITwebexperts_Payperrentals_Helper_Data::getStock($Product->getId(), $startingDate, $endingDate, $qty);
                        //$stockArr[$selection->getProductId()]['remaining'] = $stockArr[$selection->getProductId()]['remaining'] - ($qty-1);
                    } else {
                        $stockArr[$selection->getProductId()]['remaining'] = $stockArr[$selection->getProductId()]['remaining'] - $qty;
                    }

                } else {
                    if (!isset($stockArr[$selection->getProductId()])) {
                        $_product1 = Mage::getModel('catalog/product')->load($selection->getProductId());
                        $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product1)->getQty();
                        $stockArr[$selection->getProductId()]['avail'] = $qtyStock;
                        $stockArr[$selection->getProductId()]['remaining'] = $stockArr[$selection->getProductId()]['avail'] - $qty;
                    } else {
                        $stockArr[$selection->getProductId()]['remaining'] = $stockArr[$selection->getProductId()]['remaining'] - $qty;
                    }
                }
            }
        }

        $isAvailable = true;
        $maxQty = 100000;
        $stockRest = 100000;
        $stockAvailText = '';
        $stockRestText = '';
        foreach ($stockArr as $id => $avArr) {
            if ($avArr['remaining'] < 0) {
                if (!(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook(Mage::getModel('catalog/product')->load($id)))) {
                    if ($this->getRequest()->getParam('configurate-product-id') && !$_bundleOverbooking) {
                        $isAvailable = false;
                    }
                }
            }
            if ($stockAvail > $avArr['avail']) {
                //$maxQty = $avArr['avail'];
                $stockAvail = $avArr['avail'];
            }
            if ($stockRest > $avArr['remaining']) {
                $stockRest = $avArr['remaining'];
                $pid = $id;
            }
            $curProd = Mage::getModel('catalog/product')->load($id);
            $stockAvailText .= 'Stock available for product ' . $curProd->getName() . ': ' . $avArr['avail'] . '<br/>';
            $stockRestText .= 'Stock remaining for product ' . $curProd->getName() . ': ' . $avArr['remaining'] . '<br/>';
        }

        if (isset($pid)) {
            $maxQty = intval($stockArr[$pid]['avail'] / intval(($stockArr[$pid]['avail'] - $stockRest) / $qty1));
        }
        if (count($stockArr) > 1) {
            $stockAvailText .= 'Stock available for bundle' . ': ' . $maxQty . '<br/>';
            $stockRestText .= 'Stock remaining for bundle ' . ': ' . ($maxQty - $qty1) . '<br/>';
        }
        $price = array(
            'amount' => isset($priceAmount) ? $priceAmount : -1,
            'available' => $isAvailable,
            'stockAvail' => $stockAvail,
            'stockRest' => $stockRest,
            'stockAvailText' => $stockAvailText,
            'stockRestText' => $stockRestText,
            'maxqty' => $maxQty,
            'formatAmount' => isset($priceAmount) ? Mage::helper('core')->currency($priceAmount) : -1
        );

        $this->getResponse()->setBody(Zend_Json::encode($price));
    }

    /*updated*/
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


    public function getEventsAction()
    {
        $_startDate = date('Y-m-d', urldecode($this->getRequest()->getParam('start'))) . ' 00:00:00';
        $_endDate = date('Y-m-d', urldecode($this->getRequest()->getParam('end'))) . ' 23:59:59';
        $_productIds = explode(',', urldecode($this->getRequest()->getParam('productsids')));
        $_events = array();
        $stockIds = Mage::helper('warehouse')->getStockIds();
        /* @var $pprWarehouseHelper ITwebexperts_PPRWarehouse_Helper_Data */
        $pprWarehouseHelper = Mage::helper('pprwarehouse');
        foreach ($stockIds as $stockId) {
        $_bookedByIds = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForProducts($_productIds, $_startDate, $_endDate, false, true, $stockId);
        $_productLoadAr = $_bookedByIds['products'];

        foreach ($_bookedByIds['booked'] as $_dateFormatted => $_productAr) {
            foreach ($_productAr as $_productId => $_paramAr) {
                    $_product = $_productLoadAr[$_productId];
                    $_maxQty = $pprWarehouseHelper->getQtyForProductAndStock($_product, $stockId);
               // $_maxQty = $_product->getPayperrentalsQuantity();
                /** Functional for showing all orders as different events*/
                /*foreach($_paramAr['orders']['order_ids'] as $_incrementId){
                    $_evb = array(
                        'title' => 'Order #' . $_incrementId . '; Remaining Stock:' . ($_maxQty - $_paramAr['qty']),
                        'url' => urlencode($_dateFormatted . '||' . $_incrementId . '||' . $_productId),
                        'start' => $_paramAr['orders']['start_end'][$_incrementId]['period_start'],
                        'end' => $_paramAr['orders']['start_end'][$_incrementId]['period_end'],
                        'resource' => str_replace('.html', '', $_product->getUrlPath())
                    );
                    if ($_maxQty - $_paramAr['qty'] < 0) {
                        $_evb['backgroundColor'] = '#cc0000';
                        $_evb['className'] = 'overbookColor';
                    }
                    $_events[] = $_evb;
                }*/


                /** Functional for showing 1 event*/
                $_evb = array(
                    'title' => $_paramAr['qty'] . '/' . ($_maxQty - $_paramAr['qty']),
                    'url' => urlencode($_dateFormatted . '||' . implode(';', $_paramAr['orders']['order_ids']) . '||' . $_productId . '||' . $stockId),
                    'start' => date('Y-m-d', strtotime($_dateFormatted)) . ' 00:00:00',
                    'end' => date('Y-m-d', strtotime($_dateFormatted)) . ' 23:59:59',
                    'resource' => $_product->getId().'_'.$stockId
                );
                if ($_maxQty - $_paramAr['qty'] < 0) {
                    $_evb['backgroundColor'] = '#cc0000';
                    $_evb['className'] = 'overbookColor';
                }
                $_events[] = $_evb;

            }
        }
        }

        $this->getResponse()->setBody(Zend_Json::encode($_events));
    }

    public function getEventsActionBK()
    {
        $storeOpen = intval(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_OPEN_TIME));
        $storeClose = intval(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_CLOSE_TIME));

        $_startWithoutTime = date('Y-m-d', urldecode($this->getRequest()->getParam('start')));
        $start_date = $_startWithoutTime . ' 00:00:00';
        $end_date = date('Y-m-d', urldecode($this->getRequest()->getParam('end'))) . ' 00:00:00';
        $startTimePadding = strtotime($start_date);
        $endTimePadding = strtotime($end_date);

        $productIds = explode(',', urldecode($this->getRequest()->getParam('productsids')));

        $events = array();
        $stockIds = Mage::helper('warehouse')->getStockIds();
        /* @var $pprWarehouseHelper ITwebexperts_PPRWarehouse_Helper_Data */
        $pprWarehouseHelper = Mage::helper('pprwarehouse');

        foreach ($productIds as $prid) {
            $Product = Mage::getModel('catalog/product')->load($prid);
            foreach ($stockIds as $stockId) {
                $maxQty = $pprWarehouseHelper->getQtyForProductAndStock($Product, $stockId);
                $bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForDates($Product->getId(), $start_date, $end_date, 0, false, $stockId);
                foreach ($bookedArray as $dateFormatted => $qtyPerDay) {
                    $evb = array(
                        'title' => ' ' . ($maxQty - $qtyPerDay),
                        'url' => $dateFormatted . ';' . $prid . ';' . $stockId,
                        /*'id' => urlencode($dateFormatted.';'.$prid),*/
                        /*'textColor' => $dateFormatted,*/
                        'start' => date('Y-m-d', strtotime($dateFormatted)) . ' 00:00:00',
                        'end' => date('Y-m-d', strtotime($dateFormatted)) . ' 23:59:59',
                        'resource' => $Product->getId() . '_' . $stockId // str_replace('.html','',$Product->getUrlPath())
                    );
                    if ($maxQty - $qtyPerDay < 0) {
                        $evb['backgroundColor'] = '#cc0000';
                        $evb['className'] = 'overbookColor';
                    }
                    $events[] = $evb;
                }

                $p = 0;
                /**********************/
                /*Varien_Profiler::start('Payperrental_getevents_function');*/
                /**********************/
                while ($startTimePadding <= $endTimePadding) {
                    if (!array_key_exists(date('Y-n-j', $startTimePadding), $bookedArray)) {
                        $bookedTimesArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForTimes($Product->getId(), date('Y-m-d', $startTimePadding), 0, false, $stockId);
                        /*if ($p == 0) {
                            $startTimePaddingCur = strtotime(date('Y-m-d H:i', strtotime($start_date)));
                        } else {*/
                        $startTimePaddingCur = $startTimePadding;
                        /*}*/

                        $endTimePaddingCur = strtotime($_startWithoutTime . ' 23:00:00');
                        if ($endTimePaddingCur >= $endTimePadding) {
                            $endTimePaddingCur = $endTimePadding;
                        }

                        $intersectionArray = array();
                        while ($startTimePaddingCur <= $endTimePaddingCur) {
                            $dateFormatted = date('H:i:s', $startTimePaddingCur);
                            if (intval($dateFormatted) >= $storeOpen && intval($dateFormatted) <= $storeClose) {
                                $intersectionArray[] = $dateFormatted;
                            }
                            $startTimePaddingCur += 3600;
                        }
                        foreach ($bookedTimesArray as $dateFormatted => $qtyPerDay) {
                            //check here if there is an intersection
                            if (in_array($dateFormatted, $intersectionArray)) {
                                $evb = array(
                                    'title' => ' ' . ($maxQty - $qtyPerDay),
                                    'url' => date('Y-m-d', $startTimePadding) . ' ' . $dateFormatted . ';' . $prid . ';' . $stockId,
                                    'start' => date('Y-m-d', $startTimePadding) . ' ' . $dateFormatted,
                                    'end' => date('Y-m-d H:i:s', strtotime('+' . Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Timebox::XML_PATH_APPEARANCE_TIMEINCREMENTS) . ' minutes', strtotime(date('Y-m-d', $startTimePadding) . ' ' . $dateFormatted))),
                                    'resource' => str_replace('.html', '', $Product->getUrlPath())
                                );
                                if ($maxQty - $qtyPerDay < 0) {
                                    $evb['backgroundColor'] = '#cc0000';
                                    $evb['className'] = 'overbookColor';
                                }
                                $events[] = $evb;
                            }
                        }
                    }

                    $startTimePadding += 86400; //*time_increment
                    $p++;
                }
                /**********************/
                /*Varien_Profiler::stop('Payperrental_getevents_function');
                $_timers = Varien_Profiler::getTimers();
                $_ppTime = $_timers['Payperrental_getevents_function'];*/
                /**********************/
            }
        }

        $this->getResponse()->setBody(Zend_Json::encode($events));
    }


    public function getDateDetailsAction()
    {
        $_orderList = '<table cellpadding="10" cellspacing="10" border="0" style="min-width:350px;"><tr><td style="font-weight: bold">' . $this->__('Order ID') . '</td><td style="font-weight: bold">' . $this->__('Customer Name') . '</td><td style="font-weight: bold">' . $this->__('Start') . '</td><td style="font-weight: bold">' . $this->__('End') . '</td><td style="font-weight: bold">' . $this->__('Qty') . '</td><td style="font-weight: bold">' . $this->__('View Order') . '</td></tr>';
        $_orderArr = explode('||', urldecode($this->getRequest()->getParam('start')));

        $_orderIdsAr = explode(';', $_orderArr[1]);

        $_orderCollections = Mage::getModel('payperrentals/reservationorders')->getCollection()
            ->addProductIdFilter($_orderArr[2])
            ->addFieldToFilter('stock_id', $_orderArr[3]);
        $_orderCollections->addOrderIdsFilter($_orderIdsAr);
        //$_orderCollections->groupByOrder();

        foreach ($_orderCollections as $_orderItem) {
            $_orderList .= '<tr>';
            $_order = Mage::getModel('sales/order')->load($_orderItem->getOrderId());

            $_shippingId = $_order->getShippingAddressId();
            if (empty($shippingId)) {
                $shippingId = $_order->getBillingAddressId();
            }
            $_address = Mage::getModel('sales/order_address')->load($_shippingId);
            $_customerName = $_address->getFirstname() . ' ' . $_address->getLastname();
            $_orderList .= '<td>';
            $_orderList .= $_order->getIncrementId();
            $_orderList .= '</td>';

            $_orderList .= '<td>';
            $_orderList .= $_customerName;
            $_orderList .= '</td>';

            $_orderList .= '<td>';
            $_orderList .= ITwebexperts_Payperrentals_Helper_Data::formatDbDate($_orderItem->getStartDate());
            $_orderList .= '</td>';
            $_orderList .= '<td>';
            $_orderList .= ITwebexperts_Payperrentals_Helper_Data::formatDbDate($_orderItem->getEndDate());
            $_orderList .= '</td>';

            $_orderList .= '<td>';
            $_orderList .= $_orderItem->getQty();
            $_orderList .= '</td>';

            $_orderList .= '<td>';
            $_orderList .= '<a href="' . Mage::getUrl('adminhtml/sales_order/view', array('order_id' => $_order->getEntityId())) . '">' . Mage::helper('payperrentals')->__('View') . '</a>';
            $_orderList .= '</td>';
            $_orderList .= '</tr>';
        }
        $_orderList .= '</table>';
        $_details['html'] = $_orderList;
        $_details['date'] = ITwebexperts_Payperrentals_Helper_Data::formatDbDate($_orderArr[0]);
        $this->getResponse()->setBody(Zend_Json::encode($_details));
    }

    public function getDateDetailsActionBK()
    {
        $orderList = '<table cellpadding="10" cellspacing="10" border="0" style="min-width:350px;"><tr><td style="font-weight: bold">Order ID</td><td style="font-weight: bold">Customer Name</td><td style="font-weight: bold">Start</td><td style="font-weight: bold">End</td><td style="font-weight: bold">Qty</td><td style="font-weight: bold">View Order</td></tr>';


        $orderArr = explode(';', urldecode($this->getRequest()->getParam('start')));

        $start_date = date('Y-m-d H:i:s', strtotime($orderArr[0]));
        $productId = $orderArr[1];
        $stockId = $orderArr[2];
        $coll = Mage::getModel('payperrentals/reservationorders')
            ->getCollection()
            ->addProductIdFilter($productId);
        $coll->addFieldToFilter('stock_id', $stockId);
        $coll->addOtypeFilter(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_ORDER);
        $coll->addSelectFilter("start_date <= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date) . "' AND end_date >= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date) . "'");

        foreach ($coll as $item) {
            $orderList .= '<tr>';
            $order = Mage::getModel('sales/order')->loadByIncrementId($item->getOrderId());

            $shippingId = $order->getShippingAddressId();
            if (empty($shippingId)) {
                $shippingId = $order->getBillingAddressId();
            }
            $address = Mage::getModel('sales/order_address')->load($shippingId);
            $customerName = $address->getFirstname() . ' ' . $address->getLastname();
            $orderList .= '<td>';
            $orderList .= $item->getOrderId();
            $orderList .= '</td>';

            $orderList .= '<td>';
            $orderList .= $customerName;
            $orderList .= '</td>';

            $orderList .= '<td>';
            $orderList .= ITwebexperts_Payperrentals_Helper_Data::formatDbDate($item->getStartDate());
            $orderList .= '</td>';
            $orderList .= '<td>';
            $orderList .= ITwebexperts_Payperrentals_Helper_Data::formatDbDate($item->getEndDate());
            $orderList .= '</td>';

            $orderList .= '<td>';
            $orderList .= $item->getQty();
            $orderList .= '</td>';

            $orderList .= '<td>';
            $orderList .= '<a href="' . Mage::getUrl('adminhtml/sales_order/view', array('order_id' => $order->getEntityId())) . '">' . Mage::helper('payperrentals')->__('View') . '</a>';
            $orderList .= '</td>';
            $orderList .= '</tr>';
        }
        $orderList .= '</table>';
        $details['html'] = $orderList;
        $details['date'] = ITwebexperts_Payperrentals_Helper_Data::formatDbDate($start_date);
        $this->getResponse()->setBody(Zend_Json::encode($details));
    }

    /**
     *
     */
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
            $stockProducts = $this->getRequest()->getParam('stock_products');
        }

        $output = array();
        $productIds = $this->getRequest()->getParam('products');
        $productCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addFieldToFilter('entity_id', array('in' => $productIds));
        if(Mage::getSingleton('core/session')->getData('startDateInitial')){
            $startDate = Mage::getSingleton('core/session')->getData('startDateInitial');
        }else{
            $startDate = date('Y-m-d H:i:s');
        }
        if(Mage::getSingleton('core/session')->getData('endDateInitial')){
            $endDate = Mage::getSingleton('core/session')->getData('endDateInitial');
        }else{
            $endDate = date('Y-m-d H:i:s');
        }
        $qtys = array();
        if ($this->getRequest()->getParam('qtys')) {
            $qtys = $this->getRequest()->getParam('qtys');
        }
        $dates = array();
        if ($this->getRequest()->getParam('dates')) {
            $dates = $this->getRequest()->getParam('dates');
        }
        foreach ($productCollection AS $product) {
            if(isset($dates[$product->getId()])){
                $arrDates = explode(',', $dates[$product->getId()]);
                $startDate = $arrDates[0];
                $endDate = $arrDates[1];
            }
            $output[$product->getId()] = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getStock($product->getId(), $startDate, $endDate, (isset($qtys[$product->getId()])?$qtys[$product->getId()]:1), isset($stockProducts[$product->getId()])?$stockProducts[$product->getId()]:$stockId);
            $output[$product->getId()]['avail'] += (isset($qtys[$product->getId()])?$qtys[$product->getId()]:1);
            $output[$product->getId()]['remaining'] += (isset($qtys[$product->getId()])?$qtys[$product->getId()]:1);
        }
        $this
            ->getResponse()
            ->setBody(Zend_Json::encode($output));
    }


}
