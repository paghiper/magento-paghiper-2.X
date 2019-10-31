define([
        'jquery',
        'Magento_Checkout/js/view/payment/default'
    ],
    function ($, Component) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Foxsea_Paghiper/payment/paghiper',
                paghiper_taxvat: '',
            },
            initObservable: function () {
                this._super()
                    .observe([
                        'paghiper_taxvat',
                    ]);

                return this;
            },
            context: function() {
                return this;
            },
            getCode: function() {
                return 'foxsea_paghiper';
            },
            isActive: function() {
                return true;
            },
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'paghiper_taxvat': $('input#' + this.getCode() + '_taxvat').val()
                    }
                }
            }
        });
    }
);

