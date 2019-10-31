<?php

namespace Foxsea\Paghiper\Helper;

use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper {

    protected $storeManager;
    protected $scopeConfig;

    public function __construct(StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig){
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    public function getApiUrl(){
        return 'https://api.paghiper.com/';
    }

    public function getApiKey(){
        return $this->scopeConfig->getValue('payment/foxsea_paghiper/apikey', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);
    }

    public function getToken(){
        return $this->scopeConfig->getValue('payment/foxsea_paghiper/token', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);
    }

    public function getConfig($config){
        return $this->scopeConfig->getValue('payment/foxsea_paghiper/' . $config, \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);
    }

    public function getNotificationUrl(){
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB) . 'paghiper/order/update';
    }

}
