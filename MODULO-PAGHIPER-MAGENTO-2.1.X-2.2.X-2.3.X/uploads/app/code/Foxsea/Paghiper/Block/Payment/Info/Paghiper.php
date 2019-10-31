<?php

namespace Foxsea\Paghiper\Block\Payment\Info;

use Magento\Payment\Block\Info;
use Magento\Framework\DataObject;

class Paghiper extends Info
{
    const TEMPLATE = 'Foxsea_Paghiper::info/paghiper.phtml';

    public function _construct()
    {
        $this->setTemplate(self::TEMPLATE);
    }

    public function getTitle()
    {
        return $this->getInfo()->getMethodInstance()->getTitle();
    }

}
