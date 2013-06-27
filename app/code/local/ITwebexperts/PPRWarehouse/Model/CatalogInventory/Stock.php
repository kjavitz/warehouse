<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock
	extends Innoexts_Warehouse_Model_Cataloginventory_Stock
{
	/**
	 * override for bypassing stock qty substract
	 * (even when mange_stock is enabled, so we can still checking the stock with the original system too)
	 *
	 * @param array $items
	 * @return array
	 */
	public function registerProductsSale($items)
	{
		$productIds = array_keys($items);
		/* @var $products Mage_Catalog_Model_Resource_Product_Collection */
		$products = Mage::getResourceModel('catalog/product_collection');
		$products->addAttributeToFilter('entity_id', array('in' => $productIds));
		$reservationProductIds = array();
		foreach($products as $product){
			if(Mage::helper('pprwarehouse')->isReservationProduct($product)){
				$reservationProductIds[] = $product->getId();
			}
		}
		foreach($reservationProductIds as $productId){
			unset($items[$productId]);
		}
		if( ! $items){
			return array();
		}
		return parent::registerProductsSale($items);
	}

}
