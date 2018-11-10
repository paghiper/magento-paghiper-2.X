<?php

namespace PagHiper\Paghiperpayment\Block;
use Magento\Sales\Model\Order;

/*
 * PagHiper Paghiperpayment Block
 */

class Paghiperpayment extends \Magento\Framework\View\Element\Template{

    protected $orderRepository;
    protected $searchCriteriaBuilder;
    protected $customerSession;
   
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context, 
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\Session $customerSession,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
    }
   
    public function getOrderById($id) {
        return $this->orderRepository->get($id);
    }
   
    public function getOrderByIncrementId($incrementId) {
        $this->searchCriteriaBuilder->addFilter('increment_id', $incrementId);
 
        $order = $this->orderRepository->getList(
            $this->searchCriteriaBuilder->create()
        )->getItems();
 
        return $order;
    }

    protected function _prepareLayout(){
        return parent::_prepareLayout();
    }

    /**
     * getContentForDisplay
     * @return string
     */
    public function getContentForDisplay(){
        if (isset($_POST['transaction_id'])){
            $this->callbackAction($_POST); exit;
        }

        $customer_id = ($this->customerSession->getCustomer()->getId()) ? $this->customerSession->getCustomer()->getId() : header('Location: /') ;

        $uri = explode("/", $_SERVER['REQUEST_URI']);
        $order_id_print_bank_slip = (isset($uri[4])) ? $uri[4] : false ;

        if (isset($uri[5]) && $this->validar_cpf($uri[5])){
            $_POST['cpf'] = $uri[5];
        }

        if ($order_id_print_bank_slip && !empty($order_id_print_bank_slip)){
           $order = $this->getDataSalesOrderGrid($order_id_print_bank_slip);

            if (isset($order[0]['customer_id']) && $order[0]['customer_id'] == $customer_id){
               $link_slip = $order[0]['paghiper_url_slip'];

                if ($link_slip){
                    header("Location: $link_slip"); exit;
                }
            }
        }

        # PEGA ORDER ID DO ULTIMO PEDIDO
        $order_id = (isset($_SESSION['checkout']['last_order_id'])) ? $_SESSION['checkout']['last_order_id'] : header('Location: /') ;

        $cpf_cnpj = (isset($_POST['cpf'])) ? $_POST['cpf'] : '';
        if (isset($_SESSION['PagHiper']) && $_SESSION['PagHiper']['order_id'] == $order_id){
            # CARREGA BOLETO GERANDO ANTERIORMENTE
            echo $this->getView($_SESSION['PagHiper']);
        } elseif (!empty($cpf_cnpj) && $this->validar_cpf($cpf_cnpj)){
            # GERA NOVO BOLETO
            $bank_slip = $this->biuldBankSlip($order_id, $cpf_cnpj);
            
            if (isset($bank_slip['url_slip'])){
                # EXIBI NOVO BOLETO 
                echo $this->getView($bank_slip);
            }

        } else {
            # CARREGA FORMULARIO DE SOLICITACAO DE CPF E CNPJ
            echo $this->getFormValidation($order_id, $cpf_cnpj);
        }
    }

    private function getDataSalesOrderGrid($order_id){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_order_grid');
         
        $sql = "SELECT * FROM {$tableName} WHERE entity_id = {$order_id}";

        $result = $connection->fetchAll($sql);
         
        return $result;
    }

    private function getLastCPFCNPJSalesOrderGrid($customer_id){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_order_grid');
         
        $sql = "SELECT * FROM {$tableName} WHERE customer_id = {$customer_id} AND paghiper_cpf_cnpj <> '' ORDER BY entity_id DESC LIMIT 1";

        $result = $connection->fetchAll($sql);

        if (isset($result[0]['paghiper_cpf_cnpj'])){
            return $result[0]['paghiper_cpf_cnpj'];
        } else {
            return '';
        }
    }

    private function getOrderIdByTransactionId($paghiper_transaction_id){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_order_grid');
         
        $sql = "SELECT * FROM {$tableName} WHERE paghiper_transaction_id = '{$paghiper_transaction_id}'";

        $result = $connection->fetchAll($sql);
         
        return $result;
    }

    private function addDataPagHiperBankSlip($order_id, $paghiper_url_slip, $paghiper_transaction_id, $cpf_cnpj){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_order_grid');
        
        $sql = "UPDATE {$tableName} SET paghiper_url_slip = '{$paghiper_url_slip}', paghiper_transaction_id = '{$paghiper_transaction_id}', paghiper_cpf_cnpj = '{$cpf_cnpj}' WHERE entity_id = {$order_id}";

        return $connection->query($sql);
    }

    private  function getFormValidation($order_id, $cpf_cnpj){
        $html  = '<div class="box-bank-slip">';
        $html .= '<div class="top">';
        $html .= '<div class="order">';
        $html .= '<p>Número do pedido</p>';
        $html .= '<div class="order-id"><img class="check" src="http://opencart.zirg.com.br/wp-content/uploads/2018/08/order_check.png">' . $order_id . '</div>';
        $html .= '</div>';
        $html .= '<img class="logo-paghiper" src="http://opencart.zirg.com.br/wp-content/uploads/2018/08/logo.gif"/>';
        $html .= '</div>';
        $html .= '</div><br/><br/>';

        $html .= '<center>';
        $html .= '<div class="biuld">';
        $html .= '<form action="/paghiper/index/index" method="POST">';

        $customer_id = ($this->customerSession->getCustomer()->getId()) ? $this->customerSession->getCustomer()->getId() : 0 ;
        $cpf_cnpj_sales_order_grid = $this->getLastCPFCNPJSalesOrderGrid($customer_id);

        if (isset($cpf_cnpj) && !empty($cpf_cnpj) && !$this->validar_cpf($cpf_cnpj)){
            $html .= '<input type="text" name="cpf" class="text" placeholder="CPF" onkeydown="javascript: fMasc( this, mCPF );" value="' . $cpf_cnpj . '" /><br/><br/>';
            $html .= '<small class="error-cpf">CPF inválido</small><br/><br/>';
        } elseif (!empty($cpf_cnpj_sales_order_grid)) {
            $html .= '<input type="text" name="cpf" class="text" placeholder="CPF" onkeydown="javascript: fMasc( this, mCPF );" value="' . $cpf_cnpj_sales_order_grid . '" /><br/><br/>';
        } else {
            $html .= '<input type="text" name="cpf" class="text" placeholder="CPF" onkeydown="javascript: fMasc( this, mCPF );" /><br/><br/>';
        }

        $html .= '<button id="gerar" type="submit">GERAR BOLETO</button>';
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</center>';
        $html .= '<style type="text/css"> input[name="cpf"] { text-align: center; width: 50%; }';
        $html .= '.error-cpf { color: red; } </style>';
        $html .= '<script type="text/javascript">
        function fMasc(objeto,mascara) {
            obj=objeto
            masc=mascara
            setTimeout("fMascEx()",1)
        }
        function fMascEx() {
            obj.value=masc(obj.value)
        }
        function mTel(tel) {
            tel=tel.replace(/\D/g,"")
            tel=tel.replace(/^(\d)/,"($1")
            tel=tel.replace(/(.{3})(\d)/,"$1)$2")
            if(tel.length == 9) {
                tel=tel.replace(/(.{1})$/,"-$1")
            } else if (tel.length == 10) {
                tel=tel.replace(/(.{2})$/,"-$1")
            } else if (tel.length == 11) {
                tel=tel.replace(/(.{3})$/,"-$1")
            } else if (tel.length == 12) {
                tel=tel.replace(/(.{4})$/,"-$1")
            } else if (tel.length > 12) {
                tel=tel.replace(/(.{4})$/,"-$1")
            }
            return tel;
        }
        function mCNPJ(cnpj){
            cnpj=cnpj.replace(/\D/g,"")
            cnpj=cnpj.replace(/^(\d{2})(\d)/,"$1.$2")
            cnpj=cnpj.replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3")
            cnpj=cnpj.replace(/\.(\d{3})(\d)/,".$1/$2")
            cnpj=cnpj.replace(/(\d{4})(\d)/,"$1-$2")
            return cnpj
        }
        function mCPF(cpf){
            cpf=cpf.replace(/\D/g,"")
            cpf=cpf.replace(/(\d{3})(\d)/,"$1.$2")
            cpf=cpf.replace(/(\d{3})(\d)/,"$1.$2")
            cpf=cpf.replace(/(\d{3})(\d{1,2})$/,"$1-$2")
            return cpf
        }
        function mCEP(cep){
            cep=cep.replace(/\D/g,"")
            cep=cep.replace(/^(\d{2})(\d)/,"$1.$2")
            cep=cep.replace(/\.(\d{3})(\d)/,".$1-$2")
            return cep
        }
        function mNum(num){
            num=num.replace(/\D/g,"")
            return num
        }
        </script>';
        $html .= "<style type='text/css'>
                .box-bank-slip {
            margin: 0 auto;
            position: relative;
        }
        .box-bank-slip iframe {
            margin-top: 40px;
        }
        .box-bank-slip .logo-paghiper {
            position: absolute;
            left: 0;
        }
        .box-bank-slip .check {
            position: absolute;
            left: -40px;
            margin-top: 4px;
        }

        .box-bank-slip .order {
            position: absolute;
            right: 0;
            color: #6d6d6d;
            font-family: 'Arial';
        }
        .box-bank-slip p {
            position: absolute;
            right: 0;
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            min-width: 125px;
        }
        .box-bank-slip .order-id {
            font-weight: 700;
            text-align: right;
            font-size: 40px;
            margin-top: 10px;
        }
        .box-bank-slip .top {
            height: 80px;
        }
        .box-info-bank-slip {
            padding: 15px;
            background-color: #f2f2f2;
            border: solid;
            border-width: 0 0 0 3px;
            border-color: #1256a9;
            font-family: 'Arial';
        }
        .box-bank-slip .box-info-bank-slip input {
            margin: 10px;
            text-align: center;
            font-size: 14px;
            width: 80%;
        }
        .box-bank-slip .description {
            font-size: 14px;
            color: #000;
        }
        .box-bank-slip .title {
            font-size: 25px;
            color: #1256a9;
            border: solid;
            border-width: 0px 0px 2px 0px;
            border-color: #1256a9;
            margin-bottom: 15px;
        }
        .box-info-bank-slip .total {
            font-size: 16px;
        }
        .box-info-bank-slip button {
            border: 0;
            background-color: #5e5e5e;
            color: #fff;
            padding: 6px 15px 6px 15px;
            font-size: 14px;
            margin: 15px  0 0 0;
            border-radius: 3px;
        }
        @media only screen and (max-width: 768px){
            .box-bank-slip .order {
                display: none;
            }
            .box-bank-slip iframe {
                display: none;
            }
        }
        input[name='cpf'] {
          text-align: center;
          width: 50%;
        }
        </style>";

        return $html;
    }

    private function biuldBankSlip($order_id, $cpf_cnpj){
        unset($_SESSION['PagHiper']);
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('Magento\Sales\Api\Data\OrderInterface')->load($order_id);

        $data_order = $order->getData();
        $data_order_address = $order->getBillingAddress()->getData();

        # CONFIG MODULE
        $merchant_email = $this->scopeConfig->getValue('payment/paghiperpayment/merchant_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $discount       = $this->scopeConfig->getValue('payment/paghiperpayment/discount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $maturity_date  =  $this->scopeConfig->getValue('payment/paghiperpayment/maturity_date', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $discount_group = $this->scopeConfig->getValue('payment/paghiperpayment/discount_group', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $api_key        = $this->scopeConfig->getValue('payment/paghiperpayment/api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($discount_group && isset($data_order['discount_amount']) && abs($data_order['base_discount_amount']) > 0 && isset($discount) && $discount > 0){
            $discount_total = (($data_order['base_subtotal'] / 100) * $discount) + abs($data_order['base_discount_amount']);
        } elseif (isset($discount) && $discount > 0 && $discount > abs($data_order['base_discount_amount'])) {
            $discount_total = ($data_order['base_subtotal'] / 100) * $discount;
        } elseif (isset($data_order['discount_amount']) && abs($data_order['base_discount_amount']) > 0){
            $discount_total = abs($data_order['base_discount_amount']);
        } else {
            $discount_total = 0;
        }

        # DESCONTO TOTAL EM CENTAVOS 
        $discount_total_cents = $discount_total * 100;

        $shipping_amount = (isset($data_order['shipping_amount'])) ? $data_order['shipping_amount'] : 0;

        # DESCONTO EM FRETE EM CENTAVOS 
        $shipping_amount_cents = $shipping_amount * 100;

        # SUBTOTAL EM CENTAVOS 
        $subtotal_cents = $data_order['base_subtotal'] * 100;

        $data = array(
          'apiKey' => $api_key,
          'order_id' => $order_id, // código interno do lojista para identificar a transacao.
          'payer_email' => $data_order['customer_email'],
          'payer_name' => $data_order['customer_firstname'] . ' ' . $data_order['customer_lastname'],
          'payer_cpf_cnpj' => $cpf_cnpj, // cpf ou cnpj
          'payer_phone' => $data_order_address['telephone'], // fixou ou móvel
          'payer_street' => $data_order_address['street'],
          'payer_number' => '',
          'payer_complement' => '',
          'payer_district' => '',
          'payer_city' => $data_order_address['city'],
          'payer_state' => '',
          'payer_zip_code' => $data_order_address['postcode'],
          'notification_url' => $this->getUrlReturn() . 'paghiper/index/index/',
          'discount_cents' => $discount_total_cents, // em centavos
          'shipping_price_cents' => $shipping_amount_cents, // em centavos
          'shipping_methods' => $data_order['shipping_description'],
          'fixed_description' => false,
          'type_bank_slip' => 'boletoA4', // formato do boleto
          'days_due_date' => $maturity_date, // dias para vencimento do boleto
          'items' => array(
              array ('description' => 'Item compra',
              'quantity' => '1',
              'item_id' => '1',
              'price_cents' => $subtotal_cents), // em centavos
            ),
        );

        $data_post = json_encode( $data );

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.paghiper.com/transaction/create/",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_SSL_VERIFYHOST => false,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => $data_post,
          CURLOPT_HTTPHEADER => array(
            "Cache-Control: no-cache",
            "Content-Type: application/json",
            "Postman-Token: 676bba03-a9b7-4b63-8e47-45b0161f687e"
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        $return = json_decode($response);

        if (isset($return->create_request->result) && $return->create_request->result == 'success'){
            $data_return['order_id'] = $order_id;
            $data_return['digitable_line'] = $return->create_request->bank_slip->digitable_line;
            $data_return['total'] = $this->reais($return->create_request->value_cents);
            $data_return['url_slip'] = $return->create_request->bank_slip->url_slip;

            $this->addDataPagHiperBankSlip($order_id, $return->create_request->bank_slip->url_slip, $return->create_request->transaction_id, $cpf_cnpj);

            $_SESSION['PagHiper'] = $data_return;

            return $data_return;
        } else {
            return false;
        }
    }

    private function httpPost($url,$params){
        if (isset($_SESSION['PagHiper'])){
            unset($_SESSION['PagHiper']);
        }

        $postData = '';
        //create name value pairs seperated
        foreach($params as $k => $v) { $postData .= $k . '='.$v.'&'; }
        $postData = rtrim($postData, '&');
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, count($postData));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $output=curl_exec($ch);
        curl_close($ch);

        $data = json_decode($output);
        
        $bankSlipInfo['urlPagamento']   = $data->transacao[0]->urlPagamento;
        $bankSlipInfo['linhaDigitavel'] = $data->transacao[0]->linhaDigitavel;
        $bankSlipInfo['idPlataforma']   = $params['id_plataforma'];
        $bankSlipInfo['valorTotal']     = $this->reais($data->transacao[0]->valorTotal);

        if (isset($bankSlipInfo['urlPagamento']) && !empty($bankSlipInfo['urlPagamento'])){
            $_SESSION['PagHiper']['urlPagamento']   = $data->transacao[0]->urlPagamento;
            $_SESSION['PagHiper']['linhaDigitavel'] = $data->transacao[0]->linhaDigitavel;
            $_SESSION['PagHiper']['idPlataforma']   = $params['id_plataforma'];
            $_SESSION['PagHiper']['valorTotal']     = $this->reais($data->transacao[0]->valorTotal);
        }

        echo $this->getView($bankSlipInfo);
    }

    private function reais($value) {
      return 'R$ '.number_format($value / 100,2,',','.');
    }

    private function getView($data){
        $html  = '';
        $html .= '<div class="box-bank-slip">';
            $html .= '<div class="top">';
                $html .= '<div class="order">';
                    $html .= '<p>Número do pedido</p>';
                    $html .= '<div class="order-id"><img class="check" src="http://opencart.zirg.com.br/wp-content/uploads/2018/08/order_check.png">' . $data['order_id'] . '</div>';
                $html .= '</div>';
                $html .= '<img class="logo-paghiper" src="http://opencart.zirg.com.br/wp-content/uploads/2018/08/logo.gif"/>';
            $html .= '</div>';
            $html .= '<div class="box-info-bank-slip">';
                $html .= '<div class="title">Boleto Bancário</div>';
                $html .= '<div class="description">';
                    $html .= '<center>';
                        $html .= '<b>Use a linha digitável para pagar ou use o botão abaixo para imprimir ou fazer donwload</b>';
                    $html .= '</center>';
                $html .= '</div>';
                $html .= '<center>';
                    $html .= '<input type="text" value="' . $data['digitable_line'] . '"/>';
                    $html .= '<div class="total">Valor total do boleto: ' . $data['total'] . '</div>';
                    $html .= '<button class="btn-print" onclick="printBankSlip()">Imprimir Boleto</button>';
                $html .= '</center>';
            $html .= '</div>';
            $html .= '<center>';
                $html .= '<iframe src="' . $data['url_slip'] . '" width="100%" height="250"></iframe>';
            $html .= '</center>';
        $html .= '</div>';
        $html .= $this->getScript($data);

        return $html;
    }

    /**
    * Função responsavel por receber o retorno do Paghiper e atualizar o status de 1 pedido
    **/
    private function callbackAction($data) {
        $data['token'] = $this->scopeConfig->getValue('payment/paghiperpayment/trans_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $data_order    = $this->getOrderIdByTransactionId($data['transaction_id']);

        if (isset($data_order[0]['entity_id'])){
            $json = json_encode($data);

            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.paghiper.com/transaction/notification/",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => $json,
              CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
                "Content-Type: application/json",
                "Postman-Token: e5c8ba51-d208-4cb8-9dca-5feae056c4b8"
              ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            $this->writeLog($response);

            $return_paghiper = json_decode(utf8_encode($response));

            if (isset($return_paghiper->status_request->status)){
                switch ($return_paghiper->status_request->status) {
                    case 'canceled':
                        $orderId = $data_order[0]['entity_id'];
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
                        $orderState = Order::STATE_CANCELED;
                        $order->setState($orderState)->setStatus(Order::STATE_CANCELED);
                        $order->save();
                    break;
                    case 'paid':
                        $orderId = $data_order[0]['entity_id'];
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
                        $orderState = Order::STATE_PROCESSING;
                        $order->setState($orderState)->setStatus(Order::STATE_PROCESSING);
                        $order->save();
                    break; 
                    case 'completed':
                        $orderId = $data_order[0]['entity_id'];
                        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                        $order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
                        $orderState = Order::STATE_PROCESSING;
                        $order->setState($orderState)->setStatus(Order::STATE_PROCESSING);
                        $order->save();
                    break;
                    
                    default:
                        # code...
                    break;
                }
            }
        }
    }

    private function validar_cpf($cpf){
        $invalidos = array('00000000000',
        '11111111111',
        '22222222222',
        '33333333333',
        '44444444444',
        '55555555555',
        '66666666666',
        '77777777777',
        '88888888888',
        '99999999999');
        if (in_array($cpf, $invalidos))
        return false;

        $cpf = preg_replace('/[^0-9]/', '', (string) $cpf);
        // Valida tamanho
        if (strlen($cpf) != 11)
            return false;
        // Calcula e confere primeiro dígito verificador
        for ($i = 0, $j = 10, $soma = 0; $i < 9; $i++, $j--)
            $soma += $cpf{$i} * $j;
        $resto = $soma % 11;
        if ($cpf{9} != ($resto < 2 ? 0 : 11 - $resto))
            return false;
        // Calcula e confere segundo dígito verificador
        for ($i = 0, $j = 11, $soma = 0; $i < 10; $i++, $j--)
            $soma += $cpf{$i} * $j;
        $resto = $soma % 11;
        return $cpf{10} == ($resto < 2 ? 0 : 11 - $resto);
    }

    private function getScript($bankSlipInfo){
        $script = "<style type='text/css'>
        .box-bank-slip {
    margin: 0 auto;
    position: relative;
}
.box-bank-slip iframe {
    margin-top: 40px;
}
.box-bank-slip .logo-paghiper {
    position: absolute;
    left: 0;
}
.box-bank-slip .check {
    position: absolute;
    left: -40px;
    margin-top: 4px;
}

.box-bank-slip .order {
    position: absolute;
    right: 0;
    color: #6d6d6d;
    font-family: 'Arial';
}
.box-bank-slip p {
    position: absolute;
    right: 0;
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    min-width: 125px;
}
.box-bank-slip .order-id {
    font-weight: 700;
    text-align: right;
    font-size: 40px;
    margin-top: 10px;
}
.box-bank-slip .top {
    height: 80px;
}
.box-info-bank-slip {
    padding: 15px;
    background-color: #f2f2f2;
    border: solid;
    border-width: 0 0 0 3px;
    border-color: #1256a9;
    font-family: 'Arial';
}
.box-bank-slip .box-info-bank-slip input {
    margin: 10px;
    text-align: center;
    font-size: 14px;
    width: 80%;
}
.box-bank-slip .description {
    font-size: 14px;
    color: #000;
}
.box-bank-slip .title {
    font-size: 25px;
    color: #1256a9;
    border: solid;
    border-width: 0px 0px 2px 0px;
    border-color: #1256a9;
    margin-bottom: 15px;
}
.box-info-bank-slip .total {
    font-size: 16px;
}
.box-info-bank-slip button {
    border: 0;
    background-color: #5e5e5e;
    color: #fff;
    padding: 6px 15px 6px 15px;
    font-size: 14px;
    margin: 15px  0 0 0;
    border-radius: 3px;
}
@media only screen and (max-width: 768px){
    .box-bank-slip .order {
        display: none;
    }
    .box-bank-slip iframe {
        display: none;
    }
}
input[name='cpf'] {
  text-align: center;
  width: 50%;
}
</style>";

    $script .= '<script type="text/javascript">';
    $script .= 'function printBankSlip(){';
    $script .= 'window.open("' .  $bankSlipInfo['url_slip'] .'", "_blank");';
    $script .= '}';
    $script .= '';
    $script .= '</script>';

    return $script;

    }

    private function getUrlReturn(){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

        $protocols = array(0 => 'https://www.', 1 => 'https://', 2 => 'http://www.', 3 => 'http://', 4 => 'www.', 5 => '');

        $url_base = str_replace($protocols, '', $storeManager->getStore()->getBaseUrl());

        foreach ($protocols as $key => $protocol) {
          $url = $protocol . $url_base;

          if ($this->verifyURL($url)){
            return $url;
          } else {
            unset($url);
          }
        }

        return $url_base;
    }

    private function verifyURL($url){
      $handle = curl_init($url);
      curl_setopt($handle,  CURLOPT_RETURNTRANSFER, true);

      curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

      /* Get the HTML or whatever is linked in $url. */
      $response = curl_exec($handle);

      /* Check for 404 (file not found). */
      $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
      $output = curl_exec($handle);

      if($httpCode == 200) {
          return true;
      } else {
        return false;
      }
    }

    private function writeLog($data){
        $fp = fopen("/var/www/html/magento2/paghiper.txt", "a");
        $date = date('H:i:s Y/m/d');

        $content = '--------------- ' . $date . ' ---------------' . PHP_EOL;
        $content .= utf8_decode($data) . PHP_EOL;
        $content .= '----------------------- FIM -----------------------' . PHP_EOL;

        fwrite($fp, $content);
         
        // Fecha o arquivo
        fclose($fp);
    }
}
