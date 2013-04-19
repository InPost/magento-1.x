<?php

$installer = $this;

$installer->startSetup();

$installer->run("
	CREATE TABLE IF NOT EXISTS {$this->getTable('order_shipping_inpostparcels')} (
	  `id` int(11) unsigned NOT NULL auto_increment,
	  `order_id` int(11) NOT NULL,
	  `parcel_id` varchar(200) NOT NULL default '',
	  `parcel_status` varchar(200) NOT NULL default '',
	  `parcel_detail` text NOT NULL default '',
	  `parcel_target_machine_id` varchar(200) NOT NULL default '',
	  `parcel_target_machine_detail` text NOT NULL default '',
      `sticker_creation_date` TIMESTAMP NULL DEFAULT NULL,
      `creation_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	  `api_source` varchar(3) NOT NULL default '',
	  `variables` text NOT NULL default '',
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup(); 