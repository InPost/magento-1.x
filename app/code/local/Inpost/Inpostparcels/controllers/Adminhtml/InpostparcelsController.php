<?php

class Inpost_Inpostparcels_Adminhtml_InpostparcelsController extends Mage_Adminhtml_Controller_Action
{
	///
	// _initAction
	//
	protected function _initAction()
	{
        $this->loadLayout()
            ->_setActiveMenu('sales/inpostparcels')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));

		return $this;
	}

	///
	// indexAction
	//
	public function indexAction()
	{
		$this->_initAction()->renderLayout();
	}

	///
	// massStickersAction
	//
	public function massStickersAction($return_label = false)
	{
		$parcelsIds = $this->getRequest()->getPost('parcels_ids', array());
		$countSticker    = 0;
		$countNonSticker = 0;
		$pdf             = null;

		$parcelsCode       = array();
		$parcelsReturnCode = array();
		$parcelsToPay      = array();

		foreach ($parcelsIds as $id)
		{
			$parcelCollection = Mage::getModel('inpostparcels/inpostparcels')->load($id);
			$orderCollection = Mage::getResourceModel('sales/order_grid_collection')
			->addFieldToFilter('entity_id', $parcelCollection->getOrderId())
			->getFirstItem();

//            if($orderCollection->getStatus() != 'processing'){
//                continue;
//            }

			if($parcelCollection->getParcelId() != '')
			{
				$parcelsCode[$id] = $parcelCollection->getParcelId();
				if($return_label == true)
				{
					$parcelsReturnCode[] = $parcelCollection->getReturnParcelId();
				}

				// Only pay for non-return parcels
				if($parcelCollection->getStickerCreationDate() == '' &&
				$return_label == false)
				{
					$parcelsToPay[$id] = $parcelCollection->getParcelId();
				}
			}
			else
			{
				continue;
			}
		}

		// We can print labels in either Pdf or Epl2 format.
		// Find out which the store s configured for and then send
		// the appropriate request.
		$format = Mage::getStoreConfig('carriers/inpostparcels/label_format');
		if(strcasecmp($format, 'pdf') == 0)
		{
			// PDF format selected
			$format = 'Pdf';
			$type   = 'normal';
		}
		else
		{
			// Epl2 format selected
			$format = 'Epl2';
			$type   = 'A6P';
		}

		if(empty($parcelsCode))
		{
			$this->_getSession()->addError($this->__('Parcel ID is empty'));
		}
		else
		{
			if(!empty($parcelsToPay))
			{
				$parcelApiPay = Mage::helper('inpostparcels/data')->connectInpostparcels(array(
				'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'parcels/'.implode(';', $parcelsToPay).'/pay',
				'methodType' => 'POST',
				'params' => array(
				)
				));

				Mage::log(var_export($parcelApiPay, 1) .
					'------', null,
					date('Y-m-d H:i:s').'-parcels_pay.log');

				if(@$parcelApiPay['info']['http_code'] != '204')
				{
					$countNonSticker = count($parcelsIds);
					if(!empty($parcelApiPay['result']))
					{
						foreach(@$parcelApiPay['result'] as $key => $error)
						{
							$this->_getSession()->addError($this->__('Parcel %s '.$error, $key));
						}
					}
					$this->_redirect('*/*/');
					return;
				}
			}

			// Check if we are running Returns or normal parcel
			// sticker creation and adjust URL accordingly.

			if($return_label == true)
			{
				$url = Mage::getStoreConfig('carriers/inpostparcels/api_url') .
					'reverselogistics/' .
					$parcelsReturnCode[0] . 
					'/label.json';
			}
			else
			{
				$url = Mage::getStoreConfig('carriers/inpostparcels/api_url').'stickers/'.implode(';', $parcelsCode);
			}

			$parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels(array(
			'url'        => $url,
			'methodType' => 'GET',
			'params'     => array(
				'format' => $format,
				'type'   => $type
			)
			));
		}

		if(@$parcelApi['info']['http_code'] != '200')
		{
			$countNonSticker = count($parcelsIds);
			if(!empty($parcelApi['result']))
			{
				foreach(@$parcelApi['result'] as $key => $error)
				{
					$this->_getSession()->addError($this->__('Parcel %s '.$error, $key));
				}
			}
		}
		else
		{
			foreach ($parcelsIds as $parcelId)
			{
				if(isset($parcelsToPay[$parcelId]))
				{
					$parcelDb = Mage::getModel('inpostparcels/inpostparcels')->load($parcelId);
					$parcelDb->setParcelStatus('Prepared');
					$parcelDb->setStickerCreationDate(date('Y-m-d H:i:s'));
					$parcelDb->save();
				}
				$countSticker++;
			}

			if($return_label == true)
			{
				$pdf = base64_decode(@$parcelApi['result']->data);
			}
			else
			{
				$pdf = base64_decode(@$parcelApi['result']);
			}
		}

		if ($countNonSticker)
		{
			if ($countNonSticker)
			{
				$this->_getSession()->addError($this->__('%s sticker(s) cannot be generated', $countNonSticker));
			}
			else
			{
				$this->_getSession()->addError($this->__('The sticker(s) cannot be generated'));
			}
		}
		if ($countSticker)
		{
			$this->_getSession()->addSuccess($this->__('%s sticker(s) have been generated.', $countSticker));
		}

		if(!is_null($pdf))
		{
			// Check the kind of label that we have created and
			// output a suitable response.
			$name = 'stickers' .
				Mage::getSingleton('core/date')->date('Y-m-d_H-i-s');
			if(strcasecmp($format, 'pdf') == 0)
			{
				$name       .= '.pdf';
				$output_type = 'application/pdf';
			}
			else
			{
				$name       .= '.epl2';
				$output_type = 'application/text';
			}

			return $this->_prepareDownloadResponse($name,
					$pdf,
					$output_type);
		}
		else
		{
			$this->_redirect('*/*/');
		}
	}

	///
	// massRefreshStatusAction
	//
	public function massRefreshStatusAction()
	{
		$parcelsIds = $this->getRequest()->getPost('parcels_ids', array());
		$countRefreshStatus = 0;
		$countNonRefreshStatus = 0;

		$parcelsCode = array();
		foreach ($parcelsIds as $id)
		{
            $parcel = Mage::getModel('inpostparcels/inpostparcels')->load($id);
            if($parcel->getParcelId() != ''){
                $parcelsCode[$id] = $parcel->getParcelId();
            }else{
                continue;
            }
        }

        if(empty($parcelsCode)){
            $this->_getSession()->addError($this->__('Parcel ID is empty'));
        }else{
            $parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels(array(
                'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'parcels/'.implode(';', $parcelsCode),
                'methodType' => 'GET',
                'params' => array()
            ));
        }

        if(@$parcelApi['info']['http_code'] != '200'){
            $countNonRefreshStatus = count($parcelsIds);
            if(!empty($parcelApi['result'])){
                foreach(@$parcelApi['result'] as $key => $error){
                    $this->_getSession()->addError($this->__('Parcel %s '.$error, $key));
                }
            }
        }else{
            if(!is_array(@$parcelApi['result'])){
                @$parcelApi['result'] = array(@$parcelApi['result']);
            }
            foreach (@$parcelApi['result'] as $parcel) {
                $parcelCollection = Mage::getModel('inpostparcels/inpostparcels')->getCollection();
                $parcelCollection->addFieldToFilter('parcel_id', @$parcel->id);
                $parcelDb = $parcelCollection->getFirstItem();
                $parcelDb->setParcelStatus($parcel->status);
                $parcelDb->save();
                $countRefreshStatus++;
            }
        }

		if ($countNonRefreshStatus)
		{
			if ($countNonRefreshStatus)
			{
				$this->_getSession()->addError($this->__('%s parcel status cannot be refresh', $countNonRefreshStatus));
			}
			else
			{
				$this->_getSession()->addError($this->__('The parcel status cannot be refresh'));
			}
		}
		if ($countRefreshStatus)
		{
			$this->_getSession()->addSuccess($this->__('%s parcel status have been refresh.', $countRefreshStatus));
		}
		$this->_redirect('*/*/');
	}

	///
	// massCancelAction
	//
	public function massCancelAction()
	{
        $parcelsIds = $this->getRequest()->getPost('parcels_ids', array());
        $countCancel = 0;
        $countNonCancel = 0;

        $parcelsCode = array();
        foreach ($parcelsIds as $id) {
            $parcel = Mage::getModel('inpostparcels/inpostparcels')->load($id);
            if($parcel->getParcelId() != ''){
                $parcelsCode[$id] = $parcel->getParcelId();
            }else{
                continue;
            }
        }

        if(empty($parcelsCode)){
            $this->_getSession()->addError($this->__('Parcel ID is empty'));
        }else{
            foreach($parcelsCode as $id => $parcelId){
                $parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels(array(
                    'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'parcels',
                    'methodType' => 'PUT',
                    'params' => array(
                        'id' => $parcelId,
                        'status' => 'cancelled'
                    )
                ));

                if(@$parcelApi['info']['http_code'] != '204'){
                    $countNonCancel = count($parcelsIds);
                    if(!empty($parcelApi['result'])){
                        foreach(@$parcelApi['result'] as $key => $error){
                            if(is_array($error)){
                                foreach($error as $subKey => $subError){
                                    $this->_getSession()->addError($this->__('Parcel %s '.$subError, $parcelId));
                                }
                            }else{
                                $this->_getSession()->addError($this->__('Parcel %s '.$error, $key));
                            }
                        }
                    }
                }else{
                    foreach (@$parcelApi['result'] as $parcel) {
                        $parcelCollection = Mage::getModel('inpostparcels/inpostparcels')->getCollection();
                        $parcelCollection->addFieldToFilter('parcel_id',$parcel->id);
                        $parcelDb = $parcelCollection->getFirstItem();
                        $parcelDb->setParcelStatus($parcel->status);
                        $parcelDb->save();
                        $countCancel++;
                    }
                }
            }
        }

        if ($countNonCancel) {
            if ($countNonCancel) {
                $this->_getSession()->addError($this->__('%s parcel cannot be cancel', $countNonCancel));
            } else {
                $this->_getSession()->addError($this->__('The parcel cannot be cancel'));
            }
		}
		if ($countCancel)
		{
			$this->_getSession()->addSuccess($this->__('%s parcel have been cancel.', $countNonCancel));
		}
		$this->_redirect('*/*/');
	}

	///
	// massCreateMultipleParcelsAction
	//    
	public function massCreateMultipleParcelsAction()
	{
		Mage::log("start massCreateMultipleParcelsAction method");

		$parcelsIds = $this->getRequest()->getPost('parcels_ids', array());
		$countParcel = 0;
		$countNonParcel = 0;

		$parcels = array();

		foreach ($parcelsIds as $id)
		{
            $parcelCollection = Mage::getModel('inpostparcels/inpostparcels')->load($id);
            $orderCollection = Mage::getResourceModel('sales/order_grid_collection')
                ->addFieldToFilter('entity_id', $parcelCollection->getOrderId())
                ->getFirstItem();

            if($orderCollection->getStatus() != 'processing' || $parcelCollection->getParcelId() != ''){
                $countNonParcel++;
                continue;
            }
            //$parcelTargetMachineDetailDb = json_decode($parcelCollection->getParcelTargetMachineDetail());
            $parcelDetailDb = json_decode($parcelCollection->getParcelDetail());

            // create Inpost parcel e.g.
            $params = array(
                'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'parcels',
                'methodType' => 'POST',
                'params' => array(
                    'description' => $parcelDetailDb->description,
                    'description2' => 'magento-1.x-'.Mage::helper('inpostparcels/data')->getVersion(),
                    'receiver' => array(
                        'phone' => $parcelDetailDb->receiver->phone,
                        'email' => $parcelDetailDb->receiver->email,
                    ),
                    'size' => $parcelDetailDb->size,
                    'tmp_id' => $parcelDetailDb->tmp_id,
                    'target_machine' => $parcelDetailDb->target_machine
                )
            );

            switch($parcelCollection->getApiSource()){
                case 'PL':
                    /*
                    $insurance_amount = Mage::getSingleton('adminhtml/session')->getParcelInsuranceAmount();
                    $params['params']['cod_amount'] = @$postData['parcel_cod_amount'];
                    if(@$postData['parcel_insurance_amount'] != ''){
                        $params['params']['insurance_amount'] = @$postData['parcel_insurance_amount'];
                    }
                    $params['params']['source_machine'] = @$postData['parcel_source_machine_id'];
                    break;
                    */
            }

            $parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels($params);

            if(@$parcelApi['info']['http_code'] != '204' && @$parcelApi['info']['http_code'] != '201'){
                if(!empty($parcelApi['result'])){
                    foreach(@$parcelApi['result'] as $key => $error){
                        if(is_array($error)){
                            foreach($error as $subKey => $subError){
                                $this->_getSession()->addError($this->__('Parcel %s '.$subError, $key.' '.$id));
                            }
                        }else{
                            $this->_getSession()->addError($this->__('Parcel %s '.$error, $key));
                        }
                    }
                }
                $countNonParcel++;

            }else{
                $fields = array(
                    'parcel_id' => $parcelApi['result']->id,
                    'parcel_status' => 'Created',
                    'parcel_detail' => json_encode($params['params']),
                    'parcel_target_machine_id' => isset($postData['parcel_target_machine_id'])?$postData['parcel_target_machine_id']:$parcelCollection->getParcelTargetMachineId(),
                    'parcel_target_machine_detail' => $parcelCollection->getParcelTargetMachineDetail(),
                    'variables' => json_encode(array())
                );

                $parcelCollection->setParcelId($fields['parcel_id']);
                $parcelCollection->setParcelStatus($fields['parcel_status']);
                $parcelCollection->setParcelDetail($fields['parcel_detail']);
                $parcelCollection->setParcelTargetMachineId($fields['parcel_target_machine_id']);
                $parcelCollection->setParcelTargetMachineDetail($fields['parcel_target_machine_detail']);
                $parcelCollection->setVariables($fields['variables']);
                $parcelCollection->save();
                $countParcel++;
            }
        }

        if ($countNonParcel) {
            if ($countNonParcel) {
                $this->_getSession()->addError($this->__('%s parcel(s) cannot be created', $countNonParcel));
            } else {
                $this->_getSession()->addError($this->__('The parcel(s) cannot be created'));
            }
        }
        if ($countParcel) {
            $this->_getSession()->addSuccess($this->__('%s parcel(s) have been created.', $countParcel));
        }

        $this->_redirect('*/*/');
	}

	///
	// editAction
	//
	public function editAction()
	{
		Mage::log('in editAction method.');

		$id = $this->getRequest()->getParam('id');
		$parcel = Mage::getModel('inpostparcels/inpostparcels')->load($id);

		if ($parcel->getId() || $id == 0)
		{
			$parcelTargetMachineDetailDb = json_decode($parcel->getParcelTargetMachineDetail());
			$parcelDetailDb = json_decode($parcel->getParcelDetail());

			// set disabled
			$disabledCodAmount       = '';
			$disabledDescription     = '';
			$disabledInsuranceAmount = '';
			$disabledReceiverPhone   = '';
			$disabledReceiverEmail   = '';
			$disabledParcelSize      = '';
			$disabledParcelStatus    = '';
			$disabledSourceMachine   = '';
			$disabledTmpId           = '';
			$disabledTargetMachine   = '';

			if($parcel->getParcelStatus() != 'Created' && $parcel->getParcelStatus() != '')
			{
				$disabledCodAmount       = 'disabled';
				$disabledDescription     = 'disabled';
				$disabledInsuranceAmount = 'disabled';
				$disabledReceiverPhone   = 'disabled';
				$disabledReceiverEmail   = 'disabled';
				$disabledParcelSize      = 'disabled';
				$disabledParcelStatus    = 'disabled';
				$disabledSourceMachine   = 'disabled';
				$disabledTmpId           = 'disabled';
				$disabledTargetMachine   = 'disabled';
			}
			if($parcel->getParcelStatus() == 'Created')
			{
				$disabledCodAmount = 'disabled';
				//$disabledDescription = 'disabled';
				$disabledInsuranceAmount = 'disabled';
				$disabledReceiverPhone = 'disabled';
				$disabledReceiverEmail = 'disabled';
				//$disabledParcelSize = 'disabled';
				//$disabledParcelStatus = 'disabled';
				$disabledSourceMachine = 'disabled';
				$disabledTmpId = 'disabled';
				$disabledTargetMachine = 'disabled';
			}

			Mage::register('disabledCodAmount', $disabledCodAmount);
			Mage::register('disabledDescription', $disabledDescription);
			Mage::register('disabledInsuranceAmount', $disabledInsuranceAmount);
			Mage::register('disabledReceiverPhone', $disabledReceiverPhone);
			Mage::register('disabledReceiverEmail', $disabledReceiverEmail);
			Mage::register('disabledParcelSize', $disabledParcelSize);
			Mage::register('disabledParcelStatus', $disabledParcelStatus);
			Mage::register('disabledSourceMachine', $disabledSourceMachine);
			Mage::register('disabledTmpId', $disabledTmpId);
			Mage::register('disabledTargetMachine', $disabledTargetMachine);

			$allMachines = Mage::helper('inpostparcels/data')->connectInpostparcels(
			array(
			'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'machines',
			'methodType' => 'GET',
			'params' => array()
			)
			);

			// target machines
			$parcelTargetAllMachinesId = array();
			$parcelTargetAllMachinesDetail = array();
			$machines = array();
			if(is_array(@$allMachines['result']) && !empty($allMachines['result']))
			{
				foreach($allMachines['result'] as $key => $machine)
				{
					if(in_array($parcel->getApiSource(), array('PL')))
					{
						if($machine->payment_available == false)
						{
							// Polish machines MUST have payment available.
							continue;
						}
					}

					$parcelTargetAllMachinesId[$machine->id] = $machine->id.', '.@$machine->address->city.', '.@$machine->address->street;
					$parcelTargetAllMachinesDetail[$machine->id] = array(
					'id' => $machine->id,
					'address' => array(
					'building_number' => @$machine->address->building_number,
					'flat_number' => @$machine->address->flat_number,
					'post_code' => @$machine->address->post_code,
					'province' => @$machine->address->province,
					'street' => @$machine->address->street,
					'city' => @$machine->address->city
					)
					);
					if($machine->address->post_code == @$parcelTargetMachineDetailDb->address->post_code)
					{
						$machines[$key] = $machine;
						continue;
					}
					elseif($machine->address->city == @$parcelTargetMachineDetailDb->address->city)
					{
						$machines[$key] = $machine;
					}
				}
			}
			Mage::register('parcelTargetAllMachinesId', $parcelTargetAllMachinesId);
			Mage::register('parcelTargetAllMachinesDetail', $parcelTargetAllMachinesDetail);

			$parcelTargetMachinesId = array();
			$parcelTargetMachinesDetail = array();
			$defaultTargetMachine = $this->__('Select Machine..');
			if(is_array(@$machines) && !empty($machines))
			{
				foreach($machines as $key => $machine)
				{
					$parcelTargetMachinesId[$machine->id] = $machine->id.', '.@$machine->address->city.', '.@$machine->address->street;
					$parcelTargetMachinesDetail[$machine->id] = $parcelTargetAllMachinesDetail[$machine->id];
				}
			}
			else
			{
				$defaultTargetMachine = $this->__('no terminals in your city');
			}
			Mage::register('parcelTargetMachinesId', $parcelTargetMachinesId);
			Mage::register('parcelTargetMachinesDetail', $parcelTargetMachinesDetail);
			Mage::register('defaultTargetMachine', $defaultTargetMachine);

			//$parcel['api_source'] = 'PL';
			$parcelInsurancesAmount = array();
			$defaultInsuranceAmount = $this->__('Select insurance');
			switch($parcel->getApiSource())
			{
				case 'PL':
				$api = Mage::helper('inpostparcels/data')->connectInpostparcels(
				array(
				'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'customer/pricelist',
				'methodType' => 'GET',
				'params' => array()
				)
				);

				if(isset($api['result']) && !empty($api['result']))
				{
					$parcelInsurancesAmount = array(
					''.$api['result']->insurance_price1.'' => $api['result']->insurance_price1,
					''.$api['result']->insurance_price2.'' => $api['result']->insurance_price2,
					''.$api['result']->insurance_price3.'' => $api['result']->insurance_price3
					);
				}

				$parcelSourceAllMachinesId = array();
				$parcelSourceAllMachinesDetail = array();
				$machines = array();
				$shopCities = explode(',',Mage::getStoreConfig('carriers/inpostparcels/shop_cities'));
				if(is_array(@$allMachines['result']) && !empty($allMachines['result']))
				{
					foreach($allMachines['result'] as $key => $machine)
					{
						$parcelSourceAllMachinesId[$machine->id] = $machine->id.', '.@$machine->address->city.', '.@$machine->address->street;
						$parcelSourceAllMachinesDetail[$machine->id] = array(
						'id' => $machine->id,
						'address' => array(
						'building_number' => @$machine->address->building_number,
						'flat_number' => @$machine->address->flat_number,
						'post_code' => @$machine->address->post_code,
						'province' => @$machine->address->province,
						'street' => @$machine->address->street,
						'city' => @$machine->address->city
						)
						);
						if(in_array($machine->address->city, $shopCities)){
						$machines[$key] = $machine;
						}
					}
				}
				Mage::register('parcelInsurancesAmount', $parcelInsurancesAmount);
				Mage::getSingleton('adminhtml/session')->setParcelInsuranceAmount($parcelInsurancesAmount);
				Mage::register('defaultInsuranceAmount', $defaultInsuranceAmount);
				Mage::register('parcelSourceAllMachinesId', $parcelSourceAllMachinesId);
				Mage::register('parcelSourceAllMachinesDetail', $parcelSourceAllMachinesDetail);
				Mage::register('shopCities', $shopCities);

				$parcelSourceMachinesId = array();
				$parcelSourceMachinesDetail = array();
				$defaultSourceMachine = $this->__('Select Machine..');
				if(is_array(@$machines) && !empty($machines))
				{
					foreach($machines as $key => $machine)
					{
						$parcelSourceMachinesId[$machine->id] = $machine->id.', '.@$machine->address->city.', '.@$machine->address->street;
						$parcelSourceMachinesDetail[$machine->id] = $parcelSourceAllMachinesDetail[$machine->id];
					}
				}
				else
				{
					$defaultTargetMachine = $this->__('no terminals in your city');
					if(@$parcelDetailDb->source_machine != '')
					{
						$parcelSourceMachinesId[$parcelDetailDb->source_machine] = @$parcelSourceAllMachinesId[$parcelDetailDb->source_machine];
						$parcelSourceMachinesDetail[$parcelDetailDb->source_machine] = @$parcelSourceMachinesDetail[$parcelDetailDb->source_machine];
					}
				}

				Mage::register('parcelSourceMachinesId', $parcelSourceMachinesId);
				Mage::register('parcelSourceMachinesDetail', $parcelSourceMachinesDetail);
				Mage::register('defaultSourceMachine', $defaultTargetMachine);

			break;
			}

			$inpostparcelsData = array(
			'id' => $parcel->getId(),
			'parcel_id'                => $parcel->getParcelId(),
			'parcel_cod_amount'        => @$parcelDetailDb->cod_amount,
			'parcel_description'       => @$parcelDetailDb->description,
			'parcel_insurance_amount'  => @$parcelDetailDb->insurance_amount,
			'parcel_receiver_phone'    => @$parcelDetailDb->receiver->phone,
			'parcel_receiver_email'    => @$parcelDetailDb->receiver->email,
			'parcel_size'              => @$parcelDetailDb->size,
			'parcel_status'            => $parcel->getParcelStatus(),
			'parcel_source_machine_id' => @$parcelDetailDb->source_machine,
			'parcel_tmp_id'            => @$parcelDetailDb->tmp_id,
			'parcel_target_machine_id' => @$parcelDetailDb->target_machine,
			// Return Parcel Details
			'return_parcel_id'     => $parcel->getReturnParcelId(),
			'return_parcel_expiry' => $parcel->getReturnParcelExpiry(),
			);
			Mage::register('inpostparcelsData', $inpostparcelsData);
			Mage::register('api_source', $parcel->getApiSource());

			$defaultParcelSize = @$parcelDetailDb->size;
			Mage::register('defaultParcelSize', $defaultParcelSize);

			$this->_initAction()->renderLayout();
		}
		else
		{
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('<module>')->__('Item does not exist'));
			$this->_redirect('*/*/');
		}
	}

	///
	// saveAction
	//
	public function saveAction()
	{
		Mage::log("start saveAction method");

		if ( $this->getRequest()->getPost() )
		{
			try {
				$postData = $this->getRequest()->getPost();
				$id = $postData['id'];

				$parcel = Mage::getModel('inpostparcels/inpostparcels')->load($postData['id']);
				$parcelTargetMachineDetailDb = json_decode($parcel->getParcelTargetMachineDetail());
				$parcelDetailDb = json_decode($parcel->getParcelDetail());

				if($parcel->getParcelId() != '')
				{
					// update Inpost parcel
					$params = array(
                        'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'parcels',
                        'methodType' => 'PUT',
                        'params' => array(
                            'description' => !isset($postData['parcel_description']) || $postData['parcel_description'] == @$parcelDetailDb->description?null:$postData['parcel_description'],
                            'id' => $postData['parcel_id'],
                            'size' => !isset($postData['parcel_size']) || $postData['parcel_size'] == @$parcelDetailDb->size?null:$postData['parcel_size'],
                            'status' => !isset($postData['parcel_status']) || $postData['parcel_status'] == $parcel->getParcelStatus()?null:$postData['parcel_status'],
                            //'target_machine' => !isset($postData['parcel_target_machine_id']) || $postData['parcel_target_machine_id'] == $parcel->getParcelTargetMachineId()?null:$postData['parcel_target_machine_id']
					)
					);
				}
				else
				{
					// create Inpost parcel e.g.
					$params = array(
						'url'        => Mage::getStoreConfig('carriers/inpostparcels/api_url').'parcels',
						'methodType' => 'POST',
						'params'     => array(
							'description'    => @$postData['parcel_description'],
							'description2'   => 'magento-1.x-'.Mage::helper('inpostparcels/data')->getVersion(),
							'receiver'       => array(
								'phone' => @$postData['parcel_receiver_phone'],
								'email' => @$postData['parcel_receiver_email']
							),
							'size'           => @$postData['parcel_size'],
							'tmp_id'         => @$postData['parcel_tmp_id'],
							'target_machine' => @$postData['parcel_target_machine_id']
							)
						);

					switch($parcel->getApiSource())
					{
						case 'PL':
							$insurance_amount = Mage::getSingleton('adminhtml/session')->getParcelInsuranceAmount();
							$params['params']['cod_amount'] = @$postData['parcel_cod_amount'];
							if(@$postData['parcel_insurance_amount'] != '')
							{
								$params['params']['insurance_amount'] = @$postData['parcel_insurance_amount'];
							}
							$params['params']['source_machine'] = @$postData['parcel_source_machine_id'];
							break;
					}
				}

				$parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels($params);

				if(@$parcelApi['info']['http_code'] != '204' &&
				   @$parcelApi['info']['http_code'] != '201')
				{
					if(!empty($parcelApi['result']))
					{
						foreach(@$parcelApi['result'] as $key => $error)
						{
							if(is_array($error))
							{
								foreach($error as $subKey => $subError)
								{
									$this->_getSession()->addError(
										$this->__('Parcel %s ' .
											$subError, $key . ' ' . $postData['parcel_id']));
								}
							}
							else
							{
								$this->_getSession()->addError($this->__('Parcel %s '.$error, $key));
							}
						}
					}
					$this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
					return;
				}
				else
				{
					// We have created or updated a parcels details.
					if($parcel->getParcelId() != '')
					{
						// We updated a parcel.
						$parcelDetail = $parcelDetailDb;
						$parcelDetail->description = $postData['parcel_description'];
						$parcelDetail->size = $postData['parcel_size'];
						$parcelDetail->status = $postData['parcel_status'];

						$fields = array(
							'parcel_status' => isset($postData['parcel_status'])?$postData['parcel_status']:$parcel->getParcelStatus(),
							'parcel_detail' => json_encode($parcelDetail),
							'variables' => json_encode(array())
						);

						$parcel->setParcelStatus($fields['parcel_status']);
						$parcel->setParcelDetail($fields['parcel_detail']);
						$parcel->setVariables($fields['variables']);
						$parcel->save();
					}
					else
					{
						// We created a new parcel.
						$fields = array(
							'parcel_id' => $parcelApi['result']->id,
							'parcel_status' => 'Created',
							'parcel_detail' => json_encode($params['params']),
							'parcel_target_machine_id' => isset($postData['parcel_target_machine_id'])?$postData['parcel_target_machine_id']:$parcel->getParcelTargetMachineId(),
							'parcel_target_machine_detail' => $parcel->getParcelTargetMachineDetail(),
							'variables' => json_encode(array())
						);

						if($parcel->getParcelTargetMachineId() != $postData['parcel_target_machine_id'])
						{
							$parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels(
							array(
							'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'machines/'.$postData['parcel_target_machine_id'],
							'methodType' => 'GET',
							'params' => array()
							)
							);

							$fields['parcel_target_machine_detail'] = json_encode($parcelApi['result']);
						}

						$rrcode = "";
						$expiry = "0000-00-00 00:00:00";
						// Check to see if we should
						// allow the creation of Return
						// parcel labels.
						$returns_allowed = Mage::getStoreConfig('carriers/inpostparcels/allow_return_parcels');
						$default_returns = Mage::getStoreConfig('carriers/inpostparcels/default_return_parcels');

						if($returns_allowed == true &&
						   $default_returns == true)
						{
							$this->_create_new_return_parcel(
								@$postData['parcel_receiver_phone'],
								@$postData['parcel_receiver_email'],
								@$postData['parcel_size'],
								$rrcode, $expiry);
						}

						$parcel->setParcelId($fields['parcel_id']);
						$parcel->setParcelStatus($fields['parcel_status']);
						$parcel->setParcelDetail($fields['parcel_detail']);
						$parcel->setParcelTargetMachineId($fields['parcel_target_machine_id']);
						$parcel->setParcelTargetMachineDetail($fields['parcel_target_machine_detail']);
						$parcel->setVariables($fields['variables']);
						$parcel->setReturnParcelId($rrcode);
						$parcel->setReturnParcelExpiry($expiry);
						$parcel->save();
					}
				}
				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Item was successfully saved'));
				Mage::getSingleton('adminhtml/session')->setInpostparcelsData(false);
				Mage::getSingleton('adminhtml/session')->setParcelTargetMachinesDetail(false);
				Mage::getSingleton('adminhtml/session')->setParcelTargetMachinesDetail(false);
				Mage::getSingleton('adminhtml/session')->setParcelInsuranceAmount(false);
				$this->_redirect('*/*/');
				return;
			}
			catch (Exception $e)
			{
				Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				Mage::getSingleton('adminhtml/session')->setInpostparcelsData($this->getRequest()->getPost());
				$this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
				return;
			}
		}
		$this->_redirect('*/*/');
	}

	///
	// gridAction
	// 
	public function gridAction()
	{
		$this->loadLayout();
		$this->getResponse()->setBody(
			$this->getLayout()->createBlock('inpostparcels/adminhtml_inpostparcels_grid')->toHtml()
		);
	}

	///
	// massCreateMultipleReturnParcelsAction
	//  
	// We will go through the list of parcels selected by the user.
	// We will process only the ones that have Parcel Id's against them,
	// as the email and phone number of the customer have been finalised
	// by then.
	public function massCreateMultipleReturnParcelsAction()
	{
		// Check to see if the required data is available to allow us
		// to create a returns parcel or not.
		$days_to_add = (int)Mage::getStoreConfig('carriers/inpostparcels/expiry_return_parcels');

		$fail = false;

		if($days_to_add == 0)
		{
			// Can't continue.
			$message = $this->__("Please allow the recipient more days to collect their parcel.");
			Mage::getSingleton("core/session")->addError($message);
			$fail = true;
		}

		if($fail == true)
		{
			// return and display the error(s) to the user.
			$message = $this->__("Cannot create a Return Parcel without the above error(s) being fixed.");
			Mage::getSingleton("core/session")->addWarning($message);
			// Do a redirect to get the page to show the error(s).
        		$this->_redirect('*/*/');
			return;
		}

		$parcelsIds     = $this->getRequest()->getPost('parcels_ids',
			array());
		$countParcel    = 0;
		$countNonParcel = 0;

		$parcels = array();

		foreach ($parcelsIds as $id)
		{
			$parcelCollection = Mage::getModel('inpostparcels/inpostparcels')->load($id);
			$orderCollection = Mage::getResourceModel('sales/order_grid_collection')
			->addFieldToFilter('entity_id', $parcelCollection->getOrderId())
			->getFirstItem();

			if($parcelCollection->getParcelId() == '')
			{
				Mage::log("Failing because of the parcel status");
				$countNonParcel++;
				continue;
			}
			$parcelDetailDb = json_decode($parcelCollection->getParcelDetail());

			// Must add days, not seconds to the current day.
			$expiry_date  = date("Y-m-d", time() +
				($days_to_add * 60 * 60 * 24));

			// The mobile number saved is only the last 9 digits,
			// we need 10 for the returns process. Add a 7 to the
			// start of the number.
			$sender_phone = "7" . $parcelDetailDb->receiver->phone;
			$sender_email = $parcelDetailDb->receiver->email;

			// create Inpost Return parcel
			$params = array(
				'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'reverselogistics.json',
				'methodType'   => 'POST',
				'params'       => array(
				'parcel_size'  => $parcelDetailDb->size,
				'expire_at'    => $expiry_date,
				'sender_phone' => $sender_phone,
				'sender_email' => $sender_email,
				'with_label'   => 'TRUE',
				)
			);

			// Do the REST call.
			$parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels($params);

			//Mage::log("Result from the API call is.");
			//Mage::log($parcelApi);

			if(@$parcelApi['info']['http_code'] != '204' &&
				@$parcelApi['info']['http_code'] != '200')
			{
				if(!empty($parcelApi['result']))
				{
					foreach(@$parcelApi['result'] as $key => $error)
					{
						if(is_array($error))
						{
							foreach($error as $subKey => $subError)
							{
								$this->_getSession()->addError($this->__('Parcel %s '.
									$subError,
									$key .
									' ' .
									$id));
							}
						}
						else
						{
							$this->_getSession()->addError($this->__('Parcel %s '.
								$error,
								$key));
						}
					}
				}
				$countNonParcel++;
			}
			else
			{
				$parcel_id     = $parcelApi['result']->code;
				$actual_expiry = $parcelApi['result']->expire_at;

				$parcelCollection->setReturnParcelId($parcel_id);
				$parcelCollection->setReturnParcelExpiry($actual_expiry);
				$parcelCollection->setReturnParcelCreated(date('Y-m-d H:i:s'));
				$parcelCollection->save();
				$countParcel++;
			}
		}

		if ($countNonParcel)
		{
			if ($countNonParcel)
			{
				$this->_getSession()->addError($this->__('%s Return parcel(s) cannot be created', $countNonParcel));
			}
			else
			{
				$this->_getSession()->addError($this->__('The Return parcel(s) cannot be created'));
			}
		}
		if ($countParcel)
		{
			$this->_getSession()->addSuccess(
				$this->__('%s Return parcel(s) have been created.',
				$countParcel));
		}

		$this->_redirect('*/*/');
	}

	///
	// massCreateMultipleReturnParcelStickersAction
	//  
	// We will go through the list of parcels selected by the user.
	// We will process only the ones that have Parcel Id's against them.
	//
	// NB Only ONE return label can be created at a time. We will check
	// to see if the user has selected more than one and generate an
	// error if they have.
	//
	public function massCreateMultipleReturnParcelStickersAction()
	{
		// All of the functionality for this is in the normal parcel
		// sticker creation. There is only one slight change needed
		// to use a different URL for the sticker creation call.
		$parcelsIds = $this->getRequest()->getPost('parcels_ids', array());

		$ret = NULL;

		if(count($parcelsIds) != 1)
		{
			$this->_getSession()->addError(
				$this->__('Please select only ONE parcel to create Return Label for.'));
		}
		elseif(strlen($parcelsIds[0]) == 0)
		{
			$this->_getSession()->addError(
				$this->__('Please create parcel first.'));
		}
		else
		{
			// Check to see if the expiry date is already passed.
			// Stops them making unwanted API calls.
			foreach($parcelsIds as $id)
			{
				$parcelCollection = Mage::getModel('inpostparcels/inpostparcels')->load($id);
				$expiry_date = $parcelCollection->getReturnParcelExpiry();
			}

			$hour = substr($expiry_date, 11, 2);
			$mins = substr($expiry_date, 14, 2);
			$secs = substr($expiry_date, 17, 2);
			$mont = substr($expiry_date, 5, 2);
			$days = substr($expiry_date, 8, 2);
			$year = substr($expiry_date, 0, 4);

			if(mktime($hour, $mins, $secs, $mont, $days, $year) < time())
			{
				$this->_getSession()->addError(
					$this->__('The parcel has expired and cannot have a label printed.'));
			}
			else
			{
				$ret = $this->massStickersAction(true);
			}
		}

		if($ret == NULL)
		{
			$this->_redirect('*/*/');
			return;
		}
		else
		{
			return $ret;
		}
	}

	///
	// _create_new_return_parcel
	//
	// @param Phone number of the sender without the extra 7
	// @param Email address of the sender
	// @param Size of the parcel
	// @param The RR code of the return parcel
	// @param The expiry date of the return parcel
	//
	// @return True if the parcel creation worked, otherwise false
	//
	private function _create_new_return_parcel($phone, $email, $size,
							&$rrcode, &$expiry)
	{
		$ret = false;

		$days_to_add = (int)Mage::getStoreConfig('carriers/inpostparcels/expiry_return_parcels');

		// Must add days, not seconds to the current day.
		$expiry_date  = date("Y-m-d", time() +
				($days_to_add * 60 * 60 * 24));

		// create Inpost Return parcel
		$params = array(
			'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url') .
				'reverselogistics.json',
			'methodType'   => 'POST',
			'params'       => array(
				'parcel_size'  => $size,
				'expire_at'    => $expiry_date,
				'sender_phone' => "7" . $phone,
				'sender_email' => $email,
				'with_label'   => 'TRUE',
				)
		);

		// Do the REST call.
		$parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels($params);

		if(@$parcelApi['info']['http_code'] != '200')
		{
			if(!empty($parcelApi['result']))
			{
				foreach(@$parcelApi['result'] as $key => $error)
				{
					if(is_array($error))
					{
						foreach($error as $subKey => $subError)
						{
							$this->_getSession()->addError($this->__('Parcel %s '.
								$subError,
								$key .
								' ' .
								$id));
						}
					}
					else
					{
						$this->_getSession()->addError($this->__('Parcel %s '.
							$error,
							$key));
					}
				}
			}
		}
		else
		{
			$rrcode = $parcelApi['result']->code;
			$expiry = $parcelApi['result']->expire_at;

			$ret = true;
		}

		return($ret);
	}

}
