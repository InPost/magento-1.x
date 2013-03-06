<?php

class Inpost_Easypack24_Model_Carrier_Easypack24  extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    /**
     * unique internal shipping method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'easypack24';

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
            $maxDimensions[] = (float)$item->getProduct()->getPackageWidth().'x'.(float)$item->getProduct()->getPackageHeight().'x'.(float)$item->getProduct()->getPackageDepth();
        }

        // check max weight ( all products )
        if($maxWeight != 0 && $maxWeight > Mage::getStoreConfig('carriers/easypack24/max_weight')){
            return false;
        }

        // check dimensions ( single product )
        /*
        if(!empty($maxDimensions)){
            foreach($maxDimensions as $maxDimension){
                $dimension = explode('x', $maxDimension);
                $width = trim($dimension[0]);
                $height = trim($dimension[1]);
                $depth = trim($dimension[2]);

                if(
                    $width > Mage::getStoreConfig('carriers/easypack24/max_width') ||
                    $height > Mage::getStoreConfig('carriers/easypack24/max_height') ||
                    $depth > Mage::getStoreConfig('carriers/easypack24/max_depth')
                ){
                    return false;
                }
            }
        }
        */

        // Mage::log(var_export(array($maxWeight, $maxDimensions), 1) . '------', null, 'shipments.log');

        // check dimensions ( multiple product )
        $parcelSize = 'A';
        if(!empty($maxDimensions)){
            $maxDimensionFromConfigSizeA = explode('x', strtolower(trim(Mage::getStoreConfig('carriers/easypack24/max_dimension_a'))));
            $maxWidthFromConfigSizeA = (float)trim(@$maxDimensionFromConfigSizeA[0]);
            $maxHeightFromConfigSizeA = (float)trim(@$maxDimensionFromConfigSizeA[1]);
            $maxDepthFromConfigSizeA = (float)trim(@$maxDimensionFromConfigSizeA[2]);
            // flattening to one dimension
            $maxSumDimensionFromConfigSizeA = $maxWidthFromConfigSizeA + $maxHeightFromConfigSizeA + $maxDepthFromConfigSizeA;

            $maxDimensionFromConfigSizeB = explode('x', strtolower(trim(Mage::getStoreConfig('carriers/easypack24/max_dimension_b'))));
            $maxWidthFromConfigSizeB = (float)trim(@$maxDimensionFromConfigSizeB[0]);
            $maxHeightFromConfigSizeB = (float)trim(@$maxDimensionFromConfigSizeB[1]);
            $maxDepthFromConfigSizeB = (float)trim(@$maxDimensionFromConfigSizeB[2]);
            // flattening to one dimension
            $maxSumDimensionFromConfigSizeB = $maxWidthFromConfigSizeB + $maxHeightFromConfigSizeB + $maxDepthFromConfigSizeB;

            $maxDimensionFromConfigSizeC = explode('x', strtolower(trim(Mage::getStoreConfig('carriers/easypack24/max_dimension_c'))));
            $maxWidthFromConfigSizeC = (float)trim(@$maxDimensionFromConfigSizeC[0]);
            $maxHeightFromConfigSizeC = (float)trim(@$maxDimensionFromConfigSizeC[1]);
            $maxDepthFromConfigSizeC = (float)trim(@$maxDimensionFromConfigSizeC[2]);

            if($maxWidthFromConfigSizeC == 0 || $maxHeightFromConfigSizeC == 0 || $maxDepthFromConfigSizeC == 0){
                // bad format in admin configuration
                return false;
            }
            // flattening to one dimension
            $maxSumDimensionFromConfigSizeC = $maxWidthFromConfigSizeC + $maxHeightFromConfigSizeC + $maxDepthFromConfigSizeC;
            $maxSumDimensionsFromProducts = 0;
            foreach($maxDimensions as $maxDimension){
                $dimension = explode('x', $maxDimension);
                $width = trim(@$dimension[0]);
                $height = trim(@$dimension[1]);
                $depth = trim(@$dimension[2]);
                if($width == 0 || $height == 0 || $depth){
                    // empty dimension for product
                    continue;
                }

                if(
                    $width > $maxWidthFromConfigSizeC ||
                    $height > $maxHeightFromConfigSizeC ||
                    $depth > $maxDepthFromConfigSizeC
                ){
                    return false;
                }

                $maxSumDimensionsFromProducts = $maxSumDimensionsFromProducts + $width + $height + $depth;
                if($maxSumDimensionsFromProducts > $maxSumDimensionFromConfigSizeC){
                    return false;
                }
            }
            if($maxSumDimensionsFromProducts <= $maxDimensionFromConfigSizeA){
                $parcelSize = 'A';
            }elseif($maxSumDimensionsFromProducts <= $maxDimensionFromConfigSizeB){
                $parcelSize = 'B';
            }elseif($maxSumDimensionsFromProducts <= $maxDimensionFromConfigSizeC){
                $parcelSize = 'C';
            }
            Mage::getSingleton('checkout/session')->setParcelSize($parcelSize);
        }
        //$_SESSION['easypack24'] = $maxDimensions;

        if($show){
            $method = Mage::getModel('shipping/rate_result_method');
            $method->setCarrier($this->_code);
            $method->setMethod($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
            //$method->setMethodTitle($this->getConfigData('name'));
            $method->setMethodTitle('Price');
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
        return 'easypack24/easypack24';
    }
}