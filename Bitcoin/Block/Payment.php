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

namespace Appmerce\Bitcoin\Block;

use Appmerce\Bitcoin\Model\Api;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;

class Payment extends Template
{
    /**
     * @var Api
     */
    protected $_modelApi;

    /**
     * @var OrderFactory
     */
    protected $_modelOrderFactory;

    /**
     * @var AbstractHelper
     */
    protected $_helperAbstractHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * BTC amount
     *
     * @var float
     */
    protected $_amount;

    /**
     * Formatted price
     *
     * @var int
     */
    protected $_price;

    /**
     * QR URI
     *
     * @var int
     */
    protected $_qr;

    /**
     * Bitcoin URI
     *
     * @var int
     */
    protected $_bitcoin;

    /**
     * Payable Bitcoin address
     *
     * @var string
     */
    protected $_address;

    public function __construct(Context $context, 
        Api $modelApi, 
        OrderFactory $modelOrderFactory, 
        AbstractHelper $helperAbstractHelper, 
        StoreManagerInterface $modelStoreManagerInterface, 
        array $data = [])
    {
        $this->_modelApi = $modelApi;
        $this->_modelOrderFactory = $modelOrderFactory;
        $this->_helperAbstractHelper = $helperAbstractHelper;
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;

    }

    /**
     * Return checkout session
     *
     * @return \Magento\Checkout\Model\Session
     */
    public function getCheckout()
    {
        return ObjectManager::getInstance()->get('Magento\Checkout\Model\Session');
    }

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
     * Return order instance by lastRealOrderId
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function _getOrder()
    {
        if ($this->getOrder()) {
            $order = $this->getOrder();
        }
        elseif ($this->getCheckout()->getLastRealOrderId()) {
            $order = $this->_modelOrderFactory->create()->loadByIncrementId($this->getCheckout()->getLastRealOrderId());
        }

        return $order;
    }

    /**
     * Get formatted BTC amount
     *
     * @return string
     */
    public function getAmount()
    {
        if (is_null($this->_amount)) {
            $this->_amount = $this->getApi()->getAmount($this->_getOrder());
        }
        return $this->_amount;
    }

    /**
     * Get formatted price
     *
     * @return string
     */
    public function getPrice()
    {
        if (is_null($this->_price)) {
            $this->_price = $this->_helperAbstractHelper->currency($this->_getOrder()->getGrandTotal(), true, false);
        }
        return $this->_price;
    }

    /**
     * Get payable Bitcoin address
     *
     * @return string
     */
    public function getAddress()
    {
        if (is_null($this->_address)) {
            $this->_address = $this->_getOrder()->getPayment()->getAdditionalInformation('address');
        }
        return $this->_address;
    }

    /**
     * Get QR Code Google API URI
     *
     * @return string
     */
    public function getQr($size = 75)
    {
        if (is_null($this->_qr)) {
            $uri = 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . '&chld=L|0&cht=qr&chl=';
            $this->_qr = $uri . urlencode($this->getBitcoin());
        }
        return $this->_qr;
    }

    /**
     * Get Bitcoin URI
     * https://en.bitcoin.it/wiki/URI_Scheme
     *
     * @return string
     */
    public function getBitcoin()
    {
        if (is_null($this->_bitcoin)) {
            $label = __('%1 - Order %2', $this->_modelStoreManagerInterface->getStore()->getName(), $this->_getOrder()->getIncrementId());
            $this->_bitcoin = 'bitcoin:' . $this->getAddress() . '?amount=' . $this->getAmount() . '&label=' . $label;
        }
        return $this->_bitcoin;
    }

    /**
     * Return gateway path from admin settings
     *
     * @return string
     */
    public function getFormAction()
    {
        return $this->getApi()->getConfig()->getApiUrl('confirm');
    }

}
