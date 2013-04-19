<?php
class Inpost_Inpostparcels_Model_Sales_Order extends Mage_Sales_Model_Order{
	public function getShippingDescription(){
		$desc = parent::getShippingDescription();
    	$inpostparcelsObject = $this->getInpostparcelsObject();
        //Mage::log(var_export($inpostparcelsObject, 1) . '------', null, 'shipping_description.log');


        if($inpostparcelsObject && $inpostparcelsObject->getParcelTargetMachineId() != ''){
            if($this->getShippingMethod() == 'inpostparcels_inpostparcels'){
                $desc = Mage::getStoreConfig('carriers/inpostparcels/name').' /  Target Box Machine: '.$inpostparcelsObject->getParcelTargetMachineId().'  /  ';
            }else{
                $desc .= ' /  Target Box Machine: '.$inpostparcelsObject->getParcelTargetMachineId().'  /  ';
            }
		}
		return $desc;
	}

    public function getShippingAddress(){
        $inpostparcelsObject = $this->getInpostparcelsObject();
        //Mage::log(var_export($inpostparcelsObject, 1) . '------', null, 'shipping_address.log');

        if(is_object($inpostparcelsObject)){
            $parcelTargetMachineDetail = json_decode($inpostparcelsObject->getParcelTargetMachineDetail());
            if($inpostparcelsObject && !empty($parcelTargetMachineDetail)){
                $desc = parent::getShippingAddress();
                $desc->setCity(@$parcelTargetMachineDetail->address->city);
                $desc->setPostcode(@$parcelTargetMachineDetail->address->post_code);
                $desc->setStreet(@$parcelTargetMachineDetail->address->street);
            }else{
                $desc = parent::getShippingAddress();
            }
        }else{
            $desc = parent::getShippingAddress();
        }

        return $desc;
    }


}