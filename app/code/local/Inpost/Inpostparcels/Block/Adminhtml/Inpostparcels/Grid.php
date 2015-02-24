<?php

class Inpost_Inpostparcels_Block_Adminhtml_Inpostparcels_Grid extends Mage_Adminhtml_Block_Widget_Grid
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
        $collection = Mage::getModel('inpostparcels/inpostparcels')->getCollection();
        //$collection->addAttributeToFilter('parcel_id', array('notnull' => true));
        $collection->getSelect()->join(
            array('sfo' => $collection->getTable('sales/order')),
            'sfo.entity_id=order_id'
        );

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {

        $this->addColumn('id', array(
            'header'    => Mage::helper('inpostparcels')->__('ID'),
            'width'     => '10px',
            'index'     => 'id',
            'type'  => 'number'
        ));

        $this->addColumn('increment_id', array(
            'header'    => Mage::helper('inpostparcels')->__('Order ID'),
            'width'     => '10px',
            'index'     => 'increment_id',
            'width'     => '10px',
        ));

        $this->addColumn('parcel_id', array(
            'header'    => Mage::helper('inpostparcels')->__('Parcel ID'),
            'width'     => '10px',
            'index'     => 'parcel_id',
            'width'     => '10px',
        ));

        $this->addColumn('status', array(
            'header' => Mage::helper('sales')->__('Order Status'),
            'index' => 'status',
            'type'  => 'options',
            'width' => '70px',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
        ));

        $this->addColumn('parcel_status', array(
            'header'    =>  Mage::helper('inpostparcels')->__('Parcel Status'),
            'width'     =>  '10px',
            'index'     =>  'parcel_status',
            'type'      =>  'options',
            'options'   =>  Mage::helper('inpostparcels')->getParcelStatus(),
        ));

        $this->addColumn('parcel_target_machine_id', array(
            'header'    => 'Machine ID',
            'width'     => '10px',
            'index'     => 'parcel_target_machine_id',
            'width'     => '10px',
        ));

        $this->addColumn('sticker_creation_date', array(
            'header'    => Mage::helper('inpostparcels')->__('Sticker creation date'),
            'width'     => '10px',
            'type'      => 'datetime',
            //'align'     => 'center',
            'index'     => 'sticker_creation_date',
            'gmtoffset' => true
        ));

        $this->addColumn('creation_date', array(
            'header'    => Mage::helper('inpostparcels')->__('Creation date'),
            'width'     => '10px',
            'type'      => 'datetime',
            //'align'     => 'center',
            'index'     => 'creation_date',
            'gmtoffset' => true
        ));

	// Show the user if they have already created a Return Parcel for the
	// main parcel.
        $this->addColumn('return_parcel_id', array(
            'header'    => Mage::helper('inpostparcels')->__('Ret'),
            'index'     => 'return_parcel_id',
            'width'     => '10px',
            'align'     => 'center',
//	    'class'     => '',
	    'renderer'  => 'Mage_Adminhtml_Block_Inpostparcels_Renderer_Return'
        ));

        $this->addColumn('action',
            array(
                'header'    =>  Mage::helper('inpostparcels')->__('Action'),
                'width'     => '10',
                'type'      => 'action',
                'getter'    => 'getId',
                'actions'   => array(
                    array(
                        'caption'   => Mage::helper('inpostparcels')->__('Edit'),
                        'url'       => array('base'=> '*/*/edit'),
                        'field'     => 'id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'id',
                'is_system' => true,
                'renderer'  => new Inpost_Inpostparcels_Block_Adminhtml_Inpostparcels_Renderer_Link()
        ));

        //$this->addExportType('*/*/exportCsv', Mage::helper('inpostparcels')->__('CSV'));
        //$this->addExportType('*/*/exportXml', Mage::helper('inpostparcels')->__('Excel XML'));
        //$this->addExportType('*/*/exportPdf', Mage::helper('inpostparcels')->__('PDF'));

		return parent::_prepareColumns();
	}

	///
	// _prepareMassaction
	//
	protected function _prepareMassaction()
	{
		$this->setMassactionIdField('entity_id');
		$this->getMassactionBlock()->setFormFieldName('parcels_ids');
		$this->getMassactionBlock()->setUseSelectAll(false);
	
		$format = Mage::getStoreConfig('carriers/inpostparcels/label_format');
		if(strcasecmp($format, 'pdf') == 0)
		{
			$label = 'Parcel stickers in PDF format';
		}
		else
		{
			$label = 'Parcel stickers in Epl2 format';
		}

		$this->getMassactionBlock()->addItem('stickers',
			array(
			'label'    => Mage::helper('inpostparcels')->__($label),
			'url'      => $this->getUrl('*/*/massStickers')
		));

		$this->getMassactionBlock()->addItem('status', array(
		'label'    => Mage::helper('inpostparcels')->__('Parcel Refresh Status'),
		'url'      => $this->getUrl('*/*/massRefreshStatus')
		));

		$this->getMassactionBlock()->addItem('parcels', array(
		'label'    => Mage::helper('inpostparcels')->__('Create Multiple Parcels'),
		'url'      => $this->getUrl('*/*/massCreateMultipleParcels')
		));

		// Check to see if we should allow the creation of Return
		// parcel labels.
		$returns_allowed = Mage::getStoreConfig('carriers/inpostparcels/allow_return_parcels');
		$default_returns = Mage::getStoreConfig('carriers/inpostparcels/default_return_parcels');

		// Only allow the user to create returns if it is switched on
		// and default is set to be no.
		if($returns_allowed == true && $default_returns == false)
		{
			$this->getMassactionBlock()->addItem('parcels',
				array(
					'label' => Mage::helper('inpostparcels')->__('Create Multiple Returns Parcels'),
					'url'   => $this->getUrl('*/*/massCreateMultipleReturnParcels')
				)
			);
		}

		$this->getMassactionBlock()->addItem('cancel', array(
		'label'    => Mage::helper('inpostparcels')->__('Cancel'),
		'url'      => $this->getUrl('*/*/massCancel'),
		'confirm'  => Mage::helper('inpostparcels')->__('Are you sure?')
		));

		return $this;
	}

//    public function getRowUrl($row)
//    {
//        return $this->getUrl('*/*/edit', array('id' => $row->getRuleId()));
//    }

}
