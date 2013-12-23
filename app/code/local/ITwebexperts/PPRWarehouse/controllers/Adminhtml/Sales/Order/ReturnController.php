<?php
/**
 *
 * @author Enrique Piatti
 */
class ITwebexperts_PPRWarehouse_Adminhtml_Sales_Order_ReturnController extends Mage_Adminhtml_Controller_Action
{
	/**
	 * Return create page
	 */
	public function newAction()
	{
		$orderId = $this->getRequest()->getParam('order_id');
		$order = Mage::getModel('sales/order')->load($orderId);
		Mage::register('current_order', $order);

		$this->_title($this->__('New Return'));

		$this->loadLayout()
			->_setActiveMenu('sales/order')
			->renderLayout();
	}

	public function saveAction()
	{
		$orderId = $this->getRequest()->getParam('order_id');
		$sendItems = $this->getRequest()->getParam('send_items');
		if( ! $sendItems){
			$this->_getSession()->addError('Select at least one item to return');
			$this->_redirect('*/*/new', array('_current' => true));
			return;
		}
		/* @var $sendReturns ITwebexperts_Payperrentals_Model_Mysql4_Sendreturn_Collection */
		$sendReturns = Mage::getResourceModel('payperrentals/sendreturn_collection');
		$sendReturns->addFieldToFilter('id', array('in' => $sendItems));
		$returnTime = date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time()));
//		foreach($sendItems as $sendItemId){
//			Mage::getResourceSingleton('payperrentals/sendreturn')
//				->updateReturndateById($sendItemId, $returnTime);
//		}
		foreach($sendReturns as $sendReturn){
			$sendReturn->setReturnDate($returnTime);
			$serialNumbers = $sendReturn->getSn() ? explode(',', $sendReturn->getSn()) : array();
			foreach($serialNumbers as $serial){
				Mage::getResourceSingleton('payperrentals/serialnumbers')
					->updateStatusBySerial($serial, 'A');
			}
			$sendReturn->save();
		}
		$this->_getSession()->addSuccess('Returns saved successfully');
		$this->_redirect('*/sales_order/view', array('order_id'=>$orderId));
	}
}
