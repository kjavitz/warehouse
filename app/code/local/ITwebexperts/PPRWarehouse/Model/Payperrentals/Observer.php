<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_Payperrentals_Observer extends ITwebexperts_Payperrentals_Model_Observer
{

	/**
	 * rewrite for adding stock_id to returns
	 * (this won't be needed after the PPR refactoring, because the returns will have a reference to the reservationorder table)
	 * and anyway, this code is repeated also in ITwebexperts_Payperrentals_Adminhtml_AjaxController::sendSelectedAction so we need at leas to follow the DRY principle!
	 * @param $observer
	 */
	public function salesOrderShipmentSaveBefore($observer)
	{
		// $shipmentData = Mage::app()->getRequest()->getParam('shipment');
		/** @var Mage_Sales_Model_Order_Shipment $shipment */
		$shipment = $observer->getEvent()->getShipment();
		$shippedItems = $shipment->getAllItems();
		$order = Mage::getModel('sales/order')->load($shipment->getOrderId());

		$sns = Mage::app()->getRequest()->getParam('sn', array());

		$reservations = Mage::getModel('payperrentals/reservationorders')
			->getCollection()
			->addOtypeFilter(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_ORDER)
			->addSelectFilter("order_id = '".$order->getIncrementId()."'")
			->addSelectFilter("product_type = '".ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE."'");

		// $reservations->addSendReturnFilter();		// TODO: why it was adding this filter originally? we should be able to have more than one sendreturn for the same reservation right?

		// check if serial numbers are valid first (throw Exception if not)
		$dataToSave = array();
		foreach($reservations as $resOrder)
		{
			$orderItemId = $resOrder->getOrderItemId();
			foreach($shippedItems as $shippedItem)
			{
				/** @var $shippedItem Mage_Sales_Model_Order_Shipment_Item */
				if($shippedItem->getOrderItemId() == $orderItemId)
				{
					$dataItemToSave = array(
						'reservation' => $resOrder,
						'shipment_item' => $shippedItem,
						'serial' => array()
					);
					$shippedQty = $shippedItem->getQty();
					/* @var $product Mage_Catalog_Model_Product */
					$product = Mage::getModel('catalog/product')->load($resOrder->getProductId());
					$sn = array();
					$useSerialNumbers = $product->getPayperrentalsUseSerials();
					if( $useSerialNumbers )
					{
						if( isset($sns[$orderItemId]) ){
							foreach($sns[$orderItemId] as $serial){
								$serial = trim($serial);
								if($serial){
									//TODO check if serial exists and has status A
									$sn[] = $serial;
								}
							}
						}
						// what's the idea of this? are we adding the rest of the serial numbers automatically?
						// why are we entering the serial numbers in first place then?
						// what happens with partial shipment?
//				if(count($sn) < $resOrder->getQty()){
//					$coll = Mage::getModel('payperrentals/serialnumbers')
//						->getCollection()
//						->addEntityIdFilter($resOrder->getProductId())
//						->addSelectFilter("NOT FIND_IN_SET(sn, '".implode(',',$sn)."') AND status='A'");
//					$j = 0;
//					foreach($coll as $item){
//						$sn[] = $item->getSn();
//						if($j >= $resOrder->getQty() - count($sn)){
//							break;
//						}
//						$j++;
//					}
//				}

						$countBeforeRemovingDuplicates = count($sn);
						$sn = array_unique($sn);		// make sure there are not duplicated serial numbers
						if(count($sn) != $countBeforeRemovingDuplicates){
							Mage::throwException('There are duplicate serial numbers for the product '. $product->getName());
						}
						if(count($sn) != $shippedQty){
							Mage::throwException('Shipped Qty for item '. $product->getName(). ' should be equals to the serial numbers assigned to it');
						}

						$dataItemToSave['serials'] = $sn;

					}
					$dataToSave[] = $dataItemToSave;

					break;
				}
			}
		}

		// we are saving everything at the end so we are sure all the items are OK before saving any of them
		// TODO: do this inside a transaction
		foreach($dataToSave as $dataItemToSave)
		{
			/** @var $resOrder ITwebexperts_Payperrentals_Model_Reservationorders */
			$resOrder = $dataItemToSave['reservation'];
			$shippedItem = $dataItemToSave['shipment_item'];
			$serials = $dataItemToSave['serials'];
			$shippedQty = $shippedItem->getQty();
			$resOrderId = $resOrder->getId();
			$productId = $resOrder->getProductId();
			$stockId = $resOrder->getStockId();
			foreach($serials as $serial)
			{
				// TODO: optimize this (for example using only an UPDATE for all the serial numbers and products)
				$serialModel = Mage::getResourceModel('payperrentals/serialnumbers_collection')
					->addFieldToFilter('entity_id', $productId)
					->addFieldToFilter('stock_id', $stockId)
					->addFieldToFilter('sn', $serial)
					->getFirstItem();

				if($serialModel->getId()){
					$serialModel->setStatus('O')->save();
				}
				else {
					// TODO: what happens if we cannot find the serial number? (this could be because the admin used a wrong serial number for example)
				}
			}

			$serialNumber = implode(',',$serials);
			$sendReturn = Mage::getModel('payperrentals/sendreturn')
				->setOrderId($resOrder->getOrderId())
				->setProductId($productId)
				->setResStartdate($resOrder->getStartDate())
				->setResEnddate($resOrder->getEndDate())
				->setSendDate(date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time())))
				->setReturnDate('0000-00-00 00:00:00')
				// ->setQty($resOrder->getQty())//here needs a check this should always be true (TODO: what is this comment?)
				->setQty($shippedQty)
				->setSn($serialNumber)
				->setStockId($stockId)
				->save();

			// TODO: we should not do this! the FK is wrong (where at really there isn't any FK!), reservations HAS sendreturns, then the FK should be on the return table!
			Mage::getResourceSingleton('payperrentals/reservationorders')->updateSendReturnById($resOrderId, $sendReturn->getId());
			//ITwebexperts_Payperrentals_Helper_Data::sendEmail('send', $sendReturn->getId());
		}

	}


	/**
	 * override for saving the stock_id for the reservationorder, and using the correct qty for the stock instead of getPayperrentalsQuantity
	 * this should be checked too because we have a very denormalized table right now, why we have a qty and also a reference to the quote/order item?
	 * in which case the qty from the quote/order_item is different?
	 * @param $event
	 * @return $this
	 */
	public function updateCartReservation($event)
	{
		$quoteItem = $event->getItem();
		$stockId = $quoteItem->getStockId();

		if(Mage::getSingleton('core/session')->getData('qtyppr')){
			return;
		}

		if ($quoteItem->getProductType() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE) {
			/*todo check this one if it breaks anything*/
			if($quoteItem->getProductType() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE || ($quoteItem->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE && $quoteItem->getProduct()->getIsReservation() == ITwebexperts_Payperrentals_Model_Product_Isreservation::STATUS_DISABLED)) {
				return;
			}
		}

		if ($quoteItem->getParentItem() && $quoteItem->getParentItem()->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE) {
			return;
		}

		if ($quoteItem->isDeleted()) {
			return;
		}

		if (Mage::getModel('sales/order_item')->load($quoteItem->getId(), 'quote_item_id')->getId()) {
			return;
		}

		$Product = $quoteItem->getProduct();

		$source = unserialize($Product->getCustomOption('info_buyRequest')->getValue());
		if(!isset($source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION])){
			return;
		}

		$start_date = $source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION];
		$end_date = $source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION];

		//deposit as product part
		$quoteItems =  $quoteItem->getQuote()->getItemsCollection();
		foreach($quoteItems as $item){
			$itemId = $item->getId();

			if(!is_object($item->getProduct()->getCustomOption('quoteparent'))){
				$source = unserialize($item->getProduct()->getCustomOption('info_buyRequest')->getValue());
				if(!isset($source['quoteparent'])){
					continue;
				}else{
					$quoteId = $source['quoteparent'];
				}
			}else{
				$quoteId = $item->getProduct()->getCustomOption('quoteparent')->getValue();
			}

			if($quoteId == $quoteItem->getId()){
				$item->setQty($quoteItem->getQty());
				$item->save();
				break;
			}
		}

		try{
//end deposit as product
			if ($quoteItem->getProductType() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE) {

				if($Product->isConfigurable()){
					$qtyNotUpdate = Mage::app()->getRequest()->getParam('qty');
					if($qtyNotUpdate){
						$qty = $qtyNotUpdate;
					}else{
						$qty = $quoteItem->getQty();
					}
					if(isset($source['super_attribute'])){
						$attributes = $source['super_attribute'];
						$Product = Mage::getModel('catalog/product_type_configurable')->getProductByAttributes($attributes, $Product);
						$Product = Mage::getModel('catalog/product')->load($Product->getId());
					}else{
						Mage::throwException(Mage::helper("checkout")->__("You need to select attributes"));
						return;
					}
				}else{
					if(!Mage::getSingleton('core/session')->getData('qtyppr')){
						$qtyNotUpdate = Mage::app()->getRequest()->getParam('qty');
					}else{
						$qtyNotUpdate = '';
					}
					if($qtyNotUpdate){
						$qty = $qtyNotUpdate;
					} else{
						//check if product is part of configurable.
						if ($quoteItem->getParentItem() /*&& $quoteItem->getParentItem()->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_CONFIGURABLE*/) {
							$qty = $quoteItem->getParentItem()->getQty(); //todo check this formula
						}else{
							$qty = $quoteItem->getQty();
						}
					}
				}

				$maxQty = Mage::helper('pprwarehouse')->getQtyForProductAndStock($Product, $stockId);

				if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
					$maxQty = 100000;
				}
				if(intval($qty) > intval($maxQty)){
					Mage::throwException(Mage::helper("checkout")->__("The product you requested don't have enough quantity"));
					return;
				}

				$quoteID = Mage::getSingleton("checkout/session")->getQuote()->getId();
				if(!$quoteID){
					$quoteID = 0;
				}

				$bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForDates($Product->getId(), $start_date, $end_date,$quoteID, true, $stockId);

				$oldQty = 0;
				$coll5 = Mage::getModel('payperrentals/reservationorders')
					->getCollection()
					->addProductIdFilter($Product->getId())
					->addSelectFilter("start_date = '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date)."' AND end_date = '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($end_date)."' AND quote_id = '".$quoteID."'");

				$coll5->addFieldToFilter('stock_id', $stockId);

				foreach($coll5 as $oldQuote){
					$oldQty = $oldQuote->getQty();
					// qtyNotUpdate is trying to detect if this is an update from the cart or a new add from the PDP (I guess...)
					if($qtyNotUpdate){
						$qty += $oldQty;
					}
					break;
				}

				foreach($bookedArray as $dateFormatted => $qtyPerDay){
					if($maxQty - $qtyPerDay < $qty - $oldQty){
						Mage::throwException(Mage::helper("checkout")->__("Some of the products you requested don't have enough quantity"));
						return;
					}
				}

				$startTimePadding = strtotime(date('Y-m-d', strtotime($start_date)));
				$endTimePadding = strtotime(date('Y-m-d', strtotime($end_date)));
				$p = 0;
				if($startTimePadding <= $endTimePadding){
					while ($startTimePadding <= $endTimePadding) {
						if(!array_key_exists(date('Y-n-j', $startTimePadding), $bookedArray) ){
							$bookedTimesArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForTimes($Product->getId(), date('Y-m-d', $startTimePadding), $quoteID, true, $stockId);

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
								$intersectionArray[] = $dateFormatted;
								$startTimePaddingCur += 60 * 60;//*time_increment
							}

							foreach($bookedTimesArray as $dateFormatted => $qtyPerDay){
								//check here if there is an intersection
								if( in_array($dateFormatted, $intersectionArray) && ($maxQty - $qtyPerDay < $qty - $oldQty)){
									Mage::throwException(Mage::helper("checkout")->__("Some of the products you requested don't have enough quantity!"));
									return;
								}
							}
						}

						$startTimePadding += 60 * 60 * 24;//*time_increment
						$p++;
					}
				}

				//Mage::getResourceModel('payperrentals/reservationorders')->deleteByQuoteItem($quoteItem);
				$aChildQuoteItems = Mage::getModel("sales/quote_item")
					->getCollection()
					->setQuote($quoteItem->getQuote())
					->addFieldToFilter("parent_item_id", $quoteItem->getId());

				Mage::getResourceModel('payperrentals/reservationorders')->deleteByQuoteItem($quoteItem);
				$BQuoteItem = Mage::getModel('payperrentals/reservationorders');
				$BQuoteItem
					->setProductId($quoteItem->getProductId())
					->setStartDate($start_date)
					->setEndDate($end_date)
					->setOtype(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_QUOTE)
					->setOrderId($quoteItem->getId())
					->setQty($qty)
					->setQuoteId($quoteItem->getQuote()->getId())
					->setStockId($stockId)
					->save();

				foreach($aChildQuoteItems as $cItems){
					Mage::getResourceModel('payperrentals/reservationorders')->deleteByQuoteItem($cItems);
					$BQuoteItem = Mage::getModel('payperrentals/reservationorders');
					$BQuoteItem
						->setProductId($cItems->getProductId())
						->setStartDate($start_date)
						->setEndDate($end_date)
						->setOtype(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_QUOTE)
						->setOrderId($cItems->getId())
						->setQty($qty)
						->setQuoteId($cItems->getQuote()->getId())
						->setStockId($stockId)
						->save();
				}
			}
			else
			{
				if(!Mage::getSingleton('core/session')->getData('qtyppr')){
					$qtyNotUpdate = Mage::app()->getRequest()->getParam('qty');
				}else{
					$qtyNotUpdate = '';
				}

				if($qtyNotUpdate){
					$qty = $qtyNotUpdate;
				} else{
					$qty = $quoteItem->getQty();
				}

				$selectionIds = $source['bundle_option'];
				$selectedQtys = $source['bundle_option_qty1'];
				foreach($selectedQtys as $i1 => $j1){
					if(is_array($j1)){
						foreach($j1 as $k1 => $p1){
							$selectedQtys[$i1][$k1] = $qty * $p1;
						}
					}else{
						$selectedQtys[$i1] = $qty * $j1;
					}
				}

				$qty1 = $qty;
				$selections = $Product->getTypeInstance(true)->getSelectionsByIds($selectionIds, $Product);
				Mage::getResourceModel('payperrentals/reservationorders')->deleteByQuoteItem($quoteItem);

				foreach ($selections->getItems() as $selection) {
					$Product = Mage::getModel('catalog/product')->load($selection->getProductId());
					if($Product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){
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

						$maxQty = Mage::helper('pprwarehouse')->getQtyForProductAndStock($Product, $stockId);

						if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
							$maxQty = 100000;
						}
						if(intval($qty) > intval($maxQty)){
							Mage::throwException(Mage::helper("checkout")->__("The product you requested don't have enough quantity"));
							return;
						}

						$quoteID = Mage::getSingleton("checkout/session")->getQuote()->getId();
						if(!$quoteID){
							$quoteID = 0;
						}

						$bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForDates($Product->getId(), $start_date, $end_date,$quoteID, true, $stockId);

						$oldQty = 0;
						$coll5 = Mage::getModel('payperrentals/reservationorders')
							->getCollection()
							->addProductIdFilter($Product->getId())
							->addSelectFilter("start_date = '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date)."' AND end_date = '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($end_date)."' AND quote_id = '".$quoteID."'");

						foreach($coll5 as $oldQuote){
							$oldQty = $oldQuote->getQty();
							if($qtyNotUpdate){
								$qty += $oldQty;
							}
							break;
						}

						foreach($bookedArray as $dateFormatted => $qtyPerDay){
							if($maxQty - $qtyPerDay < $qty - $oldQty){
								Mage::throwException(Mage::helper("checkout")->__("Some of the products you requested don't have enough quantity"));
								return;
							}
						}

						$startTimePadding = strtotime(date('Y-m-d', strtotime($start_date)));
						$endTimePadding = strtotime(date('Y-m-d', strtotime($end_date)));
						$p = 0;
						if($startTimePadding <= $endTimePadding){
							while ($startTimePadding <= $endTimePadding) {
								if(!array_key_exists(date('Y-n-j', $startTimePadding), $bookedArray) ){
									$bookedTimesArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForTimes($Product->getId(), date('Y-m-d', $startTimePadding), $quoteID, true, $stockId);

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
										$intersectionArray[] = $dateFormatted;
										$startTimePaddingCur += 60 * 60;//*time_increment
									}

									foreach($bookedTimesArray as $dateFormatted => $qtyPerDay){
										//check here if there is an intersection
										if( in_array($dateFormatted, $intersectionArray) && ($maxQty - $qtyPerDay < $qty - $oldQty)){
											Mage::throwException(Mage::helper("checkout")->__("Some of the products you requested don't have enough quantity!"));
											return;
										}
									}
								}

								$startTimePadding += 60 * 60 * 24;//*time_increment
								$p++;
							}
						}

						$BQuoteItem = Mage::getModel('payperrentals/reservationorders');
						$BQuoteItem
							->setProductId($Product->getId())
							->setStartDate($start_date)
							->setEndDate($end_date)
							->setOtype(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_QUOTE)
							->setOrderId($quoteItem->getId())
							->setQty($qty)
							->setQuoteId($quoteItem->getQuote()->getId())
							->setStockId($stockId)
							->save();
					}
				}

			}
		}
		catch(Mage_Core_Exception $e){
			throw $e;
		}
		catch(Exception $e){
			throw $e;
		}

	}


	public function reserveInventory($observer)
	{
		$order = $observer->getEvent()->getOrder();

		if(ITwebexperts_Payperrentals_Helper_Data::reserveInventoryNoInvoice()){
			$items = $observer->getEvent()->getOrder()->getItemsCollection();
			foreach ($items as $item) {
				$Product = Mage::getModel('catalog/product')->load($item->getProductId());
				if ($Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE && $Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_CONFIGURABLE/* && $Product->getTypeId() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE*/) {
					continue;
				}

				$data = $item->getProductOptionByCode('info_buyRequest');

				$_date_from = $data[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION];
				$_date_to = $data[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION];

				$qty = $item->getQtyOrdered();

				//get item parent qty
				if($item->getParentItem() && $item->getParentItem()->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE){
					//echo $item->getParentItem()->getQty();
					//print_r($item->getParentItem()->debug());
					//$qty = $qty * $item->getParentItem()->getQtyInvoiced();
					//die();
				}


				$model = Mage::getModel('payperrentals/reservationorders')
					->setProductId($item->getProductId())
					->setStartDate($_date_from)
					->setEndDate($_date_to)
					->setQty($qty)
					->setOtype(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_ORDER)
					->setOrderId($observer->getEvent()->getOrder()->getIncrementId())
					->setOrderItemId($item->getId())
					//set qty_invoiced
				;
				$model->setStockId($item->getStockId());
				$model->setId(null)->save();
				Mage::getResourceModel('payperrentals/reservationorders')->deleteByQuoteItemId($item->getQuoteItemId());
			}
		}
	}



	public function convertToOrder($observer)
	{
		if(!ITwebexperts_Payperrentals_Helper_Data::reserveInventoryNoInvoice()){

			$items = $observer->getInvoice()->getOrder()->getItemsCollection();

			foreach ($items as $item)
			{
				$productType = $item->getProductType();
				if ($productType != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE && $productType != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_CONFIGURABLE/* && $productType != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE*/) {
					continue;
				}

				$data = $item->getProductOptionByCode('info_buyRequest');

				$_date_from = $data[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION];
				$_date_to = $data[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION];

				$qty = $item->getQtyInvoiced();

				//get item parent qty
				if($item->getParentItem() && $item->getParentItem()->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_BUNDLE){
					//echo $item->getParentItem()->getQty();
					//print_r($item->getParentItem()->debug());
					//$qty = $qty * $item->getParentItem()->getQtyInvoiced();
					//die();
				}


				$model = Mage::getModel('payperrentals/reservationorders')
					->setProductId($item->getProductId())
					->setStartDate($_date_from)
					->setEndDate($_date_to)
					->setQty($qty)
					->setOtype(ITwebexperts_Payperrentals_Model_Reservationorders::TYPE_ORDER)
					->setOrderId($observer->getInvoice()->getOrder()->getIncrementId())
					->setOrderItemId($item->getId())
					//set qty_invoiced
				;
				$model->setStockId($item->getStockId());
				$model->setId(null)->save();
				Mage::getResourceModel('payperrentals/reservationorders')->deleteByQuoteItemId($item->getQuoteItemId());
			}
		}
		//die();
		return $this;
	}


	public function createOrderBeforeSave($eventData){
		$updateItems = array();
		if($eventData['request_model']->getPost('item')){
			$updateItems = $eventData['request_model']->getPost('item');
		}
		$quoteObject = $eventData['order_create_model']->getQuote();
		if(is_object($quoteObject)){
			foreach($quoteObject->getAllItems() as $item){
				if($item->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE || $item->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE_CONFIGURABLE){
					$buyRequest = $item->getBuyRequest();
					if(isset($updateItems[$item->getItemId()])){
						$updateItemsArr = $updateItems[$item->getItemId()];
					}

					if(isset($updateItemsArr['qty'])){
						$qty = $updateItemsArr['qty'];
					}else{
						$qty = $buyRequest->getQty();
						//Mage::throwException('Product '.$item->getProduct()->getName().' has no qty defined');
						//return;
					}
					if(isset($updateItemsArr['start_date'])){
						$start_date = $updateItemsArr['start_date'];
					}else{
						$start_date = $buyRequest->getStartDate();
						//Mage::throwException('Product '.$item->getProduct()->getName().' has no start date defined');
						//return;
					}
					if(isset($updateItemsArr['end_date'])){
						$end_date = $updateItemsArr['end_date'];
					} else{
						$end_date = $buyRequest->getEndDate();
						//Mage::throwException('Product '.$item->getProduct()->getName().' has no end date defined');
						//return;
					}

					$id = $item->getProduct()->getId();

					if((!isset($updateItemsArr['action']) || $updateItemsArr['action'] != 'remove') && !ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailable($id, $start_date, $end_date, $qty)){
						Mage::throwException('Product '.$item->getProduct()->getName().' is not available for that qty on the selected dates');
						return;
					}
				}
			}
		}
		return $this;
	}


}
