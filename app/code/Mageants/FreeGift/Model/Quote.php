<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */
 
namespace Mageants\FreeGift\Model;

class Quote extends \Magento\Quote\Model\Quote
{
    /**
     * Retrieve item model object by item identifier
     *
     * @param   int $itemId
     * @return  \Magento\Quote\Model\Quote\Item|false
     */
    public function getItemById($itemId)
    {
        foreach ($this->getItemsCollection() as $item) {
            if ($item->getId() == $itemId) {
                return $item;
            }
        }

        return false;
    }
}
