<?php

class Inpost_Inpostparcels_Model_Observer extends Varien_Object
{
	public function saveShippingMethod($evt){

        if(Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod() != 'inpostparcels_inpostparcels'){
            return;
        }

        $request = $evt->getRequest();
		$quote = $evt->getQuote();
        $shippingAddress = $quote->getShippingAddress();

        $inpostparcels = $request->getParam('shipping_inpostparcels',false);
		$quote_id = $quote->getId();
		$data = array($quote_id => $inpostparcels);

        //Mage::log(var_export($data, 1) . '------', null, 'save_shipping_method_DATA.log');

        $parcelTargetMachineId = @$data[$quote_id]['parcel_target_machine_id'];
        $parcelTargetMachinesDetail = Mage::getSingleton('checkout/session')->getParcelTargetMachinesDetail();
        $parcelTargetAllMachinesDetail = Mage::getSingleton('checkout/session')->getParcelTargetAllMachinesDetail();
        $parcelTargetMachineDetail = array();

        if(isset($parcelTargetMachinesDetail[@$data[$quote_id]['parcel_target_machine_id']])){
            $parcelTargetMachineDetail = $parcelTargetMachinesDetail[$parcelTargetMachineId];
        }else{
            $parcelTargetMachineDetail = $parcelTargetAllMachinesDetail[$parcelTargetMachineId];
        }

        $data[$quote_id]['parcel_detail'] = array(
            'description' => '',
            'receiver' => array(
                'email' => $shippingAddress->getEmail(),
                'phone' => @$data[$quote_id]['receiver_phone']
            ),
            'size' => Mage::getSingleton('checkout/session')->getParcelSize(),
            'tmp_id' => Mage::helper('inpostparcels/data')->generate(4, 15),
            'target_machine' => $parcelTargetMachineId
        );

        $data[$quote_id]['parcel_target_machine'] = $parcelTargetMachineId;
        $data[$quote_id]['parcel_target_machine_detail'] = $parcelTargetMachineDetail;

        //Mage::log(var_export($data, 1) . '------', null, 'save_shipping_method.log');
        if($inpostparcels){
            Mage::getSingleton('checkout/session')->setInpostparcels($data);
        }
	}

	public function saveOrderAfter($evt){
        if(Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod() != 'inpostparcels_inpostparcels'){
            return;
        }

        $order = $evt->getOrder();
		$quote = $evt->getQuote();
		$quote_id = $quote->getId();

        $inpostparcels = Mage::getSingleton('checkout/session')->getInpostparcels();
        if(isset($inpostparcels[$quote_id])){
			$data = $inpostparcels[$quote_id];
			$data['order_id'] = $order->getId();
            $data['parcel_detail']['description'] = 'Order number:'.$order->getIncrementId();
            switch (Mage::helper('inpostparcels/data')->getCurrentApi()){
                case 'PL':
                    $data['parcel_detail']['cod_amount'] = (Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getCode() == 'checkmo')? sprintf("%.2f" ,$order->getGrandTotal()) : '';
                    break;
            }

            $inpostparcelsModel = Mage::getModel('inpostparcels/inpostparcels');
            $inpostparcelsModel->setOrderId($data['order_id']);
            $inpostparcelsModel->setParcelDetail(json_encode($data['parcel_detail']));
            $inpostparcelsModel->setParcelTargetMachineId($data['parcel_target_machine_id']);
            $inpostparcelsModel->setParcelTargetMachineDetail(json_encode($data['parcel_target_machine_detail']));
            $inpostparcelsModel->setApiSource(Mage::helper('inpostparcels/data')->getCurrentApi());
            $inpostparcelsModel->save();
            //Mage::log(var_export($data, 1) . '------', null, 'save_order_after.log');
		}
	}

    public function loadOrderAfter($evt){
        $order = $evt->getOrder();
        if($order->getId()){
            $order_id = $order->getId();
            $inpostparcelsCollection = Mage::getModel('inpostparcels/inpostparcels')->getCollection();
            $inpostparcelsCollection->addFieldToFilter('order_id',$order_id);
            $inpostparcels = $inpostparcelsCollection->getFirstItem();
            $order->setInpostparcelsObject($inpostparcels);
        }
    }

    public function loadQuoteAfter($evt)
    {
        $quote = $evt->getQuote();
        if($quote->getId()){
            $quote_id = $quote->getId();
            $inpostparcels = Mage::getSingleton('checkout/session')->getInpostparcels();
            if(isset($inpostparcels[$quote_id])){
                $data = $inpostparcels[$quote_id];
                $quote->setInpostparcelsData($data);
            }
        }
    }

    public function salesOrderShipmentSaveAfter($evt){
        /*
        $shipment = $evt->getShipment();
        $order = $shipment->getOrder();
        if($order->getId()){
            $order_id = $order->getId();
            $inpostparcelsCollection = Mage::getModel('inpostparcels/inpostparcels')->getCollection();
            $inpostparcelsCollection->addFieldToFilter('order_id',$order_id);
            $inpostparcels = $inpostparcelsCollection->getFirstItem();
            $parcelDetail = json_decode($inpostparcels->getParcelDetail());
            //$parcelTargetMachineDetail = json_decode($inpostparcels->getParcelTargetMachineDetail());
            //Mage::log(var_export($inpostparcels, 1) . '------', null, 'inpostparcels.log');
            if(!$inpostparcels->getParcelTargetMachineId()){
                return null;
            }
        }else{
            return null;
        }

        // create Inpost parcel
        $params = array(
            'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'parcels',
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
                'target_machine' => $inpostparcels->getParcelTargetMachineId()
            )
        );
        $parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels($params);
        //Mage::log(var_export($params, 1) . '------', null, 'parcel_params.log');
        //Mage::log(var_export($parcelApi, 1) . '------', null, 'parcel_create.log');

        if(@$parcelApi['info']['redirect_url'] != ''){

            // get machine
            $parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels(
                array(
                    'url' => $parcelApi['info']['redirect_url'],
                    'ds' => '&',
                    'methodType' => 'GET',
                    'params' => array(
                    )
                )
            );

            if(!isset($parcelApi['result']->id)){
                $this->_getSession()->addError($this->__('Cannot create parcel.'));
                Mage::throwException('Cannot create parcel');
                Mage::log(var_export($parcelApi, 1) . '------', null, 'error_observer_sales_order_shipment_save_after_cannot_create_parcel.log');
            }

            // update parcel status on 'Created'
            $inpostparcels->setParcelStatus('Created');
            $inpostparcels->setParcelId($parcelApi['result']->id);
            $inpostparcels->save();
        }else{
            $this->_getSession()->addError($this->__('Cannot create parcel.'));
            Mage::throwException('Cannot create parcel');
            Mage::log(var_export($parcelApi, 1) . '------', null, 'error_observer_sales_order_shipment_save_after_cannot_create_parcel.log');
        }

        // update data order
        //Mage::log(var_export($inpostparcels, 1) . '------', null, 'observer_sales_order_shipment_save_after.log');
        */
    }

}
