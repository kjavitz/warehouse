<?php
/**
 *
 * @author Enrique Piatti
 */
class ITwebexperts_PPRWarehouse_Block_Adminhtml_Grid_Column_Renderer_ShippingState
	extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
	public function render(Varien_Object $row)
	{
		$this->setOrder($row);
		$totalQtyShipped = $this->_getValue($row);	// $row->getTotalQtyShipped();
		$totalQtyOrdered = $row->getTotalQtyOrdered();
		$shipButtonHtml = $this->_getShipButtonHtml();
		if( ! $totalQtyShipped){
			return 'Not Shipped<br/>'.$shipButtonHtml;
		}
		elseif($totalQtyShipped < $totalQtyOrdered){
			return 'Partially Shipped<br/>'.$shipButtonHtml;
		}
		else {
			return '<img src="'.$this->getSkinUrl('images/truck.png').'" />';
		}
	}


	protected function _getShipButtonHtml()
	{
		$buttonHtml = '';
		$order = $this->_getOrder();
		if ($this->_isAllowedToShip() && $order->canShip()
			&& !$order->getForcedDoShipmentWithInvoice()) {
			$buttonHtml = '<a><button title="Ship" type="button" class="scalable go" onclick="setLocation(\''.$this->_getShipUrl().'\'); return false;" style="">
			<span><span><span>Ship</span></span></span></button></a>';
		}
		return $buttonHtml;
	}

	protected function _isAllowedToShip()
	{
		return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/ship');
	}

	/**
	 * @return Mage_Sales_Model_Order
	 */
	protected function _getOrder()
	{
		return $this->getData('order');
	}

	protected function _getShipUrl()
	{
		return $this->getUrl('*/sales_order_shipment/start', array('order_id' => $this->_getOrder()->getId()));
	}
}

