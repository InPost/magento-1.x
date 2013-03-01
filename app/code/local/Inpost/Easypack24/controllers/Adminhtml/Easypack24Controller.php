<?php

class Inpost_Easypack24_Adminhtml_Easypack24Controller extends Mage_Adminhtml_Controller_Action
{

    protected function _initAction() {
        $this->loadLayout()
            ->_setActiveMenu('sales/easypack24')
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
        foreach ($parcelsIds as $id) {
            $parcelCollection = Mage::getModel('easypack24/easypack24')->load($id);
            if($parcelCollection->getParcelId() != ''){
                $parcelsCode[$id] = $parcelCollection->getParcelId();
            }else{
                continue;
            }
        }

        if(empty($parcelsCode)){
            $this->_getSession()->addError($this->__('Parcel ID is empty'));
        }else{

            $parcelApiPay = Mage::helper('easypack24/data')->connectEasypack24(array(
                'url' => Mage::getStoreConfig('carriers/easypack24/api_url').'parcels/'.implode(';', $parcelsCode).'/pay',
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

            $parcelApi = Mage::helper('easypack24/data')->connectEasypack24(array(
                'url' => Mage::getStoreConfig('carriers/easypack24/api_url').'stickers/'.implode(';', $parcelsCode),
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
                $parcelDb = Mage::getModel('easypack24/easypack24')->load($parcelId);
                $parcelDb->setParcelStatus('Prepared');
                $parcelDb->setStickerCreationDate(date('Y-m-d H:i:s'));
                $parcelDb->save();
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
        }

        $this->_redirect('*/*/');
    }

    public function massRefreshStatusAction()
    {
        $parcelsIds = $this->getRequest()->getPost('parcels_ids', array());
        $countRefreshStatus = 0;
        $countNonRefreshStatus = 0;

        $parcelsCode = array();
        foreach ($parcelsIds as $id) {
            $parcel = Mage::getModel('easypack24/easypack24')->load($id);
            if($parcel->getParcelId() != ''){
                $parcelsCode[$id] = $parcel->getParcelId();
            }else{
                continue;
            }
        }

        if(empty($parcelsCode)){
            $this->_getSession()->addError($this->__('Parcel ID is empty'));
        }else{
            $parcelApi = Mage::helper('easypack24/data')->connectEasypack24(array(
                'url' => Mage::getStoreConfig('carriers/easypack24/api_url').'parcels/'.implode(';', $parcelsCode),
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
                $parcelCollection = Mage::getModel('easypack24/easypack24')->getCollection();
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
            $parcel = Mage::getModel('easypack24/easypack24')->load($id);
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
                $parcelApi = Mage::helper('easypack24/data')->connectEasypack24(array(
                    'url' => Mage::getStoreConfig('carriers/easypack24/api_url').'parcels',
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
                        $parcelCollection = Mage::getModel('easypack24/easypack24')->getCollection();
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

    public function editAction(){
        $id = $this->getRequest()->getParam('id');
        $easypack24Model  = Mage::getModel('easypack24/easypack24')->load($id);

        if($easypack24Model->getParcelStatus() == ''){
            $this->_getSession()->addError($this->__('Parcel %s '.'must be shipment first in section sales/order', $id));
            $this->_redirect('*/*/');
            return;
        }

        if ($easypack24Model->getId() || $id == 0) {

            $parcelTargetMachineDetailDb = json_decode($easypack24Model->getParcelTargetMachineDetail());
            $parcelDetailDb = json_decode($easypack24Model->getParcelDetail());

            $allMachines = Mage::helper('easypack24/data')->connectEasypack24(
                array(
                    'url' => Mage::getStoreConfig('carriers/easypack24/api_url').'machines',
                    'methodType' => 'GET',
                    'params' => array(
                    )
                )
            );

            $parcelTargetAllMachinesId = array();
            $parcelTargetAllMachinesDetail = array();
            $machines = array();
            if(is_array(@$allMachines['result']) && !empty($allMachines['result'])){
                foreach($allMachines['result'] as $key => $machine){
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
                Mage::getSingleton('checkout/session')->setParcelTargetAllMachinesDetail($parcelTargetAllMachinesDetail);
            }

            $parcelTargetMachinesId = array();
            $parcelTargetMachinesDetail = array();
            $defaultSelect = $this->__('Select Machine..');
            if(is_array(@$machines) && !empty($machines)){
                foreach($machines as $key => $machine){
                    $parcelTargetMachinesId[$machine->id] = $machine->id.', '.@$machine->address->city.', '.@$machine->address->street;
                    $parcelTargetMachinesDetail[$machine->id] = array(
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
                }
                Mage::getSingleton('checkout/session')->setParcelTargetMachinesDetail($parcelTargetMachinesDetail);
            }else{
                Mage::register('defaultMachine', $this->__('no terminals in your city'));
            }

            if(Mage::getSingleton('adminhtml/session')->getEasypack24Data()){
                Mage::register('easypack24_data', Mage::getSingleton('adminhtml/session')->getEasypack24Data());
            }else{
                Mage::register('easypack24_data', array(
                    'id' => $easypack24Model->getId(),
                    'parcel_target_machine_id' => $easypack24Model->getParcelTargetMachineId(),
                    'parcel_description' => @$parcelDetailDb->description,
                    'parcel_size' => @$parcelDetailDb->size,
                    'parcel_status' => $easypack24Model->getParcelStatus(),
                    'parcel_id' => $easypack24Model->getParcelId()
                ));
            }

            Mage::register('parcelTargetAllMachinesId', $parcelTargetAllMachinesId);
            Mage::register('parcelTargetAllMachinesDetail', $parcelTargetAllMachinesDetail);
            Mage::register('parcelTargetMachinesId', $parcelTargetMachinesId);
            Mage::register('parcelTargetMachinesDetail', $parcelTargetMachinesDetail);
            Mage::register('defaultParcelSize', @$parcelDetailDb->size);

            Mage::register('disabledMachines', 'disabled');
            if($easypack24Model->getParcelStatus() != 'Created' || $easypack24Model->getParcelStatus() == ''){
                Mage::register('disabledParcelSize', 'disabled');
            }

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

                $easypack24Model = Mage::getModel('easypack24/easypack24')->load($postData['id']);
                $parcelTargetMachineDetailDb = json_decode($easypack24Model->getParcelTargetMachineDetail());
                $parcelDetailDb = json_decode($easypack24Model->getParcelDetail());

                // update Inpost parcel
                $params = array(
                    'url' => Mage::getStoreConfig('carriers/easypack24/api_url').'parcels',
                    'methodType' => 'PUT',
                    'params' => array(
                        'description' => $postData['parcel_description'] == @$parcelDetailDb->description?null:$postData['parcel_description'],
                        'id' => $postData['parcel_id'],
                        'size' => $postData['parcel_size'] == @$parcelDetailDb->size?null:$postData['parcel_size'],
                        'status' => $postData['parcel_status'] == $easypack24Model->getParcelStatus()?null:$postData['parcel_status'],
                        //'target_machine' => $postData['parcel_target_machine_id'] == $easypack24Model->getParcelTargetMachineId()?null:$postData['parcel_target_machine_id']
                    )
                );
                $parcelApi = Mage::helper('easypack24/data')->connectEasypack24($params);

                if(@$parcelApi['info']['http_code'] != '204'){
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
                    $easypack24Model->setParcelStatus($postData['parcel_status']);
                    $easypack24Model->setParcelDetail(json_encode(array(
                        'description' => $postData['parcel_description'],
                        'receiver' => array(
                            'email' => $parcelDetailDb->receiver->email,
                            'phone' => $parcelDetailDb->receiver->phone
                        ),
                        'size' => $postData['parcel_size'],
                        'tmp_id' => $parcelDetailDb->tmp_id,
                    )));

                    /*
                    $easypack24Model->setParcelTargetMachineId($postData['parcel_target_machine_id']);
                    $easypack24Model->setParcelTargetMachineDetail(json_encode(array(
                        'id' => $postData['parcel_target_machine_id'],
                        'address' => array(
                            'building_number' => @$parcelTargetMachineDetailDb->address->building_number,
                            'flat_number' => @$parcelTargetMachineDetailDb->address->flat_number,
                            'post_code' => @$parcelTargetMachineDetailDb->address->post_code,
                            'province' => @$parcelTargetMachineDetailDb->address->province,
                            'street' => @$parcelTargetMachineDetailDb->address->street,
                            'city' => @$parcelTargetMachineDetailDb->address->city
                        )
                    )));
                    */
                    $easypack24Model->save();
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Item was successfully saved'));
                Mage::getSingleton('adminhtml/session')->setEasypack24Data(false);
                Mage::getSingleton('adminhtml/session')->setParcelTargetMachinesDetail(false);
                Mage::getSingleton('adminhtml/session')->setParcelTargetMachinesDetail(false);

                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setEasypackData($this->getRequest()->getPost());
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
            $this->getLayout()->createBlock('easypack24/adminhtml_easypack24_grid')->toHtml()
        );
    }
}