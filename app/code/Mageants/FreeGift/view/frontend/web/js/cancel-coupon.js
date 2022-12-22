define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'mage/url',
    'Magento_Checkout/js/model/resource-url-manager',
    'Magento_Checkout/js/model/error-processor',
    'Magento_SalesRule/js/model/payment/discount-messages',
    'mage/storage',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/model/totals',
    'mage/translate',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/get-totals'
], function ($, quote, urlBuilder, urlManager, errorProcessor, messageContainer, storage, getPaymentInformationAction, totals, $t,
  fullScreenLoader, customerData, getTotalsAction
) {
    'use strict';
     var successCallbacks = [],
        action,
        callSuccessCallbacks;

    /**
     * Execute callbacks when a coupon is successfully canceled.
     */
    callSuccessCallbacks = function () {
        successCallbacks.forEach(function (callback) {
            callback();
        });
    };
    
    /**
     * Cancel applied coupon.
     *
     * @param {Boolean} isApplied
     * @returns {Deferred}
     */
    action = function (isApplied) {
            var couponUrl = urlBuilder.build("mageants_freegift/index/removecoupon");
            jQuery.ajax({
                type: "POST",
                url: couponUrl,
                data: {Id: quote.getQuoteId()},
                success:  function(data){
                   // window.location.reload(true);
                }
            });
        var quoteId = quote.getQuoteId(),
            url = urlManager.getCancelCouponUrl(quoteId),
            message = $t('Your coupon was successfully removed.');

        messageContainer.clear();
        fullScreenLoader.startLoader();
        setTimeout(function(){            
            return storage.delete(
                url,
                false
            ).done(function () {
                var deferred = $.Deferred();            
                totals.isLoading(true);
                getPaymentInformationAction(deferred);
                $.when(deferred).done(function () {
                    isApplied(false);
                    totals.isLoading(false);
                    fullScreenLoader.stopLoader();
                    //Allowing to tap into coupon-cancel process.
                    callSuccessCallbacks();
                });
                var sections = ['cart'];
                customerData.invalidate(sections);
                customerData.reload(sections, true);
                getTotalsAction([], deferred);
                window.location.reload(true);
                messageContainer.addSuccessMessage({
                    'message': message
                });
            }).fail(function (response) {
                totals.isLoading(false);
                fullScreenLoader.stopLoader();
                errorProcessor.process(response, messageContainer);
            });
        }, 2000);
    };
    /**
     * Callback for when the cancel-coupon process is finished.
     *
     * @param {Function} callback
     */
    action.registerSuccessCallback = function (callback) {
        successCallbacks.push(callback);
    };

    return action;
});
