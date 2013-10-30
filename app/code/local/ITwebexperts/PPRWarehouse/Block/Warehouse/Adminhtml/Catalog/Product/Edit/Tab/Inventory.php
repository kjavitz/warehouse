<?php
/**
 *
 * @author Enrique Piatti
 */ 
class ITwebexperts_PPRWarehouse_Block_Warehouse_Adminhtml_Catalog_Product_Edit_Tab_Inventory
	extends Innoexts_Warehouse_Block_Adminhtml_Catalog_Product_Edit_Tab_Inventory
{
	protected function _prepareForm()
	{
		parent::_prepareForm();
		$product = $this->getProduct();
		if(Mage::helper('pprwarehouse')->isReservationProduct($product))
		{
			$helper = Mage::helper('pprwarehouse');
			$form = $this->getForm();
			$fieldset   = $form->addFieldset('inventory_serial_numbers', array('legend' => $helper->__('Serial Numbers'), ));
			$useSerialsAttr = Mage::getModel('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'payperrentals_use_serials');
            $allowOverbooking = Mage::getModel('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'allow_overbooking');
			$serialsAttr = Mage::getModel('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'res_serialnumbers');
			// $attributes = $product->getAttributes();		// this could be even a bit faster because we have already cached the attributes list
			$attributes[] = $useSerialsAttr;
			$attributes[] = $serialsAttr;
            $attributes[] = $allowOverbooking;
			foreach($attributes as $attribute){
				$attribute->setIsVisible(true);
			}
			$this->_setFieldset($attributes, $fieldset);

			$serialNumbers = $form->getElement('res_serialnumbers');
			$serialNumbers->setRenderer(
				Mage::getSingleton('core/layout')->createBlock('payperrentals/adminhtml_catalog_product_edit_tab_payperrentals_serialnumbers')
			);

			$values = $product->getData();
			// Set default attribute values for new product
			if ($product->getId()) {
				foreach ($attributes as $attribute) {
					if (!isset($values[$attribute->getAttributeCode()])) {
						$values[$attribute->getAttributeCode()] = $attribute->getDefaultValue();
					}
				}
			}
			$form->addValues($values);
			// Mage::dispatchEvent('adminhtml_catalog_product_edit_prepare_form', array('form' => $form));
		}
		return $this;
	}


}
