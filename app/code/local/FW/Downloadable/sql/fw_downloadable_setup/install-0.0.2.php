<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$collectionDownloadable = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToFilter('type_id', array('eq' => 'downloadable'))
		->addAttributeToFilter('links_purchased_separately', array('neq' => '0'));

foreach( $collectionDownloadable as $product )
{
	//Detected a downloadable product being created, set links_purchased_seperately to no.
        $product->setData('links_purchased_separately', 0);
        $product->getResource()->saveAttribute($product, 'links_purchased_separately');
}

$installer->endSetup();
