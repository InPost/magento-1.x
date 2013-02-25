<?php

class Inpost_Easypack24_Adminhtml_Easypack24Controller extends Mage_Adminhtml_Controller_Action
{

    protected function _initAction() {
        $this->loadLayout()
            ->_setActiveMenu('sales/easypack24');
            //->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));

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


}