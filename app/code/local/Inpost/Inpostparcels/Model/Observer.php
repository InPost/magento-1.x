<?php

class Inpost_Inpostparcels_Model_Observer extends Varien_Object
{
	///
	// saveShippingMethod
	//
	// @param The details of the event.
	//
	public function saveShippingMethod($evt)
	{
		if(Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod() != 'inpostparcels_inpostparcels')
		{
			return;
		}

		$request = $evt->getRequest();
		$quote   = $evt->getQuote();
		$shippingAddress = $quote->getShippingAddress();

		$inpostparcels = $request->getParam('shipping_inpostparcels',
			false);

		$quote_id = $quote->getId();
		$data = array($quote_id => $inpostparcels);

		//Mage::log(var_export($data, 1) . '------', null, 'save_shipping_method_DATA.log');

		$parcelTargetMachineId = @$data[$quote_id]['parcel_target_machine_id'];

		$parcelTargetMachinesDetail = Mage::getSingleton('checkout/session')->getParcelTargetMachinesDetail();
		$parcelTargetAllMachinesDetail = Mage::getSingleton('checkout/session')->getParcelTargetAllMachinesDetail();

		// Because we are tring to support multiple checkout types if
		// the parcel details are null try and get the details from
		// the central system.
		if($parcelTargetMachinesDetail == null &&
			$parcelTargetAllMachinesDetail == null &&
			strlen(@$data[$quote_id]['parcel_target_machine_id']) > 1)
		{
			// We must do a quick REST API call to get the details
			// of the machine.
			//Mage::log('parcel target machine detail is NULL.');
			$params = array(
				'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').
					'machines/' .
					@$data[$quote_id]['parcel_target_machine_id'],
				'methodType' => 'GET',
				'params' => array()
			);

			$ret = Mage::helper('inpostparcels')->connectInpostparcels($params);

			if($ret['info']['http_code'] == 200)
			{
				if(isset($ret['result']->address->flat_number))
				{
					$flat_number = $ret['result']->address->flat_number;
				}
				else
				{
					$flat_number = NULL;
				}
				// We have the machine details.
            			$parcelTargetMachinesDetail[$parcelTargetMachineId] = array(
				'id'      => @$data[$quote_id]['parcel_target_machine_id'],
				'address' => array(
					'building_number' => $ret['result']->address->building_number,
					'flat_number' => $flat_number,
					'postcode' => $ret['result']->address->post_code,
					'province' => $ret['result']->address->province,
					'street'   => $ret['result']->address->street,
					'city'     => $ret['result']->address->city,
					)
				);
			}
		}

		$parcelTargetMachineDetail = array();

		if(isset($parcelTargetMachinesDetail[@$data[$quote_id]['parcel_target_machine_id']]))
		{
			$parcelTargetMachineDetail = $parcelTargetMachinesDetail[$parcelTargetMachineId];
		}
		else
		{
			if(isset($parcelTargetAllMachinesDetail[$parcelTargetMachineId]))
			{
				$parcelTargetMachineDetail = $parcelTargetAllMachinesDetail[$parcelTargetMachineId];
			}
		}

		// We can be called between the user updating the shipping
		// method. I.e. when the user switches between options.
		if(!isset($parcelTargetMachineDetail) ||
			$parcelTargetMachineDetail == '')
		{
			// Don't try and save the details as we don't have any.
			return;
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
		if($inpostparcels)
		{
			Mage::getSingleton('checkout/session')->setInpostparcels($data);
		}
	}

