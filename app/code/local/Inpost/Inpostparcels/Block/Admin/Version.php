<?php



class Inpost_Inpostparcels_Block_Admin_Version extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return (string) Mage::helper('inpostparcels/data')->getActualVersion(). "  <small><a target=\"_blank\" href=\"http://www.magentocommerce.com/magento-connect/Inpostparcels.html\">check for updates</a></small>";
    }
}