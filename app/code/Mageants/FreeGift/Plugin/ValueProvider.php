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

class ValueProvider
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }

    /**
     * AroundGetMetadataValues
     *
     * @param  SalesRuleValueProvider $subject
     * @param  \Closure               $proceed
     * @param  Rule                   $rule
     * @return mixed
     */
    public function aroundGetMetadataValues(
        SalesRuleValueProvider $subject,
        \Closure $proceed,
        Rule $rule
    ) {
        $result = $proceed($rule);

        $actions = &$result['actions']['children']['simple_action']['arguments']['data']['config']['options'];

        $actions[] = [
            'label' => __('Auto Add free gift with products'),
            'value' => 'add_free_item'
        ];

        $url = $this->storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
        ) . 'freegift/product/tmp/' . $rule->getFglabelUpload();

        $data[0]['name'] = $rule->getFglabelUpload();
        $data[0]['url'] = $url;

        $result['actions']['children']['free_gift_type']['arguments']['data']['config']['options'] = [
            ['label' => __('All of the SKUs below'), 'value' => '1'],
            ['label' => __('Customer Select sku(s) that he/she like'), 'value' => '2']
        ];
                                    
        $result['freegift_product_label']['children']['fglabel_upload_image']['arguments']['data']['config']['value'] = $data;
        return $result;
    }
}
