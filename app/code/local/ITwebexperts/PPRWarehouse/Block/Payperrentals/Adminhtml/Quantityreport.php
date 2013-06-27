<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Block_Payperrentals_Adminhtml_Quantityreport
	extends ITwebexperts_Payperrentals_Block_Adminhtml_Quantityreport
{


	/**
	 * @return array
	 */
	public function getSotckIdFilter()
	{
		$warehousesParam = $this->getRequest()->getParam('warehouses');
		if($warehousesParam){
			return explode(',', urldecode($warehousesParam));
		}
		return array();
	}
}
