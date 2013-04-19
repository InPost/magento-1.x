<?php
class Inpost_Inpostparcels_Block_Inpostparcels extends Mage_Checkout_Block_Onepage_Shipping_Method_Available
{
	public function __construct(){
		$this->setTemplate('inpostparcels/inpostparcels.phtml');
	}
}