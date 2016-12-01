<?php
/**
 * Appmerce - Applications for Ecommerce
 * http://www.appmerce.com
 *
 * @extension   Bitcoin
 * @type        Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category    Magento
 * @package     Appmerce_Bitcoin
 * @copyright   Copyright (c) 2011-2014 Appmerce (http://www.appmerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Appmerce\Bitcoin\Helper;

use Appmerce\Bitcoin\Model\Api;
use Magento\Framework\App\Cache\Proxy;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper
{
    /**
     * @var Api
     */
    protected $_modelApi;

    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * @var Proxy
     */
    protected $_cacheProxy;

    public function __construct(Context $context, 
        Api $modelApi, 
        StoreManagerInterface $modelStoreManagerInterface, 
        Proxy $cacheProxy)
    {
        $this->_modelApi = $modelApi;
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_cacheProxy = $cacheProxy;

        parent::__construct($context);
    }

    /**
     * We calculate weighted exchange rates every 24h
     * from Coindesk weighted prices
     */
    const REQUEST_TIMEOUT = 30;
    const EXCHANGE_URL = 'https://api.coindesk.com/v1/bpi/currentprice/';

    /**
     * Return payment API model
     *
     * @return Api
     */
    protected function getApi()
    {
        return $this->_modelApi;
    }

    /**
     * Request exchange rate
     *
     * @return array
     */
    protected function currencyQuery($currencyCode)
    {
        $response = $this->curlGet(self::EXCHANGE_URL . $currencyCode . '.json');
        $json = json_decode($response, TRUE);
        if (!$json) {
            $errorMessage = __('Bitcoin exchange rate could not be fetched.');
            ObjectManager::getInstance()->get('Magento\Framework\Model\Session')->addError($errorMessage);
            return false;
        }
        return $json;
    }

    /**
     * Get URL via Curl
     */
    public function curlGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Get the BTC rate for the current currency
     *
     * @return float
     */
    public function getExchangeRate()
    {
        $currencyCode = $this->_modelStoreManagerInterface->getStore()->getCurrentCurrencyCode();

        $cache = $this->_cacheProxy;
        $cacheTag = 'Appmerce\Bitcoin\BTC_' . $currencyCode;
        $currencyRate = $cache->load($cacheTag);
        if (empty($currencyRate)) {
            if ($currencyCode === 'BTC') {
                $currencyRate = 'BTC';
            }
            else {
                $response = $this->currencyQuery($currencyCode);
                if (!$response || !is_array($response) || !isset($response['bpi']) || !array_key_exists($currencyCode, $response['bpi'])) {
                    $currencyRate = false;
                }
                else {
                    $period = $this->getApi()->getConfigData('period');
                    $currencyRate = $response['bpi'][$currencyCode]['rate'];
                    $cache->save($currencyRate, $cacheTag, [$cacheTag], $period);
                }
            }
        }

        return $currencyRate;
    }

}
