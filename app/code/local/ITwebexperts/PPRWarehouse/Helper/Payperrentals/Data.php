<?php
/**
 *
 * @author Enrique Piatti
 */
/*all functions are updated*/
class ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data extends ITwebexperts_Payperrentals_Helper_Data
{

	/**
	 * @param $product Mage_Catalog_Model_Product|int
	 * @param null $stockId
	 * @param null $stDate
	 * @param null $enDate
	 * @param int $isQuote
	 * @param bool $useOverbook
	 * @return array
	 */
	public static function getBookedQtyForProducts($_productIds, $_stDate = null, $_enDate = null, $_isQuote = false, $_isReport = false, $stockId = null)
	{
	    $_configHelper = Mage::helper('payperrentals/config');
		if( ! $stockId){
			Mage::throwException('Stock ID is required in getBookedQtyForDates');
		}

		if (!is_array($_productIds)) {
            $_productIds = array($_productIds);
        }
        $_productLoadAr = array();
        $_productCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('entity_id', array('in' => $_productIds));
        foreach ($_productCollection as $_product) {
            if (!$_isReport && self::isAllowedOverbook($_product)) {
                return array('booked' => array());
            }
            $_productLoadAr[$_product->getId()] = $_product;
        }
        unset($_product);

        $_currentTimestamp = (int)Mage::getSingleton('core/date')->timestamp(time());
        if (is_null($_stDate)) {
            $_stDate = self::toDbDate($_currentTimestamp, true);
        }

        if (is_null($_enDate)) {
            $_enDate = self::toDbDate(strtotime('+' . self::CALCULATE_DAYS_AFTER . ' days', $_currentTimestamp), true);
        }

        if ($_isReport) {
            $_stDateCompare = date('Y-n-j', strtotime($_stDate));
            $_enDateCompare = date('Y-n-j', strtotime($_enDate));
        }

        $_reserveOrderCollection = Mage::getModel('payperrentals/reservationorders')->getCollection()
            ->addFieldToFilter('stock_id', $stockId)
            ->addProductIdsFilter($_productIds);

        $_orderJoinCondition = array(
            'main_table.order_id = sfo.entity_id',
            'sfo.status <> \'canceled\'',
            'sfo.status <> \'refunded\''
        );

        $_reserveOrderCollection->getSelect()->joinInner(
            array('sfo' => $_reserveOrderCollection->getTable('sales/order')),
            implode(' AND ', $_orderJoinCondition),
            array(
                'status' => 'sfo.status'
            ));
        /*$_reserveOrderCollection->groupByOrder();*/
        $_reserveOrderCollection->addSelectFilter("start_date <= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_enDate) . "' AND end_date >= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_stDate) . "'");

        $_booked = array();
        foreach ($_reserveOrderCollection as $_orderItem) {
            $_orderItemProductId = $_orderItem->getProductId();
            $_orderItemId = $_orderItem->getOrderId();
            $_reConfigure = false;
            if ($_orderItem->getItemBookedSerialize() != '') {
                $_bookedAr = unserialize($_orderItem->getItemBookedSerialize());
                if (self::pregArrayKeys('/1970-(1|01)-(1|01)/', $_bookedAr) || self::pregArrayKeys('/0000-00-00/', $_bookedAr)) $_reConfigure = true;
            } else {
                $_reConfigure = true;
            }
            if (!$_reConfigure) {
                /*$_bookedAr = unserialize($_orderItem->getItemBookedSerialize());*/
                foreach ($_bookedAr as $_bookedItemDate => $_bookedItemData) {
                    /*Compare 2 sting date with calendar range foe exclude not in range dates*/
                    if ($_isReport && (strtotime($_bookedItemDate) < strtotime($_stDateCompare) || strtotime($_bookedItemDate) > strtotime($_enDateCompare))) continue;
                    if (!$_bookedItemData['qty']) {
                        $_bookedItemData['qty'] = $_orderItem->getQty();
                        $_orderItem->setIsUpdateSerialize(true);
                    }
                    if (array_key_exists($_bookedItemDate, $_booked)) {
                        $_booked[$_bookedItemDate][$_orderItemProductId]['qty'] = (array_key_exists($_orderItemProductId, $_booked[$_bookedItemDate])) ? $_booked[$_bookedItemDate][$_orderItemProductId]['qty'] + $_bookedItemData['qty'] : $_bookedItemData['qty'];
                    } else {
                        $_booked[$_bookedItemDate][$_orderItemProductId]['qty'] = $_bookedItemData['qty'];
                    }


                    if (!$_isReport) {
                        if (!isset($_booked[$_bookedItemDate][$_orderItemProductId]['period_start']) || strtotime($_booked[$_bookedItemDate][$_orderItemProductId]['period_start']) > strtotime($_bookedItemData['start_end']['period_start'])) {
                            $_booked[$_bookedItemDate][$_orderItemProductId]['period_start'] = $_bookedItemData['start_end']['period_start'];
                        }
                        if (!isset($_booked[$_bookedItemDate][$_orderItemProductId]['period_end']) || strtotime($_booked[$_bookedItemDate][$_orderItemProductId]['period_end']) < strtotime($_bookedItemData['start_end']['period_end'])) {
                            $_booked[$_bookedItemDate][$_orderItemProductId]['period_end'] = $_bookedItemData['start_end']['period_end'];
                        }
                    } else {
                        if (!isset($_booked[$_bookedItemDate][$_orderItemProductId]['orders']['order_ids']) || array_search($_orderItemId, $_booked[$_bookedItemDate][$_orderItemProductId]['orders']['order_ids']) === false) {
                            $_booked[$_bookedItemDate][$_orderItemProductId]['orders']['order_ids'][] = $_orderItemId;
                            $_booked[$_bookedItemDate][$_orderItemProductId]['orders']['start_end'][$_orderItemId]['period_start'] = $_bookedItemData['start_end']['period_start'];
                            $_booked[$_bookedItemDate][$_orderItemProductId]['orders']['start_end'][$_orderItemId]['period_end'] = $_bookedItemData['start_end']['period_end'];
                        }
                    }
                }
            } else {
                /**
                 * If database not have serialized data for time period
                 * then calculate, serialize and save periods to database
                 */
                $_product = $_productLoadAr[$_orderItemProductId];
                if ($_product->getGlobalTurnoverBefore() == 0) {
                    $_turnoverTimeBefore = self::getPeriodInSeconds($_product->getPayperrentalsAvailNumberb(), $_product->getPayperrentalsAvailTypeb());
                } else {
                    $_turnoverTimeBefore = self::getPeriodInSeconds(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_BEFORE_NUMBER), Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_BEFORE_TYPE));
                }
                if ($_product->getGlobalTurnoverAfter() == 0) {
                    $_turnoverTimeAfter = self::getPeriodInSeconds($_product->getPayperrentalsAvailNumber(), $_product->getPayperrentalsAvailType());
                } else {
                    $_turnoverTimeAfter = self::getPeriodInSeconds(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_AFTER_NUMBER), Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_AFTER_TYPE));
                }

