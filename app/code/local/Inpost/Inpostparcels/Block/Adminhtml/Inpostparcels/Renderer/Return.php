<?php

class Inpost_Inpostparcels_Block_Adminhtml_Inpostparcels_Renderer_Return extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
	public function ____render(Varien_Object $row)
	{
	}
	
	public function render(Varien_Object $row)
	{
		$value = $row->getData($this->getColumn()->getIndex());

		if($value != '')
		{
			$url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) . "install/default/default/images/success_msg_icon.gif";

			return "<img src='" . $url . "' title='RR $value' alt='RR $value' />";
		}
		else
		{
			return "<span> </span>";
		}
	}

}
