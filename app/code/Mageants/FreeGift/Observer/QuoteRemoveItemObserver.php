<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */

namespace Mageants\FreeGift\Observer;

use Magento\Framework\Event\ObserverInterface;

class QuoteRemoveItemObserver implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $_cart;

    /**
     * @var $codeLength
     */
    protected $codeLength;

    /**
     * __construct
     * @param \Magento\Framework\App\RequestInterface    $request
     * @param \Magento\Checkout\Model\Cart               $cart
     * @param \Mageants\FreeGift\Helper\Data             $freeGiftHelper
     * @param \Magento\SalesRule\Model\Rule              $rule
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Checkout\Model\Cart $cart,
        \Mageants\FreeGift\Helper\Data $freeGiftHelper,
        \Magento\SalesRule\Model\Rule $rule,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {
        $this->_request = $request;
        $this->_cart = $cart;
        $this->_freeGiftHelper = $freeGiftHelper;
        $this->_rule = $rule;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Execute
     *
     * @param  \Magento\Framework\Event\Observer $observer
     * @return mixed
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
         /** @var \Magento\Quote\Model\Quote\Item $item */
        $item = $observer->getEvent()->getQuoteItem();
        $freeGiftItem = $this->_cart->getQuote()->getAllItems();
        
        $cartQuote = $this->_cart->getQuote();
        $oldCouponCode = $cartQuote->getCouponCode();
        
        if ($this->_request->getActionName() == 'delete') {
            $GetRule = $this->_freeGiftHelper->getCartBasedValidRuleOnAddtoCart();
            $Valid=$GetRule['valid'];

            if ($oldCouponCode) {
                $codeLength = strlen($oldCouponCode);
                if ($codeLength) {
                    foreach ($freeGiftItem as $freeItem1) {
                        if ($freeItem1->getPrice()=='0.0000') {
                            $this->_cart->removeItem($freeItem1->getItemId());
                            $this->_cart->save();
                        }
                    }
                }
            }

            foreach ($freeGiftItem as $freeItem) {
                if ($this->_request->getParam('id') == $freeItem->getItemId()) {
                    $this->_cart->removeItem($freeItem->getItemId());
                }
            }
            // $this->_cart->save();

            if ($Valid=='') {
                foreach ($freeGiftItem as $freeItem1) {
                    if ($freeItem1->getPrice()=='0.0000') {
                        $this->_cart->removeItem($freeItem1->getItemId());
                        $this->_cart->save();
                    }
                }
            }
        }
                
        if ($this->_request->getActionName() == 'removeItem') {
            $GetRule = $this->_freeGiftHelper->getCartBasedValidRuleOnAddtoCart();
            $Valid=$GetRule['valid'];
            foreach ($freeGiftItem as $freeItem) {
                if ($this->_request->getParam('item_id') == $freeItem->getItemId()) {
                    $this->_cart->removeItem($freeItem->getItemId());
                }
            }
            // $this->_cart->save();

            if ($Valid=='') {
                
                foreach ($freeGiftItem as $freeItem1) {
                    if ($freeItem1->getPrice()=='0.0000') {
                        $this->_cart->removeItem($freeItem1->getItemId());
                        $this->_cart->save();
                    }
                }
            }
        }

        $this->ApiQuoteRemoveItemObserver($observer);
    }

    /**
     * ApiQuoteRemoveItemObserver
     *
     * @param mixed $observer
     */
    public function ApiQuoteRemoveItemObserver($observer)
    {
        /** @var \Magento\Quote\Model\Quote\Item $item */
        $item = $observer->getEvent()->getQuoteItem();
        $quote = $this->quoteRepository->get($item->getQuoteId());
        $freeGiftItem = $quote->getAllItems();

        foreach ($freeGiftItem as $freeItem) {
            if ($item->getId() == $freeItem->getParentProductId()) {
                $quote->removeItem($freeItem->getItemId());
            }
        }
        $quote->save();
    }
}
