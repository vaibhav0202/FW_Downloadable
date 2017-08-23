<?php
require_once Mage::getModuleDir('controllers','Mage_Downloadable') . DS . 'DownloadController.php';

class FW_Downloadable_DownloadController extends Mage_Downloadable_DownloadController
{
    /**
     * Download link action
     */
    public function linkAction()
    {
        $id = $this->getRequest()->getParam('id', 0);
        $linkPurchasedItem = Mage::getModel('downloadable/link_purchased_item')->load($id, 'link_hash');
        if (! $linkPurchasedItem->getId() ) { //if request ling doesnt exist
            $this->_getCustomerSession()->addNotice(Mage::helper('downloadable')->__("Requested link does not exist."));
            return $this->_redirect('*/customer/products');
        }
        if (!Mage::helper('downloadable')->getIsShareable($linkPurchasedItem)) {//if not shareable
            $customerId = $this->_getCustomerSession()->getCustomerId();
            if (!$customerId) { //if not logged in, do stuff then return
                $product = Mage::getModel('catalog/product')->load($linkPurchasedItem->getProductId()); //load product
                if ($product->getId()) {
                    $notice = Mage::helper('downloadable')->__(
                        'Please log in to download your product or purchase <a href="%s">%s</a>.',
                        $product->getProductUrl(), $product->getName()
                    );
                } else {
                    $notice = Mage::helper('downloadable')->__('Please log in to download your product.');
                }
                $this->_getCustomerSession()->addNotice($notice);
                $this->_getCustomerSession()->authenticate($this);
                $this->_getCustomerSession()->setBeforeAuthUrl(Mage::getUrl('downloadable/customer/products/'),
                    array('_secure' => true)
                );
                return ;
            }
            $linkPurchased = Mage::getModel('downloadable/link_purchased')->load($linkPurchasedItem->getPurchasedId());
            if ($linkPurchased->getCustomerId() != $customerId) { //if person logged is is not the same person that purchased it, then notify and redirect
                $this->_getCustomerSession()->addNotice(Mage::helper('downloadable')->__("Requested link does not exist."));
                return $this->_redirect('*/customer/products');
            }
        }
        $downloadsLeft = $linkPurchasedItem->getNumberOfDownloadsBought()
            - $linkPurchasedItem->getNumberOfDownloadsUsed(); //calculate how many downloads are left based off bought - previously purchased

        $status = $linkPurchasedItem->getStatus();
        if ($status == Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE ///if all flags allow the download to proceed
            && ($downloadsLeft || $linkPurchasedItem->getNumberOfDownloadsBought() == 0)
        ) {
            $resource = '';
            $resourceType = '';
            if ($linkPurchasedItem->getLinkType() == Mage_Downloadable_Helper_Download::LINK_TYPE_URL) {
                $resource = $linkPurchasedItem->getLinkUrl();
                $resourceType = Mage_Downloadable_Helper_Download::LINK_TYPE_URL;
            } elseif ($linkPurchasedItem->getLinkType() == Mage_Downloadable_Helper_Download::LINK_TYPE_FILE) {
                $resource = Mage::helper('downloadable/file')->getFilePath(
                    Mage_Downloadable_Model_Link::getBasePath(), $linkPurchasedItem->getLinkFile()
                );
                $resourceType = Mage_Downloadable_Helper_Download::LINK_TYPE_FILE;
            }
            try {
                
                //Leaving the the core way if a product has a non-aws url
                //the media2 url check is because this code was launched before moving all the files to new buckets and using those bucket names in the url setup on the magento admin side
                if(strrpos($linkPurchasedItem->getLinkUrl(), 'amazonaws') === false &&  strrpos($linkPurchasedItem->getLinkUrl(), 'media2.fwpublications.com') === false) 
                {
                    $this->_processDownload($resource, $resourceType);
                }
                else
                {
                    // Custom Code, if a AWS link then direct to that instead of Mage Core functionality
                    $this->getResponse()->setRedirect($this->getDownloadUrl($linkPurchasedItem))->sendResponse();
                }
                    
                $linkPurchasedItem->setNumberOfDownloadsUsed($linkPurchasedItem->getNumberOfDownloadsUsed() + 1);

                if ($linkPurchasedItem->getNumberOfDownloadsBought() != 0 && !($downloadsLeft - 1)) {
                    $linkPurchasedItem->setStatus(Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_EXPIRED);
                }
                $linkPurchasedItem->save();
                exit(0);
            }
            catch (Exception $e) {
                $this->_getCustomerSession()->addError(
                    Mage::helper('downloadable')->__('An error occurred while getting the requested content. Please contact the store owner.')
                );
            }
        } elseif ($status == Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_EXPIRED) {
            $this->_getCustomerSession()->addNotice(Mage::helper('downloadable')->__('The link has expired.'));
        } elseif ($status == Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_PENDING
            || $status == Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_PAYMENT_REVIEW
        ) {
            $this->_getCustomerSession()->addNotice(Mage::helper('downloadable')->__('The link is not available.'));
        } else {
            $this->_getCustomerSession()->addError(
                Mage::helper('downloadable')->__('An error occurred while getting the requested content. Please contact the store owner.')
            );
        }
        return $this->_redirect('*/customer/products');
    }
    
    protected function getDownloadUrl(Mage_Downloadable_Model_Link_Purchased_Item $item)
    {
        //if a file is private on AWS upon purchase it enforces the expiration even if you set the file to public after the fact
        //if a file is public on AWS upon purchase it enforces the expiration but if you remove the signed portion of the url it gives access
        //when url is handled in the mage core way it has to be non-https and it shows munched url to user, aws or not
        //if we handle all urls this way, the source url will always show to user

        $source = $item->getLinkUrl();
        $expires   = time() + (int)Mage::getStoreConfig('catalog/downloadable/s3_link_duration');

        $apiSecret = Mage::helper('core')->decrypt(Mage::getStoreConfig('catalog/downloadable/s3_api_secret'));
        
        //parse the url to account for 2 formats of the url
        //Example 1: http://downloads.fwmedia.s3.amazonaws.com/December_2011.pdf
        //Example 2: http://s3.amazonaws.com/downloads.fwmedia/December_2011.pdf
        //This is because 3rd party products like CloudBerry present the url for a S3 file as example 1 but the S3 AWS console url is presented as example 2 and its the example 2 that the signed url expects
        //Most users do not have access to the AWS console. Also the bucket name is not a config item because download files might be moved or multiple buckets may end up being used 
        //URLS that are stored in the Magento system need to be non-https
        $bucketAndFile = "";
        $bucketAndFile = str_replace(array('.s3.amazonaws.com','s3.amazonaws.com/','http://','https://'), '', $source);

        $apiKey    = Mage::getStoreConfig('catalog/downloadable/s3_api_key');
        $stringToSign = "GET\n\n\n$expires\n/$bucketAndFile";
        $signature = urlencode(base64_encode(hash_hmac('sha1',utf8_encode($stringToSign),$apiSecret,true)));
        
        $source = preg_replace('/^https:\/\/(.*)\.s3.amazonaws.com\/(.*)$/','https://s3.amazonaws.com/$1/$2',$source);

        $linkUrl = sprintf($source.'?AWSAccessKeyId=%s&Expires=%s&Signature=%s',
            $apiKey,
            $expires,
            $signature
        ); 
     
        return $linkUrl;
    }

}
