<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_Payperrentals_Product_Type_Reservation
	extends ITwebexperts_Payperrentals_Model_Product_Type_Reservation
{

	public function isAvailable($Product = null, $qty=1)
	{
		if(!$qty) {
			$qty = 1;
		}
		if(is_null($Product)) {
			$Product = ($this->_product) ? $this->_product : $this->getProduct();
		}

		if(ITwebexperts_Payperrentals_Helper_Data::isAllowedOverbook($Product)){
			return true;
		}

		$start_date = $Product->getCustomOption(self::START_DATE_OPTION)->getValue();
		$end_date = $Product->getCustomOption(self::END_DATE_OPTION)->getValue();

		$isAvailable = false;
		$helper = Mage::helper('pprwarehouse');
		foreach($helper->getValidStockIds() as $stockId)
		{
			$isAvailable = true;
			$maxQty = $helper->getQtyForProductAndStock($Product, $stockId);
			if($maxQty < $qty){
				$isAvailable = false;
				continue;
			}

			// don't take into the account the current reserved qty (we will replace that value if is available the new qty)
			$quoteID = Mage::getSingleton("checkout/session")->getQuote()->getId();
			// what if the quote_id is null (0), are we searching order rows too here???
			if(!$quoteID){
				$quoteID = 0;
			}
			$bookedArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForDates($Product->getId(), $start_date, $end_date, $quoteID, true, $stockId);
			$oldQty = 0;
			$coll5 = Mage::getModel('payperrentals/reservationorders')
				->getCollection()
				->addProductIdFilter($Product->getId())
				->addFieldToFilter('stock_id', $stockId)
				->addSelectFilter("start_date = '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($start_date)."' AND end_date = '".ITwebexperts_Payperrentals_Helper_Data::toDbDate($end_date)."' AND quote_id = '".$quoteID."'");

			foreach($coll5 as $oldQuote){
				$oldQty = $oldQuote->getQty();
				break;
			}

			if(Mage::app()->getRequest()->getParam('qty')){
				$oldQty = 0;
			}

			foreach($bookedArray as $dateFormatted => $qtyPerDay){
				if($maxQty - $qtyPerDay < $qty - $oldQty){
					$isAvailable = false;
					break;
				}
			}
			if( ! $isAvailable){
				continue;
			}

			$startTimePadding = strtotime(date('Y-m-d', strtotime($start_date)));
			$endTimePadding = strtotime(date('Y-m-d', strtotime($end_date)));
			$p = 0;
			if($startTimePadding <= $endTimePadding){
				while ($startTimePadding <= $endTimePadding && $isAvailable) {
					if(!array_key_exists(date('Y-n-j', $startTimePadding), $bookedArray) ){
						$bookedTimesArray = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getBookedQtyForTimes($Product->getId(), date('Y-m-d', $startTimePadding),$quoteID, true, $stockId);
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
								$isAvailable = false;
								break;
							}
						}
					}

					$startTimePadding += 60 * 60 * 24;//*time_increment
					$p++;
				}
				// invalid stock, continue with another one
				if( ! $isAvailable){
					continue;
				}
			}
			// valid stock found, stop checking
			if( $isAvailable){
				break;
			}
		}

		return $isAvailable;
	}


}
