define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'Paghiperpayment',
                component: 'PagHiper_Paghiperpayment/js/view/payment/method-renderer/Paghiperpayment'
            }
        );
        return Component.extend({});
    }
);
