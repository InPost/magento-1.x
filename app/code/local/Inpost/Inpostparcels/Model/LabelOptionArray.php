<?php
///
// Inpost_Inpostparcels_Model_LabelOptionArray
//
// @brief Return the label information for the admin page
//
class Inpost_Inpostparcels_Model_LabelOptionArray
{
	///
	// toOptionArray
	//
	// @brief The function is required to return data for an admin select
	//
	public function toOptionArray()
	{
		return array(
			array('value' => 'Pdf',
				'label' => Mage::helper('inpostparcels')->__('Pdf')),
			array('value' => 'Epl2',
				'label' => Mage::helper('inpostparcels')->__('Epl2'))
		);

	}
}