                $_itemDateAr = self::getOrderOrQuoteTurnoverForSerialize($_product, $_orderItem);
                $_itemStartDate = $_itemDateAr['item_start'];
                $_itemEndDate = $_itemDateAr['item_end'];
                $_turnoverForOrder = $_itemDateAr['turnover_for_order_or_quote'];

                $_qty = $_orderItem->getQty();


                Mage::dispatchEvent('ppr_get_booked_qty_for_dates', array('turnover_timestamp_before' => &$_turnoverTimeBefore, 'turnover_timestamp_after' => &$_turnoverTimeAfter));

                $_startTimePadding = strtotime(date('Y-m-d', strtotime($_itemStartDate))) - $_turnoverTimeBefore;
                $_endTimePadding = strtotime(date('Y-m-d', strtotime($_itemEndDate))) + $_turnoverTimeAfter;

                while ($_startTimePadding <= $_endTimePadding) {
                    $_dateFormatted = date('Y-n-j', $_startTimePadding);
                    $_startTimePadding += 86400;
                    /*Compare 2 sting date with calendar range foe exclude not in range dates*/
                    if ($_isReport && (strtotime($_dateFormatted) < strtotime($_stDateCompare) || strtotime($_dateFormatted) > strtotime($_enDateCompare))) continue;
                    if (isset($_booked[$_dateFormatted][$_orderItemProductId])) {
                        $_booked[$_dateFormatted][$_orderItemProductId]['qty'] += $_qty;
                    } else {
                        $_booked[$_dateFormatted][$_orderItemProductId]['qty'] = (int)$_qty;
                    }
                    if (!$_isReport) {
                        if (!isset($_booked[$_dateFormatted][$_orderItemProductId]['period_start']) || strtotime($_booked[$_dateFormatted][$_orderItemProductId]['period_start']) > strtotime($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'])) {
                        $_booked[$_dateFormatted][$_orderItemProductId]['period_start'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'];
                        }
                        if (!isset($_booked[$_dateFormatted][$_orderItemProductId]['period_end']) || strtotime($_booked[$_dateFormatted][$_orderItemProductId]['period_end']) < strtotime($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'])) {
                        $_booked[$_dateFormatted][$_orderItemProductId]['period_end'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'];
                        }

                    } else {
                        if (!isset($_booked[$_dateFormatted][$_orderItemProductId]['orders']['order_ids']) || array_search($_orderItemId, $_booked[$_dateFormatted][$_orderItemProductId]['orders']['order_ids']) === false) {
                            $_booked[$_dateFormatted][$_orderItemProductId]['orders']['order_ids'][] = $_orderItemId;
                            $_booked[$_dateFormatted][$_orderItemProductId]['orders']['start_end'][$_orderItemId]['period_start'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'];
                            $_booked[$_dateFormatted][$_orderItemProductId]['orders']['start_end'][$_orderItemId]['period_end'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'];
                        }
                    }
                }
            }
            if ($_orderItem->getIsUpdateSerialize()) {
                $_product = $_productLoadAr[$_orderItemProductId];
                self::getOrderOrQuoteTurnoverForSerialize($_product, $_orderItem);
            }
        }

        if ($_isQuote) {
            $_reserveQuote = Mage::getModel('payperrentals/reservationquotes')->getCollection()
                ->addQuoteIdFilter($_isQuote)
                 ->addFieldToFilter('stock_id', $stockId)
                ->addProductIdsFilter($_productIds);
            $_reserveQuote->groupByQuoteItem();
            $_reserveQuote->addSelectFilter("start_date <= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_enDate) . "' AND end_date >= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_stDate) . "'");


            foreach ($_reserveQuote as $_quoteItem) {
                $_quoteItemProductId = $_quoteItem->getProductId();
                $_quoteItemId = $_quoteItem->getQuoteId();
                $_reConfigure = false;
                if ($_quoteItem->getItemBookedSerialize() != '') {
                    $_bookedAr = unserialize($_quoteItem->getItemBookedSerialize());
                    if (self::pregArrayKeys('/1970-(1|01)-(1|01)/', $_bookedAr) || self::pregArrayKeys('/0000-00-00/', $_bookedAr)) $_reConfigure = true;
                } else {
                    $_reConfigure = true;
                }
                if (!$_reConfigure) {
                    /*$_bookedAr = unserialize($_quoteItem->getItemBookedSerialize());*/
                    foreach ($_bookedAr as $_bookedItemDate => $_bookedItemData) {
                        /*Compare 2 sting date with calendar range foe exclude not in range dates*/
                        if ($_isReport && (strtotime($_bookedItemDate) < strtotime($_stDateCompare) || strtotime($_bookedItemDate) > strtotime($_enDateCompare))) continue;
                        if (!$_bookedItemData['qty']) {
                            $_bookedItemData['qty'] = $_quoteItem->getQty();
                            $_quoteItem->setIsUpdateSerialize(true);
                        }
                        if (array_key_exists($_bookedItemDate, $_booked)) {
                            $_booked[$_bookedItemDate][$_quoteItemProductId]['qty'] = (array_key_exists($_quoteItemProductId, $_booked[$_bookedItemDate])) ? $_booked[$_bookedItemDate][$_quoteItemProductId]['qty'] + $_bookedItemData['qty'] : $_bookedItemData['qty'];
                        } else {
                            $_booked[$_bookedItemDate][$_quoteItemProductId]['qty'] = $_bookedItemData['qty'];
                        }


                        if (!$_isReport) {
                            if (!isset($_booked[$_bookedItemDate][$_quoteItemProductId]['period_start']) || strtotime($_booked[$_bookedItemDate][$_quoteItemProductId]['period_start']) > strtotime($_bookedItemData['start_end']['period_start'])) {
                                $_booked[$_bookedItemDate][$_quoteItemProductId]['period_start'] = $_bookedItemData['start_end']['period_start'];
                            }
                            if (!isset($_booked[$_bookedItemDate][$_quoteItemProductId]['period_end']) || strtotime($_booked[$_bookedItemDate][$_quoteItemProductId]['period_end']) < strtotime($_bookedItemData['start_end']['period_end'])) {
                                $_booked[$_bookedItemDate][$_quoteItemProductId]['period_end'] = $_bookedItemData['start_end']['period_end'];
                            }
                        } else {
                            if (!isset($_booked[$_bookedItemDate][$_quoteItemProductId]['quotes']['quote_ids']) || array_search($_quoteItemId, $_booked[$_bookedItemDate][$_quoteItemProductId]['quotes']['quote_ids']) === false) {
                                $_booked[$_bookedItemDate][$_quoteItemProductId]['quotes']['quote_ids'][] = $_quoteItemId;
                                $_booked[$_bookedItemDate][$_quoteItemProductId]['quotes']['start_end'][$_quoteItemId]['period_start'] = $_bookedItemData['start_end']['period_start'];
                                $_booked[$_bookedItemDate][$_quoteItemProductId]['quotes']['start_end'][$_quoteItemId]['period_end'] = $_bookedItemData['start_end']['period_end'];
                            }
                        }
                    }
                } else {
                    /**
                     * If database not have serialized data for time period
                     * then calculate, serialize and save periods to database
                     */
                    $_product = $_productLoadAr[$_quoteItemProductId];
                    if ($_product->getGlobalTurnoverBefore() == 0) {
                        $_turnoverTimeBefore = self::getPeriodInSeconds($_product->getPayperrentalsAvailNumberb(), $_product->getPayperrentalsAvailTypeb());
                    } else {
                        $_turnoverTimeBefore = self::getPeriodInSeconds(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_BEFORE_NUMBER), Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_BEFORE_TYPE));
                    }
                    if ($_product->getGlobalTurnoverAfter() == 0) {
                        $_turnoverTimeAfter = self::getPeriodInSeconds($_product->getPayperrentalsAvailNumber(), $_product->getPayperrentalsAvailType());
                    } else {
                        $_turnoverTimeAfter = self::getPeriodInSeconds(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_AFTER_NUMBER), Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_AFTER_TYPE));
                    }

                    $_itemDateAr = self::getOrderOrQuoteTurnoverForSerialize($_product, $_quoteItem, true);
                    $_itemStartDate = $_itemDateAr['item_start'];
                    $_itemEndDate = $_itemDateAr['item_end'];
                    $_turnoverForOrder = $_itemDateAr['turnover_for_order_or_quote'];

                    $_qty = $_quoteItem->getQty();

                    $_startTimePadding = strtotime(date('Y-m-d', strtotime($_itemStartDate))) - $_turnoverTimeBefore;
                    $_endTimePadding = strtotime(date('Y-m-d', strtotime($_itemEndDate))) + $_turnoverTimeAfter;

                    while ($_startTimePadding <= $_endTimePadding) {
                        $_dateFormatted = date('Y-n-j', $_startTimePadding);
                        $_startTimePadding += 86400;
                        /*Compare 2 sting date with calendar range foe exclude not in range dates*/
                        if ($_isReport && (strtotime($_dateFormatted) < strtotime($_stDateCompare) || strtotime($_dateFormatted) > strtotime($_enDateCompare))) continue;
                        if (isset($_booked[$_dateFormatted][$_quoteItemProductId])) {
                            $_booked[$_dateFormatted][$_quoteItemProductId]['qty'] += $_qty;
                        } else {
                            $_booked[$_dateFormatted][$_quoteItemProductId]['qty'] = (int)$_qty;
                        }
                        if (!$_isReport) {
                            if (!isset($_booked[$_dateFormatted][$_quoteItemProductId]['period_start']) || strtotime($_booked[$_dateFormatted][$_quoteItemProductId]['period_start']) > strtotime($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'])) {
                                $_booked[$_dateFormatted][$_quoteItemProductId]['period_start'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'];
                            }
                            if (!isset($_booked[$_dateFormatted][$_quoteItemProductId]['period_end']) || strtotime($_booked[$_dateFormatted][$_quoteItemProductId]['period_end']) < strtotime($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'])) {
                                $_booked[$_dateFormatted][$_quoteItemProductId]['period_end'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'];
                            }
                        } else {
                            if (!isset($_booked[$_dateFormatted][$_quoteItemProductId]['quotes']['quote_ids']) || array_search($_quoteItemId, $_booked[$_dateFormatted][$_quoteItemProductId]['quotes']['quote_ids']) === false) {
                                $_booked[$_dateFormatted][$_quoteItemProductId]['quotes']['quote_ids'][] = $_quoteItemId;
                                $_booked[$_dateFormatted][$_quoteItemProductId]['quotes']['start_end'][$_quoteItemId]['period_start'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'];
                                $_booked[$_dateFormatted][$_quoteItemProductId]['quotes']['start_end'][$_quoteItemId]['period_end'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'];
                            }
                        }
                    }
                }


                if ($_quoteItem->getIsUpdateSerialize()) {
                    $_product = $_productLoadAr[$_quoteItemProductId];
                    self::getOrderOrQuoteTurnoverForSerialize($_product, $_quoteItem, true);
                }
            }
                }

                foreach ($_productLoadAr as $_id => $_product) {
                    if ($_product->getGlobalExcludedays() == 0) {
                        $_disabledDaysInt = explode(',', $_product->getResExcludedDaysweek());
                    } else {
                        $_disabledDaysInt = explode(',', Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_DISABLED_DAYS_WEEK));
                    }
                    $_disabledDays = array();
                    foreach ($_disabledDaysInt as $_disabledDay) {
                        switch ($_disabledDay) {
                            case(ITwebexperts_Payperrentals_Model_Product_Excludedaysweek::MONDAY):
                                $_disabledDays[] = 'Mon';
                                break;
                            case(ITwebexperts_Payperrentals_Model_Product_Excludedaysweek::TUESDAY):
                                $_disabledDays[] = 'Tue';
                                break;
                            case(ITwebexperts_Payperrentals_Model_Product_Excludedaysweek::WEDNESDAY):
                                $_disabledDays[] = 'Wed';
                                break;
                            case(ITwebexperts_Payperrentals_Model_Product_Excludedaysweek::THURSDAY):
                                $_disabledDays[] = 'Thu';
                                break;
                            case(ITwebexperts_Payperrentals_Model_Product_Excludedaysweek::FRIDAY):
                                $_disabledDays[] = 'Fri';
                                break;
                            case(ITwebexperts_Payperrentals_Model_Product_Excludedaysweek::SATURDAY):
                                $_disabledDays[] = 'Sat';
                                break;
                            case(ITwebexperts_Payperrentals_Model_Product_Excludedaysweek::SUNDAY):
                                $_disabledDays[] = 'Sun';
                                break;
                        }
                    }
                    $_paddingDays = self::getProductPaddingDays($_product, $_currentTimestamp);
                    $_blockedDates = self::getDisabledDates($_product);
                    foreach ($_blockedDates as $_dateFormattedString) {
                        $_dateFormattedStr = substr($_dateFormattedString, 1, strlen($_dateFormattedString) - 2);
                        $_dateFormatted = date('Y-n-j', strtotime($_dateFormattedStr));
                        if (strtotime($_dateFormattedStr) >= strtotime($_stDate) && strtotime($_dateFormattedStr) <= strtotime($_enDate)) {
                            $_booked[$_dateFormatted][$_id]['qty'] = 10000;
                            $_booked[$_dateFormatted][$_id]['period_start'] = date('Y-m-d H:i:s', strtotime($_dateFormattedStr));
                            $_booked[$_dateFormatted][$_id]['period_end'] = date('Y-m-d H:i:s', strtotime($_dateFormattedStr));
                        }
                    }
                    foreach ($_paddingDays as $_dateFormattedString) {
                        $_dateFormattedStr = substr($_dateFormattedString, 1, strlen($_dateFormattedString) - 2);
                        $_dateFormatted = date('Y-n-j', strtotime($_dateFormattedStr));
                        if (strtotime($_dateFormattedStr) >= strtotime($_stDate) && strtotime($_dateFormattedStr) <= strtotime($_enDate)) {
                            $_booked[$_dateFormatted][$_id]['qty'] = 10000;
                            $_booked[$_dateFormatted][$_id]['period_start'] = date('Y-m-d H:i:s', strtotime($_dateFormattedStr));
                            $_booked[$_dateFormatted][$_id]['period_end'] = date('Y-m-d H:i:s', strtotime($_dateFormattedStr));
                        }
                    }
                    if (count($_disabledDays) > 0) {
                        $startTimePadding = strtotime(date('Y-m-d', strtotime($_stDate)));
                        $endTimePadding = strtotime(date('Y-m-d', strtotime($_enDate)));
                        //while ($startTimePadding <= $endTimePadding) {
                        $dayofWeek = date('D', $startTimePadding);
                        if (in_array($dayofWeek, $_disabledDays)) {
                            $_dateFormatted = date('Y-n-j', $startTimePadding);
                            $_booked[$_dateFormatted][$_id]['qty'] = 10000;
                            $_booked[$_dateFormatted][$_id]['period_start'] = date('Y-m-d H:i:s', $startTimePadding);
                            $_booked[$_dateFormatted][$_id]['period_end'] = date('Y-m-d H:i:s', $startTimePadding);
                        }
                        $dayofWeek = date('D', $endTimePadding);
                        if (in_array($dayofWeek, $_disabledDays)) {
                            $_dateFormatted = date('Y-n-j', $endTimePadding);
                            $_booked[$_dateFormatted][$_id]['qty'] = 10000;
                            $_booked[$_dateFormatted][$_id]['period_start'] = date('Y-m-d H:i:s', $endTimePadding);
                            $_booked[$_dateFormatted][$_id]['period_end'] = date('Y-m-d H:i:s', $endTimePadding);
                        }
                        //  $startTimePadding += 60 * 60 * 24;
                        //}
                    }
                }

        $_result = array(
            'booked' => $_booked
        );
        if ($_isReport) {
            $_result['products'] = $_productLoadAr;
        }
        return $_result;
    }


	/**
	 *
	 * @param $productId
	 * @param int $qty
	 * @param $start_date
	 * @param $end_date
	 * @param null $stockId
	 * @param bool $returnQty	when true $stockID must not be null
	 * @return bool
	 */
	public static function isAvailable($productId, $start_date, $end_date, $qty = 1, $stockId = null)
	{
		if(! $stockId){
			Mage::throwException('StockId is required when the qty is requested in isAvailable');
		}

		if(!$qty) {
			$qty = 1;
		}
		$Product = self::_initProduct($productId);
        $productId = $Product->getId();


		if (ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)) {
            return true;
        }


		$helper = Mage::helper('pprwarehouse');
		$stockIds = $stockId ? array($stockId) : $helper->getValidStockIds();
        $isAvailable = true;
		foreach($stockIds as $stockId)
		{
			$isAvailable = true;
			$maxQty = $helper->getQtyForProductAndStock($Product, $stockId);

			$minQty = self::getStockOnly($productId, $start_date, $end_date, $stockId);

            if ($maxQty < $qty || $minQty < $qty) {
                $isAvailable = false;
            }
        }

        return $isAvailable;
	}

    public static function isAvailableWithQuote($Product = null, $qty = 1, $_bundleOverbooking = false, $attributes = null)
    {
        if(!is_null($attributes)){
            $Product = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($attributes, $Product);
            $Product = Mage::getModel('catalog/product')->load($Product->getId());
        }

        //$maxQty = ITwebexperts_Payperrentals_Helper_Data::getQuantity($Product);
        if($_bundleOverbooking || ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
            return true;
        }

        $isAvailable = false;
		$helper = Mage::helper('pprwarehouse');
		foreach($helper->getValidStockIds() as $stockId)
		{
			$isAvailable = true;

			$maxQty = $helper->getQtyForProductAndStock($Product, $stockId);

            if ($maxQty < $qty) {
                $isAvailable = false;
                continue;
            }
            $quoteID = Mage::getSingleton("checkout/session")->getQuote()->getId();
            if(!$quoteID){
                $quoteID = 0;
            }
            $nonSequential = $Product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL)->getValue();
            $start_date_val = $Product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION)->getValue();
            $end_date_val = $Product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION)->getValue();
            if($nonSequential == 1){
                $startDateArr = explode(',', $start_date_val);
                $endDateArr = explode(',', $start_date_val);
            } else {
                $startDateArr = array($start_date_val);
                $endDateArr = array($end_date_val);
            }
            foreach($startDateArr as $count => $start_date){
                $end_date = $endDateArr[$count];
                $isAvailable = Mage::helper('payperrentals/rendercart')->checkAvailability($Product, $start_date, $end_date, $quoteID, $qty, $maxQty, 0, $stockId);
                //if(!$avail) return false;
                if( ! $isAvailable){
                    break;
                }
            }

            if( ! $isAvailable){
                    continue;
            }
            // valid stock found, stop checking
            if( $isAvailable){
                break;
            }
        }

        return $isAvailable;
    }


    public static function isAvailableWithQty($productId, $qty = 1, $start_date, $end_date, $stockId = null)
    {
        if(! $stockId){
            Mage::throwException('StockId is required when the qty is requested in isAvailable');
        }

        if(!$qty) {
            $qty = 1;
        }
        $Product = self::_initProduct($productId);
        $productId = $Product->getId();


        if (ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)) {
            return array('avail' => true, 'maxqty' => $qty);
        }

        $helper = Mage::helper('pprwarehouse');
        $stockIds = $stockId ? array($stockId) : $helper->getValidStockIds();

        foreach($stockIds as $stockId)
        {
            $maxQty = $helper->getQtyForProductAndStock($Product, $stockId);

            if ($maxQty < $qty) {
                return array('avail' => false, 'maxqty' => $maxQty);
            }
            $qty = self::getStockOnly($productId, $start_date, $end_date, $stockId);
            if ($qty > 0) {
                return array('avail' => true, 'maxqty' => $qty);
            } else {
                return array('avail' => false, 'maxqty' => $qty);
            }
        }
        return array('avail' => true, 'maxqty' => $qty);

    }


