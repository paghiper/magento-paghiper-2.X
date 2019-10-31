define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
    ],
    function (Component, rendererList) {
        'use strict';
        rendererList.push(
            {
                type: 'foxsea_paghiper',
                component: 'Foxsea_Paghiper/js/view/payment/method-renderer/paghiper'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    });
