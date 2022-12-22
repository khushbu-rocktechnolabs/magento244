<?php
namespace Mageants\FreeGift\Model\Resolver;

use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use Magento\Framework\Api\ExtensibleDataObjectConverter;

/**
 * Customers field resolver, used for GraphQL request processing.
 */
class UpdateFreeGiftBannerProducts implements ResolverInterface
{
    /**
     * @var ValueFactory
     */
    private $valueFactory;

    /**
     * @var ServiceOutputProcessor
     */
    private $serviceOutputProcessor;

    /**
     * @var ExtensibleDataObjectConverter
     */
    private $dataObjectConverter;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $_productRepository;
    
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;
    
    /**
     * @var \Magento\Framework\DataObject
     */
    protected $dataObject;

    /**
     * @var \Mageants\FreeGift\Helper\Data
     */
    protected $_freeGiftHelper;

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
     * @param ValueFactory                                         $valueFactory
     * @param ServiceOutputProcessor                               $serviceOutputProcessor
     * @param ExtensibleDataObjectConverter                        $dataObjectConverter
     * @param \Psr\Log\LoggerInterface                             $logger
     * @param \Magento\Catalog\Model\ProductRepository             $productRepository
     * @param \Magento\Framework\Serialize\Serializer\Json|null    $serializer
     * @param \Mageants\FreeGift\Helper\Data                       $freeGiftHelper
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param \Magento\Quote\Api\CartRepositoryInterface           $quoteRepository
     * @param \Magento\Framework\DataObject                        $dataObject
     */
    public function __construct(
        ValueFactory $valueFactory,
        ServiceOutputProcessor $serviceOutputProcessor,
        ExtensibleDataObjectConverter $dataObjectConverter,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null,
        \Mageants\FreeGift\Helper\Data $freeGiftHelper,
        \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\DataObject $dataObject
    ) {
        $this->valueFactory = $valueFactory;
        $this->serviceOutputProcessor = $serviceOutputProcessor;
        $this->dataObjectConverter = $dataObjectConverter;
        $this->logger = $logger;
        $this->_productRepository = $productRepository;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\Json::class);
        $this->_freeGiftHelper = $freeGiftHelper;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteRepository = $quoteRepository;
        $this->dataObject = $dataObject;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        
        if (!isset($args['itemId']) || $args['itemId'] == 0) {
            throw new GraphQlAuthorizationException(
                __(
                    'Item ID should be specified',
                    [\Magento\Catalog\Model\Product::ENTITY]
                )
            );
        }
        if (!isset($args['storeId'])) {
            throw new GraphQlAuthorizationException(
                __(
                    'Store ID should be specified',
                    [\Magento\Store\Model\Store::ENTITY]
                )
            );
        }
        if (!isset($args['cartId'])) {
            throw new GraphQlAuthorizationException(
                __(
                    'Quote ID should be specified',
                    '[\Magento\Store\Model\Store::ENTITY]'
                )
            );
        }
        if (!isset($args['freeGiftSkus'])) {
            throw new GraphQlAuthorizationException(
                __(
                    'SKU should be specified',
                    [\Magento\Catalog\Model\Product::ENTITY]
                )
            );
        }
        try {
            $data = $this->updateFreeGiftBannerProducts(
                $args['cartId'],
                $args['freeGiftSkus'],
                $args['freeGiftSuperAttributes'],
                $args['storeId'],
                $args['itemId']
            );
            
            $result = function () use ($data) {
                return !empty($data) ? $data : [];
            };
            return $this->valueFactory->create($result);
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(__($exception->getMessage()));
        } catch (LocalizedException $exception) {
            throw new GraphQlNoSuchEntityException(__($exception->getMessage()));
        }
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
            
                $selectedFreeGiftSkus = '';
                $freeGiftSuperAttrs = '';
                $selectedFreeGiftQty = 1;
                $selectedFreeGiftSkusArray = '';
                if (isset($freeGiftSkus)) {
                    $selectedFreeGiftSkus = $freeGiftSkus;
                }
                if ($freeGiftSuperAttributes && $freeGiftSuperAttributes!='') {
                    $freeGiftSuperAttributes = str_replace("'", '"', $freeGiftSuperAttributes);
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
                }
                $cart->save();
                $response = ['success' => true];
                $this->_freeGiftHelper->updateConfigFreeGiftItem();
            }
        }
        return $response;
    }
}
