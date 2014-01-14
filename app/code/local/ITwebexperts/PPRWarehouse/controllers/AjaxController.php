<?php
/**
 *
 * @author Enrique Piatti
 */
require_once 'ITwebexperts/Payperrentals/controllers/AjaxController.php';


class ITwebexperts_PPRWarehouse_AjaxController extends ITwebexperts_Payperrentals_AjaxController
{

    /*todo need update rental 1.1.4*/
	public function updateBookedForProductActionBK()
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

    public function updateBookedForProductAction()
    {
        $helper = Mage::helper('pprwarehouse');
        $_product = Mage::getSingleton('catalog/product')->load($this->getRequest()->getParam('product_id'));
        $_booked = array();
        $_partiallyBooked = array();
        $_isDisabled = false;

        $_qty = urldecode($this->getRequest()->getParam('qty'));
        $_storeTimeAr = ITwebexperts_Payperrentals_Helper_Timebox::getTimeOptionsArray(Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_OPEN_TIME), Mage::getStoreConfig(ITwebexperts_Payperrentals_Helper_Data::XML_PATH_STORE_CLOSE_TIME), array(), 'value');

        /*Allow rent configurable with associated simply products*/
        if ($_product->isConfigurable()) {
            $_childProduct = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($this->getRequest()->getParam('super_attribute'), $_product);
            if(is_object($_childProduct) && $_childProduct->getTypeId() != 'simple') {
                $_product = $_childProduct;
            }
        }
        if (is_object($_product)) {
            if ($_product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_GROUPED) {
                $_associatedProducts = $_product->getTypeInstance(true)->getAssociatedProducts($_product);
                foreach ($_associatedProducts as $_asProduct) {
                    //Zend_Debug::dump($selection->getData());
                    if ($_product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {
                        $_asProduct->load();
                        $_isDisabled = 0;
                        //todo here should check only for a specific warehouse. If is split qty or not sure how should be handled. Needs client help

                        foreach($helper->getValidStockIds() as $stockId)
                        {
                            $_maxQty = $helper->getQtyForProductAndStock($_asProduct, $stockId);

                            if (ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($_asProduct)) {
                                $_maxQty = 100000;
                            }
                            if ($_maxQty >= $_qty) {
                                $_key = 0;
                                $_bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForProducts($_asProduct->getId(), null, null, 0, false, $stockId);
                                if (count($_bookedArray)) {
                                    foreach ($_bookedArray['booked'] as $_dateFormatted => $_paramAr) {
                                        foreach ($_paramAr[$_asProduct->getId()]['period_start'] as $_periodKey => $_periodStartTime) {
                                            if (preg_match_all('/^[0-9]{4}-[01][0-9]-[0-3][0-9] [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $_paramAr[$_asProduct->getId()]['period_start'][$_periodKey], $arr, PREG_PATTERN_ORDER)) {
                                                $_startPeriodAr = explode(' ', $_paramAr[$_asProduct->getId()]['period_start'][$_periodKey]);
                                                $_startPeriod = ITwebexperts_Payperrentals_Helper_Timebox::convertTimeToSecond($_startPeriodAr[1]);
                                            } else {
                                                $_startPeriod = null;
                                            }
                                            if (preg_match_all('/^[0-9]{4}-[01][0-9]-[0-3][0-9] [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $_paramAr[$_product->getId()]['period_end'][$_periodKey], $arr, PREG_PATTERN_ORDER)) {
                                                $_endPeriodAr = explode(' ', $_paramAr[$_asProduct->getId()]['period_end'][$_periodKey]);
                                                $_endPeriod = ITwebexperts_Payperrentals_Helper_Timebox::convertTimeToSecond($_endPeriodAr[1]);
                                            } else {
                                                $_endPeriod = null;
                                            }
                                            if ($_maxQty < $_paramAr[$_asProduct->getId()]['qty'] + $_qty) {
                                                if (!(is_null($_startPeriod)) && !(is_null($_endPeriod))) {
                                                    $_periodTimeAr = ITwebexperts_Payperrentals_Helper_Timebox::getTimeOptionsArray($_startPeriodAr[1], $_endPeriodAr[1], array(), 'value');
                                                    $_intersectAr = array_intersect($_periodTimeAr, $_storeTimeAr);
                                                    if (count($_intersectAr) == count($_storeTimeAr)) {
                                                        $_booked[] = $_dateFormatted;
                                                    } else {
                                                        $_partiallyBooked[$_key][] = $_dateFormatted;
                                                        $_partiallyBooked[$_key] = array_merge_recursive($_partiallyBooked[$_key], $_intersectAr);
                                                        $_key++;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                }

                            } else {
                                $_isDisabled++;
                            }
                        }
                        if($_isDisabled == count($helper->getValidStockIds())){
                            $_isDisabled = true;
                        }else{
                            $_isDisabled = false;
                        }
                    }
                }
            } elseif ($_product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE) {
                $_product->load($_product->getId());
                $_productId = $_product->getId();
                $_isDisabled = 0;
                foreach($helper->getValidStockIds() as $stockId)
                {
                    $_maxQty = $helper->getQtyForProductAndStock($_product, $stockId);
                    if (ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($_product)) {
                        $_maxQty = 100000;
                    }

                    if ($_maxQty >= $_qty) {
                        $_key = 0;
                        $_bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForProducts($_productId, null, null, 0, false, $stockId);
                        if (count($_bookedArray)) {
                            foreach ($_bookedArray['booked'] as $_dateFormatted => $_paramAr) {
                                foreach ($_paramAr[$_productId]['period_start'] as $_periodKey => $_periodStartTime) {
                                    if (preg_match_all('/^[0-9]{4}-[01][0-9]-[0-3][0-9] [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $_paramAr[$_productId]['period_start'][$_periodKey], $arr, PREG_PATTERN_ORDER)) {
                                        $_startPeriodAr = explode(' ', $_paramAr[$_productId]['period_start'][$_periodKey]);
                                        $_startPeriod = ITwebexperts_Payperrentals_Helper_Timebox::convertTimeToSecond($_startPeriodAr[1]);
                                    } else {
                                        $_startPeriod = null;
                                    }
                                    if (preg_match_all('/^[0-9]{4}-[01][0-9]-[0-3][0-9] [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $_paramAr[$_productId]['period_end'][$_periodKey], $arr, PREG_PATTERN_ORDER)) {
                                        $_endPeriodAr = explode(' ', $_paramAr[$_productId]['period_end'][$_periodKey]);
                                        $_endPeriod = ITwebexperts_Payperrentals_Helper_Timebox::convertTimeToSecond($_endPeriodAr[1]);
                                    } else {
                                        $_endPeriod = null;
                                    }
                                    if ($_maxQty < $_paramAr[$_productId]['qty'] + $_qty) {
                                        if (!(is_null($_startPeriod)) && !(is_null($_endPeriod))) {
                                            $_periodTimeAr = ITwebexperts_Payperrentals_Helper_Timebox::getTimeOptionsArray($_startPeriodAr[1], $_endPeriodAr[1], array(), 'value');
                                            $_intersectAr = array_intersect($_periodTimeAr, $_storeTimeAr);
                                            if (count($_intersectAr) == count($_storeTimeAr)) {
                                                $_booked[] = $_dateFormatted;
                                            } else {
                                                $_partiallyBooked[$_key][] = $_dateFormatted;
                                                $_partiallyBooked[$_key] = array_merge_recursive($_partiallyBooked[$_key], $_intersectAr);
                                                $_key++;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $_isDisabled ++;
                    }
                }
                if($_isDisabled == count($helper->getValidStockIds())){
                    $_isDisabled = true;
                }else{
                    $_isDisabled = false;
                }
            } elseif ($this->getRequest()->getParam('bundle_option')) {
                //get selected bundle id
                $_selectionIds = $this->getRequest()->getParam('bundle_option');
                $_selectedQtys1 = $this->getRequest()->getParam('bundle_option_qty1');
                $_selectedQtys2 = $this->getRequest()->getParam('bundle_option_qty');
                if ($_selectedQtys1)
                    foreach ($_selectedQtys1 as $_i1 => $_j1) {
                        if (is_array($_j1)) {
                            foreach ($_j1 as $_k1 => $_p1) {
                                $_selectedQtys[$_i1][$_k1] = $_qty * $_p1;
                            }
                        } else {
                            $_selectedQtys[$_i1] = $_qty * $_j1;
                        }
                    }
                if ($_selectedQtys2)
                    foreach ($_selectedQtys2 as $_i1 => $_j1) {
                        if (is_array($_j1)) {
                            foreach ($_j1 as $_k1 => $_p1) {
                                $_selectedQtys[$_i1][$_k1] = $_qty * $_p1;
                            }
                        } else {
                            $_selectedQtys[$_i1] = $_qty * $_j1;
                        }
                    }
                $_selections = $_product->getTypeInstance(true)->getSelectionsByIds($_selectionIds, $_product);
                $_qty1 = $_qty;
                foreach ($_selections->getItems() as $_selection) {
                    $_product = Mage::getModel('catalog/product')->load($_selection->getProductId());
                    //Zend_Debug::dump($selection->getData());
                    if ($_product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {

                        if (isset($_selectedQtys[$_selection->getOptionId()][$_selection->getSelectionId()])) {
                            $_qty = $_selectedQtys[$_selection->getOptionId()][$_selection->getSelectionId()];
                        } elseif (isset($_selectedQtys[$_selection->getOptionId()])) {
                            $_qty = $_selectedQtys[$_selection->getOptionId()];
                        } else {
                            $_qty = $_qty1;
                        }
                        $_isDisabled = 0;
                        foreach($helper->getValidStockIds() as $stockId)
                        {
                        //$_maxQty = ITwebexperts_Payperrentals_Helper_Data::getQuantity($_product);
                            $_maxQty = $helper->getQtyForProductAndStock($_product, $stockId);
                            if (ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($_product)) {
                                $_maxQty = 100000;
                            }
                            if ($_maxQty >= $_qty) {
                                $_key = 0;
                                $_bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForProducts($_product->getId(), null, null, 0, false, $stockId);
                                if (count($_bookedArray)) {
                                    foreach ($_bookedArray['booked'] as $_dateFormatted => $_paramAr) {
                                        foreach ($_paramAr[$_product->getId()]['period_start'] as $_periodKey => $_periodStartTime) {
                                            if (preg_match_all('/^[0-9]{4}-[01][0-9]-[0-3][0-9] [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $_paramAr[$_product->getId()]['period_start'][$_periodKey], $arr, PREG_PATTERN_ORDER)) {
                                                $_startPeriodAr = explode(' ', $_paramAr[$_product->getId()]['period_start'][$_periodKey]);
                                                $_startPeriod = ITwebexperts_Payperrentals_Helper_Timebox::convertTimeToSecond($_startPeriodAr[1]);
                                            } else {
                                                $_startPeriod = null;
                                            }
                                            if (preg_match_all('/^[0-9]{4}-[01][0-9]-[0-3][0-9] [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $_paramAr[$_product->getId()]['period_end'][$_periodKey], $arr, PREG_PATTERN_ORDER)) {
                                                $_endPeriodAr = explode(' ', $_paramAr[$_product->getId()]['period_end'][$_periodKey]);
                                                $_endPeriod = ITwebexperts_Payperrentals_Helper_Timebox::convertTimeToSecond($_endPeriodAr[1]);
                                            } else {
                                                $_endPeriod = null;
                                            }
                                            if ($_maxQty < $_paramAr[$_product->getId()]['qty'] + $_qty) {
                                                if (!(is_null($_startPeriod)) && !(is_null($_endPeriod))) {
                                                    $_periodTimeAr = ITwebexperts_Payperrentals_Helper_Timebox::getTimeOptionsArray($_startPeriodAr[1], $_endPeriodAr[1], array(), 'value');
                                                    $_intersectAr = array_intersect($_periodTimeAr, $_storeTimeAr);
                                                    if (count($_intersectAr) == count($_storeTimeAr)) {
                                                        $_booked[] = $_dateFormatted;
                                                    } else {
                                                        $_partiallyBooked[$_key][] = $_dateFormatted;
                                                        $_partiallyBooked[$_key] = array_merge_recursive($_partiallyBooked[$_key], $_intersectAr);
                                                        $_key++;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                $_isDisabled++;
                            }
                        }
                        if($_isDisabled == count($helper->getValidStockIds())){
                            $_isDisabled = true;
                        }else{
                            $_isDisabled = false;
                        }
                    }
                }
            }

            $_bookedHtml = array(
                'bookedDates' => implode(',', $_booked),
                'isDisabled' => $_isDisabled,
                'partiallyBooked' => $_partiallyBooked
            );
        } else {
            $_bookedHtml = array(
                'bookedDates' => '',
                'isDisabled' => true,
                'partiallyBooked' => ''
            );
        }

        $this->getResponse()->setBody(Zend_Json::encode($_bookedHtml));
    }

	public function getPriceAction()
	{
		if(!$this->getRequest()->getParam('product_id')) {
			return;
		}
		 /*Allow rent configurable with associated simply products*/
        $Product = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id'));
        /*Allow rent configurable with associated simply products*/
        $normalPrice = '';
        if ($Product->isConfigurable()) {
            $_childProduct = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($this->getRequest()->getParam('super_attribute'), $Product);
            if(is_object($_childProduct) && $_childProduct->getTypeId() != 'simple') {
                $Product = $_childProduct;
            }
            $normalPrice = ITwebexperts_Payperrentals_Helper_Price::getPriceListHtml($Product);
        }
        if (is_object($Product) && $this->getRequest()->getParam('start_date')) {
            $qty = urldecode($this->getRequest()->getParam('qty'));
            $customerGroup = ITwebexperts_Payperrentals_Helper_Data::getCustomerGroup();

            $params = $this->getRequest()->getPost();
            if(!ITwebexperts_Payperrentals_Helper_Data::useNonSequential()){
                $newParams['start_date'] = $params['start_date'];
                $newParams['end_date'] = $params['end_date'];
                $newParams = ITwebexperts_Payperrentals_Helper_Data::filterDates($newParams, true);
                $startingDate = $newParams['start_date'];
                $endingDate = $newParams['end_date'];
            }else{
                $startingDate = $params['start_date'];
                $endingDate = $params['end_date'];
            }
            $selDays = false;
            $availDate = $startingDate;
            if ($this->getRequest()->getParam('selDays')) {
                $selDays = (int)$this->getRequest()->getParam('selDays') + 1;
                $availDate = false;
            }
            $onclick = '';
            if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_GROUPED) {
                if (is_object($Product) && urldecode($this->getRequest()->getParam('read_start_date')) != '' && (urldecode($this->getRequest()->getParam('read_end_date')) || ITwebexperts_Payperrentals_Helper_Data::useNonSequential())){
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
                                $helper = Mage::helper('pprwarehouse');
                                $stockIds = $helper->getValidStockIds();
                                $isAvailable = false;
                                while( ! $isAvailable){
                                    foreach($stockIds as $stockId)
                                    {
                                        $_maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
                                        if ($_maxQty < $qty) {
                                            $isAvailable = 0;
                                            break;
                                        }

                                        $isAvailableArr = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate, $stockId);
                                        $isAvailable = $isAvailableArr['avail'];
                                        if($isAvailable){
                                            break;
                                        }
                                    }
                                    if($isAvailable >= 1){
                                        break;
                                    }
                                    $startingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($startingDate)));
                                    $endingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($endingDate)));

                                }
                                if($isAvailable >= 1){
                                    $availDate = $startingDate;
                                }
                            }
                        }
                    }
                    $onclick = "setLocation('" . Mage::helper('checkout/cart')->getAddUrl($_productAssoc, array('_query' => array('options' => array('start_date' => date('Y-m-d H:i:s', strtotime($startingDate)), 'end_date' => date('Y-m-d H:i:s', strtotime($endingDate))), 'start_date' => date('Y-m-d H:i:s', strtotime($startingDate)), 'end_date' => date('Y-m-d H:i:s', strtotime($endingDate))))) . "');";

                } else {
                    $priceAmount = -1;
                }
            } elseif ($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE || $Product->getBundlePricingtype() == ITwebexperts_Payperrentals_Model_Product_Bundlepricingtype::PRICING_BUNDLE_FORALL) {
                if (is_object($Product) && urldecode($this->getRequest()->getParam('read_start_date')) != '' && (urldecode($this->getRequest()->getParam('read_end_date')) || ITwebexperts_Payperrentals_Helper_Data::useNonSequential())){
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
                            $_maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
                            if ($_maxQty < $qty) {
                                $isAvailable = 0;
                                break;
                            }

							$isAvailableArr = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate, $stockId);
							$isAvailable = $isAvailableArr['avail'];
							if($isAvailable){
								break;
							}
						}
                        if($isAvailable >= 1){
                            break;
                        }
						$startingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($startingDate)));
						$endingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($endingDate)));

					}
                    if($isAvailable >= 1){
					    $availDate = $startingDate;
                    }
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
			foreach ($selections->getItems() as $selection) {
				$Product = Mage::getModel('catalog/product')->load($selection->getProductId());
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
                                $_maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
                                if ($_maxQty < $qty) {
                                    $isAvailable = 0;
                                    break;
                                }
								$isAvailableArr = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate, $stockId);
								$isAvailable = $isAvailableArr['avail'];
								if($isAvailable >= 1){
									break;
								}
							}
                            if($isAvailable >= 1){
                                break;
                            }
							$startingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($startingDate)));
							$endingDate = date('Y-m-d', strtotime('+'.$selDays.' days', strtotime($endingDate)));

						}
                        if($isAvailable >= 1){
						    $availDateMax = $startingDate;
                        }
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
                $paramsAll = $this->getRequest()->getPost();
                $newParams['start_date'] = $paramsAll['start_date'];
                $newParams['end_date'] = $paramsAll['end_date'];
                $newParams = ITwebexperts_Payperrentals_Helper_Data::filterDates($newParams, true);
                $startingDateFiltered = $newParams['start_date'];
                $endingDateFiltered = $newParams['end_date'];
                Mage::getSingleton('core/session')->setData('startDateInitial', $startingDateFiltered);
                Mage::getSingleton('core/session')->setData('endDateInitial', $endingDateFiltered);
            }
            $price = array(
                'amount' => isset($priceAmount) ? $priceAmount : -1,
                'onclick' => $onclick,
                'needsConfigure' => false,
                'normalPrice' => $normalPrice,
              	'availdate' => ($availDate != false)?date('m/d/Y', strtotime($availDate)):'',
                'availdatetime'=> ($availDate != false)?strtotime($availDate) * 1000:'',
                'btnList' => (ITwebexperts_Payperrentals_Helper_Data::useListButtons() ? ITwebexperts_Payperrentals_Helper_Price::getPriceListHtml(Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id')), -1, false, true) : ''),
                'isavail' => ((date('Y-m-d', strtotime($availDate)) != $nextDay && $selDays !== false) ? false : true),
                'formatAmount' => isset($priceAmount) ? Mage::helper('core')->currency($priceAmount) : -1
            );
        } else {
            $price = array(
                'amount' => -1,
                'onclick' => '',
                'needsConfigure' => true,
                'normalPrice' => '',
                'availdate' => '',
                'availdatetime' => '',
                'isavail' => false,
                'formatAmount' => -1
            );
        }
        $this->getResponse()->setBody(Zend_Json::encode($price));
    }
    public function getPriceActionNew()
    {
        if (!$this->getRequest()->getParam('product_id')) {
            return;
        }
        $Product = Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id'));
        /*Allow rent configurable with associated simply products*/
        $normalPrice = '';
        if ($Product->isConfigurable()) {
            $_childProduct = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($this->getRequest()->getParam('super_attribute'), $Product);
            if(is_object($_childProduct) && $_childProduct->getTypeId() != 'simple') {
                $Product = $_childProduct;
            }
            $normalPrice = ITwebexperts_Payperrentals_Helper_Price::getPriceListHtml($Product);
        }
        if (is_object($Product) && $this->getRequest()->getParam('start_date')) {
            $qty = urldecode($this->getRequest()->getParam('qty'));
            $customerGroup = ITwebexperts_Payperrentals_Helper_Data::getCustomerGroup();

            $params = $this->getRequest()->getPost();
            if(!ITwebexperts_Payperrentals_Helper_Data::useNonSequential()){
                $params = $this->_filterDates($params, array('start_date', 'end_date'));
                $startingDate = $params['start_date'];
                $endingDate = $params['end_date'];
            }else{
                $startingDate = $params['start_date'];
                $endingDate = $params['end_date'];
            }
            $selDays = false;
            $availDate = $startingDate;
            if ($this->getRequest()->getParam('selDays')) {
                $selDays = (int)$this->getRequest()->getParam('selDays') + 1;
                $availDate = false;
            }
            $onclick = '';
            if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_GROUPED) {
                if (is_object($Product) && urldecode($this->getRequest()->getParam('read_start_date')) != '' && (urldecode($this->getRequest()->getParam('read_end_date')) || ITwebexperts_Payperrentals_Helper_Data::useNonSequential())){
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
                            $_maxQty = ITwebexperts_Payperrentals_Helper_Data::getQuantity($Product);
                            if ($_maxQty >= $qty) {
                                if ($selDays !== false) {
                                    $isAvailable = 0;
                                    while (true) {
                                        $isAvailableArr = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate);
                                        $isAvailable = $isAvailableArr['avail'];

                                        if ($isAvailable >= 1) break;
                                        $startingDate = date('Y-m-d', strtotime('+' . $selDays . ' days', strtotime($startingDate)));
                                        $endingDate = date('Y-m-d', strtotime('+' . $selDays . ' days', strtotime($endingDate)));
                                    }
                                    if ($isAvailable >= 1){
                                        $availDate = $startingDate;
                                    }

                                }
                            }
                        }
                    }
                    $onclick = "setLocation('" . Mage::helper('checkout/cart')->getAddUrl($_productAssoc, array('_query' => array('options' => array('start_date' => date('Y-m-d H:i:s', strtotime($startingDate)), 'end_date' => date('Y-m-d H:i:s', strtotime($endingDate))), 'start_date' => date('Y-m-d H:i:s', strtotime($startingDate)), 'end_date' => date('Y-m-d H:i:s', strtotime($endingDate))))) . "');";

                } else {
                    $priceAmount = -1;
                }
            } elseif ($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE || $Product->getBundlePricingtype() == ITwebexperts_Payperrentals_Model_Product_Bundlepricingtype::PRICING_BUNDLE_FORALL) {
                if (is_object($Product) && urldecode($this->getRequest()->getParam('read_start_date')) != '' && (urldecode($this->getRequest()->getParam('read_end_date')) || ITwebexperts_Payperrentals_Helper_Data::useNonSequential())){
                    $Product = Mage::getModel('catalog/product')->load($Product->getId());
                    $priceAmount = ITwebexperts_Payperrentals_Helper_Price::calculatePrice($Product, $startingDate, $endingDate, $qty, $customerGroup);

                    $availDate = false;
                    $_maxQty = ITwebexperts_Payperrentals_Helper_Data::getQuantity($Product);
                    if ($_maxQty >= $qty) {
                        if ($selDays !== false) {
                            $isAvailable = 0;
                            while (true) {
                                $isAvailableArr = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate);
                                $isAvailable = $isAvailableArr['avail'];
                                if ($isAvailable >= 1) break;
                                $startingDate = date('Y-m-d', strtotime('+' . $selDays . ' days', strtotime($startingDate)));
                                $endingDate = date('Y-m-d', strtotime('+' . $selDays . ' days', strtotime($endingDate)));
                            }
                            if($isAvailable >= 1){
                                $availDate = $startingDate;
                            }
                        }
                    }
                } else {
                    $priceAmount = -1;
                }
            } elseif ($this->getRequest()->getParam('bundle_option')) {
                if (urldecode($this->getRequest()->getParam('read_start_date')) != '' && (urldecode($this->getRequest()->getParam('read_end_date')) || ITwebexperts_Payperrentals_Helper_Data::useNonSequential())){
                    $selectionIds = $this->getRequest()->getParam('bundle_option');
                    $selectedQtys1 = $this->getRequest()->getParam('bundle_option_qty1');
                    $selectedQtys2 = $this->getRequest()->getParam('bundle_option_qty');
                    if ($selectedQtys1)
                        foreach ($selectedQtys1 as $i1 => $j1) {
                            if (is_array($j1)) {
                                foreach ($j1 as $k1 => $p1) {
                                    $selectedQtys[$i1][$k1] = $p1;
                                }
                            } else {
                                $selectedQtys[$i1] = /*$qty **/
                                    $j1;
                            }
                        }
                    if ($selectedQtys2)
                        foreach ($selectedQtys2 as $i1 => $j1) {
                            if (is_array($j1)) {
                                foreach ($j1 as $k1 => $p1) {
                                    $selectedQtys[$i1][$k1] = $p1;
                                }
                            } else {
                                $selectedQtys[$i1] = /*$qty **/
                                    $j1;
                            }
                        }

                    $selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);
                    $priceVal = 0;
                    $availDate = false;
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
                        if ($qty == 0) {
                            $qty = 1;
                        }
                        if ($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {
                            $priceAmount = $qty * ITwebexperts_Payperrentals_Helper_Price::calculatePrice($Product, $startingDate, $endingDate, $qty, $customerGroup);
                            //echo $qty.'-'.$priceAmount;
                            if ($priceAmount == -1) {
                                $priceVal = -1;
                                break;
                            }

                            $availDateMax = false;
                            $_maxQty = ITwebexperts_Payperrentals_Helper_Data::getQuantity($Product);
                            if ($_maxQty >= $qty) {
                                if ($selDays !== false) {
                                    $isAvailable = 0;
                                    while (true) {
                                        $isAvailableArr = ITwebexperts_Payperrentals_Helper_Data::isAvailableWithQty($Product->getId(), $qty, $startingDate, $endingDate);
                                        $isAvailable = $isAvailableArr['avail'];

                                        if ($isAvailable >= 1) break;
                                        $startingDate = date('Y-m-d', strtotime('+' . $selDays . ' days', strtotime($startingDate)));
                                        $endingDate = date('Y-m-d', strtotime('+' . $selDays . ' days', strtotime($endingDate)));
                                    }
                                    if($isAvailable >= 1){
                                        $availDateMax = $startingDate;
                                    }
                                }
                            }
                            if ($availDate === false || ($availDateMax !== false && strtotime($availDate) > strtotime($availDateMax))) {
                                $availDate = $availDateMax;
                            }

                            $priceVal = $priceVal + /*$qty **/
                                $priceAmount;
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
                $paramsAll = $this->getRequest()->getPost();
                $newParams['start_date'] = $paramsAll['start_date'];
                $newParams['end_date'] = $paramsAll['end_date'];
                $newParams = ITwebexperts_Payperrentals_Helper_Data::filterDates($newParams, true);
                $startingDateFiltered = $newParams['start_date'];
                $endingDateFiltered = $newParams['end_date'];
                Mage::getSingleton('core/session')->setData('startDateInitial', $startingDateFiltered);
                Mage::getSingleton('core/session')->setData('endDateInitial', $endingDateFiltered);
            }
            $price = array(
                'amount' => isset($priceAmount) ? $priceAmount : -1,
                'onclick' => $onclick,
                'needsConfigure' => false,
                'normalPrice' => $normalPrice,
                'availdate' => ($availDate != false)?date('m/d/Y', strtotime($availDate)):'',
                'availdatetime'=> ($availDate != false)?strtotime($availDate) * 1000:'',
                'btnList' => (ITwebexperts_Payperrentals_Helper_Data::useListButtons() ? ITwebexperts_Payperrentals_Helper_Price::getPriceListHtml(Mage::getModel('catalog/product')->load($this->getRequest()->getParam('product_id')), -1, false, true) : ''),
                'isavail' => ((date('Y-m-d', strtotime($availDate)) != $nextDay && $selDays !== false) ? false : true),
                'formatAmount' => isset($priceAmount) ? Mage::helper('core')->currency($priceAmount) : -1
            );
        } else {
            $price = array(
                'amount' => -1,
                'onclick' => '',
                'needsConfigure' => true,
                'normalPrice' => '',
                'availdate' => '',
                'availdatetime' => '',
                'isavail' => false,
                'formatAmount' => -1
            );
        }
        $this->getResponse()->setBody(Zend_Json::encode($price));
    }
}
