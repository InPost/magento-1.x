<?php

class Inpost_Easypack24_Model_Observer extends Varien_Object
{
	public function saveShippingMethod($evt){

        if(Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod() != 'easypack24_easypack24'){
            return;
        }

        $request = $evt->getRequest();
		$quote = $evt->getQuote();
        $shippingAddress = $quote->getShippingAddress();

        $easypack24 = $request->getParam('shipping_easypack24',false);
		$quote_id = $quote->getId();
		$data = array($quote_id => $easypack24);

        //Mage::log(var_export($data, 1) . '------', null, 'save_shipping_method_DATA.log');

        $parcelTargetMachineId = @$data[$quote_id]['parcel_target_machine_id'];
        $parcelTargetMachinesDetail = Mage::getSingleton('checkout/session')->getParcelTargetMachinesDetail();
        $parcelTargetAllMachinesDetail = Mage::getSingleton('checkout/session')->getParcelTargetAllMachinesDetail();
        $parcelTargetMachineDetail = array();

        if(isset($parcelTargetMachinesDetail[@$data[$quote_id]['parcel_target_machine_id']])){
            $parcelTargetMachineDetail = $parcelTargetMachinesDetail[$parcelTargetMachineId];
        }else{
            $parcelTargetMachineDetail = $parcelTargetAllMachinesDetail[$parcelTargetMachineId];

            /*
            $machine = Mage::helper('easypack24/data')->connectEasypack24(
                array(
                    'url' => Mage::getStoreConfig('carriers/easypack24/api_url').'machines/'.$parcelTargetMachineId,
                    'methodType' => 'GET',
                    'params' => array(
                        'town' => $request->getParam('box_machine_town',false)
                    )
                )
            );
            if(is_array(@$machine['result'])){
                $machine = $machine['result'];
                $parcelTargetMachineDetail = array(
                    'id' => $parcelTargetMachineId,
                    'address' => array(
                        'building_number' => @$machine->address->building_number,
                        'flat_number' => @$machine->address->flat_number,
                        'post_code' => @$machine->address->post_code,
                        'province' => @$machine->address->province,
                        'street' => @$machine->address->street,
                        'city' => @$machine->address->city
                    )
                );
                $parcelTargetMachineDetail = $parcelTargetMachineDetail[$parcelTargetMachineId];
            }
            */
        }

        $data[$quote_id]['parcel_detail'] = array(
            //'cod_amount' => Mage::getStoreConfig('carriers/easypack24/cod_amount'),
            'description' => '',
            //'insurance_amount' => Mage::getStoreConfig('carriers/easypack24/insurance_amount'),
            'receiver' => array(
                'email' => $shippingAddress->getEmail(),
                'phone' => @$data[$quote_id]['receiver_phone']
            ),
            'size' => Mage::getSingleton('checkout/session')->getParcelSize(),
            //'source_machine' => $data['parcel_source_machine'],
            'tmp_id' => Mage::helper('easypack24/data')->generate(4, 15),
        );
        $data[$quote_id]['parcel_target_machine'] = $parcelTargetMachineId;
        $data[$quote_id]['parcel_target_machine_detail'] = $parcelTargetMachineDetail;

        //Mage::log(var_export($data, 1) . '------', null, 'save_shipping_method.log');
        if($easypack24){
            Mage::getSingleton('checkout/session')->setEasypack24($data);
        }
	}

	public function saveOrderAfter($evt){
        if(Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod() != 'easypack24_easypack24'){
            return;
        }

        $order = $evt->getOrder();
		$quote = $evt->getQuote();
		$quote_id = $quote->getId();

		$easypack24 = Mage::getSingleton('checkout/session')->getEasypack24();
        if(isset($easypack24[$quote_id])){
			$data = $easypack24[$quote_id];
			$data['order_id'] = $order->getId();
            $easypack24Model = Mage::getModel('easypack24/easypack24');
            $easypack24Model->setOrderId($data['order_id']);
            $easypack24Model->setParcelDetail(json_encode($data['parcel_detail']));
            $easypack24Model->setParcelTargetMachineId($data['parcel_target_machine_id']);
            $easypack24Model->setParcelTargetMachineDetail(json_encode($data['parcel_target_machine_detail']));
            $easypack24Model->save();
            //Mage::log(var_export($data, 1) . '------', null, 'save_order_after.log');
		}
	}

    public function loadOrderAfter($evt){
        $order = $evt->getOrder();
        if($order->getId()){
            $order_id = $order->getId();
            $easypack24Collection = Mage::getModel('easypack24/easypack24')->getCollection();
            $easypack24Collection->addFieldToFilter('order_id',$order_id);
            $easypack24 = $easypack24Collection->getFirstItem();
            $order->setEasypack24Object($easypack24);
        }
    }

    public function loadQuoteAfter($evt)
    {
        $quote = $evt->getQuote();
        if($quote->getId()){
            $quote_id = $quote->getId();
            $easypack24 = Mage::getSingleton('checkout/session')->getEasyPack24();
            if(isset($easypack24[$quote_id])){
                $data = $easypack24[$quote_id];
                $quote->setEasypack24Data($data);
            }
        }
    }

    public function salesOrderShipmentSaveAfter($evt){
        $shipment = $evt->getShipment();
        $order = $shipment->getOrder();
        if($order->getId()){
            $order_id = $order->getId();
            $easypack24Collection = Mage::getModel('easypack24/easypack24')->getCollection();
            $easypack24Collection->addFieldToFilter('order_id',$order_id);
            $easypack24 = $easypack24Collection->getFirstItem();
            $parcelDetail = json_decode($easypack24->getParcelDetail());
            //$parcelTargetMachineDetail = json_decode($easypack24->getParcelTargetMachineDetail());
            //Mage::log(var_export($easypack24, 1) . '------', null, 'easypack24.log');
            if(!$easypack24->getParcelTargetMachineId()){
                return null;
            }
        }else{
            return null;
        }

        // create Inpost parcel
        $params = array(
            'url' => Mage::getStoreConfig('carriers/easypack24/api_url').'parcels',
            'methodType' => 'POST',
            'params' => array(
                //'cod_amount' => '',
                'description' => @$parcelDetail->description,
                //'insurance_amount' => '',
                'receiver' => array(
                    'phone' => @$parcelDetail->receiver->phone,
                    'email' => @$parcelDetail->receiver->email
                ),
                'size' => @$parcelDetail->size,
                //'source_machine' => '',
                'tmp_id' => @$parcelDetail->tmp_id,
                'target_machine' => $easypack24->getParcelTargetMachineId()
            )
        );
        $parcelApi = Mage::helper('easypack24/data')->connectEasypack24($params);
        //Mage::log(var_export($params, 1) . '------', null, 'parcel_params.log');
        //Mage::log(var_export($parcelApi, 1) . '------', null, 'parcel_create.log');

        if(@$parcelApi['info']['redirect_url'] != ''){
            $tmp = explode('/', @$parcelApi['info']['redirect_url']);
            $parcelId = $tmp[count($tmp)-1];
            // update parcel status on 'Created'
            $easypack24->setParcelStatus('Created');
            $easypack24->setParcelId($parcelId);
            $easypack24->save();
        }else{
            $this->_getSession()->addError($this->__('Cannot create parcel.'));
            Mage::throwException('Cannot create parcel');
            Mage::log(var_export($parcelApi, 1) . '------', null, 'error_observer_sales_order_shipment_save_after_cannot_create_parcel.log');
        }

        // update data order
        //Mage::log(var_export($easypack24, 1) . '------', null, 'observer_sales_order_shipment_save_after.log');
    }

}