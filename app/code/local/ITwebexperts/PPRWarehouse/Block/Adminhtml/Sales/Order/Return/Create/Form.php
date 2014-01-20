<?php
/**
 *
 * @author Enrique Piatti
 */
class ITwebexperts_PPRWarehouse_Block_Adminhtml_Sales_Order_Return_Create_Form extends Mage_Adminhtml_Block_Sales_Order_Abstract
{

	/**
	 * Retrieve invoice order
	 *
	 * @return Mage_Sales_Model_Order
	 */
	public function getOrder()
	{
		return Mage::registry('current_order');
	}

	public function getSentItems()
	{
		if( ! $this->hasData('sent_items')){
			/* @var $sentItems ITwebexperts_Payperrentals_Model_Mysql4_Sendreturn_Collection */
			$sentItems = Mage::getResourceModel('payperrentals/sendreturn_collection');
			$sentItems->addFieldToFilter('order_id', $this->getOrder()->getId())
				->addFieldToFilter('return_date', '0000-00-00 00:00:00');
			$this->setData('sent_items', $sentItems);
		}
		return $this->getData('sent_items');
	}

	public function getProductFromSentItem($sentItem)
	{
		$product = Mage::getModel('catalog/product')->load($sentItem->getProductId());
		return $product;
	}

	public function getWarehouseSentItem($sentItem)
	{
		return Mage::helper('warehouse')->getWarehouseTitleByStockId($sentItem->getStockId());
	}


	protected function _prepareLayout()
	{
//        $infoBlock = $this->getLayout()->createBlock('adminhtml/sales_order_view_info')
//            ->setOrder($this->getShipment()->getOrder());
//        $this->setChild('order_info', $infoBlock);

		$this->setChild(
			'items',
			$this->getLayout()->createBlock('adminhtml/sales_order_shipment_create_items')
		);
//        $paymentInfoBlock = $this->getLayout()->createBlock('adminhtml/sales_order_payment')
//            ->setPayment($this->getShipment()->getOrder()->getPayment());
//        $this->setChild('payment_info', $paymentInfoBlock);

//        return parent::_prepareLayout();
	}

	public function getPaymentHtml()
	{
		return $this->getChildHtml('order_payment');
	}

	public function getItemsHtml()
	{
		return $this->getChildHtml('order_items');
	}

	public function getSaveUrl()
	{
		return $this->getUrl('*/*/save', array('order_id' => $this->getOrder()->getId()));
	}
}
