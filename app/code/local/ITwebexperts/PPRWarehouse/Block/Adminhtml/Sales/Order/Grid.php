<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Sales_Order_Grid
{


	/**
	 * @param ITwebexperts_PPRWarehouse_Model_Resource_Sales_Order_Grid_Collection $collection
	 */
	public function setCollection($collection)
	{
		$collection->getSelect()
			->joinLeft(
				array('sfs' => $collection->getTable('sales/shipment')),
				'main_table.entity_id = sfs.order_id',
				array()
		);
		$collection->getSelect()
			->joinLeft(
				array('send' => $collection->getTable('payperrentals/sendreturn')),
				"sfo.increment_id = send.order_id",
				array()
		);
		$collection->getSelect()
			->joinLeft(
				array('return' => $collection->getTable('payperrentals/sendreturn')),
				"sfo.increment_id = return.order_id AND return.return_date != '0000-00-00 00:00:00'",
				array()
		);

		$shippingStateExpr = new Zend_Db_Expr(<<<SQL
SUM(sfs.total_qty)*count(DISTINCT sfs.entity_id)/count(*)
SQL
		);
		$reservedItemsSentExpr = new Zend_Db_Expr(<<<SQL
SUM(send.qty)*count(DISTINCT send.id)/count(*)
SQL
		);
		$returnStateExpr = new Zend_Db_Expr(<<<SQL
SUM(return.qty)*count(DISTINCT return.id)/count(*)
SQL
		);
		$collection->getSelect()->columns(
			array(
				'total_qty_shipped' => $shippingStateExpr,
				'total_qty_ordered' => 'sfo.total_qty_ordered',
				'is_virtual' => 'sfo.is_virtual',
				'total_qty_returned' => $returnStateExpr,
				'total_qty_sent' => $reservedItemsSentExpr,
			)
		);

		return parent::setCollection( $collection );
	}


	protected function _prepareColumns()
	{
		$this->addColumnAfter('shipping_state', array(
			'header' => Mage::helper('pprwarehouse')->__('Shipping'),
			'index' => 'total_qty_shipped',
			'renderer'  => 'pprwarehouse/adminhtml_grid_column_renderer_shippingState',
			'width' => '120px',
			'type'  => 'options',
			'options' => $this->_getShippingStates(),
			'sortable'  => false,
			'filter'    => false,
		),'status');

		$this->addColumnAfter('return_state', array(
			'header' => Mage::helper('pprwarehouse')->__('Return'),
			'index' => 'total_qty_returned',
			'renderer'  => 'pprwarehouse/adminhtml_grid_column_renderer_returnState',
			'width' => '120px',
			'type'  => 'options',
			'options' => $this->_getReturnStates(),
			'sortable'  => false,
			'filter'    => false,
		),'shipping_state');

		return parent::_prepareColumns();
	}

	protected function _getShippingStates()
	{
		return array(
			'0' => 'Not Shipped',
			'1' => 'Partially Shipped',
			'2' => 'Shipped'
		);
	}

	protected function _getReturnStates()
	{
		return array(
			'0' => 'Not Returned',
			'1' => 'Partially Returned',
			'2' => 'Returned'
		);
	}


}
