<?php

class Inpost_Inpostparcels_Model_Carrier_Inpostparcels  extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    /**
     * unique internal shipping method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'inpostparcels';

    /**
     * Collect rates for this shipping method based on information in $request
     *
     * @param Mage_Shipping_Model_Rate_Request $data
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
        if (!Mage::getStoreConfig('carriers/'.$this->_code.'/active')) {
            return false;
        }

        $handling = Mage::getStoreConfig('carriers/'.$this->_code.'/handling');
        $result = Mage::getModel('shipping/rate_result');
        $show = true;
        $error_message = $this->getConfigData('specificerrmsg');

        $cart = Mage::getModel('checkout/cart')->getQuote();
        $maxWeight = 0;
        $maxDimensions = array();
        foreach ($cart->getAllItems() as $item) {
            $maxWeight += $item->getProduct()->getWeight();
            $product_dimensions[] = (float)$item->getProduct()->getPackageWidth().'x'.(float)$item->getProduct()->getPackageHeight().'x'.(float)$item->getProduct()->getPackageDepth();
        }

        // check max weight ( all products )
        if($maxWeight != 0 && $maxWeight > Mage::getStoreConfig('carriers/inpostparcels/max_weight')){
            return false;
        }

        // check dimensions ( multiple product )
        $calculateDimension = Mage::helper('inpostparcels/data')->calculateDimensions(
            $product_dimensions,
            array(
                'MAX_DIMENSION_A' => Mage::getStoreConfig('carriers/inpostparcels/max_dimension_a'),
                'MAX_DIMENSION_B' => Mage::getStoreConfig('carriers/inpostparcels/max_dimension_b'),
                'MAX_DIMENSION_C' => Mage::getStoreConfig('carriers/inpostparcels/max_dimension_c')
            )
        );

        if(!$calculateDimension['isDimension']){
            return false;
        }

        Mage::getSingleton('checkout/session')->setParcelSize($calculateDimension['parcelSize']);

        if($show){
            $method = Mage::getModel('shipping/rate_result_method');
            $method->setCarrier($this->_code);
            $method->setMethod($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethodTitle($this->getConfigData('name'));
            //$method->setMethodTitle('Price');
            $method->setMethodDescription($this->getConfigData('desc'));
            $method->setPrice($this->getConfigData('price'));
            $method->setCost($this->getConfigData('price'));
            $result->append($method);
        }else{
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('name'));
            $error->setErrorMessage($error_message);
            $result->append($error);
        }
        return $result;
    }

    public function getAllowedMethods() {
        return array($this->_code => $this->getConfigData('name'));
    }

    public function getFormBlock(){
        return 'inpostparcels/inpostparcels';
    }
}