<?php
/**
 *
 * @author Enrique Piatti
 */
class ITwebexperts_PPRWarehouse_Model_Observer
{
	/**
	 * total_qty_ordered is wrong when Warehouse module splits the order by Warehouse (this is a bug in Warehouse)
	 * @param $event
	 */
	public function salesOrderSaveBefore($event)
	{
		/** @var Mage_Sales_Model_Order $order */
		$order = $event->getOrder();
		$realTotalQtyOrdered = 0; // $order->getTotalQtyOrdered();
		foreach($order->getAllVisibleItems() as $visibleItem){
			/** @var $visibleItem Mage_Sales_Model_Order_Item */
			$realTotalQtyOrdered += $visibleItem->getQtyOrdered();
		}
		$order->setTotalQtyOrdered($realTotalQtyOrdered);
	}
}
