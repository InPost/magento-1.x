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
			return "<span class='config-header'>&nbsp;</span>";
		}
		else
		{
			return "<span>&nbsp;</span>";
		}
	}

}
