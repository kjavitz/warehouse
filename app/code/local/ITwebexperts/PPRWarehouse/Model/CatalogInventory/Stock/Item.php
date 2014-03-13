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
		if($quoteItem && $quoteItem->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){

			$product = $quoteItem->getProduct();
            $result = new Varien_Object();
            $result->setHasError(false);
            /*if($quoteItem->getParentItem()){
                $options = $quoteItem->getParentItem()->getProductOptionByCode('info_buyRequest');
            }else{
                $options = $quoteItem->getProductOptionByCode('info_buyRequest');
            }*/
            if (!is_object($product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION))) {
                $source = unserialize($product->getCustomOption('info_buyRequest')->getValue());
                if (isset($source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION])) {
                    $startDateval = $source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION];
                    $endDateVal = $source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION];
                    if (isset($source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL])) {
                        $nonSequential = $source[ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL];
                    }
                }
            } else {
                $startDateval = $product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::START_DATE_OPTION)->getValue();
                $endDateVal = $product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::END_DATE_OPTION)->getValue();
                if (is_object($product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL))) {
                    $nonSequential = $product->getCustomOption(ITwebexperts_Payperrentals_Model_Product_Type_Reservation::NON_SEQUENTIAL)->getValue();
                }
            }

            if ($nonSequential == 1) {
                $startDateArr = explode(',', $startDateval);
                $endDateArr = explode(',', $startDateval);
            } else {
                $startDateArr = array($startDateval);
                $endDateArr = array($endDateVal);
            }
            $stockId = $this->getStockId();
            //$newQty = 0;
            foreach ($startDateArr as $count => $startDate) {
                $endDate = $endDateArr[$count];

                if($startDate && $endDate){
                    if(Mage::registry('stock_id')){
                        $_regKey = Mage::registry('stock_id');
                        Mage::unregister('stock_id');
                    }
                    Mage::register('stock_id', $stockId);
                    /** @var $inventoryHelper ITwebexperts_Payperrentals_Helper_Inventory */

                    $iQty = ITwebexperts_Payperrentals_Helper_Data::getUpdatingQty($quoteItem);
                    if($iQty){
                        $qty = $iQty;
                    }
                    Mage::register('no_quote', 1);
                    $inventoryHelper = Mage::helper('payperrentals/inventory');
                    $isAvailable = $inventoryHelper->isAvailable($product->getId(), $startDate, $endDate, $qty, $quoteItem);
                    //$return = parent::checkQuoteItemQty( $newQty, $summaryQty, $origQty );
                    if(!$isAvailable){
                        $result->setHasError(true);
                    }
                }
            }

            if(isset($_regKey)){
                Mage::unregister('stock_id');
                Mage::register('stock_id', $_regKey);
            }

            return $result;
		}
        $return = parent::checkQuoteItemQty( $qty, $summaryQty, $origQty );
		return $return;
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
