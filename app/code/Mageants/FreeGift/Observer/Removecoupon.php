<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */
 
namespace Mageants\FreeGift\Observer;

use Magento\Framework\Event\ObserverInterface;

class Removecoupon implements ObserverInterface
{
    /**
     * @var \Mageants\FreeGift\Helper\Data
     */
    protected $_freeGiftHelper;
    
    /**
     * __construct
     * @param \Magento\SalesRule\Model\Rule          $rule
     * @param \Magento\Checkout\Model\Session        $checkoutSession
     * @param \Magento\SalesRule\Model\CouponFactory $couponFactory
     * @param \Magento\Checkout\Model\Cart           $cart
     * @param \Magento\Quote\Model\Quote\Item        $itemModel
     */
    public function __construct(
        \Magento\SalesRule\Model\Rule $rule,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\SalesRule\Model\CouponFactory $couponFactory,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Quote\Model\Quote\Item $itemModel
    ) {
        $this->_rule = $rule;
        $this->_checkoutSession = $checkoutSession;
        $this->couponFactory = $couponFactory;
        $this->_cart = $cart;
        $this->itemModel = $itemModel;
    }

    /**
     * Execute
     *
     * @param  \Magento\Framework\Event\Observer $observer
     * @return mixed
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $request = $observer->getEvent()->getData('request');
        if ($request->getParam('remove')) {
                
                $coupon = $this->couponFactory->create();
                $couponcodes = $coupon->load($request->getParam('freegift-coupon_code'), 'code');
            if (strpos($this->_checkoutSession->getQuote()->getAppliedRuleIds(), $couponcodes->getRuleId())!==false) {

                $rules = $this->_rule->load($couponcodes->getRuleId());
                if ($rules->getSimpleAction()=='add_free_item' && (int)$rules->getCouponType()== 2) {
                       
                    $qty = $rules->getDiscountAmount();
                    $freeGiftItem = $this->_cart->getQuote()->getAllItems();
                       
                    $checkoutSession = $this->getCheckoutSession();
                    $allItems = $checkoutSession->getQuote()->getAllItems();//returns all teh items in session
                    foreach ($allItems as $item) {
                        $itemId = $item->getItemId();//item id of particular item
                        if (strpos($rules->getFreeGiftSku(), $item->getSku())!==false) {
                            //load particular item which you want to delete by his item id
                            $quoteItem=$this->getItemModel()->load($itemId);
                            $quoteItem->delete();//deletes the item
                            $this->_cart->removeItem($itemId);
                        }
                    }
                        
                }
                    
            }
                
        }
    }

    /**
     * GetItemModel
     *
     * @return mixed
     */
    public function getItemModel()
    {
        // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
        $itemModel = $this->itemModel;//Quote item model to load quote item
        return $itemModel;
    }

    /**
     * GetCheckoutSession
     *
     * @return mixed
     */
    public function getCheckoutSession()
    {
        // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
        $checkoutSession = $this->_checkoutSession;//checkout session
        return $checkoutSession;
    }
}