	/**
	 *
	 * @param $product_id
	 * @param int $qty
	 * @param $startingDate
	 * @param $endingDate
	 * @param null|int $stockId this parameter is required, but because we need to use the same definition we are assigning a default value
	 * @return int
	 */
	public static function getAvailability($product_id, $qty = 1, $startingDate, $endingDate, $stockId = null)
	{

		if( ! $stockId){
			Mage::throwException('Stock ID is required in getAvailability');
		}

		 $stockArr = array();
        $Product = self::_initProduct($product_id);
        $product_id = $Product->getId();

		if($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE)
		{
			if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE || ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_CONFIGURABLE && $Product->getIsReservation() != ITwebexperts_Payperrentals_Model_Product_Isreservation::STATUS_DISABLED)){
				$stockArr[$Product->getId()] = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getStock($Product->getId(), $startingDate, $endingDate, $qty, $stockId);
			}else{
				$qtyStock = Mage::helper('pprwarehouse')->getQtyForProductAndStock($Product, $stockId);
				$stockArr[$Product->getId()]['avail'] = $qtyStock;
			}
		}
		else
		{
			$optionCol= $Product->getTypeInstance(true)
				->getOptionsCollection($Product);
			$selectionCol= $Product->getTypeInstance(true)
				->getSelectionsCollection(
					$Product->getTypeInstance(true)->getOptionsIds($Product),
					$Product
				);
			$optionCol->appendSelections($selectionCol);
			foreach ($optionCol as $option) {
				if($option->required) {
					$selections = $option->getSelections();
					foreach ($selections as $selection) {
						$Product = Mage::getModel('catalog/product')->load($selection->getProductId());

						if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){
							if(!isset($stockArr[$selection->getProductId()])){
								$stockArr[$selection->getProductId()] = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getStock($Product->getId(), $startingDate, $endingDate, $qty, $stockId);
                            }

						}else{
							if(!isset($stockArr[$selection->getProductId()])){
                                $_product1 = Mage::getModel('catalog/product')->load($selection->getProductId());
								$qtyStock = Mage::helper('pprwarehouse')->getQtyForProductAndStock($_product1, $stockId);
								$stockArr[$selection->getProductId()]['avail'] = $qtyStock;
							}
						}
					}
				}
			}
		}
		$stockAvail = 10000;
		foreach($stockArr as $id => $avArr){
			if($stockAvail > $avArr['avail']){
				$stockAvail = $avArr['avail'];
			}
		}
		return $stockAvail;
	}


    /**
     * @param $productId
     * @param $start_date
     * @param $end_date
     * @return int
     */
    public static function getStockOnly($productId, $start_date, $end_date, $stockId = null)
    {
        $Product = self::_initProduct($productId);
        $productId = $Product->getId();

        $helper = Mage::helper('pprwarehouse');
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
    }



	/**
	 * @param $product
	 * @param $start_date
	 * @param $end_date
	 * @param $qty	int this is not used anymore (will be removed in next releases)
	 * @param null $stockId
	 * @return array
	 */
	public static function getStock($productId, $start_date, $end_date, $qty, $stockId = null)
	{
        $stockArr = array();

        $minQty = self::getStockOnly($productId, $start_date, $end_date, $stockId);

        if (!$qty) {
            $qty = 1;
        }

        $stockArr['avail'] = $minQty;
        $stockArr['remaining'] = $minQty - $qty;

        return $stockArr;
	}

    /*updated*/
    public static function reserveOrder($items, $order)
    {
        $coll = Mage::getModel('payperrentals/reservationorders')
            ->getCollection()
            ->addSelectFilter("order_id='" . $order->getId() . "'");
        if ($coll->getSize() == 0) {
            foreach ($items as $item) {
                $Product = Mage::getModel('catalog/product')->load($item->getProductId());
                if ($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE /*&& $Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_CONFIGURABLE /* && $Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE*/) {
                    continue;
                }

                $data = $item->getProductOptionByCode('info_buyRequest');

                // $_date_from = $data[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION];
                //$_date_to = $data[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION];
                $nonSequential = $data[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL];
                $start_date_val = $data[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION];
                $end_date_val = $data[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION];

                if($nonSequential == 1){
                    $startDateArr = explode(',', $start_date_val);
                    $endDateArr = explode(',', $start_date_val);
                }else{
                    $startDateArr = array($start_date_val);
                    $endDateArr = array($end_date_val);
                }
                foreach($startDateArr as $count => $start_date){
                    $end_date = $endDateArr[$count];
                    /*TODO check needing add turnover save to other functions*/
                    $qty = $item->getQtyOrdered();
                    $_resultDates = self::matchStartEndDates($start_date, $end_date);
                    $_turnoverAr = ITwebexperts_Payperrentals_Helper_Data::getTurnoverDatesForOrderItem($Product, strtotime($_resultDates['start_date']), strtotime($_resultDates['end_date']), $qty);

                    //get item parent qty
                    if ($item->getParentItem() && $item->getParentItem()->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE) {
                        //echo $item->getParentItem()->getQty();
                        //print_r($item->getParentItem()->debug());
                        //$qty = $qty * $item->getParentItem()->getQtyInvoiced();
                        //die();
                    }
                    if ($item->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {
                        $model = Mage::getModel('payperrentals/reservationorders')
                            ->setProductId($item->getProductId())
                                ->setStartDate($start_date)
                                ->setEndDate($end_date)
                            ->setStartTurnoverBefore($_turnoverAr['before'])
                            ->setEndTurnoverAfter($_turnoverAr['after'])
                            ->setItemBookedSerialize(serialize($_turnoverAr['full_date_ar']))
                            ->setQty($qty)
                            ->setOrderId($order->getId())
                            ->setOrderItemId($item->getId());

                        $model->setStockId($item->getStockId());
                        $model->setId(null)->save();
                    }
                }
                Mage::getResourceModel('payperrentals/reservationquotes')->deleteByQuoteItemId($item->getQuoteItemId());
            }
        }

    }

}
