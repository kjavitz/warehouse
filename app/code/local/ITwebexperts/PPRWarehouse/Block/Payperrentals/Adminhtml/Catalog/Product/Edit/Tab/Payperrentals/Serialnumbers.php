<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Block_Payperrentals_Adminhtml_Catalog_Product_Edit_Tab_Payperrentals_Serialnumbers
	extends ITwebexperts_Payperrentals_Block_Adminhtml_Catalog_Product_Edit_Tab_Payperrentals_Serialnumbers
{
	public function __construct()
	{
		parent::__construct();
		$this->setTemplate('pprwarehouse/catalog/product/edit/tab/inventory/serialnumbers.phtml');
	}

	public function getStockInventories()
	{
		$stockIds = $this->getWarehouseHelper()->getCatalogInventoryHelper()->getStockIds();
		if (count($stockIds)) {
			return $stockIds;
		}
		return array();
	}

	/**
	 * Get warehouse helper
	 *
	 * @return Innoexts_Warehouse_Helper_Data
	 */
	public function getWarehouseHelper()
	{
		return Mage::helper('warehouse');
	}

	protected function _prepareLayout()
	{
		parent::_prepareLayout();
		$this->getChild('add_button')->setOnclick('addNewSerialNumber(this)');
		return $this;
	}


}


