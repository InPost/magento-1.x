<?php

class Inpost_Inpostparcels_Model_Mysql4_Inpostparcels extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
        $this->_init('inpostparcels/inpostparcels', 'id');
    }
}