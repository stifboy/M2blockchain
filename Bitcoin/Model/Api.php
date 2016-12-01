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

namespace Appmerce\Bitcoin\Model;

use Appmerce\Bitcoin\Helper\Data as BitcoinHelperData;
use Appmerce\Bitcoin\Model\Api\Bitcoin;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Cache\Proxy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as HelperData;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Store\Model\StoreManagerInterface;

class Api extends AbstractMethod
{
    /**
     * @var Config
     */
    protected $_modelConfig;

    /**
     * @var Proxy
     */
    protected $_cacheProxy;

    /**
     * @var BitcoinHelperData
     */
    protected $_helperData;

    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * @var Bitcoin
     */
    protected $_apiBitcoin;

    protected $_code = 'bitcoin';
    protected $_formBlockType = 'bitcoin/form';
    protected $_infoBlockType = 'bitcoin/info';

    // Magento features
    protected $_isGateway = false;
    protected $_canOrder = false;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = true;
    protected $_canFetchTransactionInfo = false;
    protected $_canReviewPayment = false;
    protected $_canCreateBillingAgreement = false;
    protected $_canManageRecurringProfiles = false;

    // Restrictions
    protected $_allowCurrencyCode = [];

    // Local variables
    const SUPPORTED_CURRENCIES = 'http://api.coindesk.com/v1/bpi/supported-currencies.json';
    const CACHE_LIFETIME = '7d';

    /**
     * Return Bitcoin config instance
     *
     * @return Config
     */
    public function __construct(Context $context, 
        Registry $registry, 
        ExtensionAttributesFactory $extensionFactory, 
        AttributeValueFactory $customAttributeFactory, 
        HelperData $paymentData, 
        ScopeConfigInterface $scopeConfig, 
        Logger $logger, 
        Config $modelConfig, 
        Proxy $cacheProxy, 
        BitcoinHelperData $helperData, 
        StoreManagerInterface $modelStoreManagerInterface, 
        Bitcoin $apiBitcoin, 
        AbstractResource $resource = null, 
        AbstractDb $resourceCollection = null, 
        array $data = [])
    {
        $this->_modelConfig = $modelConfig;
        $this->_cacheProxy = $cacheProxy;
        $this->_helperData = $helperData;
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_apiBitcoin = $apiBitcoin;

        $this->_config = $this->_modelConfig;

        // Set $_allowCurrencyCode, cache once per week
        $cache = $this->_cacheProxy;
        $cacheTag = 'Appmerce\Bitcoin\allowCurrencyCode';
        $this->_allowCurrencyCode = unserialize($cache->load($cacheTag));
        if (empty($this->_allowCurrencyCode)) {
            $response = $this->_helperData->curlGet(self::SUPPORTED_CURRENCIES);
            $json = json_decode($response, TRUE);
            if (!$json || !is_array($json)) {
                $errorMessage = __('Supported currencies could not be fetched.');
                ObjectManager::getInstance()->get('Magento\Framework\Model\Session')->addError($errorMessage);
                return false;
            }
            else {
                foreach ($json as $key => $values) {
                    $this->_allowCurrencyCode[] = $values['currency'];
                }
                $cache->save(serialize($this->_allowCurrencyCode), $cacheTag, [$cacheTag], self::CACHE_LIFETIME);
            }
        }

        return $this;
    }

    /**
     * Return bitcoin configuration instance
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->_config;
    }

    /**
     * Validate if payment is possible
     *  - check allowed currency codes
     *
     * @return bool
     */
    public function validate()
    {
        parent::validate();
        $currency_code = $this->getCurrencyCode();
        if (!empty($this->_allowCurrencyCode) && !in_array($currency_code, $this->_allowCurrencyCode)) {
            $errorMessage = __('Selected currency (%1) is not compatible with this payment method.', $currency_code);
            throw new \Exception($errorMessage);
        }
        return $this;
    }

    /**
     * Decide currency code type
     *
     * @return string
     */
    public function getCurrencyCode()
    {
        return $this->_modelStoreManagerInterface->getStore()->getCurrentCurrencyCode();
    }

    /**
     * Bitcoin redirect URL for payment page
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->getConfig()->getApiUrl('payment');
    }

    /**
     * Return order process instance
     *
     * @return Bitcoin
     */
    public function getBitcoin()
    {
        return $this->_apiBitcoin;
    }

    /**
     * Get formatted BTC amount
     *
     * @return string
     */
    public function getAmount($order)
    {
        $price = $order->getGrandTotal();
        $currency = $this->getCurrencyCode();

        // Do not convert native currency
        switch ($currency) {
            case 'XBT' :
            case 'BTC' :
                $amount = $price;
                break;

            default :
                $rate = $this->_helperData->getExchangeRate();
                $amount = $price / $rate;
        }

        return number_format($amount, 8);
    }

    /**
     * Get order statuses
     */
    public function getOrderStatus()
    {
        $status = $this->getConfigData('order_status');
        if (empty($status)) {
            $status = Config::DEFAULT_STATUS_PENDING;
        }
        return $status;
    }

    public function getPendingStatus()
    {
        $status = $this->getConfigData('pending_status');
        if (empty($status)) {
            $status = Config::DEFAULT_STATUS_PENDING_PAYMENT;
        }
        return $status;
    }

    public function getProcessingStatus()
    {
        $status = $this->getConfigData('processing_status');
        if (empty($status)) {
            $status = Config::DEFAULT_STATUS_PROCESSING;
        }
        return $status;
    }

}
