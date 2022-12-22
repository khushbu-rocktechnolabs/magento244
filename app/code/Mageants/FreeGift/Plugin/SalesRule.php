<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */

namespace Mageants\FreeGift\Plugin;

class SalesRule
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    /**
     * __construct
     * @param \Magento\Framework\Registry $registry [description]
     */
    public function __construct(
        \Magento\Framework\Registry $registry
    ) {
        $this->coreRegistry = $registry;
    }

    /**
     * AfterSave
     *
     * @param  \Magento\SalesRule\Model\Rule $subject
     * @param  mixed                         $result
     * @return mixed
     */
    public function afterSave(\Magento\SalesRule\Model\Rule $subject, $result)
    {
        $this->coreRegistry->register('freegift_salesrule', $subject, true);
        return $result;
    }
}
