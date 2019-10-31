<?php
namespace Foxsea\Paghiper\Observer;

use Magento\Payment\Observer\AbstractDataAssignObserver;

class DataAssignObserver extends AbstractDataAssignObserver {

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $method = $this->readMethodArgument($observer);
        $data = $this->readDataArgument($observer);

        $paymentInfo = $method->getInfoInstance();

        if($data->getDataByKey('additional_data') !== null){
            $additional = $data->getDataByKey('additional_data');
            if(isset($additional['paghiper_taxvat'])){
                $paymentInfo->setAdditionalInformation(
                    'paghiper_taxvat',
                    $additional['paghiper_taxvat']
                );
            }
        }
    }

    protected function log($msg){
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/paghiper.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($msg);
    }
}
