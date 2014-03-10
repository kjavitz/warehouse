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
    public function getBookedQtyForProducts($_productIds, $_stDate = null, $_enDate = null, $_isQuote = false, $_isReport = false)
    {
        $_stockArr = array();
        if (Mage::app()->getRequest()->getParam('stock_id')) {
            $_stockArr[] = Mage::app()->getRequest()->getParam('stock_id');
        } elseif (Mage::registry('stock_id')) {
            $_stockArr[] = Mage::registry('stock_id');
        } else {
            $helper = Mage::helper('pprwarehouse');
            $_stockArr = $helper->getValidStockIds();
        }
        $_booked = array();
        $_isQuote = Mage::getSingleton("checkout/session")->getQuote()->getId();
        if (!$_isQuote) {
            $_isQuote = false;
        }

        if (!is_array($_productIds)) {
            $_productIds = array($_productIds);
        }

        $_productLoadAr = array();
        foreach ($_productIds as $_iProduct) {
            if (!$_isReport && ITwebexperts_Payperrentals_Helper_Inventory::isAllowedOverbook($_iProduct)) {
                return array('booked' => array());
            }
            $_productLoadAr[$_iProduct] = ITwebexperts_Payperrentals_Helper_Data::initProduct($_iProduct); //todo we could work only with ids, I think we don't need to load the product collection
        }

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

        foreach ($_stockArr as $_stockId) {

            $_reserveOrderCollection = Mage::getModel('payperrentals/reservationorders')->getCollection()
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
            $_sendReturnJoinConditionAr = array(
                'main_table.sendreturn_id = srt.id',
            );
            $_reserveOrderCollection->getSelect()
                ->joinLeft(
                    array('srt' => $_reserveOrderCollection->getTable('payperrentals/sendreturn')),
                    implode(' AND ', $_sendReturnJoinConditionAr),
                    array(
                        'send_date' => 'srt.send_date',
                        'return_date' => 'srt.return_date'
                    ));

            $_reserveOrderCollection->addSelectFilter("start_date <= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_enDate) . "' AND end_date >= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_stDate) . "'");
            $_reserveOrderCollection->addFieldToFilter('main_table.stock_id', $_stockId);


            foreach ($_reserveOrderCollection as $_reserveOrderItem) {
                $_reserveOrderItemProductId = $_reserveOrderItem->getProductId();
                $_realOrderId = $_reserveOrderItem->getOrderId();
                $_reConfigure = false;
                if ($_reserveOrderItem->getItemBookedSerialize() != '') {
                    $_bookedAr = unserialize($_reserveOrderItem->getItemBookedSerialize());
                    if (self::pregArrayKeys('/1970-(1|01)-(1|01)/', $_bookedAr) || self::pregArrayKeys('/0000-00-00/', $_bookedAr)) $_reConfigure = true;
                } else {
                    $_reConfigure = true;
                }
                if (!$_reConfigure) {
                    foreach ($_bookedAr as $_bookedItemDate => $_bookedItemData) {
                        /*Compare 2 sting date with calendar range foe exclude not in range dates*/
                        if ($_isReport && (strtotime($_bookedItemDate) < strtotime($_stDateCompare) || strtotime($_bookedItemDate) > strtotime($_enDateCompare))) continue;

                        if (!$_bookedItemData['qty']) {
                            $_bookedItemData['qty'] = $_reserveOrderItem->getQty();
                            $_reserveOrderItem->setIsUpdateSerialize(true);
                        }

                        if (!isset($_booked[$_bookedItemDate][$_reserveOrderItemProductId])) {
                            /** Initialize empty value */
                            $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['qty'] = 0;
                        }
                        $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['qty'] += $_bookedItemData['qty'];

                        /** Min/max time for start and end period functional. This functional used in first available range */
                        $_startTimestamp = strtotime($_bookedItemData['start_end']['period_start']);
                        $_endTimestamp = strtotime($_bookedItemData['start_end']['period_end']);
                        if (isset($_booked[$_bookedItemDate][$_reserveOrderItemProductId]['start_min_max'])) {
                            $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['start_min_max']['max'] = max($_booked[$_bookedItemDate][$_reserveOrderItemProductId]['start_min_max']['max'], $_startTimestamp);
                            $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['start_min_max']['min'] = min($_booked[$_bookedItemDate][$_reserveOrderItemProductId]['start_min_max']['min'], $_startTimestamp);
                        } else {
                            $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['start_min_max'] = array(
                                'min' => $_startTimestamp,
                                'max' => $_startTimestamp
                            );
                        }
                        if (isset($_booked[$_bookedItemDate][$_reserveOrderItemProductId]['end_min_max'])) {
                            $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['end_min_max']['max'] = max($_booked[$_bookedItemDate][$_reserveOrderItemProductId]['end_min_max']['max'], $_endTimestamp);
                            $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['end_min_max']['min'] = min($_booked[$_bookedItemDate][$_reserveOrderItemProductId]['end_min_max']['min'], $_endTimestamp);
                        } else {
                            $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['end_min_max'] = array(
                                'min' => $_endTimestamp,
                                'max' => $_endTimestamp
                            );
                        }
                        /******************/

                        if (!$_isReport) {
                            if (!array_key_exists('period_start', $_booked[$_bookedItemDate][$_reserveOrderItemProductId]) || !array_search($_bookedItemData['start_end']['period_start'], $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['period_start'])) {
                                $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['period_start'][] = $_bookedItemData['start_end']['period_start'];
                            }
                            if (!array_key_exists('period_end', $_booked[$_bookedItemDate][$_reserveOrderItemProductId]) || !array_search($_bookedItemData['start_end']['period_end'], $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['period_end'])) {
                                $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['period_end'][] = $_bookedItemData['start_end']['period_end'];
                            }
                        } else {
                            if (!isset($_booked[$_bookedItemDate][$_reserveOrderItemProductId]['orders']['order_ids']) || array_search($_realOrderId, $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['orders']['order_ids']) === false) {
                                $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['orders']['order_ids'][] = $_realOrderId;
                                $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['orders']['start_end'][$_realOrderId]['period_start'] = $_bookedItemData['start_end']['period_start'];
                                $_booked[$_bookedItemDate][$_reserveOrderItemProductId]['orders']['start_end'][$_realOrderId]['period_end'] = $_bookedItemData['start_end']['period_end'];
                            }
                        }
                    }
                } else {
                    /**
                     * If database not have serialized data for time period
                     * then calculate, serialize and save periods to database
                     */
                    $_product = $_productLoadAr[$_reserveOrderItemProductId];
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

                    $_itemDateAr = self::getOrderOrQuoteTurnoverForSerialize($_product, $_reserveOrderItem);
                    $_itemStartDate = $_itemDateAr['item_start'];
                    $_itemEndDate = $_itemDateAr['item_end'];
                    $_turnoverForOrder = $_itemDateAr['turnover_for_order_or_quote'];

                    $_qty = $_reserveOrderItem->getQty();

                    Mage::dispatchEvent('ppr_get_booked_qty_for_dates', array('turnover_timestamp_before' => &$_turnoverTimeBefore, 'turnover_timestamp_after' => &$_turnoverTimeAfter));

                    $_startTimePadding = strtotime(date('Y-m-d', strtotime($_itemStartDate))) - $_turnoverTimeBefore;
                    $_endTimePadding = strtotime(date('Y-m-d', strtotime($_itemEndDate))) + $_turnoverTimeAfter;

                    while ($_startTimePadding <= $_endTimePadding) {
                        $_dateFormatted = date('Y-n-j', $_startTimePadding);
                        $_startTimePadding += 86400;
                        /*Compare 2 sting date with calendar range foe exclude not in range dates*/
                        if ($_isReport && (strtotime($_dateFormatted) < strtotime($_stDateCompare) || strtotime($_dateFormatted) > strtotime($_enDateCompare))) continue;

                        if (!isset($_booked[$_dateFormatted][$_reserveOrderItemProductId])) {
                            $_booked[$_dateFormatted][$_reserveOrderItemProductId]['qty'] = 0;
                        }
                        $_booked[$_dateFormatted][$_reserveOrderItemProductId]['qty'] += (int)$_qty;

                        /** Min/max time for start and end period functional. This functional used in first available range */
                        $_startTimestamp = strtotime($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start']);
                        $_endTimestamp = strtotime($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end']);
                        if (isset($_booked[$_dateFormatted][$_reserveOrderItemProductId]['start_min_max'])) {
                            $_booked[$_dateFormatted][$_reserveOrderItemProductId]['start_min_max']['max'] = max($_booked[$_dateFormatted][$_reserveOrderItemProductId]['start_min_max']['max'], $_startTimestamp);
                            $_booked[$_dateFormatted][$_reserveOrderItemProductId]['start_min_max']['min'] = min($_booked[$_dateFormatted][$_reserveOrderItemProductId]['start_min_max']['min'], $_startTimestamp);
                        } else {
                            $_booked[$_dateFormatted][$_reserveOrderItemProductId]['start_min_max'] = array(
                                'min' => $_startTimestamp,
                                'max' => $_startTimestamp
                            );
                        }
                        if (isset($_booked[$_dateFormatted][$_reserveOrderItemProductId]['end_min_max'])) {
                            $_booked[$_dateFormatted][$_reserveOrderItemProductId]['end_min_max']['max'] = max($_booked[$_dateFormatted][$_reserveOrderItemProductId]['end_min_max']['max'], $_endTimestamp);
                            $_booked[$_dateFormatted][$_reserveOrderItemProductId]['end_min_max']['min'] = min($_booked[$_dateFormatted][$_reserveOrderItemProductId]['end_min_max']['min'], $_endTimestamp);
                        } else {
                            $_booked[$_dateFormatted][$_reserveOrderItemProductId]['end_min_max'] = array(
                                'min' => $_endTimestamp,
                                'max' => $_endTimestamp
                            );
                        }
                        /******************/

                        if (!$_isReport) {
                            if (!array_key_exists('period_start', $_booked[$_dateFormatted][$_reserveOrderItemProductId]) || !array_search($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'], $_booked[$_dateFormatted][$_reserveOrderItemProductId]['period_start'])) {
                                $_booked[$_dateFormatted][$_reserveOrderItemProductId]['period_start'][] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'];
                            }
                            if (!array_key_exists('period_end', $_booked[$_dateFormatted][$_reserveOrderItemProductId]) || !array_search($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'], $_booked[$_dateFormatted][$_reserveOrderItemProductId]['period_end'])) {
                                $_booked[$_dateFormatted][$_reserveOrderItemProductId]['period_end'][] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'];
                            }
                        } else {
                            if (!isset($_booked[$_dateFormatted][$_reserveOrderItemProductId]['orders']['order_ids']) || array_search($_realOrderId, $_booked[$_dateFormatted][$_reserveOrderItemProductId]['orders']['order_ids']) === false) {
                                $_booked[$_dateFormatted][$_reserveOrderItemProductId]['orders']['order_ids'][] = $_realOrderId;
                                $_booked[$_dateFormatted][$_reserveOrderItemProductId]['orders']['start_end'][$_realOrderId]['period_start'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'];
                                $_booked[$_dateFormatted][$_reserveOrderItemProductId]['orders']['start_end'][$_realOrderId]['period_end'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'];
                            }
                        }
                    }
                }
                if ($_reserveOrderItem->getIsUpdateSerialize()) {
                    $_product = $_productLoadAr[$_reserveOrderItemProductId];
                    self::getOrderOrQuoteTurnoverForSerialize($_product, $_reserveOrderItem);
                }
            }

            if ($_isQuote) {
                $_reserveQuote = Mage::getModel('payperrentals/reservationquotes')->getCollection()
                    ->addQuoteIdFilter($_isQuote)
                    ->addProductIdsFilter($_productIds);
                $_reserveQuote->addFieldToFilter('main_table.stock_id', $_stockId);

                if (Mage::helper('payperrentals/config')->isHotelMode(Mage::app()->getStore()->getId()) || date('Y-m-d', strtotime($_stDate)) == date('Y-m-d', strtotime($_enDate))) {
                    $_reserveQuote->addSelectFilter("start_date <= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_enDate, false, -60) . "' AND DATE_SUB(end_date, INTERVAL 1 MINUTE) >= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_stDate) . "'");
                } else {
                    $_reserveQuote->addSelectFilter("start_date <= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_enDate) . "' AND end_date >= '" . ITwebexperts_Payperrentals_Helper_Data::toDbDate($_stDate) . "'");
                }

                foreach ($_reserveQuote as $_reserveQuoteItem) {
                    $_reserveQuoteItemProductId = $_reserveQuoteItem->getProductId();
                    $_realQuoteId = $_reserveQuoteItem->getQuoteId();
                    $_reConfigure = false;
                    if ($_reserveQuoteItem->getItemBookedSerialize() != '') {
                        $_bookedAr = unserialize($_reserveQuoteItem->getItemBookedSerialize());
                        if (self::pregArrayKeys('/1970-(1|01)-(1|01)/', $_bookedAr) || self::pregArrayKeys('/0000-00-00/', $_bookedAr)) $_reConfigure = true;
                    } else {
                        $_reConfigure = true;
                    }
                    if (!$_reConfigure) {
                        foreach ($_bookedAr as $_bookedItemDate => $_bookedItemData) {
                            /*Compare 2 sting date with calendar range foe exclude not in range dates*/
                            if ($_isReport && (strtotime($_bookedItemDate) < strtotime($_stDateCompare) || strtotime($_bookedItemDate) > strtotime($_enDateCompare))) continue;
                            if (!$_bookedItemData['qty']) {
                                $_bookedItemData['qty'] = $_reserveQuoteItem->getQty();
                                $_reserveQuoteItem->setIsUpdateSerialize(true);
                            }
                            if (!isset($_booked[$_bookedItemDate][$_reserveQuoteItemProductId])) {
                                $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['qty'] = 0;
                            }
                            $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['qty'] += $_bookedItemData['qty'];

                            /** Min/max time for start and end period functional. This functional used in first available range */
                            $_startTimestamp = strtotime($_bookedItemData['start_end']['period_start']);
                            $_endTimestamp = strtotime($_bookedItemData['start_end']['period_end']);
                            if (isset($_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['start_min_max'])) {
                                $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['start_min_max']['max'] = max($_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['start_min_max']['max'], $_startTimestamp);
                                $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['start_min_max']['min'] = min($_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['start_min_max']['min'], $_startTimestamp);
                            } else {
                                $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['start_min_max'] = array(
                                    'min' => $_startTimestamp,
                                    'max' => $_startTimestamp
                                );
                            }
                            if (isset($_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['end_min_max'])) {
                                $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['end_min_max']['max'] = max($_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['end_min_max']['max'], $_endTimestamp);
                                $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['end_min_max']['min'] = min($_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['end_min_max']['min'], $_endTimestamp);
                            } else {
                                $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['end_min_max'] = array(
                                    'min' => $_endTimestamp,
                                    'max' => $_endTimestamp
                                );
                            }
                            /******************/

                            if (!$_isReport) {
                                if (!array_key_exists('period_start', $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]) || !array_search($_bookedItemData['start_end']['period_start'], $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['period_start'])) {
                                    $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['period_start'][] = $_bookedItemData['start_end']['period_start'];
                                }
                                if (!array_key_exists('period_end', $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]) || !array_search($_bookedItemData['start_end']['period_end'], $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['period_end'])) {
                                    $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['period_end'][] = $_bookedItemData['start_end']['period_end'];
                                }
                            } else {
                                if (!isset($_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['quotes']['quote_ids']) || array_search($_realQuoteId, $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['quotes']['quote_ids']) === false) {
                                    $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['quotes']['quote_ids'][] = $_realQuoteId;
                                    $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['quotes']['start_end'][$_realQuoteId]['period_start'] = $_bookedItemData['start_end']['period_start'];
                                    $_booked[$_bookedItemDate][$_reserveQuoteItemProductId]['quotes']['start_end'][$_realQuoteId]['period_end'] = $_bookedItemData['start_end']['period_end'];
                                }
                            }
                        }
                    } else {
                        /**
                         * If database not have serialized data for time period
                         * then calculate, serialize and save periods to database
                         */
                        $_product = $_productLoadAr[$_reserveQuoteItemProductId];
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

                        $_itemDateAr = self::getOrderOrQuoteTurnoverForSerialize($_product, $_reserveQuoteItem, true);
                        $_itemStartDate = $_itemDateAr['item_start'];
                        $_itemEndDate = $_itemDateAr['item_end'];
                        $_turnoverForOrder = $_itemDateAr['turnover_for_order_or_quote'];

                        $_qty = $_reserveQuoteItem->getQty();

                        $_startTimePadding = strtotime(date('Y-m-d', strtotime($_itemStartDate))) - $_turnoverTimeBefore;
                        $_endTimePadding = strtotime(date('Y-m-d', strtotime($_itemEndDate))) + $_turnoverTimeAfter;

                        while ($_startTimePadding <= $_endTimePadding) {
                            $_dateFormatted = date('Y-n-j', $_startTimePadding);
                            $_startTimePadding += 86400;
                            /*Compare 2 sting date with calendar range foe exclude not in range dates*/
                            if ($_isReport && (strtotime($_dateFormatted) < strtotime($_stDateCompare) || strtotime($_dateFormatted) > strtotime($_enDateCompare))) continue;
                            if (!isset($_booked[$_dateFormatted][$_reserveQuoteItemProductId])) {
                                $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['qty'] = 0;
                            }
                            $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['qty'] += (int)$_qty;

                            /** Min/max time for start and end period functional. This functional used in first available range */
                            $_startTimestamp = strtotime($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start']);
                            $_endTimestamp = strtotime($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end']);
                            if (isset($_booked[$_dateFormatted][$_reserveQuoteItemProductId]['start_min_max'])) {
                                $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['start_min_max']['max'] = max($_booked[$_dateFormatted][$_reserveQuoteItemProductId]['start_min_max']['max'], $_startTimestamp);
                                $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['start_min_max']['min'] = min($_booked[$_dateFormatted][$_reserveQuoteItemProductId]['start_min_max']['min'], $_startTimestamp);
                            } else {
                                $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['start_min_max'] = array(
                                    'min' => $_startTimestamp,
                                    'max' => $_startTimestamp
                                );
                            }
                            if (isset($_booked[$_dateFormatted][$_reserveQuoteItemProductId]['end_min_max'])) {
                                $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['end_min_max']['max'] = max($_booked[$_dateFormatted][$_reserveQuoteItemProductId]['end_min_max']['max'], $_endTimestamp);
                                $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['end_min_max']['min'] = min($_booked[$_dateFormatted][$_reserveQuoteItemProductId]['end_min_max']['min'], $_endTimestamp);
                            } else {
                                $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['end_min_max'] = array(
                                    'min' => $_endTimestamp,
                                    'max' => $_endTimestamp
                                );
                            }
                            /******************/

                            if (!$_isReport) {
                                if (!array_key_exists('period_start', $_booked[$_dateFormatted][$_reserveQuoteItemProductId]) || !array_search($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'], $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['period_start'])) {
                                    $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['period_start'][] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'];
                                }
                                if (!array_key_exists('period_end', $_booked[$_dateFormatted][$_reserveQuoteItemProductId]) || !array_search($_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'], $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['period_end'])) {
                                    $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['period_end'][] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'];
                                }
                            } else {
                                if (!isset($_booked[$_dateFormatted][$_reserveQuoteItemProductId]['quotes']['quote_ids']) || array_search($_realQuoteId, $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['quotes']['quote_ids']) === false) {
                                    $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['quotes']['quote_ids'][] = $_realQuoteId;
                                    $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['quotes']['start_end'][$_realQuoteId]['period_start'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_start'];
                                    $_booked[$_dateFormatted][$_reserveQuoteItemProductId]['quotes']['start_end'][$_realQuoteId]['period_end'] = $_turnoverForOrder['full_date_ar'][$_dateFormatted]['start_end']['period_end'];
                                }
                            }
                        }
                    }


                    if ($_reserveQuoteItem->getIsUpdateSerialize()) {
                        $_product = $_productLoadAr[$_reserveQuoteItemProductId];
                        self::getOrderOrQuoteTurnoverForSerialize($_product, $_reserveQuoteItem, true);
                    }
                }
            }
        }
        if (!$_isReport) {
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
                $resultObject = new Varien_Object();
                $resultObject->setBooked($_booked);
                Mage::dispatchEvent('ppr_disabled_dates', array('result' => $resultObject, 'request_params' => Mage::app()->getRequest()->getParams(), 'product' => $_product, 'id' => $_id, 'start_date' => $_stDate, 'end_date' => $_enDate));
                $_booked = $resultObject->getBooked();
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

}
