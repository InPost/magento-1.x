<?php
class Inpost_Easypack24_Model_Sales_Order extends Mage_Sales_Model_Order{
	public function getShippingDescription(){
		$desc = parent::getShippingDescription();
    	$easypack24Object = $this->getEasypack24Object();
        //Mage::log(var_export($easypack24Object, 1) . '------', null, 'shipping_description.log');


        if($easypack24Object && $easypack24Object->getParcelTargetMachineId() != ''){
            if($this->getShippingMethod() == 'easypack24_easypack24'){
                $desc = Mage::getStoreConfig('carriers/easypack24/name').' /  Target Box Machine: '.$easypack24Object->getParcelTargetMachineId().'  /  ';
            }else{
                $desc .= ' /  Target Box Machine: '.$easypack24Object->getParcelTargetMachineId().'  /  ';
            }
		}
		return $desc;
	}

    public function getShippingAddress(){
        $easypack24Object = $this->getEasypack24Object();
        //Mage::log(var_export($easypack24Object, 1) . '------', null, 'shipping_address.log');

        if(is_object($easypack24Object)){
            $parcelTargetMachineDetail = json_decode($easypack24Object->getParcelTargetMachineDetail());
            if($easypack24Object && !empty($parcelTargetMachineDetail)){
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