<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_Sales_Quote_Item extends Innoexts_Warehouse_Model_Sales_Quote_Item
{

    /**
     * Get splitted stock data
     *
     * @return array of Varien_Object
     */
    public function getSplittedStockData()
    {
        return parent::getSplittedStockData();
        $stockData = array();
        $stockQtys = $this->getSplittedStockQtys();
        if (count($stockQtys)) {
            $productId = $this->getProductId();
            foreach ($stockQtys as $stockId => $qty) {
                $stockIds = array($stockId => $stockId);
                $stockItems = array();
                foreach ($this->getStockItems() as $_stockId => $stockItem) {
                    if ($_stockId == $stockId) {
                        $stockItems[$stockId] = $stockItem;
                        break;
                    }
                }
                $itemStockData = new Varien_Object();
                $itemStockData->setProductId($productId);
                $itemStockData->setProduct($this->getProduct());
                $itemStockData->setStockItems($stockItems);
                $itemStockData->setStockIds($stockIds);
                $itemStockData->setStockId($stockId);
                $itemStockData->setIsInStock((count($stockIds) ? true : false));
                $itemStockData->setQty($qty);
                if ($this->isParentItem()) {
                    $children = array();
                    foreach ($this->getChildren() as $childItem) {
                        $childItemStockData = $childItem->getStockData($stockIds);
                        $children[$childItem->getProductId()] = $childItemStockData;
                    }
                } else {
                    $children = null;
                }
                $itemStockData->setChildren($children);
                $itemStockData->setParent((count($children) ? true : false));
                $stockData[] = $itemStockData;
            }
        }
        return $stockData;
    }

    /**
     * Get complex item splitted stock quantities
     *
     * @param array $children
     * @param string $childQtyMethod
     *
     * @return array
     */
    protected function _getContainerItemSplittedStockQtys($children, $childQtyMethod)
    {
        $stockQtys = array();
        $qty = $this->getQty();
        foreach ($children as $childItem) {
            $childProductId = $childItem->getProductId();
            $childQty = $childItem->$childQtyMethod();
            if ($childQty <= 0) {
                $childQty = 1;
            }
            $totalQty = $qty * $childQty;
            foreach ($childItem->getStockItems() as $stockId => $stockItem) {
                //$stockQty = $stockItem->getMaxStockQty($totalQty);
                $stockQty = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Inventory::getQuantityForProductAndStock($childItem->getProduct(), $stockId, $totalQty);
                if (($stockQty !== false) && ($stockQty > 0)) {
                    $stockQtys[$stockId][$childProductId] = floor($stockQty / $childQty);
                } else {
                    $stockQtys[$stockId][$childProductId] = null;
                }
            }
        }
        $_stockQtys = $stockQtys;
        $stockQtys = array();
        $stockIds = $this->getStockIds();
        foreach ($_stockQtys as $stockId => $_qtys) {
            $_qty = null;
            if (in_array($stockId, $stockIds)) {
                foreach ($children as $childItem) {
                    $childProductId = $childItem->getProductId();
                    if (!isset($_qtys[$childProductId]) && is_null($_qtys[$childProductId])) {
                        $_qty = null;
                        break;
                    } else {
                        if (is_null($_qty) || ($_qtys[$childProductId] < $_qty)) {
                            $_qty = $_qtys[$childProductId];
                        }
                    }
                }
            }
            $stockQtys[$stockId] = $_qty;
        }
        $_stockQtys = $stockQtys;
        $stockQtys = array();
        $totalQty = $this->getQty();
        $stockIds = array();
        foreach ($_stockQtys as $stockId => $_qty) {
            array_push($stockIds, $stockId);
        }
        usort($stockIds, array($this, 'sortStockIds'));
        foreach ($stockIds as $stockId) {
            if (isset($_stockQtys[$stockId])) {
                $_qty = $_stockQtys[$stockId];
                if (!is_null($_qty)) {
                    if ($totalQty > $_qty) {
                        $stockQtys[$stockId] = $_qty;
                        $totalQty -= $_qty;
                    } else {
                        $stockQtys[$stockId] = $totalQty;
                        $totalQty = 0;
                        break;
                    }
                }
            }
        }
        if ($totalQty > 0) {
            $stockQtys = array();
        }
        return $stockQtys;
    }
    /**
     * Get complex item splitted stock quantities
     *
     * @return array
     */
    protected function getContainerItemSplittedStockQtys()
    {
        $stockQtys = array();
        if ($this->isParentItem()) {
            $stockQtys = $this->_getContainerItemSplittedStockQtys($this->getChildren(), 'getQty');
        }
        return $stockQtys;
    }
    /**
     * Get simple item splitted stock quantities
     *
     * @return array
     */
    protected function getSimpleItemSplittedStockQtys()
    {
        $stockQtys = array();
        if (!count($this->getQtyOptions())) {
            $totalQty = $this->getTotalQty();
            $stockItems = $this->getStockItems();
            $stockIds = $this->getStockIds();
            usort($stockIds, array($this, 'sortStockIds'));
            foreach ($stockIds as $stockId) {
                if (isset($stockItems[$stockId])) {
                    //$stockItem = $stockItems[$stockId];
                    //$stockQty = $stockItem->getMaxStockQty($totalQty);
                    $stockQty = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Inventory::getQuantityForProductAndStock($this->getProduct(), $stockId, $totalQty);
                    if (($stockQty !== false) && ($stockQty > 0)) {
                        $stockQtys[$stockId] = $stockQty;
                        $totalQty -= $stockQty;
                        if ($totalQty <= 0) {
                            break;
                        }
                    }
                }
            }
            if ($totalQty > 0) {
                $stockQtys = array();
            }
        } else {
            $stockQtys = $this->_getContainerItemSplittedStockQtys($this->getQtyOptions(), 'getValue');
        }
        return $stockQtys;
    }

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
