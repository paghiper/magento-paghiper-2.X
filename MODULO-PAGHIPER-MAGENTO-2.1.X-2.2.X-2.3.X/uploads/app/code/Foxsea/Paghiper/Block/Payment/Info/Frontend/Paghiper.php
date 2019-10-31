<?php

namespace Foxsea\Paghiper\Block\Payment\Info\Frontend;

class Paghiper extends \Magento\Checkout\Block\Onepage\Success
{

    public function getOrder() {
        return $this->_checkoutSession->getLastRealOrder();
    }

}
