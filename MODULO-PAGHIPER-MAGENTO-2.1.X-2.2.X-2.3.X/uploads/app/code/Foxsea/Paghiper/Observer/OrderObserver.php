<?php
namespace Foxsea\Paghiper\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderObserver implements ObserverInterface
{

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $orderStatus = $this->helper()->getConfig('order_status');
        $status = ($orderStatus != '') ? $orderStatus : 'pending_payment';

        $order = $observer->getEvent()->getOrder();

        $order->addStatusToHistory($status, 'Aguardando pagamento do boleto.', false);
        $order->save();
    }

    protected function helper(){
        return \Magento\Framework\App\ObjectManager::getInstance()->get('Foxsea\Paghiper\Helper\Data');
    }

    private function log($msg){
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/paghiper.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($msg);
    }

}
