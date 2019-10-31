<?php

namespace Foxsea\Paghiper\Helper;

use \Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\RequestInterface;

class Order extends \Magento\Framework\App\Helper\AbstractHelper {

    protected $orderFactory;
    protected $request;

    public function __construct( OrderFactory $orderFactory, RequestInterface $request ) {
        $this->orderFactory = $orderFactory;
        $this->request = $request;
    }

    public function createOrderArray($order, $payment){
        if($order && $order->getRealOrderId()){
            $address = $order->getBillingAddress();

            $discount_payment = 0;
            $discount_days = 0;
            $early_payment = $this->helper()->getConfig('early_payment_discount');

            if($early_payment == 1){
                $discount_payment = $this->helper()->getConfig('early_payment_discounts_cents');
                if($discount_payment != '' && intval($discount_payment) >= 1){
                    $discount_payment = $order->getGrandTotal() * ($discount_payment / 100);
                    $discount_payment = intval($discount_payment * 100);
                }
                $discount_days = $this->helper()->getConfig('early_payment_discounts_days');
            }

            $taxvat = ($payment->getAdditionalInformation('paghiper_taxvat') != '') ? $payment->getAdditionalInformation('paghiper_taxvat') : $order->getCustomerTaxvat();

            if(!$taxvat){
                if(isset($this->request->getPost()['payment']['foxsea_paghiper_taxvat']) && $this->request->getPost()['payment']['foxsea_paghiper_taxvat'] != ''){
                    $taxvat = $this->request->getPost()['payment']['foxsea_paghiper_taxvat'];
                }
            }

            if(!$this->validateTaxvat($taxvat)){
                return ['error' => true, 'error_message' => 'CPF/CNPJ inválido.'];
            }

            $name = $address->getFirstname() . ' ' . $address->getLastname();

            $data = [
                'order_id' => $order->getIncrementId(),
                'payer_email' => $order->getCustomerEmail(),
                'payer_name' => $name,
                'payer_cpf_cnpj' => $taxvat,
                'payer_phone' => $address->getTelephone(),
                'payer_street' => (isset($address->getStreet()[0])) ? $address->getStreet()[0] : '',
                'payer_number' => (isset($address->getStreet()[1])) ? $address->getStreet()[1] : '',
                'payer_complement' => (isset($address->getStreet()[2])) ? $address->getStreet()[2] : '',
                'payer_district' => (isset($address->getStreet()[3])) ? $address->getStreet()[3] : '',
                'payer_city' => $address->getCity(),
                'payer_zip_code' => $address->getPostcode(),
                'discount_cents' => ($order->getDiscountAmount()*-1) * 100,
                'shipping_price_cents' => $order->getShippingAmount() * 100,
                'shipping_methods' => $order->getShippingDescription(),
                'fixed_description' => false,
                'days_due_date' => $this->helper()->getConfig('days_due_date'), // vencimento
                'late_payment_fine' => $this->helper()->getConfig('late_payment_fine'), // percentual multa
                'per_day_interest' => $this->helper()->getConfig('per_day_interest'), // juros por atraso (bool)
                'early_payment_discounts_cents' => $discount_payment,
                'early_payment_discounts_days' => $discount_days,
                'open_after_day_due' => $this->helper()->getConfig('open_after_day_due'),
                'notification_url' => $this->helper()->getNotificationUrl(),
                'type_bank_slip' => 'boletoA4',
                'partners_id' => 'N2DXMMU6',
                'items' => []
            ];

            foreach ($order->getAllVisibleItems() as $item) {
                $data['items'][] = [
                    'description' => $item->getName(),
                    'quantity' => $item->getQtyToShip() ?: 1,
                    'item_id' => $item->getSku(),
                    'price_cents' => $item->getPrice() * 100
                ];
            }
            return $data;
        }else{
            return ['error' => true];
        }
    }

    public function validateTaxvat($taxvat){
        $taxvat = str_replace('-', '', str_replace('.', '', $taxvat));
        //Caso seja CNPJ
        if(strlen($taxvat) == 14) {
            return $this->validateCnpj($taxvat);
        }

        //Caso seja CPF
        if(strlen($taxvat) == 11) {
            return $this->validateCpf($taxvat);
        }
    }

    public function generate($data){
        $data['apiKey'] = $this->helper()->getApiKey();

        $url = $this->helper()->getApiUrl() . 'transaction/create';

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

        if($result->create_request->result == 'reject'){
            $this->log('Problema ao gerar boleto #'. $data['order_id'] .': ' . $result->create_request->response_message);
            return ['success' => false, 'message' => $result->create_request->response_message];
        }else if($result->create_request->result == 'success'){
            $additional = [
                'boleto_url' => $result->create_request->bank_slip->url_slip_pdf,
                'linha_digitavel' => $result->create_request->bank_slip->digitable_line,
                'vencimento' => $result->create_request->due_date
            ];
            return ['success' => true, 'additional' => $additional];
        }
    }

    public function addInformation($order, $additional){
        if($order && is_array($additional) && count($additional) >= 1){
            $_additional = $order->getPayment()->getAdditionalInformation();
            foreach ($additional as $key => $value) {
                $_additional[$key] = $value;
            }
            $this->log($_additional);
            $order->getPayment()->setAdditionalInformation($_additional);
        }else{
            $this->log('Problema no IF');
            $this->log(var_export($additional));
        }
    }

    protected function helper(){
        return \Magento\Framework\App\ObjectManager::getInstance()->get('Foxsea\Paghiper\Helper\Data');
    }

    private function validateCpf($taxvat)
    {
        if (empty($taxvat)) {
            return false;
        }

        $taxvat = preg_replace('#[^0-9]#', '', $taxvat);
        $taxvat = str_pad($taxvat, 11, '0', STR_PAD_LEFT);

        if (strlen($taxvat) != 11) {
            return false;
        }

        if ($taxvat == '00000000000' ||
            $taxvat == '11111111111' ||
            $taxvat == '22222222222' ||
            $taxvat == '33333333333' ||
            $taxvat == '44444444444' ||
            $taxvat == '55555555555' ||
            $taxvat == '66666666666' ||
            $taxvat == '77777777777' ||
            $taxvat == '88888888888' ||
            $taxvat == '99999999999'
        ) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $taxvat{$c} * (($t + 1) - $c);
            }

            $d = ((10 * $d) % 11) % 10;

            if ($taxvat{$c} != $d) {
                return false;
            }
        }

        return true;
    }

    private function validateCnpj($taxvat)
    {
        $taxvat = preg_replace('/[^0-9]/', '', (string) $taxvat);

        if (strlen($taxvat) != 14) {
            return false;
        }

        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += $taxvat{$i} * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }

        $resto = $soma % 11;

        if ($taxvat{12} != ($resto < 2 ? 0 : 11 - $resto)) {
            return false;
        }

        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += $taxvat{$i} * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }

        $resto = $soma % 11;

        return $taxvat{13} == ($resto < 2 ? 0 : 11 - $resto);
    }

    private function log($msg){
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/paghiper.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($msg);
    }

}
