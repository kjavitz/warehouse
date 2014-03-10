<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock_Item extends Innoexts_Warehouse_Model_Cataloginventory_Stock_Item
{

	/**
	 * override for changing the current stock qty based on the quote_item
	 * (this is a bad design from Magento, checkQuoteItemQty should receive the quoteItem in the original code too)
	 * @param mixed $qty
	 * @param mixed $summaryQty
	 * @param int $origQty
	 * @param Mage_Sales_Model_Quote_Item $quoteItem
	 * @return Varien_Object
	 */
	public function checkQuoteItemQty($qty, $summaryQty, $origQty = 0, $quoteItem = null)
	{

		$originalQty = $this->getData('qty');
		if($quoteItem && $quoteItem->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){

			$product = $quoteItem->getProduct();

            if($quoteItem->getParentItem()){
                $options = $quoteItem->getParentItem()->getProductOptionByCode('info_buyRequest');
            }else{
                $options = $quoteItem->getProductOptionByCode('info_buyRequest');
            }
			$startDate = $options['start_date'];
			$endDate = $options['end_date'];

			$stockId = $this->getStockId(); // $quoteItem->getStockId();

            if($startDate && $endDate){
                $newStock = ITwebexperts_Payperrentals_Helper_Data::getStock($product, $startDate, $endDate, 0, $stockId);
			    $newQty = isset($newStock['avail']) ? $newStock['avail'] : 0;
            }else{
                $newQty = 10000000;
            }
			$this->setQty($newQty);
		}
		$return = parent::checkQuoteItemQty( $qty, $summaryQty, $origQty );
		$this->setQty($originalQty);
		return $return;
	}

	public function getQty()
	{
		// this could be an easier solution: just change the "getQty" to return the real stock qty
		// if we could do this we don't so many rewrites and changes, it's enough with this
		// but we need to test it much more before choosing this way

//		$product = $this->_productInstance;
//		if($product && $product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE)
//		{
//			if( $this->getRealQty() === null)
//			{
//				$options = $product->getCustomOptions();
//				if(isset($options['start_date']) && isset($options['end_date']))
//				{
//					$startDate = $options['start_date']->getValue();
//					$endDate = $options['end_date']->getValue();
//					$stockId = $this->getStockId();
//					$newStock = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::getStock($product, $startDate, $endDate, 0, $stockId);
//					$newQty = isset($newStock['avail']) ? $newStock['avail'] : 0;
//					$this->setRealQty($newQty);
//				}
//			}
//			return $this->getRealQty();
//		}
		return $this->getData('qty');
	}

    public function getStockId(){
		$product = $this->getProduct();
		if($product && $product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE)
		{
				$options = $product->getCustomOptions();
				if(isset($options['stock_id']))
				{
                    return $options['stock_id'];
                }
        }
        return parent::getStockId();
    }
	protected function _beforeSave()
	{
		return parent::_beforeSave();
	}


}
