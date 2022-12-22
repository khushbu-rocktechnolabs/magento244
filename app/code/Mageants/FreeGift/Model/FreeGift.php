<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */

namespace Mageants\FreeGift\Model;

use Mageants\FreeGift\Api\FreeGiftRepositoryInterface;
use Psr\Log\LoggerInterface;

class FreeGift implements FreeGiftRepositoryInterface
{
    /**
     * @var \Mageants\FreeGift\Helper\Data
     */
    protected $_freeGiftHelper;

    /**
     * @var \Mageants\FreeGift\Block\Freegift
     */
    protected $_freeGiftBlock;

    /**
     * @var \Magento\Framework\View\Asset\
     */
    protected $_assetRepo;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $_productRepository;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Quote\Model\Quote\Item\Factory
     */
    protected $quoteItemFactory;

    /**
     * @var \Magento\SalesRule\Model\ResourceModel\Rule\Collection
     */
    protected $ruleCollection;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \Magento\SalesRule\Model\Rule
     */
    protected $_rule;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;
    
    /**
     * __construct
     * @param \Magento\Framework\View\Element\Template\Context              $context
     * @param \Mageants\FreeGift\Helper\Data                                $freeGiftHelper
     * @param \Mageants\FreeGift\Block\Freegift                             $freeGiftBlock
     * @param \Magento\Framework\View\Asset\Repository                      $assetRepo
     * @param \Magento\Catalog\Model\ProductRepository                      $productRepository
     * @param \Magento\Checkout\Model\Session                               $checkoutSession
     * @param \Magento\Quote\Model\Quote\ItemFactory                        $quoteItemFactory
     * @param \Magento\Quote\Model\QuoteFactory                             $quoteFactory
     * @param \Magento\SalesRule\Model\Rule                                 $rule
     * @param \Magento\Framework\Serialize\Serializer\Json|null             $serializer
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface          $maskedQuoteIdToQuoteId
     * @param \Magento\Quote\Api\CartRepositoryInterface                    $quoteRepository
     * @param \Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory $ruleCollectionFactory
     * @param \Magento\Framework\DataObject                                 $dataObject
     * @param \Magento\SalesRule\Model\CouponFactory                        $couponFactory
     * @param \Magento\ConfigurableProduct\Model\Product\Type\Configurable  $configurableModel
     * @param \Magento\Catalog\Helper\Product\Configuration                 $configurationHelper
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Mageants\FreeGift\Helper\Data $freeGiftHelper,
        \Mageants\FreeGift\Block\Freegift $freeGiftBlock,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\SalesRule\Model\Rule $rule,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null,
        \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory $ruleCollectionFactory,
        \Magento\Framework\DataObject $dataObject,
        \Magento\SalesRule\Model\CouponFactory $couponFactory,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurableModel,
        \Magento\Catalog\Helper\Product\Configuration $configurationHelper
    ) {
        $this->_freeGiftHelper = $freeGiftHelper;
        $this->_freeGiftBlock = $freeGiftBlock;
        $this->_assetRepo = $assetRepo;
        $this->_productRepository = $productRepository;
        $this->checkoutSession = $checkoutSession;
        $this->quoteItemFactory = $quoteItemFactory;
        $this->quoteFactory = $quoteFactory;
        $this->_rule = $rule;
        $this->ruleCollectionFactory = $ruleCollectionFactory;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\Json::class);
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteRepository = $quoteRepository;
        $this->dataObject = $dataObject;
        $this->couponFactory = $couponFactory;
        $this->configurableModel = $configurableModel;
        $this->configurationHelper = $configurationHelper;
    }
    
    /**
     * Returns Banner details
     *
     * @api
     * @param int $productId
     * @param int $storeId
     * @return array
     */
    public function getFreeGiftBannerDetails($productId, $storeId)
    {
        $response = ['success' => false];
        $configActive = $this->_freeGiftBlock->getIsActive();
        if (count($this->getValidRules($productId, $storeId)) > 0) {
            if ($productId && $configActive == 1) {
                $configBannerUrl = $this->_freeGiftBlock->getFreeGiftBanner();
                
                if ($configBannerUrl == '') {
                    $response = [
                        'success' => true,
                        'bannerUrl' => $this->_assetRepo->getUrl('Mageants_FreeGift::images/free-gift-banner.png')
                    ];
                    
                } else {
                    $response = ['success' => true, 'bannerUrl' => $configBannerUrl];
                }

                $isShowFreeGiftText = $this->_freeGiftBlock->isShowFreeGiftText();
                if ($isShowFreeGiftText) {
                    $response['freeGiftText'] =  $this->_freeGiftBlock->getAllSkusText();
                }
                $bannerHeight = $this->_freeGiftBlock->getBannerHeight();

                if ($bannerHeight) {
                    $response['bannerHeight'] = $bannerHeight;
                }
                $bannerWidth = $this->_freeGiftBlock->getBannerWidth();
                if ($bannerWidth) {
                    $response['bannerWidth'] = $bannerWidth;
                }
                
                foreach ($this->getValidRules($productId, $storeId) as $_rule) {
                    if ($_rule->getSimpleAction() == 'add_free_item' && (int)$_rule->getCouponType() !== 2) {
                        if ($_rule->getFreeGiftType() == 1) {
                            $response['freegiftMsg'] = $this->_freeGiftBlock->getAllSkusText();
                        }
                        if ($_rule->getFreeGiftType() == 2) {
                            $response['freegiftMsg'] = sprintf(__($this->_freeGiftBlock->getSelectedSkusText()), $_rule->getSelectNoOfFreegift());
                        }
                    }
                }
            }
        } else {
            $response['error_message'] = (__('Free Gift Banner Details not available'));
        }
        return json_encode($response);
    }

