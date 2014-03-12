<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_Sales_Quote_Item extends Innoexts_Warehouse_Model_Sales_Quote_Item
{
	/**
	 * @param null $stockItem
	 * @return Varien_Object
	 */
	public function checkQty($stockItem = null)
	{
		$result = new Varien_Object();
		$result->setHasError(false);
		if (!$this->getProductId() || !$this->getQuote()) {
			$result->setHasError(true);
			return $result;
		}
		if ($this->getQuote()->getIsSuperMode()) {
			$result->setHasError(false);
			return $result;
		}
		$product = $this->getProduct();
		if (!$stockItem) {
			$stockItem = $this->getStockItem();
		} else if (!$stockItem->getProduct()) {
			$stockItem->setProduct($product);
		}
		$qty = $this->getQty();
		if (($options = $this->getQtyOptions()) && $qty > 0) {
			$qty = $product->getTypeInstance(true)->prepareQuoteItemQty($qty, $product);
			if ($stockItem) {
				$result = $stockItem->checkQtyIncrements($qty);
				if ($result->getHasError()) {
					return $result;
				}
			}
			foreach ($options as $option) {
				if ($stockItem) {
					$option->setStockId($stockItem->getStockId());
				}
				$optionQty = $qty * $option->getValue();
				$increaseOptionQty = ($this->getQtyToAdd() ? $this->getQtyToAdd() : $qty) * $option->getValue();
				$option->unsetStockItem();
				$stockItem = $option->getStockItem();
				if (!$stockItem instanceof Mage_CatalogInventory_Model_Stock_Item) return false;
				$stockItem->setOrderedItems(0);
				$stockItem->setIsChildItem(true);
				$stockItem->setSuppressCheckQtyIncrements(true);
				$qtyForCheck = $increaseOptionQty;
				/** @var ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock_Item $stockItem */
				$optionResult = $stockItem->checkQuoteItemQty($optionQty, $qtyForCheck, $option->getValue(), $this);
				$stockItem->unsIsChildItem();
				if (!$optionResult->getHasError()) {
					if ($optionResult->getHasQtyOptionUpdate()) {
						$result->setHasQtyOptionUpdate(true);
					}
					if ($optionResult->getItemIsQtyDecimal()) {
						$result->setItemIsQtyDecimal(true);
					}
					if ($optionResult->getItemQty()) {
						$result->setItemQty(floatval($result->getItemQty()) + $optionResult->getItemQty());
					}
					if ($optionResult->getOrigQty()) {
						$result->setOrigQty(floatval($result->getOrigQty()) + $optionResult->getOrigQty());
					}
					if ($optionResult->getItemUseOldQty()) {
						$result->setItemUseOldQty(true);
					}
					if ($optionResult->getItemBackorders()) {
						$result->setItemBackorders(floatval($result->getItemBackorders()) + $optionResult->getItemBackorders());
					}
				} else {
					return $optionResult;
				}
			}
		} else {
			if (!$stockItem instanceof Mage_CatalogInventory_Model_Stock_Item) {
				$result->setHasError(true);
				return $result;
			}
			$rowQty = $increaseQty = 0;
			if (!$this->getParentItem()) {
				$increaseQty = $this->getQtyToAdd() ? $this->getQtyToAdd() : $qty;
				$rowQty = $qty;
			} else {
				$rowQty = $this->getParentItem()->getQty() * $qty;
			}
			$qtyForCheck = $increaseQty;
			$productTypeCustomOption = $product->getCustomOption('product_type');
			if (!is_null($productTypeCustomOption)) {
				if ($productTypeCustomOption->getValue() == Mage_Catalog_Model_Product_Type_Grouped::TYPE_CODE) {
					$stockItem->setIsChildItem(true);
				}
			}
			/** @var ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock_Item $stockItem */
			$result = $stockItem->checkQuoteItemQty($rowQty, $qtyForCheck, $qty, $this);
			if ($stockItem->hasIsChildItem()) {
				$stockItem->unsIsChildItem();
			}
		}
		return $result;
	}


}
