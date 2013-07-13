<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data extends ITwebexperts_Payperrentals_Helper_Data
{

	public static function getBookedQtyForTimes($product, $start_date, $isQuote = 0, $useOverbook = true, $stockId = null)
	{
		if( ! $stockId){
			Mage::throwException('Stock ID is required in getBookedQtyForTimes');
		}

		if( ! $product instanceof Mage_Catalog_Model_Product){
			$product = Mage::getModel('catalog/product')->load($product);
		}
		$productId = $product->getId();

		if($product->getGlobalTurnoverBefore() == 0){
			$turnoverTimeBefore = self::getPeriodInSeconds($product->getPayperrentalsAvailNumberb(), $product->getPayperrentalsAvailTypeb());
		}else{
			$turnoverTimeBefore = self::getPeriodInSeconds(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_BEFORE_NUMBER),Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_BEFORE_TYPE));
		}

		if($product->getGlobalTurnoverAfter() == 0){
			$turnoverTimeAfter = self::getPeriodInSeconds($product->getPayperrentalsAvailNumber(), $product->getPayperrentalsAvailType());
		}else{
			$turnoverTimeAfter = self::getPeriodInSeconds(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_AFTER_NUMBER),Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_AFTER_TYPE));
		}

		//here might need a check for turnover - not sure how should be handled. The problem
		//might appear when there is a resorvation with a turnovertime of days for a hourly reservation.

		$coll = Mage::getModel('payperrentals/reservationorders')
			->getCollection()
			->addProductIdFilter($productId)
			->addFieldToFilter('stock_id', $stockId);
		if($isQuote == 0){
			$coll->addOtypeFilter(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_ORDER);
		}else{
			$coll->addQuoteIdFilter($isQuote);
		}
		$coll->addBetweenDatesFilter(date('Y-m-d',strtotime($start_date)));

		$booked = array();

		if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($product) && $useOverbook){
			return $booked;
		}

		if(!Mage::app()->getStore()->isAdmin()){
			/*this part disables blocked times*/
			$coll2 = Mage::getModel('payperrentals/excludeddates')
				->getCollection()
				->addEntityIdFilter($product->getId())
				->addStoreIdFilter(Mage::app()->getStore()->getId())
				->addSelectFilter('disabled_type = "daily" AND date(disabled_from) = date(disabled_to)');

			foreach ($coll2 as $item) {
				$startTimePadding = strtotime(date('Y-m-d H:i', strtotime($item->getDisabledFrom())));
				$endTimePadding = strtotime(date('Y-m-d H:i', strtotime($item->getDisabledTo())));
				while ($startTimePadding <= $endTimePadding) {
					$dateFormatted = date('H:i:s', $startTimePadding);
					$booked[$dateFormatted] = 100000; /*todo check this - for sure it will be a bug in the quantity report*/
					$startTimePadding += 60 * 60;//*time_increment
				}
			}
		}

		foreach ($coll as $item) {
			$startTimePadding = strtotime(date('Y-m-d H:i', strtotime($item->getStartDate()))) - $turnoverTimeBefore;
			$endTimePadding = strtotime(date('Y-m-d H:i', strtotime($item->getEndDate()))) + $turnoverTimeAfter;
			$qty = $item->getQty();//here should be qty invoiced- also check with cancelled qty
			while ($startTimePadding < $endTimePadding) {
				if(date('Y-m-d', $startTimePadding) == date('Y-m-d', strtotime($start_date))){
					$dateFormatted = date('H:i:s', $startTimePadding);
					if(isset($booked[$dateFormatted])){
						$booked[$dateFormatted] += $qty;
					}else{
						$booked[$dateFormatted] = $qty;
					}
				}

				$startTimePadding += 60 * 60;//*time_increment
			}
		}
		return $booked;
	}


	/**
	 * @param $product Mage_Catalog_Model_Product|int
	 * @param null $stockId
	 * @param null $stDate
	 * @param null $enDate
	 * @param int $isQuote
	 * @param bool $useOverbook
	 * @return array
	 */
	public static function getBookedQtyForDates($product, $stDate = null, $enDate = null, $isQuote = 0, $useOverbook = true, $stockId = null)
	{
		if( ! $stockId){
			Mage::throwException('Stock ID is required in getBookedQtyForDates');
		}

		if( ! $product instanceof Mage_Catalog_Model_Product){
			$product = Mage::getModel('catalog/product')->load($product);
		}
		$productId = $product->getId();

		if($product->getGlobalTurnoverBefore() == 0){
			$turnoverTimeBefore = self::getPeriodInSeconds($product->getPayperrentalsAvailNumberb(), $product->getPayperrentalsAvailTypeb());
		}else{
			$turnoverTimeBefore = self::getPeriodInSeconds(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_BEFORE_NUMBER),Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_BEFORE_TYPE));
		}

		if($product->getGlobalTurnoverAfter() == 0){
			$turnoverTimeAfter = self::getPeriodInSeconds($product->getPayperrentalsAvailNumber(), $product->getPayperrentalsAvailType());
		}else{
			$turnoverTimeAfter = self::getPeriodInSeconds(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_AFTER_NUMBER),Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_TURNOVER_AFTER_TYPE));
		}

		if(is_null($stDate)){
			$stDate = date('Y-m-d', strtotime('-'.self::CALCULATE_DAYS_BEFORE.' days', time()));
		}

		if(is_null($enDate)){
			$enDate = date('Y-m-d', strtotime('+'.self::CALCULATE_DAYS_AFTER.' days', time()));
		}

		if(!is_null($stDate)){
			$stDate = date('Y-m-d', strtotime('-'.$turnoverTimeAfter.' seconds', strtotime($stDate)));
		}

		if(!is_null($enDate)){
			$enDate = date('Y-m-d', strtotime('+'.$turnoverTimeBefore.' seconds', strtotime($enDate)));
		}


		$coll = Mage::getModel('payperrentals/reservationorders')
			->getCollection()
			->addProductIdFilter($productId)
			->addFieldToFilter('stock_id', $stockId);

		if($isQuote == 0){
			$coll->addOtypeFilter(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_ORDER);
		}else{
			$coll->addQuoteIdFilter($isQuote);
		}
		$coll->addSelectFilter("start_date < '".self::toDbDate($enDate)."' AND end_date > '".self::toDbDate($stDate)."'")
		;
		//Zend_Debug::dump($coll->getData());
		$booked = array();
		$bookedTimes = array();

		if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($product) && $useOverbook){
			return $booked;
		}

		$storeOpen = intval(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_OPEN_TIME));
		$storeClose = intval(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_CLOSE_TIME));

		$useTimes = self::useTimes($product->getId());

		foreach ($coll as $item) {

			$startTimePadding = strtotime(date('Y-m-d', strtotime($item->getStartDate()))) - $turnoverTimeBefore;
			$endTimePadding = strtotime(date('Y-m-d', strtotime($item->getEndDate()))) + $turnoverTimeAfter;

			$qty = $item->getQty();//here should be qty invoiced- also check with cancelled qty

			if($useTimes == 2){
				$initialStartTime = strtotime(date('Y-m-d H:i:s', strtotime($item->getStartDate()))) - $turnoverTimeBefore;
				$startTimePadding += 60 * 60 * 24;
				$initialEndTime = strtotime(date('Y-m-d H:i:s', strtotime($item->getEndDate()))) + $turnoverTimeAfter;
				$initStartEndTime = strtotime('-0 day', strtotime(date('Y-m-d',$initialEndTime)));
				$endTimePadding -= 60 * 60 * 24;
			}

			while ($startTimePadding <= $endTimePadding) {
				$dateFormatted = date('Y-n-j', $startTimePadding);
				if(isset($booked[$dateFormatted])){
					$booked[$dateFormatted] += $qty;
				}else{
					$booked[$dateFormatted] = $qty;
				}
				$startTimePadding += 60 * 60 * 24;
			}

			if($useTimes == 2){
				if(date('Y-m-d', strtotime($item->getStartDate())) != date('Y-m-d', strtotime($item->getEndDate())) ){ //difference is bigger than 1 day
					$endInitialStartTime = strtotime(date('Y-m-d',$initialStartTime).' 23:00:00');

					while ($initialStartTime <= $endInitialStartTime) {
						$dateFormatted = date('Y-n-j H:i', $initialStartTime);
						$dateFormat = date('Y-n-j', $initialStartTime);
						$timeFormat = date('H:i', $initialStartTime);

						if(isset($bookedTimes[$dateFormat][$timeFormat])){
							$bookedTimes[$dateFormat][$timeFormat] += $qty;
						}else{
							$bookedTimes[$dateFormat][$timeFormat] = $qty;
						}
						$initialStartTime += 60 * 60; //*time increment
					}

					while ($initStartEndTime < $initialEndTime) {
						$dateFormatted = date('Y-n-j H:i', $initStartEndTime);
						$dateFormat = date('Y-n-j', $initStartEndTime);
						$timeFormat = date('H:i', $initStartEndTime);

						if(isset($bookedTimes[$dateFormat][$timeFormat])){
							$bookedTimes[$dateFormat][$timeFormat] += $qty;
						}else{
							$bookedTimes[$dateFormat][$timeFormat] = $qty;
						}
						$initStartEndTime += 60 * 60;//*time increment
					}
				} else{
					while ($initialStartTime <= $initialEndTime) {
						$dateFormatted = date('Y-n-j H:i', $initialStartTime);
						$dateFormat = date('Y-n-j', $initialStartTime);
						$timeFormat = date('H:i', $initialStartTime);

						if(isset($bookedTimes[$dateFormat][$timeFormat])){
							$bookedTimes[$dateFormat][$timeFormat] += $qty;
						}else{
							$bookedTimes[$dateFormat][$timeFormat] = $qty;
						}
						$initialStartTime += 60 * 60; //*time increment
					}
				}
			}
		}

		foreach($bookedTimes as $dateFormatted => $timesFormatted){
			if(self::countNotExtra($timesFormatted, $storeOpen, $storeClose) >= ($storeClose - $storeOpen + 1)){
				if(isset($booked[$dateFormatted])){
					$booked[$dateFormatted] += min($timesFormatted);
				}else{
					$booked[$dateFormatted] = min($timesFormatted);
				}
			}
		}

		return $booked;
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
	public static function isAvailable($productId, $start_date, $end_date, $qty=1, $stockId = null, $returnQty = false)
	{
		if($returnQty && ! $stockId){
			Mage::throwException('StockId is required when the qty is requested in isAvailable');
		}

		if(!$qty) {
			$qty = 1;
		}

		$isAvailable = true;
		$returnData = array(
			'avail' => $isAvailable,
			'maxqty' => $qty
		);

		$Product = Mage::getModel('catalog/product')->load($productId);

		if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
			return $returnQty ? $returnData : true;
		}

		$helper = Mage::helper('pprwarehouse');
		$stockIds = $stockId ? array($stockId) : $helper->getValidStockIds();
		foreach($stockIds as $stockId)
		{
			$isAvailable = true;
			$maxQty = $helper->getQtyForProductAndStock($Product, $stockId);

			if($maxQty < $qty){
				$isAvailable = false;
				if($returnQty){
					return array('avail'=>false,'maxqty' => $maxQty);
				}
				continue;
			}
			$bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForDates($Product->getId(), $start_date, $end_date, 0, true, $stockId);

			foreach($bookedArray as $dateFormatted => $qtyPerDay){
				if($maxQty - $qtyPerDay < $qty){
					$isAvailable = false;
					if($returnQty){
						return array('avail'=>false,'maxqty' => ($maxQty - $qtyPerDay ));
					}
					break;
				}
			}
			if( ! $isAvailable){
				continue;
			}

			//check if this function needs somehow the quoteid
			$startTimePadding = strtotime(date('Y-m-d', strtotime($start_date)));
			$endTimePadding = strtotime(date('Y-m-d', strtotime($end_date)));

			if($startTimePadding <= $endTimePadding){
				while ($startTimePadding <= $endTimePadding && $isAvailable) {
					if(!array_key_exists(date('Y-n-j', $startTimePadding), $bookedArray) ){
						$bookedTimesArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForTimes($Product->getId(), date('Y-m-d', $startTimePadding), 0, true, $stockId);
						foreach($bookedTimesArray as $dateFormatted => $qtyPerDay){
							if($maxQty - $qtyPerDay < $qty){
								$isAvailable = false;
								if($returnQty){
									return array('avail'=>false,'maxqty' => ($maxQty - $qtyPerDay ));
								}
								break;
							}
						}
					}

					$startTimePadding += 60 * 60 * 24;//*time_increment
				}
			}
		}

		return $returnQty ? $returnData : $isAvailable;
	}




	public static function isAvailableWithQty($productId, $qty=1, $start_date, $end_date, $stockId = null)
	{
		return ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailable($productId, $start_date, $end_date, $qty, $stockId, true);
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
	public static function getAvailability($product_id, $qty=1, $startingDate, $endingDate, $stockId = null)
	{

		if( ! $stockId){
			Mage::throwException('Stock ID is required in getAvailability');
		}

		$stockArr = array();
		$Product = Mage::getModel('catalog/product')->load($product_id);

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
			//$price = $Product->getPrice();

			foreach ($optionCol as $option) {
				if($option->required) {
					$selections = $option->getSelections();
					//print_r($selections);
					foreach ($selections as $selection) {
						$Product = Mage::getModel('catalog/product')->load($selection->getProductId());

						if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){
							if(!isset($stockArr[$selection->getProductId()])){
								$stockArr[$selection->getProductId()] = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getStock($Product->getId(), $startingDate, $endingDate, $qty, $stockId);
								//$stockArr[$selection->getProductId()]['remaining'] = $stockArr[$selection->getProductId()]['remaining'] - ($qty-1);
							}else{
								// @TODO else what?????
								//$stockArr[$selection->getProductId()]['remaining'] = $stockArr[$selection->getProductId()]['remaining'] - $qty;
							}

						}else{
							if(!isset($stockArr[$selection->getProductId()])){
								$qtyStock = Mage::helper('pprwarehouse')->getQtyForProductAndStock($Product, $stockId);
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
				//$maxQty = $avArr['avail'];
				$stockAvail = $avArr['avail'];
			}
		}
		return $stockAvail;
	}



	/**
	 * @param $product
	 * @param $start_date
	 * @param $end_date
	 * @param $qty	int this is not used anymore (will be removed in next releases)
	 * @param null $stockId
	 * @return array
	 */
	public static function getStock($product, $start_date, $end_date, $qty, $stockId = null)
	{
		$stockArr = array();

		$stockArr['avail'] = 0;
		$stockArr['remaining'] = 0;


		if( ! $product instanceof Mage_Catalog_Model_Product){
			$product = Mage::getModel('catalog/product')->load($product);
		}
		else {
			// TODO: fix this, we should not load the product if we have a valid model here
			// the problem is that probably that model has not all the needed attributes, so we need to modify the collections first
			$product = Mage::getModel('catalog/product')->load($product->getId());
		}
		$productId = $product->getId();

		$helper = Mage::helper('pprwarehouse');
		$originalMaxQty = $helper->getQtyForProductAndStock($product, $stockId);

		$maxQty = $originalMaxQty;

		$bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForDates($product, $start_date, $end_date, 0, false, $stockId);
		$maxBookedQtyByDate = 0;
		if($bookedArray){
			$maxBookedQtyByDate = max($bookedArray);
			$maxQty = $maxQty - $maxBookedQtyByDate;
		}

		//check if this function needs somehow the quoteid
		$startTimePadding = strtotime(date('Y-m-d', strtotime($start_date)));
		$endTimePadding = strtotime(date('Y-m-d', strtotime($end_date)));

		if($startTimePadding <= $endTimePadding){
			while ($startTimePadding <= $endTimePadding) {
				if(!array_key_exists(date('Y-n-j', $startTimePadding), $bookedArray) ){
					$bookedTimesArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForTimes($product, date('Y-m-d', $startTimePadding), 0, false, $stockId);
					if($bookedTimesArray){
						$maxBookedQtyByTime = max($bookedTimesArray);
						if($maxBookedQtyByTime > $maxBookedQtyByDate){
							$maxQty = $originalMaxQty - $maxBookedQtyByTime;
						}
					}
				}

				$startTimePadding += 60 * 60 * 24;//*time_increment
			}
		}

		$stockArr['avail'] = $maxQty;
		$stockArr['remaining'] = $maxQty;

		return $stockArr;
	}




}
