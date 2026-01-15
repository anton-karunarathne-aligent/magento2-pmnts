/**
 * Copyright © PMNTS. All rights reserved.
 */

define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    // check if ApplePay session is available and Apple Pay payment method is active
    if (window.ApplePaySession) {
        var canMakeApplePayPayment = window.ApplePaySession.canMakePayments();
        var paymentConfig = window.checkoutConfig?.payment?.fatzebra_applepay || {};
        var isActive = paymentConfig.is_active || false;

        if (isActive && canMakeApplePayPayment) {
            rendererList.push({
                type: 'fatzebra_applepay',
                component: 'PMNTS_Gateway/js/view/payment/method-renderer/applepay'
            });
        }
    }

    return Component.extend({});
});
