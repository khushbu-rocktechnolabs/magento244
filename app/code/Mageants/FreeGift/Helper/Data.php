<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */
// @codingStandardsIgnoreFile
namespace Mageants\FreeGift\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
  /**
   * @var \Magento\Framework\App\Config\ScopeConfigInterface
   */
    protected $scopeConfig;
  
  /**
   * @var \Magento\Framework\Registry
   */
    protected $_registry;
  
  /**
   * @var \Magento\Checkout\Model\Session
   */
    protected $checkoutSession;
    
  /**
   * @var \Magento\SalesRule\Model\ResourceModel\Rule\Collection
   */
    protected $ruleCollection;
    
    /**
     * @var \Magento\Quote\Model\Quote\Item\Factory
     */
    protected $quoteItemFactory;
    
    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;
    
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $_cart;
  
  /**
   * @var \Magento\SalesRule\Model\Rule
   */
    protected $_rule;
    
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;
    
    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory $ruleCollectionFactory
     * @param \Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\SalesRule\Model\Rule $rule
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory $ruleCollectionFactory,
        \Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\SalesRule\Model\Rule $rule,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null
    ) {
        parent::__construct($context);
        $this->scopeConfig = $context->getScopeConfig();
        $this->_registry = $registry;
        $this->checkoutSession = $checkoutSession;
        $this->ruleCollectionFactory = $ruleCollectionFactory;
        $this->quoteItemFactory = $quoteItemFactory;
        $this->quoteFactory = $quoteFactory;
        $this->productFactory = $productFactory;
        $this->_cart = $cart;
        $this->_rule = $rule;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\Json::class);
    }
  
    /**
     * GetFreeGiftConfig Get Store Config Value
     *
     * @param  mixed $configPath
     * @return mixed
     */
    public function getFreeGiftConfig($configPath)
    {
        return $this->scopeConfig->getValue(
            $configPath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    /**
     * GetProduct
     *
     * @return Current Product
     */
    public function getProduct()
    {
        return $this->_registry->registry('current_product');
    }
    
    /**
     * GetProductBasedValidRuleIds
     *
     * @return array
     */
    public function getProductBasedValidRuleIds()
    {
        $validRules = [];
        $currentQuote = $this->checkoutSession->getQuote();
        $quoteItem = $this->quoteItemFactory->create();
        $quoteItem->setProduct($this->getProduct());
        $quoteItem->setStoreId($currentQuote->getStoreId());
        $quoteItem->setIsVirtual(false);
        $quoteItem->setQuote($currentQuote);
        $quoteItem->setAllItems([$quoteItem]);
        
        /** @var \Magento\SalesRule\Model\Rule $rule */
        foreach ($this->getRulesCollection() as $rule) {

            if ($rule->getActions()->validate($quoteItem)) {
                $validRules[] = $rule->getId();
            }
        }
        return $validRules;
    }
  
    /**
     * GetCartBasedValidRuleIds
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCartBasedValidRuleIds()
    {
        $validRules = [];
        $product = $this->_registry->registry('current_product');
        
        $product = clone $product;
        if ($product->isSalable()) {
            if ($product->getTypeId() == 'configurable') {
                $configProduct = $this->productFactory->create()->load($product->getId());
              
                $childrenProducts = $configProduct->getTypeInstance()->getUsedProductIds($configProduct);
                if (count($childrenProducts) > 0) {
                    $productId = end($childrenProducts);
                    $product = $this->productFactory->create()->load($productId);
                }
            }
                
                $currentQuote = $this->checkoutSession->getQuote();

                $afterQuote = $this->quoteFactory->create()
                    ->merge($currentQuote);

                $afterQuote->addProduct($product);
                $afterQuote->collectTotals();
          
                $currentRules = [];

                /**
                 * validate rules according to current quote
                 */
            foreach ($this->getRulesCollection() as $rule) {
                foreach ($currentQuote->getItemsCollection() as $item) {
                    if ($item->getProduct()->getId() == $product->getId()) {
                        if ($rule->getActions()->validate($item)) {
                            $currentRules[] = $rule->getId();
                        }
                    }
                }
            }

                /**
                 * match with quote after add current product
                 */
            foreach ($this->getRulesCollection() as $rule) {
                if (!in_array($rule->getId(), $currentRules)) {
                    foreach ($afterQuote->getItemsCollection() as $item) {
                        if ($item->getProduct()->getId() == $product->getId()) {
                            if ($rule->getActions()->validate($item)) {
                                $validRules[] = $rule->getId();
                            }
                        }
                    }
                }
            }
                
                /**
                 * match qty condition
                 */
                $quoteSummeryQty = $currentQuote->getItemsSummaryQty();
                
                $ruleCollections = $this->_rule->getCollection()->addFilter('is_active', 1);

            foreach ($ruleCollections as $ruleCollection) {
                  
                $getActionsSerialize = $this->serializer->unserialize($ruleCollection->getActionsSerialized());
                if (isset($getActionsSerialize['conditions'])) {
                    foreach ($getActionsSerialize['conditions'] as $conditions) {
                        if ($conditions['attribute'] == 'quote_item_qty') {
                            $opt = $conditions['operator'];
                            if ($opt == '==') {
                                if ($conditions['value'] == $quoteSummeryQty) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            } elseif ($opt == '!=') {
                                if ($conditions['value'] != $quoteSummeryQty) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            } elseif ($opt == '>=') {
                                if ($quoteSummeryQty >= $conditions['value']) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            } elseif ($opt == '<=') {
                                if ($quoteSummeryQty <= $conditions['value']) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            } elseif ($opt == '>') {
                                if ($quoteSummeryQty > $conditions['value']) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            } elseif ($opt == '<') {
                                if ($quoteSummeryQty < $conditions['value']) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            } elseif ($opt == '{}') {
                                if (strpos($quoteSummeryQty, $conditions['value']) !== false) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            } elseif ($opt == '!{}') {
                                if (strpos($quoteSummeryQty, $conditions['value']) === false) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            } elseif ($opt == '()') {
                                $actionConditionValues = explode(',', $conditions['value']);
                                if (in_array($quoteSummeryQty, $actionConditionValues)) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            } elseif ($opt == '!()') {
                                $actionConditionValues = explode(',', $conditions['value']);
                                if (!in_array($quoteSummeryQty, $actionConditionValues)) {
                                    array_push($validRules, $ruleCollection->getRuleId());
                                }
                            }
                        }
                    }
                }
            }
        }
        return array_unique($validRules);
    }
    
    /**
     * GetValidRules
     *
     * @return array|\Magento\SalesRule\Model\ResourceModel\Rule\Collection[]
     */
    public function getValidRules()
    {
        $productBasedValidRuleIds = $this->getProductBasedValidRuleIds();
        $cartBasedValidRuleIds = $this->getCartBasedValidRuleIds();
        $validRulesIds = array_unique(array_merge($productBasedValidRuleIds, $cartBasedValidRuleIds));
        $validRules = $this->ruleCollectionFactory->create()
                        ->addFieldToFilter('rule_id', ['in' => $validRulesIds]);

        return $validRules;
    }
  
    /**
     * GetRulesCollection
     *
     * @return $this|\Magento\SalesRule\Model\ResourceModel\Rule\Collection
     */
    public function getRulesCollection()
    {
        if ($this->ruleCollection === null) {
            $currentQuote = $this->checkoutSession->getQuote();

            $this->ruleCollection = $this->ruleCollectionFactory->create()
                ->setValidationFilter(
                    $currentQuote->getStore()->getWebsiteId(),
                    $currentQuote->getCustomerGroupId(),
                    $currentQuote->getCouponCode()
                )
                ->addFilter('is_active', 1);
        }
        return $this->ruleCollection;
    }
    
    /**
     * UpdateConfigFreeGiftItem
     *
     * @return mixed
     */
    public function updateConfigFreeGiftItem()
    {
        $allQuoteItems = $this->_cart->getQuote()->getAllItems();
        foreach ($allQuoteItems as $item) {
            if ($item->getParentItemId() != null && $item->getIsFreeItem() == 1) {
                $parentItem = $this->_cart->getQuote()->getItemById($item->getParentItemId());
                $parentItem->setPrice(0);
                $parentItem->setBasePrice(0);
                $parentItem->setCustomPrice(0);
                $parentItem->setOriginalCustomPrice(0);
                $parentItem->setPriceInclTax(0);
                $parentItem->setBasePriceInclTax(0);
                $parentItem->setIsFreeItem(1);
                $parentItem->setParentProductId($item->getParentProductId());
                $parentItem->save();
            }
        }
    }
    
    /**
     * GetFreeQuoteItems
     *
     * @return mixed
     */
    public function getFreeQuoteItems()
    {
        $freeQuoteItems = $this->checkoutSession->getQuote()->getItemsCollection();

        $itemId = '';
        foreach ($freeQuoteItems as $freeItems) {
            if ($freeItems->getProductId() == $this->getProduct()->getId()) {
                $itemId = $freeItems->getItemId();
                break;
            }
        }
    
        $quoteFreeProductIds = [];
        foreach ($freeQuoteItems as $freeItems) {
            if ($freeItems->getParentProductId() == $itemId) {
                $quoteFreeProductIds[] = $freeItems->getProductId();
            }
        }
        return $quoteFreeProductIds;
    }
    
    /**
     * GetCartBasedValidRuleOnAddtoCart
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCartBasedValidRuleOnAddtoCart()
    {
        $subtotalDisplay = $this->getFreeGiftConfig('tax/cart_display/subtotal');
        $validRules = [];
        $ruleCollections = $this->_rule->getCollection()->addFilter('is_active', 1);
        $valid = true;
        $skus = [];
        $valid_qty_subtotal = [];
        $quoteTotals = $this->_cart->getQuote()->collectTotals()->getTotals();
        $subtotal = $quoteTotals['subtotal']->getValue();
        // display for exclude tax subtotal
        if ($subtotalDisplay == 1) {
            $subtotalExcl = $quoteTotals['subtotal']->getValue();
            $subtotal_include_Tax = $subtotal + $quoteTotals['tax']->getValue();
        }
        if ($subtotalDisplay == 2 || $subtotalDisplay == 3) {
            $subtotal_include_Tax = $quoteTotals['subtotal']->getValue();
            $subtotalExcl = $subtotal - $quoteTotals['tax']->getValue();
        }

        $rulInc = 0;
        $nocondition = [];
        foreach ($ruleCollections as $ruleCollection) {
            
            $getActionsSerialize = $this->serializer->unserialize($ruleCollection->getActionsSerialized());
            $conditionSerialized = $ruleCollection->getConditionsSerialized();

            if ($ruleCollection->getSimpleAction()=='add_free_item' && (int)$ruleCollection->getCouponType()!== 2) {
                $rulInc++;
                $cond = $this->serializer->unserialize($conditionSerialized);
                $validArray=[];
                $aggregator='';
                $result = null;

                if (array_key_exists('aggregator', $cond)) {
                    $aggregator = $cond['aggregator'];
                    $result  = $cond['value'];
                }
                if (!array_key_exists('conditions', $cond)) {
                    $nocondition[$ruleCollection->getId()] =  $ruleCollection->getFreeGiftSku();
                }
                if (array_key_exists('conditions', $cond)) {
                    foreach ($cond['conditions'] as $rulecond) {
                    
                        if ($rulecond['attribute'] == 'total_qty') {
                            $valid = false;
                            $code = 'if((int)$this->_cart->getQuote()->getItemsQty()
                         '.$rulecond['operator'].' (int)$rulecond["value"]){$valid=true; $validArray[] = true; }else{$validArray[] = false;  $valid=false;}';
                            eval($code);
                        }

                        if ($rulecond['attribute'] == 'base_subtotal') {
                            $valid = false;
                            $code = 'if((int)$subtotal '.$rulecond['operator'].' (int)$rulecond["value"]){$valid=true; $validArray[] = true; }else{$validArray[] = false; $valid=false;}';
                            eval($code);//phpcs:enable
                        
                        }
                        if ($rulecond['attribute'] == 'base_subtotal_with_discount') {
                            $valid = false;
                            $code = 'if((int)$subtotalExcl '.$rulecond['operator'].' (int)$rulecond["value"]){$valid=true; $validArray[] = true; }else{$validArray[] = false; $valid=false;}';
                            eval($code);
                        
                        }
                        if ($rulecond['attribute'] == 'base_subtotal_total_incl_tax') {
                            $valid = false;
                            $code = 'if((int)$subtotal_include_Tax '.$rulecond['operator'].' (int)$rulecond["value"]){$valid=true; $validArray[] = true; }else{$validArray[] = false; $valid=false;}';
                            eval($code);
                        
                        }
                    }
                            
                    if ($aggregator == 'all') {
                        if ($result) {
                            if (in_array(0, $validArray) || in_array(false, $validArray) || in_array('', $validArray)) {
                                $valid_qty_subtotal[]= false;
                                $valid=false;

                            } else {
                                $valid=true;
                                $valid_qty_subtotal[]= true;
                                $skus[$ruleCollection->getId()] = $ruleCollection->getFreeGiftSku();
                            }
                        } else {
                            if (in_array(1, $validArray)) {
                                $valid_qty_subtotal[]= false;
                                $valid=false;
                            } else {
                                $valid=true;
                                $valid_qty_subtotal[]= true;
                                $skus[$ruleCollection->getId()] = $ruleCollection->getFreeGiftSku();
                            }
                        }
                    } elseif ($aggregator == 'any') {
                        if ($result) {
                            if (in_array($result, $validArray)) {
                                $valid=true;
                                $valid_qty_subtotal[]= true;
                                $skus[$ruleCollection->getId()] = $ruleCollection->getFreeGiftSku();

                            } else {
                                $valid=false;
                                $valid_qty_subtotal[]= false;
                            }
                        } else {
                            if (in_array(0, $validArray)) {
                                $valid=true;
                                $valid_qty_subtotal[]= true;
                                $skus[$ruleCollection->getId()] = $ruleCollection->getFreeGiftSku();
                                ;
                            } else {
                                $valid=false;
                                $valid_qty_subtotal[]= false;
                            }
                        }
                    }
                }

            }
        }
        $validation['valid'] = $valid;
        $validation['totalrule'] = $rulInc;
        $validation['valid_qty_subtotal'] = $valid_qty_subtotal;
        $validation['skus'] = $skus;
        if (!empty($nocondition)) {
            $validation['free_gift_sku'] = $nocondition;
        }
                
        return $validation;
    }
}
