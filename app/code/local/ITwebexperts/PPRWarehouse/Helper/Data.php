<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Helper_Data extends Mage_Core_Helper_Abstract
{

	protected $_qtyByProductAndStock = array();

	/**
	 * @param $product Mage_Catalog_Model_Product |int
	 * @param $stockId int
	 * @param bool $useCache
	 * @return mixed
	 */
	public function getQtyForProductAndStock($product, $stockId, $useCache = true)
	{
		if ($product instanceof Mage_Catalog_Model_Product) {
			$product = $product->getId();
		}
		if( ! $stockId){
			Mage::throwException('Stock Identifier was not specified');
		}
		if( ! isset($this->_qtyByProductAndStock[$product][$stockId]) || ! $useCache){
			$stockItem = Mage::helper('warehouse')->getCatalogInventoryHelper()->getStockItem($stockId);
			$stockItem->loadByProduct($product);
			$this->_qtyByProductAndStock[$product][$stockId] = $stockItem->getQty();
		}
		return $this->_qtyByProductAndStock[$product][$stockId];
	}

	/**
	 * in multiple mode the stock_id for the quote is always null
	 * @return int|null
	 */
	public function getCurrentQuoteStock()
	{
		$quote = Mage::helper('checkout')->getQuote();
		return Mage::helper('warehouse')->getAssignmentMethodHelper()->getQuoteStockId($quote); // $quote ? $quote->getStockId() : null;
	}


	public function getValidStockIds($quote = null)
	{
		$helper = Mage::helper('warehouse')->getCatalogInventoryHelper();
		if( ! $quote){
			$quote = Mage::helper('checkout')->getQuote();
		}
		// $stockData = Mage::helper('warehouse')->getQuoteHelper()->getStockData($quote);
		$currentQuoteStockId = Mage::helper('pprwarehouse')->getCurrentQuoteStock();
		return $currentQuoteStockId && $quote->getItemsCount() ? array($currentQuoteStockId) : $helper->getStockIds();
	}

	/**
	 * @param $product Mage_Catalog_Model_Product | Mage_Sales_Model_Order_Item
	 * @return bool
	 */
	public function isReservationProduct($product)
	{
		$productType = '';
		if($product instanceof Mage_Catalog_Model_Product){
			$productType = $product->getTypeId();
		}
		elseif($product instanceof Mage_Sales_Model_Order_Item){
			$productType = $product->getProductType();
		}
		return $productType == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE;
	}

}
