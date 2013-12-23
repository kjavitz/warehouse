<?php
/**
 *
 * @author Enrique Piatti
 */
class ITwebexperts_PPRWarehouse_Block_Adminhtml_Grid_Column_Renderer_ReturnState
	extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
	public function render(Varien_Object $row)
	{
		$this->setOrder($row);

//		 for this we need to check the items to see if there's some reservation item, or include that info in the query
//		if( ! $this->_isAllowedToReturn()){
//			return 'Not Return needed';
//		}

		$totalQtyReturned = $this->_getValue($row);
		$totalQtySent = $row->getTotalQtySent();	// this is not the same that getTotalQtyShipped when there are mixed items (simple products with reservation products)
		$buttonHtml = $this->_getReturnButtonHtml();
		if( ! $totalQtyReturned){
			return 'Not Returned<br/>'.$buttonHtml;
		}
		elseif($totalQtyReturned < $totalQtySent){
			return 'Partially Returned<br/>'.$buttonHtml;
		}
		else {
			return '<img src="'.$this->getSkinUrl('images/truck.png').'" />';
		}
	}


	protected function _getReturnButtonHtml()
	{
		$buttonHtml = '';
		if ($this->_isAllowedToReturn()) {
			$buttonHtml = '<a><button title="Return" type="button" class="scalable go" onclick="setLocation(\''.$this->_getReturnUrl().'\'); return false;" style="">
			<span><span><span>Return</span></span></span></button></a>';
		}
		return $buttonHtml;
	}

	protected function _isAllowedToReturn()
	{
		$order = $this->_getOrder();
		$totalQtyReturned = $order->getTotalQtyReturned();
		$totalQtySent = $order->getTotalQtySent();
		return $totalQtySent && $totalQtySent != $totalQtyReturned;
	}

	/**
	 * @return Mage_Sales_Model_Order
	 */
	protected function _getOrder()
	{
		return $this->getData('order');
	}

	protected function _getReturnUrl()
	{
		return $this->getUrl('*/sales_order_return/new', array('order_id' => $this->_getOrder()->getId()));
	}
}

