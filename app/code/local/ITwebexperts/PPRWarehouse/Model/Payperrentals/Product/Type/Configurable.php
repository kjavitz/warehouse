<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Model_Payperrentals_Product_Type_Configurable
	extends ITwebexperts_Payperrentals_Model_Product_Type_Configurable
{
	public function isAvailable($Product = null, $qty = 1, $attributes = null) {
		if(!$qty) {
			$qty = 1;
		}
		if(is_null($Product)) {
			$Product = ($this->_product) ? $this->_product : $this->getProduct();
		}

        return ITwebexperts_PPRWarehouse_Helper_Payperrentals_Data::isAvailableWithQuote($Product, $qty, false, $attributes);
	}

}
