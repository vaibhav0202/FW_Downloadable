<?php
/**
 * @category    FW
 * @package     FW_Downloadable
 * @copyright   Copyright (c) 2013 F+W Media, Inc. (http://www.fwmedia.com)
 * @author		J.P. Daniel <jp.daniel@fwmedia.com>
 */
class FW_Downloadable_Model_Observer
{
    /**
     * Observe the creation of a new product record, set the purchase links seperately value to no
     * @param Varien_Event_Observer $observer
     * @return FW_Downloadable_Model_Observer
     */
    public function handleDefaultLinksPurchasedSeperately(Varien_Event_Observer $observer)
    {
	$product = $observer->getEvent()->getProduct();
	
	if( $product->getCreatedAt() == $product->getUpdatedAt() && $product->getTypeId() == "downloadable" )
	{
		//Detected a downloadable product being created, set links_purchased_seperately to no.
		$product->setData('links_purchased_separately', 0);
	}

	return $this;
    }

    /**
     * Observe the controller action that is being dispatched
     * @param Varien_Event_Observer $observer
     */
	public function onControllerAction(Varien_Event_Observer $observer)
	{
	    $errorMessage = '';                                                 // Default to no error message
        $cartHelper = Mage::helper('checkout/cart');                        // Get the cart helper object
        $controller = $observer->getControllerAction();                     // Get the controller
        $billingAddress = $controller->getRequest()->getPost('billing');    // Get billing address
        $destinationCountry = $billingAddress['country_id'];                // Get the country id
        $customerAddressId = $controller->getRequest()->getPost('billing_address_id');  // Get saved address ID
        if (!empty($customerAddressId)) {
            $customerAddress = Mage::getModel('customer/address')->load($customerAddressId);  // Load saved address
            $destinationCountry = $customerAddress->getCountryId();          // Get the country ID from saved address
        }
        $cartItems = $cartHelper->getQuote()->getAllItems();          // Get all the cart items

        //Get any items that might be restricted to the selected country
        $restrictedItems = $this->_getItemCountryRestrictions($cartItems, $destinationCountry);

        //If there are restrictions create error message and return the error
        /*if($restrictedItems !== false) 
        {
            $marketRestriction = Mage::getSingleton('fw_shipping/marketrestriction');
            $errorMessageArray = array();
            foreach($restrictedItems as $marketRestrictionValue=>$productArray)
            {
                //Add error message to errorMessageArray to create an error string later using the
                //marketRestrictionValue and the productArray that's in restrictedItems
                $errorMessageArray[] = $marketRestriction->getErrorMessage($marketRestrictionValue, $productArray);
            }

            //Separate all the error messages with a <br /> to have one line per error when displaying to the customer.
            $errorMessage = $errorMessageArray;
        }*/
        if ($errorMessage) {                                                                // If there was an error
            $error = array('error' => true);                                                // Set error to true for JSON response
            $error['message'] = $errorMessage;                   // Set the error message
            $controller->setFlag('', 'no-dispatch', true);                                  // Tell controller to not dispatch action
            $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));  // Set the response object
        }
    }

    //Checks to see if items can be sent to destiniation country or not.
    /*private function _getItemCountryRestrictions($cartItems, $destinationCountry) {
        $ret = array();

        //Get singleton of the Marketrestriction Object
        $marketRestriction = Mage::getSingleton('fw_shipping/marketrestriction');

        //Loop through cart items and check for restrictions.
        foreach($cartItems as $item) {
            if ($item->getProductType() == 'downloadable' || $item->getProductType() == 'grouped') { // Check if product is downloadable
                $product = Mage::getModel('catalog/product')->load($item->getProductId());

                //Grab the market restriction value that the product is set to.
                $marketRestrictionValue = $product->getMarketRestriction();

                //If the value is 0 there are no restrictions, so move on to next product
                if($marketRestrictionValue == 0)
                {
                    continue;
                }

                //Check to see if the product is restricted based on the market restriction value and destination country.
                if($marketRestriction->isRestricted($marketRestrictionValue, $destinationCountry))
                {
                    //Place in return array with the marketRestrictionValue as the key and the product name as the value.
                    $ret[$marketRestrictionValue][] = $product->getName();
                }
            }
        }
        //If data in $ret then return the array otherwise return false.
        return (count($ret) > 0) ? $ret : false;
    }*/
}
