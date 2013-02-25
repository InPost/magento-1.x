<?php

class Inpost_Easypack24_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function getActualVersion() {
        return (string) Mage::getConfig()->getNode()->modules->Inpost_Easypack24->version;
    }

    public function test(){
        echo 'test';
    }

    public function connectEasypack24($params = array()){

        $params = array_merge(
            array(
                'url' => $params['url'],
                'token' => Mage::getStoreConfig('carriers/easypack24/api_key'),
                'methodType' => $params['methodType'],
                'params' => $params['params']
            ),
            $params
        );

        $ch = curl_init();

        switch($params['methodType']){
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: GET') );
                $getParams = null;
                if(!empty($params['params'])){
                    foreach($params['params'] as $field_name => $field_value){
                        $getParams .= $field_name.'='.urlencode($field_value).'&';
                    }
                    curl_setopt($ch, CURLOPT_URL, $params['url'].'?token='.$params['token'].'&'.$getParams);
                }else{
                    curl_setopt($ch, CURLOPT_URL, $params['url'].'?token='.$params['token']);
                }
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                break;

            case 'POST':
                $string = json_encode($params['params']);
                #$string = $params['params'];
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: POST') );
                curl_setopt($ch, CURLOPT_URL, $params['url'].'?token='.$params['token']);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $string);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($string))
                );
                break;

            case 'PUT':
                $string = json_encode($params['params']);
                #$string = $params['params'];
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: PUT') );
                curl_setopt($ch, CURLOPT_URL, $params['url'].'?token='.$params['token']);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $string);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($string))
                );
                break;

        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        return array(
            'result' => json_decode(curl_exec($ch)),
            'info' => curl_getinfo($ch),
            'errno' => curl_errno($ch),
            'error' => curl_error($ch)
        );
    }

    public function generate($type = 1, $length){
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";

        if($type == 1){
            # AZaz09
            $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
        }elseif($type == 2){
            # az09
            $chars = "abcdefghijklmnopqrstuvwxyz1234567890";
        }elseif($type == 3){
            # AZ
            $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        }elseif($type == 4){
            # 09
            $chars = "0123456789";
        }

        $token = "";
            for ($i = 0; $i < $length; $i++) {
                $j = rand(0, strlen($chars) - 1);
                if($i==0 && $j == 0){
                    $j = rand(2,9);
                }
                $token .= $chars[$j];
            }
        return $token;
    }

    public function getParcelStatus(){
        return array(
            'Created' => 'Created',
            'Prepared' => 'Prepared'
        );
    }
}