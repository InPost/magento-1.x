<?php
class Inpost_Easypack24_Block_Easypack24 extends Mage_Checkout_Block_Onepage_Shipping_Method_Available
{
	public function __construct(){
		$this->setTemplate('easypack24/easypack24.phtml');
	}
}