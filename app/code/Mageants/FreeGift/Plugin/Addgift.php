<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */

namespace Mageants\FreeGift\Plugin;

use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Rule\Metadata\ValueProvider as SalesRuleValueProvider;

class Addgift
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * __construct
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }

    /**
     * BeforeExecute
     *
     * @return mixed
     */
    public function beforeExecute()
    {
        $data = [];
    }
}