	///
	// saveOrderAfter
	//
	// @param The event that has triggered the action.
	//
	// NB The following code uses a mix of
	// $order->getId()
	// and
	// $order->getIncrementId()
	//
	// It is very impostant that the calls are in the correct places.
	//
	public function saveOrderAfter($evt)
	{
		if(Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod() != 'inpostparcels_inpostparcels')
		{
			return;
		}

		$order    = $evt->getOrder();
		$quote    = $evt->getQuote();
		$quote_id = $quote->getId();

		$inpostparcels = Mage::getSingleton('checkout/session')->getInpostparcels();
		if(isset($inpostparcels[$quote_id]))
		{
			$data = $inpostparcels[$quote_id];
			$data['order_id'] = $order->getId();
			$data['parcel_detail']['description'] = 'Order number:'.$order->getIncrementId();
			switch (Mage::helper('inpostparcels/data')->getCurrentApi())
			{
				case 'PL':
				$data['parcel_detail']['cod_amount'] = (Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getCode() == 'checkmo')? sprintf("%.2f" ,$order->getGrandTotal()) : '';
				break;
			}
			// Check to see if the user's email address is set or
			// not. If not get it from the order details. They
			// are paying using Amazon Payments.
			if(!isset($data['parcel_detail']['receiver']['email']))
			{
				$data['parcel_detail']['receiver']['email'] = $order->getCustomerEmail();
			}
			// Check to see if the phone number enetered is 9
			// digits or if it is longer.
			$phone_len = strlen($data['parcel_detail']['receiver']['phone']);

			if($phone_len > 9)
			{
				$data['parcel_detail']['receiver']['phone'] = 
					substr($data['parcel_detail']['receiver']['phone'],
						-9);
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
		// PayPal change SN
		else
		{
			// We don't have the standard one-page checkout data
			// but we might have the review page data.
			if($quote->getInpostparcelsData())
			{
				$var = $quote->getInpostparcelsData();

				if(strlen($var['parcel_receiver_email']) == 0)
				{
					// If the checkout has been triggered
					// from Amazon Payments then the email
					// address will be EMPTY. Fill it now.
					$evt['parcel_receiver_email'] = $order->getCustomerEmail();
				}
				$phone_len = strlen($var['parcel_receiver_phone']);
				if($phone_len > 9)
				{
					$var['parcel_receiver_phone'] = 
						substr($var['parcel_receiver_phone'],
							-9);
				}

				//Mage::log('Order = ' . $order->getIncrementId() .
					//' quote = ' . $quote->getId());

				$data['parcel_detail'] = array(
					'description' => 'Order Number:' .
						$order->getIncrementId(),
						//$order->getId(),
					'receiver' => array(
						'email' => $var['parcel_receiver_email'],
						'phone' => $var['parcel_receiver_phone']
					),
					'size' => Mage::getSingleton('checkout/session')->getParcelSize(),
					'tmp_id' => Mage::helper('inpostparcels/data')->generate(4, 15),
					'target_machine' => $var['parcel_source_machine_id']
				);

				switch (Mage::helper('inpostparcels/data')->getCurrentApi())
				{
					case 'PL':
					$data['parcel_detail']['cod_amount'] = (Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getCode() == 'checkmo')? sprintf("%.2f" ,$order->getGrandTotal()) : '';
					break;
				}
				
				$inpostparcelsModel = Mage::getModel('inpostparcels/inpostparcels');

				// Build the Machine Details information
				$machine_details['id'] = $var['parcel_source_machine_id'];
				$machine_bits = explode(';', 
					$var['parcel_receiver_details']);

				$machine_details['address'] = array(
					'building_number' => $machine_bits[2],
					'flat_number'     => '',
					'post_code'       => '',
					'province'        => '',
					'street'          => $machine_bits[1],
					'city'            => $machine_bits[0],
				);

				//$inpostparcelsModel->setId($order->getId());
				$inpostparcelsModel->setOrderId($order->getId());
				$inpostparcelsModel->setParcelDetail(json_encode($data['parcel_detail']));
				$inpostparcelsModel->setParcelTargetMachineId($var['parcel_source_machine_id']);
				$inpostparcelsModel->setParcelTargetMachineDetail(json_encode($machine_details));
				$inpostparcelsModel->setApiSource(Mage::helper('inpostparcels/data')->getCurrentApi());
				$inpostparcelsModel->save();
			}
		}
		// PayPal change EN
	}

	///
	// loadOrderAfter
	//
	public function loadOrderAfter($evt)
	{
		$order = $evt->getOrder();
		if($order->getId())
		{
            $order_id = $order->getId();
            $inpostparcelsCollection = Mage::getModel('inpostparcels/inpostparcels')->getCollection();
            $inpostparcelsCollection->addFieldToFilter('order_id',$order_id);
            $inpostparcels = $inpostparcelsCollection->getFirstItem();
            $order->setInpostparcelsObject($inpostparcels);
		}
	}

	///
	// loadQuoteAfter
	//
	public function loadQuoteAfter($evt)
	{
		$quote = $evt->getQuote();
		if($quote->getId())
		{
			$quote_id = $quote->getId();
			$inpostparcels = Mage::getSingleton('checkout/session')->getInpostparcels();
			if(isset($inpostparcels[$quote_id]))
			{
				$data = $inpostparcels[$quote_id];
				$quote->setInpostparcelsData($data);
			}
		}
	}

	// PayPal change SN
	///
	// saveQuoteBefore
	//
	public function saveQuoteBefore($evt)
	{
		$quote = $evt->getQuote();

		$post = Mage::app()->getFrontController()->getRequest()->getPost();

		if(isset($post['inpost_hidden_machine']))
		{
			$shippingAddress = $quote->getShippingAddress();

			//$data[] = $quote->getId();
			$data['parcel_source_machine_id'] = $post['inpost_hidden_machine'];
			$data['parcel_receiver_phone']    = $post['inpost_hidden_mobile'];
			$data['parcel_receiver_details']  = $post['inpost_hidden_details'];
			if($shippingAddress != null)
			{
				$data['parcel_receiver_email'] = $shippingAddress->getEmail();
			}
			else
			{
				$data['parcel_receiver_email'] = '';
			}
			$quote->setInpostparcelsData($data);
		}
	}
	// PayPal change EN

	public function salesOrderShipmentSaveAfter($evt)
	{
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

	///
	// insertBlock
	//
	// @param The observer.
	// @brief Control how the One Page shipping form shows.
	//
	public function insertBlock($observer)
	{
		/// @var $_block Mage_Core_Block_Abstrat
		// Get block instance
		$_block = $observer->getBlock();

		// Get block type
		$_type  = $_block->getType();

		// Check block type
		if($_type == 'checkout/onepage_shipping_method_available')
		{
			// Clone block instance
			$_child = clone $_block;

			// Set another type for block
			$_child->setType('test/block');

			// Set child for block
			$_block->setChild('child', $_child);
		
			// Set our template
			$_block->setTemplate('inpostparcels/at.phtml');
		}
	}
}
