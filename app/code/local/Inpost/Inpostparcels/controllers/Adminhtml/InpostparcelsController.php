<?php

class Inpost_Inpostparcels_Adminhtml_InpostparcelsController extends Mage_Adminhtml_Controller_Action
{

    protected function _initAction() {
        $this->loadLayout()
            ->_setActiveMenu('sales/inpostparcels')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));

        return $this;
    }

    public function indexAction() {
        $this->_initAction()
            ->renderLayout();
    }

    public function massStickersAction()
    {
        $parcelsIds = $this->getRequest()->getPost('parcels_ids', array());
        $countSticker = 0;
        $countNonSticker = 0;
        $pdf = null;

        $parcelsCode = array();
        $parcelsToPay = array();

        foreach ($parcelsIds as $id) {
            $parcelCollection = Mage::getModel('inpostparcels/inpostparcels')->load($id);
            $orderCollection = Mage::getResourceModel('sales/order_grid_collection')
                ->addFieldToFilter('entity_id', $parcelCollection->getOrderId())
                ->getFirstItem();

//            if($orderCollection->getStatus() != 'processing'){
//                continue;
//            }

            if($parcelCollection->getParcelId() != ''){
                $parcelsCode[$id] = $parcelCollection->getParcelId();
                if($parcelCollection->getStickerCreationDate() == ''){
                    $parcelsToPay[$id] = $parcelCollection->getParcelId();
                }

            }else{
                continue;
            }
        }

        if(empty($parcelsCode)){
            $this->_getSession()->addError($this->__('Parcel ID is empty'));
        }else{
            if(!empty($parcelsToPay)){
                $parcelApiPay = Mage::helper('inpostparcels/data')->connectInpostparcels(array(
                    'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'parcels/'.implode(';', $parcelsToPay).'/pay',
                    'methodType' => 'POST',
                    'params' => array(
                    )
                ));

                Mage::log(var_export($parcelApiPay, 1) . '------', null, date('Y-m-d H:i:s').'-parcels_pay.log');
                if(@$parcelApiPay['info']['http_code'] != '204'){
                    $countNonSticker = count($parcelsIds);
                    if(!empty($parcelApiPay['result'])){
                        foreach(@$parcelApiPay['result'] as $key => $error){
                            $this->_getSession()->addError($this->__('Parcel %s '.$error, $key));
                        }
                    }
                    $this->_redirect('*/*/');
                    return;
                }
            }    

            $parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels(array(
                'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'stickers/'.implode(';', $parcelsCode),
                'methodType' => 'GET',
                'params' => array(
                    'format' => 'Pdf',
                    'type' => 'normal'
                )
            ));
        }

        if(@$parcelApi['info']['http_code'] != '200'){
            $countNonSticker = count($parcelsIds);
            if(!empty($parcelApi['result'])){
                foreach(@$parcelApi['result'] as $key => $error){
                    $this->_getSession()->addError($this->__('Parcel %s '.$error, $key));
                }
            }
        }else{
            foreach ($parcelsIds as $parcelId) {
                if(isset($parcelsToPay[$parcelId])){
                    $parcelDb = Mage::getModel('inpostparcels/inpostparcels')->load($parcelId);
                    $parcelDb->setParcelStatus('Prepared');
                    $parcelDb->setStickerCreationDate(date('Y-m-d H:i:s'));
                    $parcelDb->save();
                }
                $countSticker++;
            }
            $pdf = base64_decode(@$parcelApi['result']);
        }

        if ($countNonSticker) {
            if ($countNonSticker) {
                $this->_getSession()->addError($this->__('%s sticker(s) cannot be generated', $countNonSticker));
            } else {
                $this->_getSession()->addError($this->__('The sticker(s) cannot be generated'));
            }
        }
        if ($countSticker) {
            $this->_getSession()->addSuccess($this->__('%s sticker(s) have been generated.', $countSticker));
        }

        if(!is_null($pdf)){
            return $this->_prepareDownloadResponse(
                'stickers'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.pdf', $pdf,
                'application/pdf'
            );
        }else{
            $this->_redirect('*/*/');
        }

    }

    public function massRefreshStatusAction()
    {
        $parcelsIds = $this->getRequest()->getPost('parcels_ids', array());
        $countRefreshStatus = 0;
        $countNonRefreshStatus = 0;

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

        if ($countNonRefreshStatus) {
            if ($countNonRefreshStatus) {
                $this->_getSession()->addError($this->__('%s parcel status cannot be refresh', $countNonRefreshStatus));
            } else {
                $this->_getSession()->addError($this->__('The parcel status cannot be refresh'));
            }
        }
        if ($countRefreshStatus) {
            $this->_getSession()->addSuccess($this->__('%s parcel status have been refresh.', $countRefreshStatus));
        }
        $this->_redirect('*/*/');
    }

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
        if ($countCancel) {
            $this->_getSession()->addSuccess($this->__('%s parcel have been cancel.', $countNonCancel));
        }
        $this->_redirect('*/*/');
    }

    public function massCreateMultipleParcelsAction(){
        $parcelsIds = $this->getRequest()->getPost('parcels_ids', array());
        $countParcel = 0;
        $countNonParcel = 0;

        $parcels = array();

        foreach ($parcelsIds as $id) {
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

    public function editAction(){
        $id = $this->getRequest()->getParam('id');
        $parcel = Mage::getModel('inpostparcels/inpostparcels')->load($id);

        if ($parcel->getId() || $id == 0) {
            $parcelTargetMachineDetailDb = json_decode($parcel->getParcelTargetMachineDetail());
            $parcelDetailDb = json_decode($parcel->getParcelDetail());

            // set disabled
            $disabledCodAmount = '';
            $disabledDescription = '';
            $disabledInsuranceAmount = '';
            $disabledReceiverPhone = '';
            $disabledReceiverEmail = '';
            $disabledParcelSize = '';
            $disabledParcelStatus = '';
            $disabledSourceMachine = '';
            $disabledTmpId = '';
            $disabledTargetMachine = '';

            if($parcel->getParcelStatus() != 'Created' && $parcel->getParcelStatus() != ''){
                $disabledCodAmount = 'disabled';
                $disabledDescription = 'disabled';
                $disabledInsuranceAmount = 'disabled';
                $disabledReceiverPhone = 'disabled';
                $disabledReceiverEmail = 'disabled';
                $disabledParcelSize = 'disabled';
                $disabledParcelStatus = 'disabled';
                $disabledSourceMachine = 'disabled';
                $disabledTmpId = 'disabled';
                $disabledTargetMachine = 'disabled';
            }
            if($parcel->getParcelStatus() == 'Created'){
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
                    'params' => array(
                    )
                )
            );

            // target machines
            $parcelTargetAllMachinesId = array();
            $parcelTargetAllMachinesDetail = array();
            $machines = array();
            if(is_array(@$allMachines['result']) && !empty($allMachines['result'])){
                foreach($allMachines['result'] as $key => $machine){
                    if(in_array($parcel->getApiSource(), array('PL'))){
                        if($machine->payment_available == false){
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
                    if($machine->address->post_code == @$parcelTargetMachineDetailDb->address->post_code){
                        $machines[$key] = $machine;
                        continue;
                    }elseif($machine->address->city == @$parcelTargetMachineDetailDb->address->city){
                        $machines[$key] = $machine;
                    }
                }
            }
            Mage::register('parcelTargetAllMachinesId', $parcelTargetAllMachinesId);
            Mage::register('parcelTargetAllMachinesDetail', $parcelTargetAllMachinesDetail);

            $parcelTargetMachinesId = array();
            $parcelTargetMachinesDetail = array();
            $defaultTargetMachine = $this->__('Select Machine..');
            if(is_array(@$machines) && !empty($machines)){
                foreach($machines as $key => $machine){
                    $parcelTargetMachinesId[$machine->id] = $machine->id.', '.@$machine->address->city.', '.@$machine->address->street;
                    $parcelTargetMachinesDetail[$machine->id] = $parcelTargetAllMachinesDetail[$machine->id];
                }
            }else{
                $defaultTargetMachine = $this->__('no terminals in your city');
            }
            Mage::register('parcelTargetMachinesId', $parcelTargetMachinesId);
            Mage::register('parcelTargetMachinesDetail', $parcelTargetMachinesDetail);
            Mage::register('defaultTargetMachine', $defaultTargetMachine);

            //$parcel['api_source'] = 'PL';
            $parcelInsurancesAmount = array();
            $defaultInsuranceAmount = $this->__('Select insurance');
            switch($parcel->getApiSource()){
                case 'PL':
                    $api = Mage::helper('inpostparcels/data')->connectInpostparcels(
                        array(
                            'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'customer/pricelist',
                            'methodType' => 'GET',
                            'params' => array(
                            )
                        )
                    );

                    if(isset($api['result']) && !empty($api['result'])){
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
                    if(is_array(@$allMachines['result']) && !empty($allMachines['result'])){
                        foreach($allMachines['result'] as $key => $machine){
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
                    if(is_array(@$machines) && !empty($machines)){
                        foreach($machines as $key => $machine){
                            $parcelSourceMachinesId[$machine->id] = $machine->id.', '.@$machine->address->city.', '.@$machine->address->street;
                            $parcelSourceMachinesDetail[$machine->id] = $parcelSourceAllMachinesDetail[$machine->id];
                        }
                    }else{
                        $defaultTargetMachine = $this->__('no terminals in your city');
                        if(@$parcelDetailDb->source_machine != ''){
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
                'parcel_id' => $parcel->getParcelId(),

                'parcel_cod_amount' => @$parcelDetailDb->cod_amount,
                'parcel_description' => @$parcelDetailDb->description,
                'parcel_insurance_amount' => @$parcelDetailDb->insurance_amount,
                'parcel_receiver_phone' => @$parcelDetailDb->receiver->phone,
                'parcel_receiver_email' => @$parcelDetailDb->receiver->email,
                'parcel_size' => @$parcelDetailDb->size,
                'parcel_status' => $parcel->getParcelStatus(),
                'parcel_source_machine_id' => @$parcelDetailDb->source_machine,
                'parcel_tmp_id' => @$parcelDetailDb->tmp_id,
                'parcel_target_machine_id' => @$parcelDetailDb->target_machine,
            );
            Mage::register('inpostparcelsData', $inpostparcelsData);
            Mage::register('api_source', $parcel->getApiSource());

            $defaultParcelSize = @$parcelDetailDb->size;
            Mage::register('defaultParcelSize', $defaultParcelSize);

            $this->_initAction()
                ->renderLayout();

        } else {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('<module>')->__('Item does not exist'));
            $this->_redirect('*/*/');
        }
    }

    public function saveAction()
    {
        if ( $this->getRequest()->getPost() ) {
            try {
                $postData = $this->getRequest()->getPost();
                $id = $postData['id'];

                $parcel = Mage::getModel('inpostparcels/inpostparcels')->load($postData['id']);
                $parcelTargetMachineDetailDb = json_decode($parcel->getParcelTargetMachineDetail());
                $parcelDetailDb = json_decode($parcel->getParcelDetail());

                if($parcel->getParcelId() != ''){
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
                }else{
                    // create Inpost parcel e.g.
                    $params = array(
                        'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'parcels',
                        'methodType' => 'POST',
                        'params' => array(
                            'description' => @$postData['parcel_description'],
                            'description2' => 'magento-1.x-'.Mage::helper('inpostparcels/data')->getVersion(),
                            'receiver' => array(
                                'phone' => @$postData['parcel_receiver_phone'],
                                'email' => @$postData['parcel_receiver_email']
                            ),
                            'size' => @$postData['parcel_size'],
                            'tmp_id' => @$postData['parcel_tmp_id'],
                            'target_machine' => @$postData['parcel_target_machine_id']
                        )
                    );

                    switch($parcel->getApiSource()){
                        case 'PL':
                            $insurance_amount = Mage::getSingleton('adminhtml/session')->getParcelInsuranceAmount();
                            $params['params']['cod_amount'] = @$postData['parcel_cod_amount'];
                            if(@$postData['parcel_insurance_amount'] != ''){
                                $params['params']['insurance_amount'] = @$postData['parcel_insurance_amount'];
                            }
                            $params['params']['source_machine'] = @$postData['parcel_source_machine_id'];
                            break;
                    }
                }

                $parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels($params);

                if(@$parcelApi['info']['http_code'] != '204' && @$parcelApi['info']['http_code'] != '201'){
                    if(!empty($parcelApi['result'])){
                        foreach(@$parcelApi['result'] as $key => $error){
                            if(is_array($error)){
                                foreach($error as $subKey => $subError){
                                    $this->_getSession()->addError($this->__('Parcel %s '.$subError, $key.' '.$postData['parcel_id']));
                                }
                            }else{
                                $this->_getSession()->addError($this->__('Parcel %s '.$error, $key));
                            }
                        }
                    }
                    $this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
                    return;
                }else{
                    if($parcel->getParcelId() != ''){
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

                    }else{
//                        $parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels(
//                            array(
//                                'url' => $parcelApi['info']['redirect_url'],
//                                'ds' => '&',
//                                'methodType' => 'GET',
//                                'params' => array(
//                                )
//                            )
//                        );

                        $fields = array(
                            'parcel_id' => $parcelApi['result']->id,
                            'parcel_status' => 'Created',
                            'parcel_detail' => json_encode($params['params']),
                            'parcel_target_machine_id' => isset($postData['parcel_target_machine_id'])?$postData['parcel_target_machine_id']:$parcel->getParcelTargetMachineId(),
                            'parcel_target_machine_detail' => $parcel->getParcelTargetMachineDetail(),
                            'variables' => json_encode(array())
                        );

                        if($parcel->getParcelTargetMachineId() != $postData['parcel_target_machine_id']){
                            $parcelApi = Mage::helper('inpostparcels/data')->connectInpostparcels(
                                array(
                                    'url' => Mage::getStoreConfig('carriers/inpostparcels/api_url').'machines/'.$postData['parcel_target_machine_id'],
                                    'methodType' => 'GET',
                                    'params' => array(
                                    )
                                )
                            );

                            $fields['parcel_target_machine_detail'] = json_encode($parcelApi['result']);
                        }

                        $parcel->setParcelId($fields['parcel_id']);
                        $parcel->setParcelStatus($fields['parcel_status']);
                        $parcel->setParcelDetail($fields['parcel_detail']);
                        $parcel->setParcelTargetMachineId($fields['parcel_target_machine_id']);
                        $parcel->setParcelTargetMachineDetail($fields['parcel_target_machine_detail']);
                        $parcel->setVariables($fields['variables']);
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
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setInpostparcelsData($this->getRequest()->getPost());
                $this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('inpostparcels/adminhtml_inpostparcels_grid')->toHtml()
        );
    }
}