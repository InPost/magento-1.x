<?php

class Inpost_Inpostparcels_Model_Mysql4_Inpostparcels_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('inpostparcels/inpostparcels');
    }
}