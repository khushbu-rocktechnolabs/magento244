<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */

namespace Mageants\FreeGift\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;

class AddToCartObserver implements ObserverInterface
{
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $_productRepository;
    
    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $_cart;
    
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    
    /**
     * @var \Mageants\FreeGift\Helper\Data
     */
    protected $_freeGiftHelper;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;
    
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;
    
    /**
     * __construct
     * @param \Magento\Catalog\Model\ProductRepository      $productRepository
     * @param \Magento\Checkout\Model\Cart                  $cart
     * @param \Magento\Store\Model\StoreManagerInterface    $storeManager
     * @param \Mageants\FreeGift\Helper\Data                $freeGiftHelper
     * @param \Magento\Checkout\Model\Session               $_checkoutSession
     * @param \Magento\Framework\Message\ManagerInterface   $messageManager
     * @param \Magento\Framework\Serialize\Serializer\Json  $serializer
     * @param \Magento\Catalog\Helper\Product\Configuration $configurationHelper
     */
    public function __construct(
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Mageants\FreeGift\Helper\Data $freeGiftHelper,
        \Magento\Checkout\Model\Session $_checkoutSession,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Magento\Catalog\Helper\Product\Configuration $configurationHelper
    ) {
        $this->_productRepository = $productRepository;
        $this->_cart = $cart;
        $this->_checkoutSession = $_checkoutSession;
        $this->_storeManager = $storeManager;
        $this->_freeGiftHelper = $freeGiftHelper;
        $this->_messageManager = $messageManager;
        $this->serializer = $serializer;
        $this->configurationHelper = $configurationHelper;
    }
    
