<?php
namespace Mageants\FreeGift\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class RemoveCoupon extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Customer\Model\Account\Redirect
     */
    protected $_redirectCustomer;

    /**
     * @var $_stockNotification
     */
    protected $_stockNotification;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var $_logger
     */
    protected $_logger;

    /**
     * __construct
     * @param Context                                           $context
     * @param \Magento\SalesRule\Model\Rule                     $rule
     * @param \Magento\Checkout\Model\Session                   $checkoutSession
     * @param \Magento\SalesRule\Model\CouponFactory            $couponFactory
     * @param \Magento\Checkout\Model\Cart                      $cart
     * @param \Magento\Quote\Api\CartRepositoryInterface        $quoteRepository
     * @param \Magento\Quote\Model\Quote\Item                   $itemModel
     * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
     */
    public function __construct(
        Context $context,
        \Magento\SalesRule\Model\Rule $rule,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\SalesRule\Model\CouponFactory $couponFactory,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\Quote\Item $itemModel,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null
    ) {
        $this->_rule = $rule;
        $this->_checkoutSession = $checkoutSession;
        $this->_couponFactory = $couponFactory;
        $this->_cart = $cart;
        $this->itemModel = $itemModel;
        $this->quoteRepository = $quoteRepository;
        $this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\Json::class);
        parent::__construct($context);
    }
    
    /**
     * Execute
     *
     * @return redirect at customer Dashboard
     */
    public function execute()
    {
        $cartId = $this->_cart->getQuote()->getId();
        $quote = $this->quoteRepository->getActive($cartId);
        $my_code = $quote->getCouponCode();
        $coupon = $this->_couponFactory->create();
        $couponcodes = $coupon->load($my_code, 'code');
        
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
