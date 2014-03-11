<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_CatalogInventory_Stock_Item extends Innoexts_Warehouse_Model_Cataloginventory_Stock_Item
{

    public function getStockId(){
		$product = $this->getProduct();
		if($product && $product->getTypeId() == ITwebexperts_Payperrentals_Helper_Data::PRODUCT_TYPE)
		{
				$options = $product->getCustomOptions();
				if(isset($options['stock_id']))
				{
                    return $options['stock_id'];
                }
        }
        return parent::getStockId();
    }

	protected function _beforeSave()
	{
		return parent::_beforeSave();
	}


}