    /**
     * Execute
     *
     * @param  \Magento\Framework\Event\Observer $observer
     * @return mixed
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        //$resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $isActive = $this->_freeGiftHelper->getFreeGiftConfig('mageants_freegift/general/active');
        if ($isActive) {
            $validation = $this->_freeGiftHelper->getCartBasedValidRuleOnAddtoCart();
            
            $appliedIds = [];
            if (isset($validation['skus'])) {
                foreach ($validation['skus'] as $id => $sku) {
                    $appliedIds[] = $id;
                }
            }
            $allAddedItems = $this->_cart->getItems();

            foreach ($allAddedItems as $allAddedItem) {
                $helper = $this->configurationHelper;
                $options=$helper->getCustomOptions($allAddedItem);
                
                if ($options) {
                    foreach ($options as $option) {
                        if (!in_array($option['rules_id'], $appliedIds)) {
                            $this->_cart->removeItem($allAddedItem->getItemId());
                            $this->_cart->save();
                        }
                    }
                }
            }

            $event = $observer->getEvent();
            $product = $event->getData('product');
            $request = $event->getData('request');
            $params = $request->getParams();

            $storeId = $this->_storeManager->getStore()->getId();

            $selectedFreeGiftSkus = '';
            $freeGiftSuperAttrs = '';
            $selectedFreeGiftQty = '';
            $selectedFreeGiftSkusArray = '';
            if (isset($params['selected_free_gifts'])) {
                $selectedFreeGiftSkus = $params['selected_free_gifts'];
            }
            if (isset($params['freegift_super_attribute'])) {
                $freeGiftSuperAttrs = $params['freegift_super_attribute'];
            }

            if (isset($params['selected_free_gifts_qty'])) {
                $selectedFreeGiftQty = $params['selected_free_gifts_qty'];
            }
            /* check whether free gift already added or not (start)*/
            $allItems = $this->_cart->getItems();
            if (is_array($selectedFreeGiftSkus)) {
                $selectedGifts = $selectedFreeGiftSkus;
            } else {
                $selectedGifts = explode(',', $selectedFreeGiftSkus);
            }
            $addedSkus=[];
            foreach ($allItems as $addedItems) {
                if ($addedItems->getIsFreeItem() && in_array($addedItems->getSku(), $selectedGifts)) {
                    $addedSkus[$addedItems->getSku()] = true;
                }
            }
            /* check whether free gift already added or not (end)*/
            $getLastItem = $this->_cart->getItems()->addFieldToFilter('product_id', $product->getId())->setOrder(
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

                    $this->dataRetieve(
                        $skus,
                        $addedSkus,
                        $params,
                        $selectedFreeGiftQty,
                        $freeGiftSuperAttrs,
                        $lastItemId
                    );
                }
            }
            if (in_array(true, $validation['valid_qty_subtotal'])) {
                if ($selectedFreeGiftSkus != '') {
                    $selectedFreeGiftSkusArray = explode(',', $selectedFreeGiftSkus);
                }

                $this->dataFetch(
                    $selectedFreeGiftSkusArray,
                    $validation,
                    $addedSkus,
                    $selectedFreeGiftQty,
                    $freeGiftSuperAttrs,
                    $lastItemId
                );
                $this->_freeGiftHelper->updateConfigFreeGiftItem();
            }
        }
    }

    /**
     * DataRetieve
     *
     * @param  mixed $skus
     * @param  mixed $addedSkus
     * @param  mixed $params
     * @param  mixed $selectedFreeGiftQty
     * @param  mixed $freeGiftSuperAttrs
     * @param  mixed $lastItemId
     * @return mixed
     */
    public function dataRetieve($skus, $addedSkus, $params, $selectedFreeGiftQty, $freeGiftSuperAttrs, $lastItemId)
    {
        $storeId = $this->_storeManager->getStore()->getId();

        if ($skus != '') {
            foreach (explode(',', $skus) as $sku) {
                 // check whether free gift already added or not (start)
                if (array_key_exists($sku, $addedSkus)) {
                    continue;
                }
                 // check whether free gift already added or not (end)
                if (array_key_exists('selected_free_gifts', $params)) {
                    if (strpos($params['selected_free_gifts'], $sku) !== false) {
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
                        
                            // $loadProduct->addCustomOption(
                            //     'additional_options',
                            //     $this->serializer->serialize($additionalOptions)
                            // );
                        
                            $freeGiftParams = [
                            'product' => $freeGiftProduct->getId(),
                            'qty' => $selectedFreeGiftQty
                            ];

                            /*if (isset($freeGiftSuperAttrs[$sku])) {
                                $freeGiftParams = [
                                'product' => $loadProduct->getId(),
                                'qty' => $selectedFreeGiftQty,
                                'super_attribute' => $freeGiftSuperAttrs[$sku]
                                ];
                            }*/

                            isset($freeGiftSuperAttrs[$sku]) ? $freeGiftParams = [
                                'product' => $loadProduct->getId(),
                                'qty' => $selectedFreeGiftQty,
                                'super_attribute' => $freeGiftSuperAttrs[$sku]
                                ] : "";
                        
                            $this->_cart->addProduct($loadProduct, $freeGiftParams);

                            $lastFreeItem = $this->_cart->getItems()->getLastItem();
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
                        } else {
                            $this->_messageManager->addErrorMessage(__('freegift product is out of stock'));
                            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
                            return $resultRedirect;
                        }

                    }
                }
            }
            $this->_cart->save();
            $this->_freeGiftHelper->updateConfigFreeGiftItem();
        }
    }

    /**
     * DataFetch
     *
     * @param  mixed $selectedFreeGiftSkusArray
     * @param  mixed $validation
     * @param  mixed $addedSkus
     * @param  mixed $selectedFreeGiftQty
     * @param  mixed $freeGiftSuperAttrs
     * @param  mixed $lastItemId
     * @return mixed
     */
    public function dataFetch(
        $selectedFreeGiftSkusArray,
        $validation,
        $addedSkus,
        $selectedFreeGiftQty,
        $freeGiftSuperAttrs,
        $lastItemId
    ) {
        $storeId = $this->_storeManager->getStore()->getId();

        if (is_array($selectedFreeGiftSkusArray)) {
                    $validatedSku = implode('', $validation['skus']);
                        
            foreach ($selectedFreeGiftSkusArray as $sku) {
                 // check whether free gift already added or not (start)
                if (array_key_exists($sku, $addedSkus)) {
                    continue;
                }
                 // check whether free gift already added or not (end)
                if (strpos($validatedSku, $sku)!==false) {
                    $freeGiftProduct = $this->_productRepository->get($sku);
                    if ($freeGiftProduct->isSalable()) {
                        $rulesId = implode('', array_keys($validation['skus']));
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
                            'rules_id' => $rulesId,
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
                        
                        $this->_cart->addProduct($loadProduct, $freeGiftParams);

                        $lastFreeItem = $this->_cart->getItems()->getLastItem();
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
                    } else {
                        $this->_messageManager->addErrorMessage(__('freegift product is out of stock'));
                        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
                        return $resultRedirect;
                    }
                }
            }
            $this->_cart->save();
        }
    }
}
