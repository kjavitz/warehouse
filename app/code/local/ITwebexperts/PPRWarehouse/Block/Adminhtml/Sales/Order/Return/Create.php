<?php
/**
 *
 * @author Enrique Piatti
 */
class ITwebexperts_PPRWarehouse_Block_Adminhtml_Sales_Order_Return_Create extends Mage_Adminhtml_Block_Widget_Form_Container
{
	public function __construct()
	{
		// $this->_blockGroup . '/' . $this->_controller . '_' . $this->_mode . '_form'
		$this->_objectId = 'order_id';
		$this->_blockGroup = 'pprwarehouse';
		$this->_controller = 'adminhtml_sales_order_return';
		$this->_mode = 'create';

		parent::__construct();

		//$this->_updateButton('save', 'label', Mage::helper('sales')->__('Submit Shipment'));
		$this->_removeButton('save');
		$this->_removeButton('delete');
	}

	/**
	 *
	 * @return Mage_Sales_Model_Order
	 */
	public function getOrder()
	{
		return Mage::registry('current_order');
	}

	public function getHeaderText()
	{
		$header = Mage::helper('sales')->__('New Return for Order #%s', $this->getOrder()->getRealOrderId());
		return $header;
	}

	public function getBackUrl()
	{
		return $this->getUrl('*/sales_order/view', array('order_id'=>$this->getOrder()->getId()));
	}
}

