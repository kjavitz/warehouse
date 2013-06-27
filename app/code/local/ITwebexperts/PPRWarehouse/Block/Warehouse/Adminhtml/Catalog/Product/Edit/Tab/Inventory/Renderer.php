<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Block_Warehouse_Adminhtml_Catalog_Product_Edit_Tab_Inventory_Renderer
	extends Innoexts_Warehouse_Block_Adminhtml_Catalog_Product_Edit_Tab_Inventory_Renderer
{
	public function __construct()
	{
		parent::__construct();
		$product = $this->getProduct();
		if(Mage::helper('pprwarehouse')->isReservationProduct($product))
		{
			$this->setTemplate('pprwarehouse/catalog/product/edit/tab/inventory/renderer.phtml');
		}
	}


}
