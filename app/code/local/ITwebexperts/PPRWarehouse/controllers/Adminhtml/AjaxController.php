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
	public function sendSelectedAction()
	{
		$sns = $this->getRequest()->getParam('sn');
		$resids = $this->getRequest()->getParam('sendRes');

		foreach($resids as $id){
			$resOrder = Mage::getModel('payperrentals/reservationorders')->load($id);
			$product = Mage::getModel('catalog/product')->load($resOrder->getProductId());
			$sn = array();
			if($product->getPayperrentalsUseSerials()){
				foreach($sns as $sid => $serialArr){
					if($sid == $id){
						foreach($serialArr as $serial){
							if($serial != ''){
								//todo check if serial exists and has status A
								$sn[] = $serial;
							}
						}
					}
				}
				// what is this code for?
				if(count($sn) < $resOrder->getQty()){
					$coll = Mage::getModel('payperrentals/serialnumbers')
						->getCollection()
						->addEntityIdFilter($resOrder->getProductId())
						->addSelectFilter("NOT FIND_IN_SET(sn, '".implode(',',$sn)."') AND status='A'");
					$j = 0;
					foreach($coll as $item){
						$sn[] = $item->getSn();
						if($j >= $resOrder->getQty() - count($sn)){
							break;
						}
						$j++;
					}

				}
				// this should be done inside an observer probably
				foreach($sn as $serial){
					Mage::getResourceSingleton('payperrentals/serialnumbers')
						->updateStatusBySerial($serial, 'O');
				}
			}
			$serialNumber = implode(',',$sn);
			$sendReturn = Mage::getModel('payperrentals/sendreturn')
				->setOrderId($resOrder->getOrderId())
				->setProductId($resOrder->getProductId())
				->setResStartdate($resOrder->getStartDate())
				->setResEnddate($resOrder->getEndDate())
				->setSendDate(date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time())))
				->setReturnDate('0000-00-00 00:00:00')
				->setQty($resOrder->getQty())//here needs a check this should always be true
				->setSn($serialNumber)
				->setStockId($resOrder->getStockId())
				->save();

			// this relationship should be in the opposite side (returns has a reference to reservation order and not the resOrder to the return)
			Mage::getResourceSingleton('payperrentals/reservationorders')->updateSendReturnById($id, $sendReturn->getId());
			//ITwebexperts_Payperrentals_Helper_Data::sendEmail('send', $sendReturn->getId());
		}
		$error = '';

		$results = array(
			'error' => $error
		);
		$this
			->getResponse()
			->setBody(Zend_Json::encode($results));
	}


	/**
	 * @TODO we are not implementing the Rental Queue features now
	 */
	public function sendSelectedQueueAction()
	{
		parent::sendSelectedQueueAction();
	}

	public function getPriceandavailabilityAction()
	{
		if(!$this->getRequest()->getParam('product_id')) {
			return;
		}


		$stockId = $this->getRequest()->getParam('stock_id');
		if( ! $stockId){
			/** @var Innoexts_Warehouse_Helper_Data $helper */
			$helper = Mage::helper('warehouse');
			$stockId = $helper->getSessionStockId() ? : $helper->getDefaultStockId();
		}

		$Product = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id'));
		if($Product->isConfigurable()){
			$Product = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($this->getRequest()->getParam('super_attribute'), $Product);
		}

		$qty = urldecode($this->getRequest()->getParam('qty'));
		if(!$qty){
			$qty = 1;
		}
		$qty1 = $qty;
		$customerGroup = ITwebexperts_Payperrentals_Helper_Data::getCustomerGroup();

		$startingDate = urldecode($this->getRequest()->getParam('start_date'));
		$endingDate = urldecode($this->getRequest()->getParam('end_date'));
		$stockAvail = '0';
		$stockRest = '0';
		if($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE || $Product->getBundlePricingtype() == ITwebexperts_Payperrentals_Model_Product_Bundlepricingtype::PRICING_BUNDLE_FORALL){
			if(is_object($Product)){
				$Product = Mage::getModel('catalog/product')->load($Product->getId());
				$priceAmount = ITwebexperts_Payperrentals_Helper_Data::calculatePrice($Product->getId(), $startingDate, $endingDate, $qty, $customerGroup);
			}else{
				$priceAmount = -1;
			}

			if($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE)
			{
				// I'm commenting this because is not used anywhere !
//				$isAvailableArr = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate);
//				$isAvailable = $isAvailableArr['avail'];
//				$maxQty = $isAvailableArr['maxqty'];
			}
			elseif($this->getRequest()->getParam('bundle_option'))
			{
				$selectionIds = $this->getRequest()->getParam('bundle_option');
				$selectedQtys = $this->getRequest()->getParam('bundle_option_qty');
				foreach($selectedQtys as $i1 => $j1){
					if(is_array($j1)){
						foreach($j1 as $k1 => $p1){
							$selectedQtys[$i1][$k1] = $qty * ($p1 == 0?1:$p1);
						}
					}else{
						$selectedQtys[$i1] = $qty * ($j1 == 0?1:$j1);
					}
				}
				$selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);
				$isAvailable = true;
				$maxQty = 100000;
				$qty1 = $qty;
				foreach ($selections->getItems() as $selection) {
					$Product = Mage::getModel('catalog/product')->load($selection->getProductId());
					/*if(isset($selectedQtys[$selection->getOptionId()])){
						$qty = $selectedQtys[$selection->getOptionId()];
					}else{
						$qty = $qty1;
					}*/
					if(isset($selectedQtys[$selection->getOptionId()][$selection->getSelectionId()])){
						$qty = $selectedQtys[$selection->getOptionId()][$selection->getSelectionId()];
					}elseif(isset($selectedQtys[$selection->getOptionId()])){
						$qty = $selectedQtys[$selection->getOptionId()];
					}else{
						$qty = $qty1;
					}

					// What is doing this code???? and why is repited below???
					if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){
						$isAvailableArr = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate);
						$isAvailable = $isAvailable && $isAvailableArr['avail'];
						if($maxQty > intval($isAvailableArr['maxqty'] / ($qty / $qty1))){
							$maxQty = intval($isAvailableArr['maxqty'] / ($qty / $qty1));
						}
					}
				}
			}
		}
		elseif($this->getRequest()->getParam('bundle_option')){
			$selectionIds = $this->getRequest()->getParam('bundle_option');
			$selectedQtys = $this->getRequest()->getParam('bundle_option_qty');
			foreach($selectedQtys as $i1 => $j1){
				if(is_array($j1)){
					foreach($j1 as $k1 => $p1){
						$selectedQtys[$i1][$k1] = $qty * (($p1 == 0)?1:$p1);
					}
				}else{
					$selectedQtys[$i1] = $qty * (($j1 == 0)?1:$j1);
				}

			}
			$selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);
			$priceVal = 0;
			$isAvailable = true;
			$maxQty = 100000;
			$qty1 = $qty;
			foreach ($selections->getItems() as $selection) {
				$Product = Mage::getModel('catalog/product')->load($selection->getProductId());
				/*if(isset($selectedQtys[$selection->getOptionId()])){
					$qty = $selectedQtys[$selection->getOptionId()];
				}else{
					$qty = $qty1;
				}*/
				if(isset($selectedQtys[$selection->getOptionId()][$selection->getSelectionId()])){
					$qty = $selectedQtys[$selection->getOptionId()][$selection->getSelectionId()];
				}elseif(isset($selectedQtys[$selection->getOptionId()])){
					$qty = $selectedQtys[$selection->getOptionId()];
				}else{
					$qty = $qty1;
				}

				if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){
					$priceAmount = ITwebexperts_Payperrentals_Helper_Data::calculatePrice($Product->getId(), $startingDate, $endingDate, $qty, $customerGroup);
					if($priceAmount == -1){
						$priceVal = -1;
						break;
					}
					$priceVal = $priceVal + $qty * $priceAmount;
					$isAvailableArr = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate);
					$isAvailable = $isAvailable && $isAvailableArr['avail'];
					//$maxQty = $isAvailableArr['maxqty'] / $qty1;
					if($maxQty > intval($isAvailableArr['maxqty'] / ($qty / $qty1))){
						$maxQty = intval($isAvailableArr['maxqty'] / $qty / $qty1);
					}
				}else{
					$priceVal = $priceVal + $qty * $Product->getPrice();
				}
			}
			$priceAmount = $priceVal;

		}


		if((isset($priceAmount)) && $priceAmount != -1){
			if($Product->getHasmultiply() == ITwebexperts_Payperrentals_Model_Product_Hasmultiply::STATUS_ENABLED && !is_null($qty)){
				$priceAmount += ITwebexperts_Payperrentals_Helper_Data::getOptionsPrice($Product, $priceAmount) * $qty;
			}else{
				$priceAmount += ITwebexperts_Payperrentals_Helper_Data::getOptionsPrice($Product, $priceAmount);
			}
		}

		if((isset($priceAmount)) && $priceAmount != -1 && $this->getRequest()->getParam('saveDates')){
			if(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_USE_GLOBAL_DAYS) == 1){
				Mage::getSingleton('core/session')->setData('startDateInitial',date('Y-m-d H:i:s', strtotime($startingDate)));
				Mage::getSingleton('core/session')->setData('endDateInitial',date('Y-m-d H:i:s', strtotime($endingDate)));
			}
		}

		$stockArr = array();
		$Product = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id'));

		if($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE)
		{
			if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE ||
				($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_CONFIGURABLE
					&& $Product->getIsReservation() != ITwebexperts_Payperrentals_Model_Product_Isreservation::STATUS_DISABLED)
			){
				$stockArr[$Product->getId()] = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getStock($Product->getId(), $startingDate, $endingDate, $qty, $stockId);
			}
			else{
				// $_product1 = Mage::getModel('catalog/product')->load($Product->getId());  // stop loading products !!
				$qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($Product)->getQty();
				$stockArr[$Product->getId()]['avail'] = $qtyStock;
				$stockArr[$Product->getId()]['remaining'] = $stockArr[$Product->getId()]['avail'] - $qty;
			}

		}
		elseif($this->getRequest()->getParam('bundle_option'))
		{
			$selectionIds = $this->getRequest()->getParam('bundle_option');
			$selectedQtys = $this->getRequest()->getParam('bundle_option_qty');
			foreach($selectedQtys as $i1 => $j1){
				if(is_array($j1)){
					foreach($j1 as $k1 => $p1){
						$selectedQtys[$i1][$k1] = $qty * ($p1 == 0?1:$p1);
					}
				}else{
					$selectedQtys[$i1] = $qty * ($j1 == 0?1:$j1);
				}
			}
			$selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);

			$qty1 = $qty;
			foreach ($selections->getItems() as $selection) {
				$Product = Mage::getModel('catalog/product')->load($selection->getProductId());
				/*if(isset($selectedQtys[$selection->getOptionId()])){
					$qty = $selectedQtys[$selection->getOptionId()];
				}else{
					$qty = $qty1;
				}*/

				if(isset($selectedQtys[$selection->getOptionId()][$selection->getSelectionId()])){
					$qty = $selectedQtys[$selection->getOptionId()][$selection->getSelectionId()];
				}elseif(isset($selectedQtys[$selection->getOptionId()])){
					$qty = $selectedQtys[$selection->getOptionId()];
				}else{
					$qty = $qty1;
				}


				if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){
					if(!isset($stockArr[$selection->getProductId()])){
						$stockArr[$selection->getProductId()] = ITwebexperts_Payperrentals_Helper_Data::getStock($Product->getId(), $startingDate, $endingDate, $qty);
						//$stockArr[$selection->getProductId()]['remaining'] = $stockArr[$selection->getProductId()]['remaining'] - ($qty-1);
					}else{
						$stockArr[$selection->getProductId()]['remaining'] = $stockArr[$selection->getProductId()]['remaining'] - $qty;
					}

				}else{
					if(!isset($stockArr[$selection->getProductId()])){
						$_product1 = Mage::getModel('catalog/product')->load($selection->getProductId());
						$qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product1)->getQty();
						$stockArr[$selection->getProductId()]['avail'] = $qtyStock;
						$stockArr[$selection->getProductId()]['remaining'] = $stockArr[$selection->getProductId()]['avail'] - $qty;
					}else{
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
		foreach($stockArr as $id => $avArr){
			if($avArr['remaining'] < 0){
				$isAvailable = false;
			}
			if($stockAvail > $avArr['avail']){
				//$maxQty = $avArr['avail'];
				$stockAvail = $avArr['avail'];
			}
			if($stockRest > $avArr['remaining']){
				$stockRest = $avArr['remaining'];
				$pid = $id;
			}
			$curProd = Mage::getModel('catalog/product')->load($id);
			$stockAvailText .= 'Stock available for product '. $curProd->getName().': '.$avArr['avail'].'<br/>';
			$stockRestText .= 'Stock remaining for product '. $curProd->getName().': '.$avArr['remaining'].'<br/>';
		}

		if(isset($pid)){
			// what is this?????????? this should be SO easy why everything is done so complex everywhere! KISS principle !!!
			if($qty1 && ($stockArr[$pid]['avail'] - $stockRest)){
				$maxQty = intval($stockArr[$pid]['avail']/ intval(($stockArr[$pid]['avail'] - $stockRest)/$qty1));
			}
		}

		if(count($stockArr) > 1){
			$stockAvailText .= 'Stock available for bundle'.': '.$maxQty.'<br/>';
			$stockRestText .= 'Stock remaining for bundle '.': '.($maxQty-$qty1).'<br/>';
		}
		$price = array(
			'amount' => isset($priceAmount)?$priceAmount:-1,
			'available' => $isAvailable,
			'stockAvail' => $stockAvail,
			'stockRest' => $stockRest,
			'stockAvailText' => $stockAvailText,
			'stockRestText' => $stockRestText,
			'maxqty' => $maxQty,
			'formatAmount' => isset($priceAmount)?Mage::helper('core')->currency($priceAmount):-1
		);

		$this
			->getResponse()
			->setBody(Zend_Json::encode($price));
	}




	public function getSerialNumbersbyItemIdAction()
	{
		$query = $this->getRequest()->getParam('value');
		$oId = $this->getRequest()->getParam('productId');
		$oitem = Mage::getModel('sales/order_item')->load($oId);
		$productId = $oitem->getProductId();

		$results = array();

		$coll = Mage::getModel('payperrentals/serialnumbers')
			->getCollection()
			->addEntityIdFilter($productId)
			->addSelectFilter("sn like '%".$query."%' AND status='A'")
		;

		$coll->addFieldToFilter('stock_id', $oitem->getStockId());

		foreach ($coll as $item) {
			$results[] = $item->getSn();
		}

		$this
			->getResponse()
			->setBody(Zend_Json::encode($results));
	}



	public function getEventsAction()
	{
		$storeOpen = intval(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_OPEN_TIME));
		$storeClose = intval(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_CLOSE_TIME));

		$start_date = date('Y-m-d',urldecode($this->getRequest()->getParam('start'))).' 00:00:00';
		$end_date = date('Y-m-d',urldecode($this->getRequest()->getParam('end'))).' 00:00:00';

		$productIds = explode(',',urldecode($this->getRequest()->getParam('productsids')));

		$events = array();
		foreach($productIds as $prid){
			$Product = Mage::getModel('catalog/product')->load($prid);
			$stockIds = Mage::helper('warehouse')->getStockIds();
			/* @var $pprWarehouseHelper ITwebexperts_PPRWarehouse_Helper_Data */
			$pprWarehouseHelper = Mage::helper('pprwarehouse');
			foreach($stockIds as $stockId)
			{
				$maxQty = $pprWarehouseHelper->getQtyForProductAndStock($Product, $stockId);
				$bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForDates($Product->getId(), $start_date, $end_date, 0, false, $stockId);

				foreach($bookedArray as $dateFormatted => $qtyPerDay){
					$evb = array(
						'title' => ' '.($maxQty - $qtyPerDay),
						'url' => $dateFormatted.';'.$prid.';'.$stockId,
						/*'id' => urlencode($dateFormatted.';'.$prid),*/
						/*'textColor' => $dateFormatted,*/
						'start' => date('Y-m-d', strtotime($dateFormatted)).' 00:00:00',
						'end' => date('Y-m-d', strtotime($dateFormatted)).' 23:59:59',
						'resource' => $Product->getId().'_'.$stockId // str_replace('.html','',$Product->getUrlPath())
					);
					if($maxQty - $qtyPerDay < 0){
						$evb['backgroundColor'] = '#cc0000';
						$evb['className'] = 'overbookColor';
					}
					$events[] = $evb;
				}

				$startTimePadding = strtotime(date('Y-m-d', strtotime($start_date)));
				$endTimePadding = strtotime(date('Y-m-d', strtotime($end_date)));

				$p = 0;
				while ($startTimePadding <= $endTimePadding) {
					if(!array_key_exists(date('Y-n-j', $startTimePadding), $bookedArray) ){
						$bookedTimesArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForTimes($Product->getId(), date('Y-m-d', $startTimePadding), 0, false, $stockId);
						if($p == 0){
							$startTimePaddingCur = strtotime(date('Y-m-d H:i', strtotime($start_date)));
						}else{
							$startTimePaddingCur = $startTimePadding;
						}

						$endTimePaddingCur = strtotime(date('Y-m-d', $startTimePadding).' 23:00:00');
						if($endTimePaddingCur >= strtotime($end_date)){
							$endTimePaddingCur = strtotime($end_date);
						}

						$intersectionArray = array();
						while ($startTimePaddingCur <= $endTimePaddingCur) {
							$dateFormatted = date('H:i:s', $startTimePaddingCur);
							if(intval($dateFormatted) >= $storeOpen && intval($dateFormatted) <= $storeClose){
								$intersectionArray[] = $dateFormatted;
							}
							$startTimePaddingCur += 60 * 60;//*time_increment
						}

						foreach($bookedTimesArray as $dateFormatted => $qtyPerDay){
							//check here if there is an intersection
							if( in_array($dateFormatted, $intersectionArray)){
								$evb = array(
									'title' => ' '.($maxQty - $qtyPerDay),
									'url' => date('Y-m-d', $startTimePadding).' '.$dateFormatted.';'.$prid.';'.$stockId,
									/*'id' => urlencode(date('Y-m-d', $startTimePadding).' '.$dateFormatted.';'.$prid),*/
									/*'textColor' => $dateFormatted,*/
									'start' => date('Y-m-d', $startTimePadding).' '.$dateFormatted,
									'end' => date('Y-m-d H:i:s',strtotime('+'.Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Timebox::XML_PATH_APPEARANCE_TIMEINCREMENTS).' minutes',strtotime(date('Y-m-d', $startTimePadding).' '.$dateFormatted))),
									'resource' => str_replace('.html','',$Product->getUrlPath())
								);
								if($maxQty - $qtyPerDay < 0){
									$evb['backgroundColor'] = '#cc0000';
									$evb['className'] = 'overbookColor';
								}
								$events[] = $evb;
							}
						}
					}

					$startTimePadding += 60 * 60 * 24;//*time_increment
					$p++;
				}
			}
		}

		$this
			->getResponse()
			->setBody(Zend_Json::encode($events));
	}


	public function getDateDetailsAction()
	{
		$orderList = '<table cellpadding="10" cellspacing="10" border="0" style="min-width:350px;"><tr><td style="font-weight: bold">Order ID</td><td style="font-weight: bold">Customer Name</td><td style="font-weight: bold">Start</td><td style="font-weight: bold">End</td><td style="font-weight: bold">Qty</td><td style="font-weight: bold">View Order</td></tr>';


		$orderArr = explode(';', urldecode($this->getRequest()->getParam('start')));

		$start_date = date('Y-m-d H:i:s',strtotime($orderArr[0]));
		$productId = $orderArr[1];
		$stockId = $orderArr[2];
		$coll = Mage::getModel('payperrentals/reservationorders')
			->getCollection()
			->addProductIdFilter($productId);
		$coll->addFieldToFilter('stock_id', $stockId);
		$coll->addOtypeFilter(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_ORDER);
		$coll->addSelectFilter("start_date <= '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date)."' AND end_date >= '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date)."'")
		;

		foreach($coll as $item){
			$orderList .= '<tr>';
			$order = Mage::getModel('sales/order')->loadByIncrementId($item->getOrderId());

			$shippingId = $order->getShippingAddressId();
			if(empty($shippingId)){
				$shippingId = $order->getBillingAddressId();
			}
			$address = Mage::getModel('sales/order_address')->load($shippingId);
			$customerName = $address->getFirstname(). ' '.$address->getLastname();
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
			$orderList .= '<a href="'.Mage::getUrl('adminhtml/sales_order/view', array('order_id' => $order->getEntityId())).'">'.Mage::helper('payperrentals')->__('View').'</a>';
			$orderList .= '</td>';
			$orderList .= '</tr>';
		}
		$orderList .= '</table>';
		$details['html'] = $orderList;
		$details['date'] = ITwebexperts_Payperrentals_Helper_Data::formatDbDate($start_date);
		$this
			->getResponse()
			->setBody(Zend_Json::encode($details));
	}




}
