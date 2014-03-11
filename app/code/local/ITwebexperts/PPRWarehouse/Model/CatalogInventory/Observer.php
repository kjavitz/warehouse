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


}
