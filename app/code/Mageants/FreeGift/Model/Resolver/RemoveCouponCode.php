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
class RemoveCouponCode implements ResolverInterface
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
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

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
     * @var \Magento\Quote\Model\Quote\Item\Factory
     */
    protected $quoteItemFactory;

    /**
     * @var \Magento\SalesRule\Model\Rule
     */
    protected $_rule;

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
     * @param \Magento\Quote\Model\Quote\ItemFactory               $quoteItemFactory
     * @param \Magento\SalesRule\Model\Rule                        $rule
     * @param \Magento\SalesRule\Model\CouponFactory               $couponFactory
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
        \Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory,
        \Magento\SalesRule\Model\Rule $rule,
        \Magento\SalesRule\Model\CouponFactory $couponFactory
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
        $this->quoteItemFactory = $quoteItemFactory;
        $this->_rule = $rule;
        $this->couponFactory = $couponFactory;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        
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
        if (!isset($args['couponCode'])) {
            throw new GraphQlAuthorizationException(
                __(
                    'Coupon Code should be specified',
                    [\Magento\Catalog\Model\Product::ENTITY]
                )
            );
        }
        try {
            $data = $this->removeCouponCode($args['cartId'], $args['couponCode'], $args['storeId']);
                 
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
     * Remove free gift product when Coupon Code in cart
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
        return $response;
    }
}
