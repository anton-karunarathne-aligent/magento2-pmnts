/**
 * Copyright © PMNTS. All rights reserved.
 */

define([
    'jquery',
    'ko',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/redirect-on-success',
    'mage/translate',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage'
], function ($, ko, Component, quote, fullScreenLoader, additionalValidators, redirectOnSuccessAction, $t, customer, urlBuilder, storage) {
    'use strict';

    const APPLE_PAY_VERSION = 3;

    return Component.extend({
        defaults: {
            template: 'PMNTS_Gateway/payment/applepay'
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.applePayAvailable = ko.observable(false);
            this.applePaySession = null;
            this.paymentToken = null;
            this.fullScreenLoaderVisible = false;
            this.checkApplePayAvailability();
            return this;
        },

        /**
         * Check if Apple Pay is available
         */
        checkApplePayAvailability: function () {
            var isActive = this.isActive();

            if (isActive) {
                // check if device support Apple Pay
                if (window.ApplePaySession) {
                    var canMakeApplePayPayment = window.ApplePaySession.canMakePayments();
                    if (canMakeApplePayPayment) {
                        this.applePayAvailable(true);
                    } else {
                        console.warn('Can not make apple pay payment');
                    }
                } else {
                    console.warn('Apple Pay session not available.');
                }
            } else {
                this.applePayAvailable(false);
                console.warn('PMNTS Apple Pay is not enabled.');
            }
        },

        /**
         * Is Apple Pay available
         */
        isApplePayAvailable: function () {
            return this.applePayAvailable();
        },

        /**
         * Get payment method code
         */
        getCode: function () {
            return 'fatzebra_applepay';
        },

        /**
         * Get payment method data
         */
        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'applepay_wallet_payload': this.paymentToken
                }
            };
        },

        /**
         * Get config
         */
        getConfig: function () {
            return window.checkoutConfig.payment.fatzebra_applepay || {};
        },

        /**
         * Is payment method active
         */
        isActive: function () {
            var applePayConfigs = this.getConfig();
            return applePayConfigs.is_active || false;
        },

        /**
         * Start Apple Pay payment
         */
        startApplePay: function () {
            var self = this;

            // Safety check
            if (typeof window.ApplePaySession === 'undefined') {
                console.error('ApplePaySession is not available');
                this.messageContainer.addErrorMessage({
                    message: $t('Apple Pay is not available on this device.')
                });
                return false;
            }

            if (!this.validate()) {
                return false;
            }

            var totals = quote.getTotals()();
            var amount = totals.base_grand_total;
            var currencyCode = totals.base_currency_code;
            var config = this.getConfig();

            var request = {
                countryCode: config.merchant_country,
                currencyCode: currencyCode,
                supportedNetworks: config.supported_networks,
                merchantCapabilities: ['supports3DS', 'supportsDebit', 'supportsCredit'],
                total: {
                    label: config.store_title,
                    amount: amount.toString(),
                    type: 'final'
                }
            };

            this.applePaySession = new ApplePaySession(APPLE_PAY_VERSION, request);

            // Handle merchant validation
            this.applePaySession.onvalidatemerchant = function (event) {
                self.validateMerchant(event.validationURL).done(function (merchantSession) {
                    var session = typeof merchantSession === 'string'
                        ? JSON.parse(merchantSession)
                        : merchantSession;

                    self.applePaySession.completeMerchantValidation(session);
                }).fail(function (error) {
                    console.error('Merchant validation failed', error);
                    self.applePaySession.abort();
                    self.messageContainer.addErrorMessage({
                        message: $t('Merchant validation failed.')
                    });
                });
            };

            // Handle payment authorization
            this.applePaySession.onpaymentauthorized = function (event) {
                self.paymentToken = JSON.stringify(event.payment.token);

                // Custom Place order
                fullScreenLoader.startLoader();
                self.fullScreenLoaderVisible = true;

                self.placeOrder().done(function () {
                    self.applePaySession.completePayment({
                        status: ApplePaySession.STATUS_SUCCESS
                    });
                    if (self.redirectAfterPlaceOrder) {
                        redirectOnSuccessAction.execute();
                    }
                }).fail(function (error) {
                    console.error('Order placement failed', error);
                    self.applePaySession.completePayment({
                        status: ApplePaySession.STATUS_FAILURE
                    });
                    fullScreenLoader.stopLoader();
                    self.fullScreenLoaderVisible = false;

                });
            };

            // Handle cancellation
            this.applePaySession.oncancel = function (event) {
                // Payment cancelled.
                console.warn('Apple Pay was cancelled:', event);
                if (self.fullScreenLoaderVisible) {
                    fullScreenLoader.stopLoader();
                    self.fullScreenLoaderVisible = false;
                }
            };

            this.applePaySession.begin();
        },

        /**
         * Validate merchant using Web API
         */
        validateMerchant: function (validationURL) {
            var payload = {
                request: {
                    validation_url: validationURL
                }
            };

            // generate URL based on logged in customer or guest
            const loggedInCustomer = customer.isLoggedIn();
            const baseUrl = loggedInCustomer ? '/pmnts/applepay/customer/session' : '/pmnts/applepay/guest/:cartId/session';
            const params = loggedInCustomer ? {} : {cartId: quote.getQuoteId()};
            const serviceUrl = urlBuilder.createUrl(baseUrl, params);

            return storage.post(
                serviceUrl,
                JSON.stringify(payload),
                true,
                'application/json'
            ).fail(function (response) {
                console.error('API call failed:', response);
            });
        },

        /**
         * Get store locale
         */
        getLocale: function () {
            return window.LOCALE || 'en-US';
        },

        /**
         * Get Apple Pay Button attributes
         */
        getApplePayButtonAttributes: function () {
            return {
                buttonstyle: 'black', // black, white, white-outline
                type: 'pay',
                locale: this.getLocale()
            };
        },

        /**
         * Place order - overridden to return deferred object while keeping all validation
         */
        placeOrder: function (data, event) {
            var self = this;

            if (event) {
                event.preventDefault();
            }

            if (this.validate() && additionalValidators.validate() && this.isPlaceOrderActionAllowed() === true) {
                this.isPlaceOrderActionAllowed(false);

                return this.getPlaceOrderDeferredObject().done(function () {
                    self.afterPlaceOrder();
                }).always(function () {
                    self.isPlaceOrderActionAllowed(true);
                });
            }

            // Return rejected deferred if validation fails
            return $.Deferred().reject().promise();

        },

    });
});