    /**
     * Returns product details
     *
     * @api
     * @param int $productId
     * @param int $storeId
     * @return array
     */
    public function getFreeGiftBannerProducts($productId, $storeId)
    {
        $products = [];
        if ($productId) {
            if (count($this->getValidRules($productId, $storeId)) > 0) {
                foreach ($this->getValidRules($productId, $storeId) as $_rule) {

                    $products = $this->dataFetch($_rule, $storeId);
                    
/*-------------------------------------------------------------------------------------------*/

                    /*if ($_rule->getSimpleAction() == 'add_free_item' && (int)$_rule->getCouponType() !== 2) {
                        foreach ($this->_freeGiftBlock->getProducts($_rule) as $product) {
                            $product->setStoreId($storeId);
                            $products[$product->getId()]['title'] = $product->getName();
                            $products[$product->getId()]['url'] = $product->getProductUrl();
                            $productImageUrl = $this->_freeGiftBlock->getImageHelper()
                                            ->init($product, 'product_thumbnail_image')
                                            ->keepFrame(false)
                                            ->constrainOnly(true)
                                            //->resize($block->getWidth())
                                            ->getUrl();
                            $products[$product->getId()]['imageurl'] = $productImageUrl;
                            $products[$product->getId()]['price'] = $this->_freeGiftBlock->getFormatCurrency($product->getPrice());
                            if ($product->getTypeId() == 'configurable') {
                                $productTypeInstance = $this->configurableModel;
                                $productAttributeOptions = $productTypeInstance->getConfigurableAttributesAsArray($product);
                                if (count($productAttributeOptions)) {
                                    foreach ($productAttributeOptions as $key => $value) {
                                        $products[$product->getId()]['attribute'][$key]['id'] = $value['id'];
                                        $products[$product->getId()]['attribute'][$key]['label'] = $value['label'];
                                        $products[$product->getId()]['attribute'][$key]['use_default'] = $value['use_default'];
                                        $products[$product->getId()]['attribute'][$key]['position'] = $value['position'];
                                        $products[$product->getId()]['attribute'][$key]['values'] = $value['values'];
                                        $products[$product->getId()]['attribute'][$key]['attribute_id'] = $value['attribute_id'];
                                        $products[$product->getId()]['attribute'][$key]['attribute_code'] = $value['attribute_code'];
                                        $products[$product->getId()]['attribute'][$key]['frontend_label'] = $value['frontend_label'];
                                        $products[$product->getId()]['attribute'][$key]['store_label'] = $value['store_label'];
                                    }
                                }
                            }
                        }
                    }*/
/*-------------------------------------------------------------------------------------------*/
                }
                if (count($products)) {
                    $products['view_imageurl'] = $this->_assetRepo->getUrl('Mageants_FreeGift::images/icon-login.png');
                }
            } else {
                $products['error_message'] = (__('Free Gift Banner Product Details not available'));
            }
        }
        
        return json_encode($products);
    }

