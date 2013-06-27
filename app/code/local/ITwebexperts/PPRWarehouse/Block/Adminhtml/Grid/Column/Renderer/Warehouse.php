<?php
/**
 *
 * @author Enrique Piatti
 */
class ITwebexperts_PPRWarehouse_Block_Adminhtml_Grid_Column_Renderer_Warehouse
	extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
	public function render(Varien_Object $row)
	{
		$options = $this->getColumn()->getOptions();
		$stockId = $row[$this->getColumn()->getIndex()];
		return isset($options[$stockId]) ? $options[$stockId] : "Unknown (Stock ID = $stockId";
		// return Mage::helper('warehouse')->getWarehouseTitleByStockId($stockId);
	}
}
