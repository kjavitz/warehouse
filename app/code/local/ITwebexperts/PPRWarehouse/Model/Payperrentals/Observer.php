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
    /*updated*/
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
			->addSelectFilter("order_id = '".$order->getId()."'")
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

}
