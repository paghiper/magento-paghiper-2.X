<?php

namespace Foxsea\Paghiper\Controller\Order;

class Update extends \Magento\Framework\App\Action\Action
{

    protected $_context;
    protected $_pageFactory;
    protected $_jsonEncoder;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Json\EncoderInterface $encoder,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Creditmemo\ItemCreationFactory $creditmemoFactory
    ) {
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;

        if($context->getRequest()->getMethod() == 'POST'){
            $key_form = $this->_objectManager->get('Magento\Framework\Data\Form\FormKey');
            $context->getRequest()->setParam('form_key', $key_form->getFormKey());
        }

        $this->_context = $context;
        $this->_pageFactory = $pageFactory;
        $this->_jsonEncoder = $encoder;
        parent::__construct($context);
    }

    public function execute()
    {
        $data = $_POST;
        $data['token'] = $this->helper()->getToken();

        $url = $this->helper()->getApiUrl() . 'transaction/notification';

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8'
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result);

        if($result->status_request->result == 'reject'){
            $this->log('Problema ao atualizar transação '. $data['transaction_id'] .': ' . $result->status_request->response_message);
        }else if($result->status_request->result == 'success'){
            $order_id = $result->status_request->order_id;
            $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($order_id);

            $status = $result->status_request->status;
            if(($status == 'paid' || $status == 'completed') && $order->getInvoiceCollection()->count() <= 0){
                // se o pedido for pago, geramos a fatura
                if($order->canInvoice()){
                    $invoice = $this->_invoiceService->prepareInvoice($order);
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                    $invoice->register();
                    $invoice->getOrder()->setCustomerNoteNotify(false);
                    $invoice->getOrder()->setIsInProcess(true);
                    $order->addStatusHistoryComment('Pedido aprovado através da Paghiper.', 'processing');
                    $transactionSave = $this->_transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                    $transactionSave->save();
                }
            }else if($status == 'refunded'){
                $_invoice = $this->_objectManager->create('Magento\Sales\Model\Order\Invoice');
                $creditMemoFactory = $this->_objectManager->create('Magento\Sales\Model\Order\CreditmemoFactory');
                $creditmemoService = $this->_objectManager->create('Magento\Sales\Model\Service\CreditmemoService');

                // se o pedido foi reembolsado, criamos um reembolso no magento
                $invoices = $order->getInvoiceCollection();
                foreach ($invoices as $invoice) {
                    $invoiceincrementid = $invoice->getIncrementId();
                }

                $invoiceobj = $_invoice->loadByIncrementId($invoiceincrementid);
                $creditmemo = $creditMemoFactory->createByOrder($order);
                $creditmemo->setInvoice($invoiceobj);
                $creditmemoService->refund($creditmemo);
            }else if($status == 'canceled'){
                // se o pedido foi cancelado, cancelamos no magento
                if($order->canCancel()) {
                    $order->cancel()->save();
                }
            }
        }
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
