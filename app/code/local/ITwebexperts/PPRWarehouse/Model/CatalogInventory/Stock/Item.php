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
        //todo check this for configurable, bundle with multiple qty options, grouped
		if($quoteItem && $quoteItem->getProductType() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE){

			$product = $quoteItem->getProduct();
            $stockId = $this->getStockId();
            $result = new Varien_Object();
            $result->setHasError(false);

            if (!is_numeric($qty)) {
                $qty = Mage::app()->getLocale()->getNumber($qty);
            }

            /**
             * Check quantity type
             */
            $result->setItemIsQtyDecimal($this->getIsQtyDecimal());

            if (!$this->getIsQtyDecimal()) {
                $result->setHasQtyOptionUpdate(true);
                $qty = intval($qty);

                /**
                 * Adding stock data to quote item
                 */
                $result->setItemQty($qty);

                if (!is_numeric($qty)) {
                    $qty = Mage::app()->getLocale()->getNumber($qty);
                }
                $origQty = intval($origQty);
                $result->setOrigQty($origQty);
            }
            if ($this->getMinSaleQty() && $qty < $this->getMinSaleQty()) {
                $result->setHasError(true)
                        ->setMessage(
                                Mage::helper('cataloginventory')->__('The minimum quantity allowed for purchase is %s.', $this->getMinSaleQty() * 1)
                        )
                        ->setErrorCode('qty_min')
                        ->setQuoteMessage(Mage::helper('cataloginventory')->__('Some of the products cannot be ordered in requested quantity.'))
                        ->setQuoteMessageIndex('qty');
                return $result;
            }

            if ($this->getMaxSaleQty() && $qty > $this->getMaxSaleQty()) {
                $result->setHasError(true)
                        ->setMessage(
                                Mage::helper('cataloginventory')->__('The maximum quantity allowed for purchase is %s.', $this->getMaxSaleQty() * 1)
                        )
                        ->setErrorCode('qty_max')
                        ->setQuoteMessage(Mage::helper('cataloginventory')->__('Some of the products cannot be ordered in requested quantity.'))
                        ->setQuoteMessageIndex('qty');
                return $result;
            }

            $result->addData($this->checkQtyIncrements($qty)->getData());
            if ($result->getHasError()) {
                return $result;
            }

            $isAvailable = false;
            if (ITwebexperts_Payperrentals_Helper_Inventory::isAllowedOverbook($product->getId())) {
                $isAvailable = true;
            }
            if(!$isAvailable){
                $maxQty = ITwebexperts_PPRWarehouse_Helper_Payperrentals_Inventory::getQuantityForProductAndStock($product, $stockId);
                if($maxQty >= $qty){
                    $isAvailable = true;
                }
            }




            if ($isAvailable) {
                return $result;
            }


            if (!$isAvailable) {
                $message = Mage::helper('cataloginventory')->__('The requested quantity for "%s" is not available.', $this->getProductName());
                $result->setHasError(true)
                        ->setMessage($message)
                        ->setQuoteMessage($message)
                        ->setQuoteMessageIndex('qty');
                return $result;
            } else {
                if (($this->getQty() - $summaryQty) < 0) {
                    if ($this->getProductName()) {
                        if ($this->getIsChildItem()) {
                            $backorderQty = ($this->getQty() > 0) ? ($summaryQty - $this->getQty()) * 1 : $qty * 1;
                            if ($backorderQty > $qty) {
                                $backorderQty = $qty;
                            }

                            $result->setItemBackorders($backorderQty);
                        } else {
                            $orderedItems = $this->getOrderedItems();
                            $itemsLeft = ($this->getQty() > $orderedItems) ? ($this->getQty() - $orderedItems) * 1 : 0;
                            $backorderQty = ($itemsLeft > 0) ? ($qty - $itemsLeft) * 1 : $qty * 1;

                            if ($backorderQty > 0) {
                                $result->setItemBackorders($backorderQty);
                            }
                            $this->setOrderedItems($orderedItems + $qty);
                        }

                        if ($this->getBackorders() == Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NOTIFY) {
                            if (!$this->getIsChildItem()) {
                                $result->setMessage(
                                        Mage::helper('cataloginventory')->__('This product is not available in the requested quantity. %s of the items will be backordered.', ($backorderQty * 1))
                                );
                            } else {
                                $result->setMessage(
                                        Mage::helper('cataloginventory')->__('"%s" is not available in the requested quantity. %s of the items will be backordered.', $this->getProductName(), ($backorderQty * 1))
                                );
                            }
                        } elseif (Mage::app()->getStore()->isAdmin()) {
                            $result->setMessage(
                                    Mage::helper('cataloginventory')->__('The requested quantity for "%s" is not available.', $this->getProductName())
                            );
                        }
                    }
                } else {
                    if (!$this->getIsChildItem()) {
                        $this->setOrderedItems($qty + (int)$this->getOrderedItems());
                    }
                }
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
