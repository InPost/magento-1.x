<?php

$installer = $this;

$installer->startSetup();

$installer->run("
	ALTER TABLE {$this->getTable('order_shipping_inpostparcels')} 
	ADD COLUMN `return_parcel_id` varchar(50) NOT NULL,
	ADD COLUMN `return_parcel_expiry` TIMESTAMP NOT NULL,
	ADD COLUMN `return_parcel_created` TIMESTAMP NOT NULL;
");

$installer->endSetup(); 