    /**
     * GetValidRules
     *
     * @param  int $productId
     * @param  int $storeId
     * @return array|\Magento\SalesRule\Model\ResourceModel\Rule\Collection[]
     */
    public function getValidRules($productId, $storeId)
    {
        $productBasedValidRuleIds = $this->getProductBasedValidRuleIds($productId, $storeId);
        $cartBasedValidRuleIds = $this->getCartBasedValidRuleIds($productId);
        
        $validRulesIds = array_unique(array_merge($productBasedValidRuleIds, $cartBasedValidRuleIds));

        $validRules = $this->ruleCollectionFactory->create()->addFieldToFilter('rule_id', ['in' => $validRulesIds]);

        return $validRules;
    }

    /**
     * GetProductBasedValidRuleIds
     *
     * @param  int $productId
     * @param  int $storeId
     * @return array
     */
    public function getProductBasedValidRuleIds($productId, $storeId)
    {
        $validRules = [];
        $currentQuote = $this->checkoutSession->getQuote();
        if ($storeId) {
            $currentQuote->setStoreId($storeId);
        }
        $quoteItem = $this->quoteItemFactory->create();
        $quoteItem->setProduct($this->getProductById($productId));
        $quoteItem->setStoreId($currentQuote->getStoreId());
        $currentQuote->getStoreId();
        $quoteItem->setIsVirtual(false);
        $quoteItem->setQuote($currentQuote);
        $quoteItem->setAllItems([$quoteItem]);
        
        /** @var \Magento\SalesRule\Model\Rule $rule */
        foreach ($this->getRulesCollection() as $rule) {

            if ($rule->getActions()->validate($quoteItem)) {
                if ($rule->getSimpleAction() == 'add_free_item' && (int)$rule->getCouponType() !== 2) {
                        $validRules[] = $rule->getId();
                }

            }
        }
        return $validRules;
    }

    /**
     * GetCartBasedValidRuleIds
     *
     * @param  int $productId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCartBasedValidRuleIds($productId)
    {
        $validRules = [];
        $product = $this->getProductById($productId);
        
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

                            /*if ($rule->getActions()->validate($item)) {
                                if ($rule->getSimpleAction() == 'add_free_item' && (int)$rule->getCouponType() !== 2) {
                                        $validRules[] = $rule->getId();
                                }
                            }*/

                            ($rule->getActions()->validate($item) && $rule->getSimpleAction() == 'add_free_item' && (int)$rule->getCouponType() !== 2) ? $validRules[] = $rule->getId() : $validRules = [];
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

