<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */
 
namespace Mageants\FreeGift\Observer;

use Magento\Framework\Event\ObserverInterface;

class UpdateQuoteItemObserver implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\ResponseFactory
     */
    protected $_responseFactory;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_url;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;
    
    /**
     * __construct
     * @param \Magento\Framework\App\ResponseFactory      $responseFactory
     * @param \Magento\Framework\UrlInterface             $url
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->_responseFactory = $responseFactory;
        $this->_url = $url;
        $this->_messageManager = $messageManager;
    }

    /**
     * Execute
     *
     * @param  \Magento\Framework\Event\Observer $observer
     * @return [type]
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $cart = $observer->getEvent()->getCart();
        $data = $observer->getEvent()->getInfo()->toArray();
        
        foreach ($data as $itemId => $itemInfo) {
            $item = $cart->getQuote()->getItemById($itemId);
            if ($item->getIsFreeItem() == 1 && $itemInfo['qty'] != $item->getQty()) {
                $CustomRedirectionUrl = $this->_url->getUrl('checkout/cart');
                $this->_messageManager->addNotice(__('You can not update Free Gift Qty Please update only main product Qty.'));
                $this->_responseFactory->create()->setRedirect($CustomRedirectionUrl)->sendResponse();
            }
        }
        return $this;
    }
}
