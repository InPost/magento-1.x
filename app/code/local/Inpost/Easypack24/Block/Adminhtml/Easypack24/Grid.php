<?php

class Inpost_Easypack24_Block_Adminhtml_Easypack24_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('id');
        //$this->setUseAjax(true);
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('easypack24/easypack24')->getCollection();
        //$collection->addAttributeToFilter('parcel_id', array('notnull' => true));
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {

        $this->addColumn('id', array(
            'header'    => Mage::helper('easypack24')->__('ID'),
            'width'     => '10px',
            'index'     => 'id',
            'type'  => 'number'
        ));

        $this->addColumn('order_id', array(
            'header'    => Mage::helper('easypack24')->__('Order ID'),
            'width'     => '10px',
            'index'     => 'order_id',
            'width'     => '10px',
        ));

        $this->addColumn('parcel_id', array(
            'header'    => Mage::helper('easypack24')->__('Parcel ID'),
            'width'     => '10px',
            'index'     => 'parcel_id',
            'width'     => '10px',
        ));

        $this->addColumn('parcel_status', array(
            'header'    =>  Mage::helper('easypack24')->__('Status'),
            'width'     =>  '10px',
            'index'     =>  'parcel_status',
            'type'      =>  'options',
            'options'   =>  Mage::helper('easypack24')->getParcelStatus(),
        ));

        $this->addColumn('parcel_target_machine_id', array(
            'header'    => 'Machine ID',
            'width'     => '10px',
            'index'     => 'parcel_target_machine_id',
            'width'     => '10px',
        ));

        $this->addColumn('sticker_creation_date', array(
            'header'    => Mage::helper('easypack24')->__('Sticker creation date'),
            'width'     => '10px',
            'type'      => 'datetime',
            //'align'     => 'center',
            'index'     => 'sticker_creation_date',
            'gmtoffset' => true
        ));

        $this->addColumn('creation_date', array(
            'header'    => Mage::helper('easypack24')->__('Creation date'),
            'width'     => '10px',
            'type'      => 'datetime',
            //'align'     => 'center',
            'index'     => 'creation_date',
            'gmtoffset' => true
        ));

        $this->addColumn('action',
            array(
                'header'    =>  Mage::helper('easypack24')->__('Action'),
                'width'     => '10',
                'type'      => 'action',
                'getter'    => 'getId',
                'actions'   => array(
                    array(
                        'caption'   => Mage::helper('easypack24')->__('Edit'),
                        'url'       => array('base'=> '*/*/edit'),
                        'field'     => 'id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'id',
                'is_system' => true,
        ));

        //$this->addExportType('*/*/exportCsv', Mage::helper('easypack24')->__('CSV'));
        //$this->addExportType('*/*/exportXml', Mage::helper('easypack24')->__('Excel XML'));
        //$this->addExportType('*/*/exportPdf', Mage::helper('easypack24')->__('PDF'));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('parcels_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        $this->getMassactionBlock()->addItem('stickers', array(
            'label'    => Mage::helper('easypack24')->__('Parcel stickers in pdf format'),
            'url'      => $this->getUrl('*/*/massStickers')
        ));

        $this->getMassactionBlock()->addItem('status', array(
            'label'    => Mage::helper('easypack24')->__('Parcel refresh status'),
            'url'      => $this->getUrl('*/*/massRefreshStatus')
        ));

        $this->getMassactionBlock()->addItem('cancel', array(
            'label'    => Mage::helper('easypack24')->__('Cancel'),
            'url'      => $this->getUrl('*/*/massCancel'),
            'confirm'  => Mage::helper('easypack24')->__('Are you sure?')
        ));

        return $this;
    }

//    public function getRowUrl($row)
//    {
//        return $this->getUrl('*/*/edit', array('id' => $row->getRuleId()));
//    }

}
