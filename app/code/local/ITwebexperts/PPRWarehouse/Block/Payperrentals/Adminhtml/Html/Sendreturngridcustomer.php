<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Block_Payperrentals_Adminhtml_Html_Sendreturngridcustomer
	extends ITwebexperts_Payperrentals_Block_Adminhtml_Html_Sendreturngridcustomer
{

	protected function _prepareColumns()
	{
		$this->addColumnAfter('warehouse',
			array(
				'header'    => Mage::helper('pprwarehouse')->__('Warehouse'),
				'align'     =>'left',
				'index'     => 'stock_id',
				'renderer'  => 'pprwarehouse/adminhtml_grid_column_renderer_warehouse',
				'options'	=> $this->_getStockOptions(),
				'type'  	=> 'options',
				'filter_index' => 'main_table.stock_id',
				// 'filter_condition_callback' => array($this, '_filterCategoriesCondition'),
				'width'		=> '100px'
			)
			,'sn');
		return parent::_prepareColumns();
	}

	protected function _getStockOptions()
	{
		/* @var $helper Innoexts_Warehouse_Helper_Data */
		$helper = Mage::helper('warehouse');
		$stockOptions = array();
		foreach($helper->getStockIds() as $stockId){
			$stockOptions[$stockId] = $helper->getWarehouseTitleByStockId($stockId);
		}
		return $stockOptions;
	}

}
