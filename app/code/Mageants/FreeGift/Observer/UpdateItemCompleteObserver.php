<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */
// @codingStandardsIgnoreFile
namespace Mageants\FreeGift\Observer;

use Magento\Framework\Event\ObserverInterface;

class UpdateItemCompleteObserver implements ObserverInterface
{
    /**
     * @var \Magento\SalesRule\Model\Rule
     */
    protected $_rule;
    
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;
    
    /**
     * __construct
     * @param \Magento\Checkout\Model\CartFactory               $cartFactory
     * @param \Magento\SalesRule\Model\Rule                     $rule
     * @param \Mageants\FreeGift\Helper\Data                    $freeGiftHelper
     * @param \Magento\Framework\App\RequestInterface           $request
     * @param \Magento\Catalog\Model\ProductRepository          $productRepository
     * @param \Magento\Store\Model\StoreManagerInterface        $storeManager
     * @param \Magento\Checkout\Model\Session                   $checkoutSession
     * @param \Magento\Quote\Api\CartRepositoryInterface        $cartRepository
     * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
     */
    public function __construct(
        \Magento\Checkout\Model\CartFactory $cartFactory,
        \Magento\SalesRule\Model\Rule $rule,
        \Mageants\FreeGift\Helper\Data $freeGiftHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null
    ) {
        $this->_rule = $rule;
        $this->_cart = $cartFactory;
        $this->freeGiftHelper = $freeGiftHelper;
        $this->_request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->_productRepository = $productRepository;
        $this->_storeManager = $storeManager;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\Json::class);
    }

    /**
     * Execute
     *
     * @param  \Magento\Framework\Event\Observer $observer
     * @return mixed
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $subtotalDisplay = $this->freeGiftHelper->getFreeGiftConfig('tax/cart_display/subtotal');
        $ruleCollections = $this->_rule->getCollection()->addFilter('is_active', 1);
        $valid = true;
        $validArray = [];
        $cart = $this->_cart->create();
        $quoteTotals = $cart->getQuote()->collectTotals()->getTotals();
        $allitems = $cart->getQuote()->getAllItems();
        $subtotal = $quoteTotals['subtotal']->getValue();
        // display for exclude tax subtotal
        if ($subtotalDisplay == 1) {
            $subtotalExcl = $quoteTotals['subtotal']->getValue();
            $subtotal_include_Tax = $subtotal + $quoteTotals['tax']->getValue();
        }
        // display for include tax and both
        if ($subtotalDisplay == 2 || $subtotalDisplay == 3) {
            $subtotal_include_Tax = $quoteTotals['subtotal']->getValue();
            $subtotalExcl = $subtotal - $quoteTotals['tax']->getValue();
        }

        $i=0;
        foreach ($ruleCollections as $ruleCollection) {
            
            if ($ruleCollection->getSimpleAction()=='add_free_item' && (int)$ruleCollection->getCouponType()!== 2) {
                $conditionSerialized = $ruleCollection->getConditionsSerialized();
                $cond = $this->serializer->unserialize($conditionSerialized);
                $trueskus = [];
                $falsesku =[];
                $aggregator='';
                $result = null;
                
                if (array_key_exists('aggregator', $cond)) {
                    $aggregator = $cond['aggregator'];
                    $result  = $cond['value'];
                }
                if (!array_key_exists('conditions', $cond)) {
                    foreach ($allitems as $cartitems) {
                        if (strpos($ruleCollection->getFreeGiftSku(), $cartitems->getSku())!==false) {
                            $cartitems->setPrice(0);
                            $cartitems->setIsFreeItem(1);
                            $cartitems->setPrice(0);
                            $cartitems->setBasePrice(0);
                            $cartitems->setCustomPrice(0);
                            $cartitems->setOriginalCustomPrice(0);
                            $cartitems->setPriceInclTax(0);
                            $cartitems->setBasePriceInclTax(0);
                            $cartitems->getProduct()->setIsSuperMode(true);
                            $cartitems->save();
                            $cart->save();
                        }
                    }
                }

                if (array_key_exists('conditions', $cond)) {
                    foreach ($cond['conditions'] as $rulecond) {
                        if ($rulecond['attribute'] == 'total_qty') {
                            $valid = false;
                            $code = 'if((int)$cart->getQuote()->getItemsQty()
							 '.$rulecond['operator'].' (int)$rulecond["value"]){$trueskus[] = $ruleCollection->getFreeGiftSku(); $validArray[] = true; }else{ $falsesku[] = $ruleCollection->getFreeGiftSku(); $validArray[] = false; $valid = true; }';
                            eval($code);
                        }
                        if ($rulecond['attribute'] == 'base_subtotal') {
                            $valid = false;
                            $code = 'if((int)$subtotal '.$rulecond['operator'].' (int)$rulecond["value"]){ $trueskus[] = $ruleCollection->getFreeGiftSku(); $validArray[] = true; }else{ $falsesku[] = $ruleCollection->getFreeGiftSku(); $validArray[] = false; $valid=false;}';
                            eval($code);
                        }
                        if ($rulecond['attribute'] == 'base_subtotal_with_discount') {
                            $valid = false;
                            $code = 'if((int)$subtotalExcl '.$rulecond['operator'].' (int)$rulecond["value"]){ $trueskus[] = $ruleCollection->getFreeGiftSku(); $validArray[] = true; }else{ $falsesku[] = $ruleCollection->getFreeGiftSku(); $validArray[] = false; $valid=false;}';
                            eval($code);
                        }

                        if ($rulecond['attribute'] == 'base_subtotal_total_incl_tax') {
                            $valid = false;
                            $code = 'if((int)$subtotal_include_Tax '.$rulecond['operator'].' (int)$rulecond["value"]){ $trueskus[] = $ruleCollection->getFreeGiftSku(); $validArray[] = true; }else{ $falsesku[] = $ruleCollection->getFreeGiftSku(); $validArray[] = false; $valid=false;}';
                            eval($code);
                        }
                    }

                    if ($aggregator == 'all') {

                        $this->getAnyData($result, $validArray, $trueskus);

                    } elseif ($aggregator == 'any') {

                        $this->getAllData($result, $validArray, $trueskus);

                    }
                }
            }
        }
    }

    /**
     * GetAnyData
     *
     * @param  mixed $result
     * @param  mixed $validArray
     * @param  mixed $trueskus
     * @return mixed
     */
    public function getAnyData($result, $validArray, $trueskus)
    {
        $cart = $this->_cart->create();
        $allitems = $cart->getQuote()->getAllItems();

        if ($result) {
            if (in_array(false, $validArray)) {

                $cart = $this->_cart->create();
                
                $itemsCollection = $cart->getQuote()->getItemsCollection();
                
                $itemsVisible = $cart->getQuote()->getAllVisibleItems();
                
                $items = $cart->getQuote()->getAllItems();
                $remove_pro_sku = [];
                foreach ($items as $item) {
                    if ($item->getIsFreeItem() == 1) {
                        if ($item->getPrice() == '0' || $item->getPrice() == '0.0000') {
                            $remove_pro_sku[] = $item->getSku();
                        }
                    }
                }
                $sku=implode(" ", $remove_pro_sku);
                foreach ($allitems as $cartitems) {
                    $sku=implode(" ", $remove_pro_sku);
                    if (strpos($sku, $cartitems->getSku())!==false) {
                        $cart->removeItem($cartitems->getItemId())->save();
                    }
                }
            }
        } else {

            if (in_array(1, $validArray)) {
                foreach ($allitems as $cartitems) {
                    $sku=implode(" ", $trueskus);
                    if (strpos($sku, $cartitems->getSku())!==false) {
                        $cart->removeItem($cartitems->getItemId())->save();
                    }
                }
            }
        }
    }

    /**
     * GetAllData
     *
     * @param  mixed $result
     * @param  mixed $validArray
     * @param  mixed $trueskus
     * @return mixed
     */
    public function getAllData($result, $validArray, $trueskus)
    {

        if ($result) {

            if (!in_array($result, $validArray)) {

                $cart = $this->_cart->create();
                
                $itemsCollection = $cart->getQuote()->getItemsCollection();
                
                $itemsVisible = $cart->getQuote()->getAllVisibleItems();
                
                $items = $cart->getQuote()->getAllItems();
                $remove_pro_sku = [];
                foreach ($items as $item) {
                    if ($item->getPrice() == '0' || $item->getPrice() == '0.0000') {
                        $remove_pro_sku[] = $item->getSku();
                    }
                }
                $sku=implode(" ", $remove_pro_sku);
                foreach ($allitems as $cartitems) {
                    $sku=implode(" ", $remove_pro_sku);
                    if (strpos($sku, $cartitems->getSku())!==false) {
                        $cart->removeItem($cartitems->getItemId())->save();
                    }
                }
            }
        } else {
            if (!in_array(0, $validArray)) {
                foreach ($allitems as $cartitems) {
                    $sku=implode(" ", $trueskus);
                    if (strpos($sku, $cartitems->getSku())!==false) {
                        $cart->removeItem($cartitems->getItemId())->save();
                    }
                }
            }
        }
    }
}
