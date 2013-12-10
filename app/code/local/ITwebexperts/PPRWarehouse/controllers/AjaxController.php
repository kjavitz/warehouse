<?php
/**
 *
 * @author Enrique Piatti
 */
require_once 'ITwebexperts/Payperrentals/controllers/AjaxController.php';


class ITwebexperts_PPRWarehouse_AjaxController extends ITwebexperts_Payperrentals_AjaxController
{

	/**
	 * override for checking the qty for every stock
	 */
    /*updated*/
	public function updateTimesAction()
	{
		if(!$this->getRequest()->getParam('product_id')) {
			return;
		}
		$Product = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id'));
        $helper = Mage::helper('pprwarehouse');
        $storeOpen = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_OPEN_TIME);
        $storeClose = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_CLOSE_TIME);
		$excludedStartHours = array();
		$excludedEndHours = array();

		$start_date = urldecode($this->getRequest()->getParam('start_date'));
		$end_date = urldecode($this->getRequest()->getParam('end_date'));
		$qty = urldecode($this->getRequest()->getParam('qty'));
        $time_increment = intval(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Timebox::XML_PATH_APPEARANCE_TIMEINCREMENTS));
		if($Product->isConfigurable()){
			$Product = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($this->getRequest()->getParam('super_attribute'), $Product);
		}
        if (is_object($Product)) {
		if($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE){
			if(is_object($Product)){
				$Product = Mage::getModel('catalog/product')->load($Product->getId());

				/*this part disables the button and shows a message because there are booked dates in between the selected dates*/
				$isDisabled = false;
				$disableType = '';
				$startTimePadding = strtotime(date('Y-m-d', strtotime($start_date)));
				$endTimePadding = strtotime(date('Y-m-d', strtotime($end_date)));

				if($Product->getGlobalMinPeriod() == 0){
					$minRentalNumber = $Product->getPayperrentalsMinNumber();
					$minRentalType = $Product->getPayperrentalsMinType();
				}else{
					$minRentalNumber = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_MIN_NUMBER);
					$minRentalType = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_MIN_TYPE);
				}
				$minRentalPeriod = ITwebexperts_Payperrentals_Helper_Data::getPeriodInSeconds($minRentalNumber, $minRentalType);
				if($Product->getGlobalMaxPeriod() == 0){
					$maxRentalNumber = $Product->getPayperrentalsMaxNumber();
					$maxRentalType = $Product->getPayperrentalsMaxType();
				}else{
					$maxRentalNumber = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_MAX_NUMBER);
					$maxRentalType = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_MAX_TYPE);
				}
				$maxRentalPeriod = ITwebexperts_Payperrentals_Helper_Data::getPeriodInSeconds($maxRentalNumber, $maxRentalType);

				if($minRentalPeriod != 0 && $minRentalPeriod > abs(strtotime($end_date) - strtotime($start_date))){
					$isDisabled = true;
					$disableType = 'min';
				}

				if($maxRentalPeriod != 0 && $maxRentalPeriod < abs(strtotime($end_date) - strtotime($start_date))){
					$isDisabled = true;
					$disableType = 'max';
				}


				if( ! $isDisabled)
				{
					foreach($helper->getValidStockIds() as $stockId)
					{
						$isDisabled = false;
						$maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
						if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
							$maxQty = 100000;
						}
						$bookedStartTimesArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForTimes($Product->getId(), $start_date, 0, true, $stockId);

						if($maxQty < $qty){
							$isDisabled = true;
						}
						//echo $start_date.'---'.$end_date.'---'.$qty;
						//print_r($bookedStartTimesArray);
						if(!$isDisabled && $startTimePadding == $endTimePadding){
							$timeStartPadding = strtotime($start_date);
							$timeEndPadding = strtotime($end_date);
                            $p = 0;
                            $k = 0;
							while ($timeStartPadding <= $timeEndPadding) {
								foreach($bookedStartTimesArray as $dateFormatted => $_paramAr){
									//echo date('H:i:s', $timeStartPadding).'---';
                                if ($dateFormatted == date('H:i:s', $timeStartPadding) && $maxQty < $_paramAr['qty'] + $qty) {
                                    if (strtotime($start_date) + 60 * $time_increment == strtotime($end_date) && (strtotime($start_date) == $timeStartPadding && $p == 0 || strtotime($end_date) == $timeStartPadding && $p == 1)) {
                                        $k++;
                                    } else {
                                        if (strtotime($start_date) != $timeStartPadding && strtotime($end_date) != $timeStartPadding) {
                                            $isDisabled = true;
                                            $disableType = 'between';
                                            break;
                                        }
                                    }
                                }
                            }
								if($isDisabled){
									break;
								}

                            $timeStartPadding += 60 * $time_increment;
                            $p++;
							}
                        if ($k == 2) {
                            $isDisabled = true;
                            $disableType = 'between';
						}
                    }

						// if we found a vdalid stock with available items, stop checking
						if( ! $isDisabled){
							break;
						}

					}
				}
            }

		}elseif($this->getRequest()->getParam('bundle_option')){
			$selectionIds = $this->getRequest()->getParam('bundle_option');
			$selectedQtys1 = $this->getRequest()->getParam('bundle_option_qty1');
			$selectedQtys2 = $this->getRequest()->getParam('bundle_option_qty');
			if($selectedQtys1){
				foreach($selectedQtys1 as $i1 => $j1){
					if(is_array($j1)){
						foreach($j1 as $k1 => $p1){
							$selectedQtys[$i1][$k1] = $qty * $p1;
						}
					}else{
						$selectedQtys[$i1] = $qty * $j1;
					}
				}
			}
			if($selectedQtys2){
				foreach($selectedQtys2 as $i1 => $j1){
					if(is_array($j1)){
						foreach($j1 as $k1 => $p1){
							$selectedQtys[$i1][$k1] = $qty * $p1;
						}
					}else{
						$selectedQtys[$i1] = $qty * $j1;
					}
				}
			}
			$selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);
			/*this part disables the button and shows a message because there are booked dates in between the selected dates*/
			$isDisabled = false;
			$disableType = '';
			$qty1 = $qty;
			$startTimePadding = strtotime(date('Y-m-d', strtotime($start_date)));
			$endTimePadding = strtotime(date('Y-m-d', strtotime($end_date)));

			foreach ($selections->getItems() as $selection)
			{

				$Product = Mage::getModel('catalog/product')->load($selection->getProductId());
                    if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {
                        if (isset($selectedQtys[$selection->getOptionId()])) {
                            $qty = $selectedQtys[$selection->getOptionId()];
                        } else {
                            $qty = $qty1;
                        }


				if($Product->getGlobalMinPeriod() == 0){
					$minRentalNumber = $Product->getPayperrentalsMinNumber();
					$minRentalType = $Product->getPayperrentalsMinType();
				}else{
					$minRentalNumber = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_MIN_NUMBER);
					$minRentalType = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_MIN_TYPE);
				}
				$minRentalPeriod = ITwebexperts_Payperrentals_Helper_Data::getPeriodInSeconds($minRentalNumber, $minRentalType);
				if($Product->getGlobalMaxPeriod() == 0){
					$maxRentalNumber = $Product->getPayperrentalsMaxNumber();
					$maxRentalType = $Product->getPayperrentalsMaxType();
				}else{
					$maxRentalNumber = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_MAX_NUMBER);
					$maxRentalType = Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_MAX_TYPE);
				}
				$maxRentalPeriod = ITwebexperts_Payperrentals_Helper_Data::getPeriodInSeconds($maxRentalNumber, $maxRentalType);

				if($minRentalPeriod != 0 && $minRentalPeriod > abs(strtotime($end_date) - strtotime($start_date))){
					$isDisabled = true;
					$disableType = 'min';
				}

				if($maxRentalPeriod != 0 && $maxRentalPeriod < abs(strtotime($end_date) - strtotime($start_date))){
					$isDisabled = true;
					$disableType = 'max';
				}

				if( ! $isDisabled && $Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE)
				{
					if(isset($selectedQtys[$selection->getOptionId()])){
						$qty = $selectedQtys[$selection->getOptionId()];
					}else{
						$qty = $qty1;
					}
					foreach($helper->getValidStockIds() as $stockId)
					{
						$isDisabled = false;
						$maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
						if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
							$maxQty = 100000;
						}
						$bookedStartTimesArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForTimes($Product->getId(), $start_date, 0, true, $stockId);


						if($maxQty < $qty){
							$isDisabled = true;
						}

						if(!$isDisabled && $startTimePadding == $endTimePadding){
							$timeStartPadding = strtotime($start_date);
							$timeEndPadding = strtotime($end_date);
							while ($timeStartPadding <= $timeEndPadding) {
								foreach($bookedStartTimesArray as $dateFormatted => $_paramAr){
									if($dateFormatted == date('H:i:s', $timeStartPadding) && $maxQty < $_paramAr['qty'] + $qty){
										$isDisabled = true;
										$disableType = 'between';
										break;
									}
								}
								if($isDisabled){
									break;
								}

                                $timeStartPadding += 60 * $time_increment;
							}
						}

						// stop checking stocks, we found a valid one
						if( ! $isDisabled){
							break;
						}
					}
					if($isDisabled){
						break;
					}
				}
			}
		}
        }

		$timesHtml = array(
                'startTime' => Mage::helper('payperrentals/timebox')->getTimeInput('start_time', $storeOpen, $storeClose, $excludedStartHours),
                'endTime' => Mage::helper('payperrentals/timebox')->getTimeInput('end_time', $storeOpen, $storeClose, $excludedEndHours),
			'isDisabled' => isset($isDisabled)?$isDisabled:false,
			'disableType' => $disableType
		);

        } else {

            $timesHtml = array(
                'startTime' => Mage::helper('payperrentals/timebox')->getTimeInput('start_time', $storeOpen, $storeClose, $excludedStartHours),
                'endTime' => Mage::helper('payperrentals/timebox')->getTimeInput('end_time', $storeOpen, $storeClose, $excludedEndHours),
                'isDisabled' => true,
                'disableType' => ''
            );
        }


		$this
			->getResponse()
			->setBody(Zend_Json::encode($timesHtml));
	}


	public function updateBookedForProductAction()
	{
		/* @var $helper ITwebexperts_PPRWarehouse_Helper_Data */
		$helper = Mage::helper('pprwarehouse');

		/* @var $Product Mage_Catalog_Model_Product */
		$Product = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id'));
		$booked = array();
		$isDisabled = false;

		$qty = urldecode($this->getRequest()->getParam('qty'));

		if($Product->isConfigurable()){
			$Product = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($this->getRequest()->getParam('super_attribute'), $Product);
			$Product = Mage::getModel('catalog/product')->load($Product->getId());
		}
        if(is_object($Product)){
        if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_GROUPED) {
            if (is_object($Product)) {
                $associatedProducts = $Product->getTypeInstance(true)
                    ->getAssociatedProducts($Product);

                foreach ($associatedProducts as $Product) {
                    //Zend_Debug::dump($selection->getData());
                    if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {
                        foreach($helper->getValidStockIds() as $stockId)
                        {
                            $isDisabled = false;
                            $maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
                            if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
                                $maxQty = 100000;
                            }
                            if($maxQty >= $qty){
                                $bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForProducts($Product->getId(), null, null, 0, false, $stockId);
                                /*foreach($bookedArray as $dateFormatted => $qtyPerDay){
                                    if($maxQty < $qtyPerDay + $qty){
                                        $booked[] = $dateFormatted;
                                    }
                                }*/
                                foreach ($bookedArray['booked'] as $dateFormatted => $_paramAr) {
                                    if ($maxQty < $_paramAr[$Product->getId()]['qty'] + $qty) {
                                        $booked[] = $dateFormatted;
                                    }
                                }
                            }
                            else{
                                $isDisabled = true;
                            }

                            // valid stock found, stop checking (or we should continue for getting all the booked possibilities?)
                            if( ! $isDisabled){
                                break;
                            }
                        }
                    }
                }
            }
        }
		elseif($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE){
			if(is_object($Product))
			{
				foreach($helper->getValidStockIds() as $stockId)
				{
					$isDisabled = false;
					$maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
					if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
						$maxQty = 100000;
					}
					if($maxQty >= $qty){
						$bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForProducts($Product->getId(), null, null, 0, false, $stockId);
						/*foreach($bookedArray as $dateFormatted => $qtyPerDay){
							if($maxQty < $qtyPerDay + $qty){
								$booked[] = $dateFormatted;
							}
						}*/
                        foreach ($bookedArray['booked'] as $dateFormatted => $_paramAr) {
                            if ($maxQty < $_paramAr[$Product->getId()]['qty'] + $qty) {
                                $booked[] = $dateFormatted;
                            }
                        }
					}
					else{
						$isDisabled = true;
					}

					// valid stock found, stop checking (or we should continue for getting all the booked possibilities?)
					if( ! $isDisabled){
						break;
					}
				}

			}
		}
		elseif($this->getRequest()->getParam('bundle_option')){
			//get selected bundle id
			$selectionIds = $this->getRequest()->getParam('bundle_option');
			$selectedQtys1 = $this->getRequest()->getParam('bundle_option_qty1');
			$selectedQtys2 = $this->getRequest()->getParam('bundle_option_qty');
			if($selectedQtys1)
				foreach($selectedQtys1 as $i1 => $j1){
					if(is_array($j1)){
						foreach($j1 as $k1 => $p1){
							$selectedQtys[$i1][$k1] = $qty * $p1;
						}
					}else{
						$selectedQtys[$i1] = $qty * $j1;
					}
				}
			if($selectedQtys2)
				foreach($selectedQtys2 as $i1 => $j1){
					if(is_array($j1)){
						foreach($j1 as $k1 => $p1){
							$selectedQtys[$i1][$k1] = $qty * $p1;
						}
					}else{
						$selectedQtys[$i1] = $qty * $j1;
					}
				}
			//print_r($selectedQtys);
			$selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);
			$qty1 = $qty;
			foreach ($selections->getItems() as $selection) {
				//print_r($selection->debug());
				//echo '-------------';
				$Product = Mage::getModel('catalog/product')->load($selection->getProductId());
				//Zend_Debug::dump($selection->getData());
				if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){

					if(isset($selectedQtys[$selection->getOptionId()][$selection->getSelectionId()])){
						$qty = $selectedQtys[$selection->getOptionId()][$selection->getSelectionId()];
					}elseif(isset($selectedQtys[$selection->getOptionId()])){
						$qty = $selectedQtys[$selection->getOptionId()];
					}else{
						$qty = $qty1;
					}
					//echo $qty.'-';
					foreach($helper->getValidStockIds() as $stockId)
					{
						$isDisabled = false;
						$maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
						if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
							$maxQty = 100000;
						}
						if($maxQty >= $qty){
							$bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForProducts($Product->getId(), null, null, 0, true, $stockId);

                            foreach ($bookedArray['booked'] as $dateFormatted => $_paramAr) {
                                if ($maxQty < $_paramAr[$Product->getId()]['qty'] + $qty) {
                                    $booked[] = $dateFormatted;
                                }
                            }
						}else{
							$isDisabled = true;
							//break;
						}

						// valid stock found, stop checking (or we should continue for getting all the booked possibilities?)
						if( ! $isDisabled){
							break;
						}

					}
				}
			}
		}

		$bookedHtml = array(
			'bookedDates' =>  implode(',', $booked),
			'isDisabled'  =>  $isDisabled
		);
        }else{

                $bookedHtml = array(
                    'bookedDates' => '',
                    'isDisabled' => true
                );

        }

		$this
			->getResponse()
			->setBody(Zend_Json::encode($bookedHtml));
	}


	public function getPriceAction()
	{
		if(!$this->getRequest()->getParam('product_id')) {
			return;
		}
		$Product = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id'));
		if($Product->isConfigurable()){
			$Product = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($this->getRequest()->getParam('super_attribute'), $Product);
		}
        if (is_object($Product) && $this->getRequest()->getParam('start_date')) {
		$qty = urldecode($this->getRequest()->getParam('qty'));
		$customerGroup = ITwebexperts_Payperrentals_Helper_Data::getCustomerGroup();

		$startingDate = urldecode($this->getRequest()->getParam('start_date'));
		$endingDate = urldecode($this->getRequest()->getParam('end_date'));
		$selDays = false;
		$availDate = $startingDate;
		if($this->getRequest()->getParam('selDays')){
			$selDays = (int)$this->getRequest()->getParam('selDays') + 1;
			$availDate = false;
		}
            $onclick = '';
            if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_GROUPED) {
                if (is_object($Product) && urldecode($this->getRequest()->getParam('read_start_date')) != '' && urldecode($this->getRequest()->getParam('read_end_date'))) {
                    $associatedProducts = $Product->getTypeInstance(true)
                        ->getAssociatedProducts($Product);
                    //$priceVal = 0;
                    foreach ($associatedProducts as $Product) {
                        //Zend_Debug::dump($selection->getData());
                        if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {

                            $Product = Mage::getModel('catalog/product')->load($Product->getId());
                            $_productAssoc = $Product;
                            $priceAmount = ITwebexperts_Payperrentals_Helper_Price::calculatePrice($Product, $startingDate, $endingDate, $qty, $customerGroup);
                            //if($priceAmount == -1){

                            //}
                            $availDate = false;
                            if ($selDays !== false) {
                                while (true) {
                                    $isAvailableArr = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate);
                                    $isAvailable = $isAvailableArr['avail'];
                                    //print_r($isAvailableArr);
                                    //echo $startingDate.'-'.$endingDate;
                                    if ($isAvailable >= 1) break;
                                    $startingDate = date('Y-m-d', strtotime('+' . $selDays . ' days', strtotime($startingDate)));
                                    $endingDate = date('Y-m-d', strtotime('+' . $selDays . ' days', strtotime($endingDate)));
                                }
                                $availDate = $startingDate;
                            }
                        }
                    }
                    $onclick = "setLocation('" . Mage::helper('checkout/cart')->getAddUrl($_productAssoc, array('_query' => array('options' => array('start_date' => date('Y-m-d H:i:s', strtotime($startingDate)), 'end_date' => date('Y-m-d H:i:s', strtotime($endingDate))), 'start_date' => date('Y-m-d H:i:s', strtotime($startingDate)), 'end_date' => date('Y-m-d H:i:s', strtotime($endingDate))))) . "');";

                } else {
                    $priceAmount = -1;
                }
            }elseif($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE || $Product->getBundlePricingtype() == ITwebexperts_Payperrentals_Model_Product_Bundlepricingtype::PRICING_BUNDLE_FORALL){
                if (is_object($Product) && urldecode($this->getRequest()->getParam('read_start_date')) != '' && urldecode($this->getRequest()->getParam('read_end_date'))) {
				$Product = Mage::getModel('catalog/product')->load($Product->getId());
                    $priceAmount = ITwebexperts_Payperrentals_Helper_Price::calculatePrice($Product, $startingDate, $endingDate, $qty, $customerGroup);

				$availDate = false;
				if($selDays !== false){
					$helper = Mage::helper('pprwarehouse');
					$stockIds = $helper->getValidStockIds();
					$isAvailable = false;
					while( ! $isAvailable){
						foreach($stockIds as $stockId)
						{
							$isAvailableArr = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailableWithQty($Product->getId(), $startingDate, $endingDate, $stockId);
							$isAvailable = $isAvailableArr['avail'];
							if($isAvailable){
								break;
							}
						}
						$startingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($startingDate)));
						$endingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($endingDate)));
					}
					$availDate = $startingDate;
				}
			}else{
				$priceAmount = -1;
			}
		}elseif($this->getRequest()->getParam('bundle_option')){
                if (urldecode($this->getRequest()->getParam('read_start_date')) != '' && urldecode($this->getRequest()->getParam('read_end_date'))) {
			$selectionIds = $this->getRequest()->getParam('bundle_option');
			$selectedQtys1 = $this->getRequest()->getParam('bundle_option_qty1');
			$selectedQtys2 = $this->getRequest()->getParam('bundle_option_qty');
			if($selectedQtys1)
				foreach($selectedQtys1 as $i1 => $j1){
					if(is_array($j1)){
						foreach($j1 as $k1 => $p1){
							$selectedQtys[$i1][$k1] = $p1;
						}
					}else{
						$selectedQtys[$i1] = /*$qty **/ $j1;
					}
				}
			if($selectedQtys2)
				foreach($selectedQtys2 as $i1 => $j1){
					if(is_array($j1)){
						foreach($j1 as $k1 => $p1){
							$selectedQtys[$i1][$k1] = $p1;
						}
					}else{
						$selectedQtys[$i1] = /*$qty **/ $j1;
					}
				}

			$selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);
			$priceVal = 0;
			$availDate = false;
			$qty1 = $qty;
			//echo 'qty:'.$qty1;
			//print_r($selectedQtys);
			foreach ($selections->getItems() as $selection) {
				$Product = Mage::getModel('catalog/product')->load($selection->getProductId());
				//echo $Product->getName().'---'.$selection->getOptionId().'iii';
				/*if(isset($selectedQtys[$selection->getOptionId()])){
					$qty = $selectedQtys[$selection->getOptionId()];
				}else{
					$qty = $qty1;
				} */
				if(isset($selectedQtys[$selection->getOptionId()][$selection->getSelectionId()])){
					$qty = $selectedQtys[$selection->getOptionId()][$selection->getSelectionId()];
				}elseif(isset($selectedQtys[$selection->getOptionId()])){
					$qty = $selectedQtys[$selection->getOptionId()];
				}else{
					$qty = $qty1;
				}

				if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){
                            $priceAmount = $qty * ITwebexperts_Payperrentals_Helper_Price::calculatePrice($Product, $startingDate, $endingDate, $qty, $customerGroup);
					//echo $qty.'-'.$priceAmount;
					if($priceAmount == -1){
						$priceVal = -1;
						break;
					}

					$availDateMax = false;
					if($selDays !== false){
						$helper = Mage::helper('pprwarehouse');
						$stockIds = $helper->getValidStockIds();
						$isAvailable = false;
						while( ! $isAvailable){
							foreach($stockIds as $stockId)
							{
								$isAvailableArr = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate, $stockId);
								$isAvailable = $isAvailableArr['avail'];
								if($isAvailable >= 1){
									break;
								}
							}
							$startingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($startingDate)));
							$endingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($endingDate)));
						}
						$availDateMax = $startingDate;
					}
					if($availDate === false || ($availDateMax !== false && strtotime($availDate) > strtotime($availDateMax))){
						$availDate = $availDateMax;
					}

					$priceVal = $priceVal + /*$qty **/ $priceAmount;
				}
			}
			$priceAmount = $priceVal;
                } else {
                    $priceAmount = -1;
                }

		}

            if (ITwebexperts_Payperrentals_Helper_Data::useCalendarForFixedSelection()) {
                $startingDateNow = $startingDate;
            } else {
                $startingDateNow = date('Y-m-d');
            }
            $nextDay = date('Y-m-d', strtotime($startingDateNow));
            if (ITwebexperts_Payperrentals_Helper_Data::isNextHourSelection() && !ITwebexperts_Payperrentals_Helper_Data::useCalendarForFixedSelection()) {
                $nextDay = date('Y-m-d', strtotime('+1 day', strtotime($startingDateNow)));
            }
            if (ITwebexperts_Payperrentals_Helper_Data::useListButtons()) {
                Mage::getSingleton('core/session')->setData('startDateInitial', date('Y-m-d H:i:s', strtotime($startingDate)));
                Mage::getSingleton('core/session')->setData('endDateInitial', date('Y-m-d H:i:s', strtotime($endingDate)));
            }
            $price = array(
                'amount' => isset($priceAmount) ? $priceAmount : -1,
                'onclick' => $onclick,
                'needsConfigure' => false,
                'availdate' => $availDate,
                'btnList' => (ITwebexperts_Payperrentals_Helper_Data::useListButtons() ? ITwebexperts_Payperrentals_Helper_Price::getPriceListHtml(Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id')), -1, false, true) : ''),
                'isavail' => ((date('Y-m-d', strtotime($availDate)) != $nextDay && $selDays !== false) ? false : true),
                'formatAmount' => isset($priceAmount) ? Mage::helper('core')->currency($priceAmount) : -1
            );
        } else {
            $price = array(
                'amount' => -1,
                'onclick' => '',
                'needsConfigure' => true,
                'availdate' => '',
                'isavail' => false,
                'formatAmount' => -1
            );
        }
        $this->getResponse()->setBody(Zend_Json::encode($price));
    }
}
