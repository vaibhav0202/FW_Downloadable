<?php
/**
 * Downloadable product type model
 *
 * @category    FW
 * @package     FW_Downloadable
 * @copyright   Copyright (c) 2014 F+W Media, Inc. (http://www.fwmedia.com)
 * @author		J.P. Daniel <jp.daniel@fwmedia.com>
 */
class FW_Downloadable_Model_Product_Type extends Mage_Downloadable_Model_Product_Type
{
    /**
     * Check if product has links
     *
     * @param Mage_Catalog_Model_Product $product
     * @return boolean
     */
    public function hasLinks($product = null)
    {
        $hasLinks = parent::hasLinks($product);
        if ($hasLinks === null) {
            return count($this->getLinks($product)) > 0;
        }
        return $hasLinks;
    }
}
