<?php

class Inpost_Inpostparcels_Block_Adminhtml_Inpostparcels_Renderer_Link extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
	public function ____render(Varien_Object $row)
	{
	}
	
	public function render(Varien_Object $row)
	{
        if(!preg_match('/pending/i', $row->getStatus()))
        {
            if($row->getParcelId() == ''){
                $link_text = Mage::helper('inpostparcels')->__('Create parcel');
            }else{
                $link_text = Mage::helper('inpostparcels')->__('Edit parcel');
            }

            $url = $this->getUrl('*/*/edit/', array(
                    '_current'=>true,
                    'id' => $row->getId(),
                    Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED => $this->helper('core/url')->getEncodedUrl())
            );

            return "<a href='".$url."'>".$link_text."</a>";
        }
    }

}