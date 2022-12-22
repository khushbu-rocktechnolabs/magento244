<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */

namespace Mageants\FreeGift\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;

/**
 * Cart source
 */
class Cart extends \Magento\Checkout\CustomerData\Cart
{
    /**
     * @inheritdoc
     */
    public function getSectionData()
    {
        $totals = $this->getQuote()->getTotals();
        $quoteItemQty = [];
        foreach ($this->getQuote()->getAllVisibleItems() as $item) {
            $quoteItemQty[] = $item->getQty();
        }
        
        return [
            'summary_count' => array_sum($quoteItemQty),
            //'summary_count' => $this->getSummaryCount(),
            'subtotal' => isset($totals['subtotal'])
                ? $this->checkoutHelper->formatPrice($totals['subtotal']->getValue())
                : 0,
            'possible_onepage_checkout' => $this->isPossibleOnepageCheckout(),
            'items' => $this->getRecentItems(),
            'extra_actions' => $this->layout->createBlock(\Magento\Catalog\Block\ShortcutButtons::class)->toHtml(),
            'isGuestCheckoutAllowed' => $this->isGuestCheckoutAllowed(),
        ];
    }
}
