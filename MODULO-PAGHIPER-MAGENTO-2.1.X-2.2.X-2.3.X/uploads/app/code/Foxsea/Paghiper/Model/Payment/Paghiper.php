<?php
namespace Foxsea\Paghiper\Model\Payment;

use Magento\Framework\App\RequestInterface;

class Paghiper extends \Magento\Payment\Model\Method\AbstractMethod
{

    protected $_code                    = 'foxsea_paghiper';
    protected $_supportedCurrencyCodes  = ['BRL'];
    protected $_canOrder                = true;
    protected $_canCapture              = true;
    protected $_canAuthorize            = true;

    protected $_infoBlockType = 'Foxsea\Paghiper\Block\Payment\Info\Paghiper';

    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if($this->canOrder()){
            $info = $this->getInfoInstance();

            $order = $payment->getOrder();
            $data = $this->helper()->createOrderArray($order, $payment);

            if(!isset($data['error'])){
                $generate = $this->helper()->generate($data);
                if(isset($generate['success']) && $generate['success']){
                    $this->helper()->addInformation($order, $generate['additional']);
                }else if($generate['error']){
                    $this->log('Erro ao else if;');
                }
            }else{
                $message = isset($data['error_message']) ? $data['error_message'] : 'Erro ao gerar boleto.';
                throw new \Magento\Framework\Exception\CouldNotSaveException(
                    __($message)
                );
            }
        }else{
            $this->log('Não entrou no canOrder');
        }
    }

    protected function helper(){
        return \Magento\Framework\App\ObjectManager::getInstance()->get('Foxsea\Paghiper\Helper\Order');
    }

    protected function log($msg){
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/paghiper.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($msg);
    }

}

