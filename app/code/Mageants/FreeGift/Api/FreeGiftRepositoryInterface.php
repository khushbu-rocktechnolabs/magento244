<?php
/**
 * @category Mageants FreeGift
 * @package Mageants_FreeGift
 * @copyright Copyright (c) 2017 Mageants
 * @author Mageants Team <support@mageants.com>
 */

namespace Mageants\FreeGift\Api;

/**
 * Interface FreeGiftRepositoryInterface
 */
interface FreeGiftRepositoryInterface
{
    /**
     * Returns Banner details
     *
     * @api
     * @param int $productId
     * @param int $storeId
     * @return string
     */
    public function getFreeGiftBannerDetails($productId, $storeId);

    /**
     * Returns Products details
     *
     * @api
     * @param int $productId
     * @param int $storeId
     * @return string
     */
    public function getFreeGiftBannerProducts($productId, $storeId);

    /**
     * Set Free Products in cart
     *
     * @api
     * @param string $cartId
     * @param string $freeGiftSkus
     * @param string $freeGiftSuperAttributes
     * @param int $storeId
     * @param int $productId
     * @return string
     */
    public function addFreeGiftBannerProducts($cartId, $freeGiftSkus, $freeGiftSuperAttributes, $storeId, $productId);

    /**
     * Update Free Products in cart
     *
     * @api
     * @param string $cartId
     * @param string $freeGiftSkus
     * @param string $freeGiftSuperAttributes
     * @param int $storeId
     * @param int $itemId
     * @return string
     */
    public function updateFreeGiftBannerProducts($cartId, $freeGiftSkus, $freeGiftSuperAttributes, $storeId, $itemId);

    /**
     * Add free gift product when Coupon Code add in the cart
     *
     * @api
     * @param string $cartId
     * @param string $couponCode
     * @param int $storeId
     * @return string
     */
    public function addCouponCode($cartId, $couponCode, $storeId);

    /**
     * Remove free gift product when remove Coupon Code from the cart
     *
     * @api
     * @param string $cartId
     * @param string $couponCode
     * @param int $storeId
     * @return string
     */
    public function removeCouponCode($cartId, $couponCode, $storeId);
}
