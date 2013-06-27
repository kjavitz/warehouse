<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_Payperrentals_Product_Backend_Serialnumbers
	extends ITwebexperts_Payperrentals_Model_Product_Backend_Serialnumbers
{
	public function afterSave($object)
	{
		// force default settings for stockData
		$product = $this->_getProduct();
		if(is_object( $product )){
			$stockskData = $product->getStocksData();
			if($stockskData)
			{
				$qtyByStock = array();
				foreach($stockskData as $key => $stockData){
					$stockskData[$key]['is_in_stock'] = 1;
					// force manage_stock, so we can use the original system for checking the stock too
					// we are bypassing the qty substract inside ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock::registerProductsSale
					$stockskData[$key]['manage_stock'] = 1;
					$stockskData[$key]['use_config_manage_stock'] = 0;
					$qtyByStock[$stockData['stock_id']] = $stockData['qty'];
				}
				$product->setStocksData($stockskData);


				if(Mage::app()->getRequest()->getActionName() == 'duplicate'){
					$product->setPayperrentalsUseSerials(ITwebexperts_Payperrentals_Model_Product_Useserials::STATUS_DISABLED);
				}

				if($product->getPayperrentalsUseSerials() == ITwebexperts_Payperrentals_Model_Product_Useserials::STATUS_ENABLED)
				{
					$sns = $object->getData($this->getAttribute()->getName());
					if( (!is_array($sns) || ! $this->_isQtyEqualsSerialNumbers($qtyByStock, $sns))) { // I need to check for broken status (what does it mean this comment? Enrique)
						Mage::getSingleton('adminhtml/session')->setData('ppr', Mage::app()->getRequest()->getParam('product'));
						Mage::throwException('Number of items is different than number of serial numbers!');
						return $this;
					}

					Mage::getResourceSingleton('payperrentals/serialnumbers')->deleteByEntityId($object->getId());

					foreach ($sns as $k=>$sn) {
						if(!is_numeric($k)) continue;	// TODO: when could this happen? this is inconsistent with the qty check

						$ex = Mage::getModel('payperrentals/serialnumbers')
							->setEntityId($product->getId())
							->setSn($sn['sn'])
							->setStatus($sn['status'])
							->setStockId($sn['stockid'])
							->save();
					}
				}
			}

		}
		return $this;
	}

	protected function _isQtyEqualsSerialNumbers($qtyByStock, $serialNumbers)
	{
		$serialNumbersByStock = array();
		foreach($serialNumbers as $sn){
			$stockId = $sn['stockid'];
			if( ! isset($serialNumbersByStock[$stockId])){
				$serialNumbersByStock[$stockId] = 0;
			}
			$serialNumbersByStock[$stockId]++;
		}
		foreach($qtyByStock as $stockId => $qty){
			if( $qty && (! isset($serialNumbersByStock[$stockId]) || $serialNumbersByStock[$stockId] != $qty)){
				return false;
			}
		}
		return true;
	}


}