                $validRules = $this->dataRetrieve($getActionsSerialize, $quoteSummeryQty, $ruleCollection);
/*-------------------------------------------------------------------------------------------*/
                /*if (isset($getActionsSerialize['conditions'])) {
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
                }*/
/*-------------------------------------------------------------------------------------------*/
            }
        }
        
        return array_unique($validRules);
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
     * GetProductById
     *
     * @param  int $id
     * @return Product
     */
    public function getProductById($id)
    {
        return $this->_productRepository->getById($id);
    }

    /**
     * Set Free Products in cart
     *
     * @api
     * @param string $cartId
     * @param string $freeGiftSkus
     * @param string $freeGiftSuperAttributes
     * @param int $storeId
     * @param int $productId
     */
    public function addFreeGiftBannerProducts($cartId, $freeGiftSkus, $freeGiftSuperAttributes, $storeId, $productId)
    {

        $response = ['success' => false];
        $isActive = $this->_freeGiftHelper->getFreeGiftConfig('mageants_freegift/general/active');
        if ($isActive) {
            $validation = $this->_freeGiftHelper->getCartBasedValidRuleOnAddtoCart();
            $selectedFreeGiftSkus = $freeGiftSkus;
            $freeGiftSuperAttrs = '';
            $selectedFreeGiftQty = 0;
            $selectedFreeGiftSkusArray = '';
            
            if ($freeGiftSuperAttributes && $freeGiftSuperAttributes!='') {
                $freeGiftSuperAttrs = json_decode($freeGiftSuperAttributes, true);
            }
            
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
            $cart = $this->quoteRepository->get($quoteId);
            $allItems = $cart->getItemsCollection();
            if (count($allItems) > 0) {
                if (count($this->getValidRules($productId, $storeId)) > 0) {
                    $selectedGifts = explode(',', $selectedFreeGiftSkus);
                    $addedSkus=[];
                    foreach ($allItems as $addedItems) {
                        if ($addedItems->getIsFreeItem() && in_array($addedItems->getSku(), $selectedGifts)) {
                            /*if ($addedItems->getIsFreeItem() && in_array($addedItems->getSku(), $selectedGifts)) {
                                $addedSkus[$addedItems->getSku()] = true;
                            }*/
                            ($addedItems->getIsFreeItem() && in_array($addedItems->getSku(), $selectedGifts)) ? $addedSkus[$addedItems->getSku()] = true : $addedSkus = [];
                        }
                    }
                    $getLastItem = $allItems->addFieldToFilter('product_id', $productId)->setOrder(
                        'item_id',
                        'DESC'
                    )->getLastItem();
                    
                    $parentItemId = $getLastItem->getParentItemId();
                    if ($parentItemId) {
                        $lastItemId = $parentItemId;
                    } else {
                        $lastItemId = $getLastItem->getItemId();
                    }
                    
                    if (array_key_exists('free_gift_sku', $validation)) {
                        foreach ($validation['free_gift_sku'] as $skus) {

                            $response =  $this->getDataRetieve(
                                $skus,
                                $addedSkus,
                                $selectedFreeGiftSkus,
                                $cart,
                                $selectedFreeGiftQty,
                                $freeGiftSuperAttrs,
                                $lastItemId,
                                $storeId
                            );
/*-------------------------------------------------------------------------------------------*/
                            /*if ($skus != '') {
                                foreach (explode(',', $skus) as $sku) {
                                     // check whether free gift already added or not (start)
                                    if (array_key_exists($sku, $addedSkus)) {
                                        continue;
                                    }
                                     // check whether free gift already added or not (end)

                                    if (strpos($selectedFreeGiftSkus, $sku) !== false) {
                                        $freeGiftProduct = $this->_productRepository->get($sku);
                                        if ($freeGiftProduct->isSalable()) {
                                            $loadProduct = $this->_productRepository->getById(
                                                $freeGiftProduct->getId(),
                                                false,
                                                $storeId,
                                                true
                                            );

                                            $additionalOptions = [];
                                            $additionalOptions[] = [
                                                'label' => "Free! ",
                                                'value' => "Product",
                                            ];

                                            $loadProduct->addCustomOption(
                                                'additional_options',
                                                $this->serializer->serialize($additionalOptions)
                                            );

                                            $freeGiftParams = [
                                                'product' => $freeGiftProduct->getId(),
                                                'qty' => $selectedFreeGiftQty
                                            ];

                                            if (isset($freeGiftSuperAttrs[$sku])) {
                                                $freeGiftParams = [
                                                    'product' => $loadProduct->getId(),
                                                    'qty' => $selectedFreeGiftQty,
                                                    'super_attribute' => $freeGiftSuperAttrs[$sku]
                                                ];
                                            }

                                            $request = $this->dataObject;
                                            $request->setData($freeGiftParams);
                                            $cart->addProduct($loadProduct, $request);

                                            $lastFreeItem = $cart->getItemsCollection()->getLastItem();
                                            $lastFreeItem->setParentProductId($lastItemId);
                                            $lastFreeItem->setIsFreeItem(1);
                                            $lastFreeItem->setPrice(0);
                                            $lastFreeItem->setBasePrice(0);
                                            $lastFreeItem->setCustomPrice(0);
                                            $lastFreeItem->setOriginalCustomPrice(0);
                                            $lastFreeItem->setPriceInclTax(0);
                                            $lastFreeItem->setBasePriceInclTax(0);
                                            $lastFreeItem->getProduct()->setIsSuperMode(true);
                                            $lastFreeItem->save();
                                            $response = ['success' => true];
                                        } else {
                                            $response['error_message'] = (__('freegift product is out of stock'));
                                        }
                                    }
                                }
                                $cart->save();
                                $this->_freeGiftHelper->updateConfigFreeGiftItem();
                            }*/
/*-------------------------------------------------------------------------------------------*/
                        }
                    }
                } else {
                    $response['error_message'] = (__("Product does have any free gift product."));
                }
            } else {
                $response['error_message'] = (__('Please Add Product into cart'));
            }
        }
        return json_encode($response);
    }

    /**
     * Update Free Products in cart
     *
     * @api
     * @param string $cartId
     * @param string $freeGiftSkus
     * @param string $freeGiftSuperAttributes
     * @param int $storeId
     * @param int $itemId
     */
    public function updateFreeGiftBannerProducts($cartId, $freeGiftSkus, $freeGiftSuperAttributes, $storeId, $itemId)
    {
        $isActive = $this->_freeGiftHelper->getFreeGiftConfig('mageants_freegift/general/active');
        $response = ['success' => false];
        if ($isActive) {
            if ($this->_freeGiftHelper->getCartBasedValidRuleOnAddtoCart()) {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
                $cart = $this->quoteRepository->get($quoteId);
                $getLastItem = $cart->getItemById((int)$itemId);

                $parentItemId = $getLastItem->getParentItemId();
                if ($parentItemId) {
                    $lastItemId = $parentItemId;
                } else {
                    $lastItemId = $getLastItem->getItemId();
                }
                //echo $lastItemId;
                //echo "string";exit();
                $selectedFreeGiftSkus = '';
                $freeGiftSuperAttrs = '';
                $selectedFreeGiftQty = 1;
                $selectedFreeGiftSkusArray = '';
                if (isset($freeGiftSkus)) {
                    $selectedFreeGiftSkus = $freeGiftSkus;
                }
                if ($freeGiftSuperAttributes && $freeGiftSuperAttributes!='') {
                    $freeGiftSuperAttrs = json_decode($freeGiftSuperAttributes, true);
                }
                
                if ($selectedFreeGiftSkus != '') {
                    $selectedFreeGiftSkusArray = explode(',', $selectedFreeGiftSkus);
                }
                
                $freeQuoteItems = $cart->getItemsCollection();

                $beforeFreeGiftIds = [];

                foreach ($freeQuoteItems as $freeItems) {
                    if ($freeItems->getParentProductId() == $itemId && $freeItems->getProductType() !=
                    'configurable') {
                        $beforeFreeGiftIds[] = $freeItems->getItemId();
                    }
                }

                $cart->removeItem($lastItemId);
                $cart->save();
                foreach ($selectedFreeGiftSkusArray as $sku) {
                    $freeGiftProduct = $this->_productRepository->get($sku);
                    $loadProduct = $this->_productRepository->getById($freeGiftProduct->getId(), false, $storeId, true);
                    
                    $additionalOptions = [];
                    $additionalOptions[] = [
                        'label' => "Free! ",
                        'value' => "Product",
                    ];
                    
                    // $loadProduct->addCustomOption(
                    //     'additional_options',
                    //     $this->serializer->serialize($additionalOptions)
                    // );
                    
                    $freeGiftParams = [
                        'product' => $freeGiftProduct->getId(),
                        'qty' => $selectedFreeGiftQty
                    ];

                    if (isset($freeGiftSuperAttrs[$sku])) {
                        $freeGiftParams = [
                            'product' => $loadProduct->getId(),
                            'qty' => $selectedFreeGiftQty,
                            'super_attribute' => $freeGiftSuperAttrs[$sku]
                        ];
                    }
                    $request = $this->dataObject;
                    $request->setData($freeGiftParams);
                    $cart->addProduct($loadProduct, $request);

                    $lastFreeItem = $cart->getItemsCollection()->getLastItem();
                    $lastFreeItem->setParentProductId($lastItemId);
                    $lastFreeItem->setIsFreeItem(1);
                    $lastFreeItem->setPrice(0);
                    $lastFreeItem->setBasePrice(0);
                    $lastFreeItem->setCustomPrice(0);
                    $lastFreeItem->setOriginalCustomPrice(0);
                    $lastFreeItem->setPriceInclTax(0);
                    $lastFreeItem->setBasePriceInclTax(0);
                    $lastFreeItem->getProduct()->setIsSuperMode(true);
                    $lastFreeItem->save();
                }
                $cart->save();
                $response = ['success' => true];
                $this->_freeGiftHelper->updateConfigFreeGiftItem();
            }
        }
        return json_encode($response);
    }

    /**
     * Add free gift product when Coupon Code add in the cart
     *
     * @api
     * @param string $cartId
     * @param string $couponCode
     * @param int $storeId
     */
    public function addCouponCode($cartId, $couponCode, $storeId)
    {
        $isActive = $this->_freeGiftHelper->getFreeGiftConfig('mageants_freegift/general/active');
        $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        $cart = $this->quoteRepository->get($quoteId);
        $response = ['success' => false];
        if ($isActive) {
            $helper = $this->configurationHelper;
            $coupon = $this->couponFactory->create();
            
            $couponcodes = $coupon->load($couponCode, 'code');
            $rule = $this->_rule;
            if ($couponcodes->getRuleId()!=null) {
                if (strpos($cart->getAppliedRuleIds(), $couponcodes->getRuleId())!==false) {
                    $rules = $rule->load($couponcodes->getRuleId());
                    if ($rules->getSimpleAction()=='add_free_item' && (int)$rules->getCouponType()== 2) {
                        $freeGiftSkus = explode(',', $rules->getFreeGiftSku());
                        $qty = $rules->getDiscountAmount();
                    }
                    if (isset($freeGiftSkus)) {
                        if (is_array($freeGiftSkus)) {

                            $response = $this->getDataFetch($freeGiftSkus, $cart, $storeId, $qty);

/*-------------------------------------------------------------------------------------------*/
                            /*foreach ($freeGiftSkus as $sku) {
                                $freeGiftItem = $cart->getAllItems();
                                foreach ($freeGiftItem as $freeItem) {
                                    $options=$helper->getCustomOptions($freeItem);
                                    if ($options) {
                                        foreach ($options as $option) {
                                            if ($option['label'] == "Free! " && $option['value'] == "Product") {
                                                $response = ['success' => true];
                                                return json_encode($response);
                                            }
                                        }
                                    }
                                }

                                $freeGiftProduct = $this->_productRepository->get($sku);
                                $loadProduct = $this->_productRepository->getById(
                                    $freeGiftProduct->getId(),
                                    false,
                                    $storeId,
                                    true
                                );

                                $additionalOptions = [];
                                $additionalOptions[] = [
                                    'label' => "Free! ",
                                    'value' => "Product",
                                ];

                                $loadProduct->addCustomOption(
                                    'additional_options',
                                    $this->serializer->serialize($additionalOptions)
                                );

                                $freeGiftParams = [
                                    'product' => $freeGiftProduct->getId(),
                                    'qty' => $qty
                                ];
                                $getLastItem = $cart->getItemsCollection()->getLastItem();

                                $request = $this->dataObject;
                                $request->setData($freeGiftParams);
                                $cart->addProduct($loadProduct, $request);
                                $parentItemId = $getLastItem->getParentItemId();
                                if ($parentItemId) {
                                    $lastItemId = $parentItemId;
                                } else {
                                    $lastItemId = $getLastItem->getItemId();
                                }
                                $lastFreeItem = $cart->getItemsCollection()->getLastItem();
                                $lastFreeItem->setParentProductId($lastItemId);
                                $lastFreeItem->setIsFreeItem(1);
                                $lastFreeItem->setPrice(0);
                                $lastFreeItem->setBasePrice(0);
                                $lastFreeItem->setCustomPrice(0);
                                $lastFreeItem->setOriginalCustomPrice(0);
                                $lastFreeItem->setPriceInclTax(0);
                                $lastFreeItem->setBasePriceInclTax(0);
                                $lastFreeItem->getProduct()->setIsSuperMode(true);
                                $lastFreeItem->save();
                                $response = ['success' => true];
                            }*/
/*-------------------------------------------------------------------------------------------*/
                            $cart->save();
                            $this->updateFreeGifts($couponcodes->getRuleId(), $cart);
                        }
                    }
                }
            }
        }
        return json_encode($response);
    }
    
    /**
     * UpdateFreeGifts
     *
     * @param  mixed $ruleid
     * @param  mixed $cart
     * @return mixed
     */
    public function updateFreeGifts($ruleid, $cart)
    {
        $allitems = $cart->getItemsCollection();
        
        $rule = $this->_rule;
        $rules = $rule->load($ruleid);
        foreach ($allitems as $cartitems) {
            if (strpos($rules->getFreeGiftSku(), $cartitems->getSku())!==false) {
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
        // return;
    }

    /**
     * Remove free gift product when remove Coupon Code from the cart
     *
     * @api
     * @param string $cartId
     * @param string $couponCode
     * @param int $storeId
     */
    public function removeCouponCode($cartId, $couponCode, $storeId)
    {
        $isActive = $this->_freeGiftHelper->getFreeGiftConfig('mageants_freegift/general/active');
        $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
        $cart = $this->quoteRepository->get($quoteId);
        $response = ['success' => false];
        if ($isActive) {
            $coupon = $this->couponFactory->create();
            $couponcodes = $coupon->load($couponCode, 'code');
            if (strpos($cart->getAppliedRuleIds(), $couponcodes->getRuleId())!==false) {

                $rules = $this->_rule->load($couponcodes->getRuleId());
                if ($rules->getSimpleAction()=='add_free_item' && (int)$rules->getCouponType()== 2) {
                    $qty = $rules->getDiscountAmount();
                    $freeGiftItem = $cart->getAllItems();
                    
                    $allItems = $cart->getAllItems();//returns all teh items in session
                    foreach ($allItems as $item) {
                        $itemId = $item->getItemId();//item id of particular item
                        if (strpos($rules->getFreeGiftSku(), $item->getSku())!==false) {
                            //load particular item which you want to delete by his item id
                            $quoteItem = $this->quoteItemFactory->create()->load($itemId);
                            $quoteItem->delete();//deletes the item
                            $cart->removeItem($itemId);
                            $response = ['success' => true];
                        }
                    }
                }
            }
        }
        return json_encode($response);
    }

    /**
     * DataFetch
     *
     * @param  mixed $_rule
     * @param  mixed $storeId
     * @return mixed
     */
    public function dataFetch($_rule, $storeId)
    {
        $products = [];
        if ($_rule->getSimpleAction() == 'add_free_item' && (int)$_rule->getCouponType() !== 2) {
            foreach ($this->_freeGiftBlock->getProducts($_rule) as $product) {
                $product->setStoreId($storeId);
                $products[$product->getId()]['title'] = $product->getName();
                $products[$product->getId()]['url'] = $product->getProductUrl();
                $productImageUrl = $this->_freeGiftBlock->getImageHelper()
                                ->init($product, 'product_thumbnail_image')
                                ->keepFrame(false)
                                ->constrainOnly(true)
                                //->resize($block->getWidth())
                                ->getUrl();
                $products[$product->getId()]['imageurl'] = $productImageUrl;
                $products[$product->getId()]['price'] = $this->_freeGiftBlock->getFormatCurrency($product->getPrice());
                if ($product->getTypeId() == 'configurable') {
                    $productTypeInstance = $this->configurableModel;
                    $productAttributeOptions = $productTypeInstance->getConfigurableAttributesAsArray($product);
                    if (count($productAttributeOptions)) {
                        foreach ($productAttributeOptions as $key => $value) {
                            $products[$product->getId()]['attribute'][$key]['id'] = $value['id'];
                            $products[$product->getId()]['attribute'][$key]['label'] = $value['label'];
                            $products[$product->getId()]['attribute'][$key]['use_default'] = $value['use_default'];
                            $products[$product->getId()]['attribute'][$key]['position'] = $value['position'];
                            $products[$product->getId()]['attribute'][$key]['values'] = $value['values'];
                            $products[$product->getId()]['attribute'][$key]['attribute_id'] = $value['attribute_id'];
                            $products[$product->getId()]['attribute'][$key]['attribute_code'] = $value['attribute_code'];
                            $products[$product->getId()]['attribute'][$key]['frontend_label'] = $value['frontend_label'];
                            $products[$product->getId()]['attribute'][$key]['store_label'] = $value['store_label'];
                        }
                    }
                }
            }
        }
        return $products;
    }

    /**
     * DataRetrieve
     *
     * @param  mixed $getActionsSerialize
     * @param  mixed $quoteSummeryQty
     * @param  mixed $ruleCollection
     * @return mixed
     */
    public function dataRetrieve($getActionsSerialize, $quoteSummeryQty, $ruleCollection)
    {
        $validRules = [];

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
        return $validRules;
    }

    /**
     * GetDataRetieve
     *
     * @param  mixed $skus
     * @param  mixed $addedSkus
     * @param  mixed $selectedFreeGiftSkus
     * @param  mixed $cart
     * @param  mixed $selectedFreeGiftQty
     * @param  mixed $freeGiftSuperAttrs
     * @param  mixed $lastItemId
     * @param  mixed $storeId
     * @return mixed
     */
    public function getDataRetieve(
        $skus,
        $addedSkus,
        $selectedFreeGiftSkus,
        $cart,
        $selectedFreeGiftQty,
        $freeGiftSuperAttrs,
        $lastItemId,
        $storeId
    ) {
        $response = ['success' => false];

        if ($skus != '') {

            foreach (explode(',', $skus) as $sku) {
                 // check whether free gift already added or not (start)
                if (array_key_exists($sku, $addedSkus)) {
                    continue;
                }
                 // check whether free gift already added or not (end)
            
                if (strpos($selectedFreeGiftSkus, $sku) !== false) {
                    $freeGiftProduct = $this->_productRepository->get($sku);
                    if ($freeGiftProduct->isSalable()) {
                        $loadProduct = $this->_productRepository->getById(
                            $freeGiftProduct->getId(),
                            false,
                            $storeId,
                            true
                        );
                        
                        $additionalOptions = [];
                        $additionalOptions[] = [
                            'label' => "Free! ",
                            'value' => "Product",
                        ];
                        
                        $loadProduct->addCustomOption(
                            'additional_options',
                            $this->serializer->serialize($additionalOptions)
                        );
                        
                        $freeGiftParams = [
                            'product' => $freeGiftProduct->getId(),
                            'qty' => $selectedFreeGiftQty
                        ];

                        if (isset($freeGiftSuperAttrs[$sku])) {
                            $freeGiftParams = [
                                'product' => $loadProduct->getId(),
                                'qty' => $selectedFreeGiftQty,
                                'super_attribute' => $freeGiftSuperAttrs[$sku]
                            ];
                        }
                        
                        $request = $this->dataObject;
                        $request->setData($freeGiftParams);
                        $cart->addProduct($loadProduct, $request);

                        $lastFreeItem = $cart->getItemsCollection()->getLastItem();
                        $lastFreeItem->setParentProductId($lastItemId);
                        $lastFreeItem->setIsFreeItem(1);
                        $lastFreeItem->setPrice(0);
                        $lastFreeItem->setBasePrice(0);
                        $lastFreeItem->setCustomPrice(0);
                        $lastFreeItem->setOriginalCustomPrice(0);
                        $lastFreeItem->setPriceInclTax(0);
                        $lastFreeItem->setBasePriceInclTax(0);
                        $lastFreeItem->getProduct()->setIsSuperMode(true);
                        $lastFreeItem->save();
                        $response = ['success' => true];
                    } else {
                        $response['error_message'] = (__('freegift product is out of stock'));
                    }
                }
            }
            $cart->save();
            $this->_freeGiftHelper->updateConfigFreeGiftItem();
        }
        return $response;
    }

    /**
     * GetDataFetch
     *
     * @param  mixed $freeGiftSkus
     * @param  mixed $cart
     * @param  mixed $storeId
     * @param  mixed $qty
     * @return mixed
     */
    public function getDataFetch($freeGiftSkus, $cart, $storeId, $qty)
    {
        $response = ['success' => false];
        $helper = $this->configurationHelper;

        foreach ($freeGiftSkus as $sku) {
            $freeGiftItem = $cart->getAllItems();
            foreach ($freeGiftItem as $freeItem) {
                $options=$helper->getCustomOptions($freeItem);
                if ($options) {
                    foreach ($options as $option) {
                        if ($option['label'] == "Free! " && $option['value'] == "Product") {
                            $response = ['success' => true];
                            return json_encode($response);
                        }
                    }
                }
            }

            $freeGiftProduct = $this->_productRepository->get($sku);
            $loadProduct = $this->_productRepository->getById(
                $freeGiftProduct->getId(),
                false,
                $storeId,
                true
            );
            
            $additionalOptions = [];
            $additionalOptions[] = [
                'label' => "Free! ",
                'value' => "Product",
            ];
            
            $loadProduct->addCustomOption(
                'additional_options',
                $this->serializer->serialize($additionalOptions)
            );
            
            $freeGiftParams = [
                'product' => $freeGiftProduct->getId(),
                'qty' => $qty
            ];
            $getLastItem = $cart->getItemsCollection()->getLastItem();
            
            $request = $this->dataObject;
            $request->setData($freeGiftParams);
            $cart->addProduct($loadProduct, $request);
            $parentItemId = $getLastItem->getParentItemId();
            if ($parentItemId) {
                $lastItemId = $parentItemId;
            } else {
                $lastItemId = $getLastItem->getItemId();
            }
            $lastFreeItem = $cart->getItemsCollection()->getLastItem();
            $lastFreeItem->setParentProductId($lastItemId);
            $lastFreeItem->setIsFreeItem(1);
            $lastFreeItem->setPrice(0);
            $lastFreeItem->setBasePrice(0);
            $lastFreeItem->setCustomPrice(0);
            $lastFreeItem->setOriginalCustomPrice(0);
            $lastFreeItem->setPriceInclTax(0);
            $lastFreeItem->setBasePriceInclTax(0);
            $lastFreeItem->getProduct()->setIsSuperMode(true);
            $lastFreeItem->save();
            $response = ['success' => true];
        }
        return $response;
    }
}
