<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_CatalogInventory_Observer extends Innoexts_Warehouse_Model_Cataloginventory_Observer
{

	/**
     * Cancel order item
     *
     * @param Varien_Event_Observer $observer
     *
     * @return Innoexts_Warehouse_Model_Cataloginventory_Observer
     */
    public function cancelOrderItem($observer)
    {
        $item       = $observer->getEvent()->getItem();
        if($item->getProductType() != ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){
            $children   = $item->getChildrenItems();
            $qty        = $item->getQtyOrdered() - max($item->getQtyShipped(), $item->getQtyInvoiced()) - $item->getQtyCanceled();
            if ($item->getId() && ($productId = $item->getProductId()) && empty($children) && $qty) {
                $this->getCatalogInventoryHelper()->getStockSingleton($item->getStockId())->backItemQty($productId, $qty);
            }
        }
        return $this;
    }
    //I should modify this function for frq... so no checking of qty should be done for rfq.
	/**
	 * override for using the new ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock_Item::checkQuoteItemQty
	 * we need to pass the quoteItem to that method
	 * @param Innoexts_Warehouse_Model_Sales_Quote_Item $quoteItem
	 * @return $this|Innoexts_Warehouse_Model_Cataloginventory_Observer
	 */
	protected function checkQuoteItemQtyWithOptions($quoteItem)
	{
		$quote      = $quoteItem->getQuote();
		$stockItem  = $quoteItem->getStockItem();
		$product    = $quoteItem->getProduct();
		$options    = $quoteItem->getQtyOptions();
		$qty        = $product->getTypeInstance(true)->prepareQuoteItemQty($quoteItem->getQty(), $product);
		$quoteItem->setData('qty', $qty);
        if(Mage::app()->getRequest()->getParam('isrfq') || (Mage::app()->getRequest()->getControllerName() == 'adminhtml_quote_edit') || (Mage::app()->getRequest()->getControllerName() == 'adminhtml_quote_create') ){
            return $this;
        }
		if ($stockItem) {
			$result = $stockItem->checkQtyIncrements($qty);
			if ($result->getHasError()) {
				$quoteItem->setHasError(true)->setMessage($result->getMessage());
				$quote->setHasError(true)->addMessage($result->getQuoteMessage(), $result->getQuoteMessageIndex());
			}
		}
		foreach ($options as $option) {
			if ($stockItem) {
				$option->setStockId($stockItem->getStockId());
			}
			$optionQty = $qty * $option->getValue();
			$increaseOptionQty = ($quoteItem->getQtyToAdd() ? $quoteItem->getQtyToAdd() : $qty) * $option->getValue();
			$option->unsetStockItem();
			$stockItem = $option->getStockItem();

			if ($this->getVersionHelper()->isGe1700()) {
				if ($quoteItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
					$stockItem->setProductName($quoteItem->getName());
				}
			}

			if (!$stockItem instanceof Mage_CatalogInventory_Model_Stock_Item) {
				$this->throwException('The stock item for Product in option is not valid.');
			}
			$stockItem->setOrderedItems(0);
			$stockItem->setIsChildItem(true);
			$stockItem->setSuppressCheckQtyIncrements(true);
			$qtyForCheck = $this->_getQuoteItemQtyForCheck2(
				$option->getProduct()->getId(), $stockItem->getStockId(), $quoteItem->getId(), $increaseOptionQty
			);
			if ($qtyForCheck > $optionQty) {
				$qtyForCheck = $optionQty;
			}
			/** @var ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock_Item $stockItem */
			$result = $stockItem->checkQuoteItemQty($optionQty, $qtyForCheck, $option->getValue(), $quoteItem);
			if (!is_null($result->getItemIsQtyDecimal())) {
				$option->setIsQtyDecimal($result->getItemIsQtyDecimal());
			}
			if ($result->getHasQtyOptionUpdate()) {
				$option->setHasQtyOptionUpdate(true);
				$quoteItem->updateQtyOption($option, $result->getOrigQty());
				$option->setValue($result->getOrigQty());
				$quoteItem->setData('qty', intval($qty));
			}
			if (!is_null($result->getMessage())) {
				$option->setMessage($result->getMessage());

				if ($this->getVersionHelper()->isGe1700()) {
					$quoteItem->setMessage($result->getMessage());
				}

			}
			if (!is_null($result->getItemBackorders())) {
				$option->setBackorders($result->getItemBackorders());
			}
			if ($result->getHasError()) {
				$option->setHasError(true);
				$quoteItem->setHasError(true)->setMessage($result->getQuoteMessage());
				$quote->setHasError(true)->addMessage($result->getQuoteMessage(), $result->getQuoteMessageIndex());
			}
			$stockItem->unsIsChildItem();
		}
		return $this;
	}


	/**
	 * override for using the new ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock_Item::checkQuoteItemQty
	 * we need to pass the quoteItem to that method
	 *
	 * @param Innoexts_Warehouse_Model_Sales_Quote_Item $quoteItem
	 * @return $this|Innoexts_Warehouse_Model_Cataloginventory_Observer
	 */
	protected function checkQuoteItemQtyWithoutOptions($quoteItem)
	{
		$quote = $quoteItem->getQuote();
		$stockItem = $quoteItem->getStockItem();
		$product = $quoteItem->getProduct();
		$qty = $quoteItem->getQty();
        if(Mage::app()->getRequest()->getParam('isrfq') || (Mage::app()->getRequest()->getControllerName() == 'adminhtml_quote_edit') || (Mage::app()->getRequest()->getControllerName() == 'adminhtml_quote_create') ){
            //or is added in admin from quote editor/// when returning stock too//on preparecartadvanced
            return $this;
        }
		if (!$stockItem instanceof Mage_CatalogInventory_Model_Stock_Item) {
			$this->throwException('The stock item for Product is not valid.');
		}
		if ($quoteItem->getParentItem()) {
			$rowQty = $quoteItem->getParentItem()->getQty() * $qty;
			$qtyForCheck = $this->_getQuoteItemQtyForCheck2($product->getId(), $stockItem->getStockId(), $quoteItem->getId(), 0);
		} else {
			$increaseQty = $quoteItem->getQtyToAdd() ? $quoteItem->getQtyToAdd() : $qty;
			$rowQty = $qty;
			$qtyForCheck = $this->_getQuoteItemQtyForCheck2($product->getId(), $stockItem->getStockId(), $quoteItem->getId(), $increaseQty);
		}
		$productTypeCustomOption = $product->getCustomOption('product_type');
		if (!is_null($productTypeCustomOption)) {
			if ($productTypeCustomOption->getValue() == Mage_Catalog_Model_Product_Type_Grouped::TYPE_CODE) {
				$stockItem->setIsChildItem(true);
			}
		}
		if ($qtyForCheck > $rowQty) {
			$qtyForCheck = $rowQty;
		}
		/** @var ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock_Item $stockItem */
		$result = $stockItem->checkQuoteItemQty($rowQty, $qtyForCheck, $qty, $quoteItem);
		if ($stockItem->hasIsChildItem()) {
			$stockItem->unsIsChildItem();
		}
		if (!is_null($result->getItemIsQtyDecimal())) {
			$quoteItem->setIsQtyDecimal($result->getItemIsQtyDecimal());
			if ($quoteItem->getParentItem()) {
				$quoteItem->getParentItem()->setIsQtyDecimal($result->getItemIsQtyDecimal());
			}
		}
		if ($result->getHasQtyOptionUpdate() && (!$quoteItem->getParentItem() ||
				$quoteItem->getParentItem()->getProduct()->getTypeInstance(true)
					->getForceChildItemQtyChanges($quoteItem->getParentItem()->getProduct()))) {
			$quoteItem->setData('qty', $result->getOrigQty());
		}
		if (!is_null($result->getItemUseOldQty())) {
			$quoteItem->setUseOldQty($result->getItemUseOldQty());
		}
		if (!is_null($result->getMessage())) {
			$quoteItem->setMessage($result->getMessage());
			if ($quoteItem->getParentItem()) {
				$quoteItem->getParentItem()->setMessage($result->getMessage());
			}
		}
		if (!is_null($result->getItemBackorders())) {
			$quoteItem->setBackorders($result->getItemBackorders());
		}
		if ($result->getHasError()) {
			$quoteItem->setHasError(true);
			$quote->setHasError(true)->addMessage($result->getQuoteMessage(), $result->getQuoteMessageIndex());
		}
		return $this;
	}


}
