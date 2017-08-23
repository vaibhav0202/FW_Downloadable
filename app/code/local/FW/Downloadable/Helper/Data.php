<?php
/**
 * @category    FW
 * @package     FW_Downloadable
 * @copyright   Copyright (c) 2013 F+W Media, Inc. (http://www.fwmedia.com)
 */
class FW_Downloadable_Helper_Data extends Mage_Core_Helper_Abstract 
{
     /**
     * @param Mage_Customer_Model_Customer $customer
     * @return Mage_Downloadable_Model_Resource_Link_Purchased_Collection
     */
    public function getCustomerDownloads(Mage_Customer_Model_Customer $customer)
    {
        return Mage::getResourceModel('downloadable/link_purchased_collection')
            ->addFieldToFilter('customer_id',$customer->getId());
    }
    
    public function canShowDownloadWarning(Mage_Sales_Model_Quote_Item $item)
    {

        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $customer = $quote->getCustomer();

        if(!$customer || !$customer->getId()) {
            return false;
        }

        $customerDownloads = $this->getCustomerDownloads($customer);
        if($item->getProductType() == 'configurable' || $item->getProductType() == 'bundle'){
            foreach($item->getChildren() as $child){
                if($customerDownloads->getItemByColumnValue('product_sku',$child->getSku())){
                    return true;
                }
            }
            return false;
        } else {
          if($customerDownloads->getItemByColumnValue('product_sku',$item->getSku())){
                return true;
          }
        }
        return false;

    }
}
