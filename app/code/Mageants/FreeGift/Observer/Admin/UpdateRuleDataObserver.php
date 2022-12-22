<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */
 
namespace Mageants\FreeGift\Observer\Admin;

use Magento\Framework\Event\ObserverInterface;

class UpdateRuleDataObserver implements ObserverInterface
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * __construct
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Framework\Registry $registry
    ) {
        $this->_coreRegistry  = $registry;
    }

    /**
     * Execute
     *
     * @param  \Magento\Framework\Event\Observer $observer
     * @return mixed
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $salesrule = $this->_coreRegistry->registry('freegift_salesrule');
        if (!$salesrule) {
            return;
        }
        $data = $observer->getRequest()->getParam('fglabel_upload_image');
        $productLabel='';
        if ($data) {
            foreach ($data as $uploaded) {
                if (isset($uploaded['name'])) {
                    $productLabel= $uploaded['name'];
                }
            }
        }
        $salesrule->setFglabelUpload($productLabel)->save();
    }
}
