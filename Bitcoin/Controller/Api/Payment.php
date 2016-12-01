<?php

namespace Appmerce\Bitcoin\Controller\Api;

use Appmerce\Bitcoin\Model\Api\DebugFactory;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;

class Payment extends AbstractApi
{
    /**
     * @var OrderFactory
     */
    protected $_modelOrderFactory;

    /**
     * @var DebugFactory
     */
    protected $_apiDebugFactory;

    public function __construct(Context $context, 
        OrderFactory $modelOrderFactory, 
        DebugFactory $apiDebugFactory)
    {
        $this->_modelOrderFactory = $modelOrderFactory;
        $this->_apiDebugFactory = $apiDebugFactory;

        parent::__construct($context);
    }

    /**
     * Render placement form and set New Order Status
     *
     * @see bitcoin/api/payment
     */
    public function execute()
    {
        $this->saveCheckoutSession();
        $order = $this->_modelOrderFactory->create()->loadByIncrementId($this->getCheckout()->getLastRealOrderId());

        // Debug
        if ($this->getApi()->getConfigData('debug_flag')) {
            if ($order->getId()) {
                $url = $this->getRequest()->getPathInfo();
                $info = $this->getApi()->getBitcoin()->getInfo();
                $data = print_r($info, true);
                $this->_apiDebugFactory->create()->setDir('out')->setUrl($url)->setData('data', $data)->save();
            }
        }

        // Get BTC amount and (new) Bitcoin address
        $amount = $this->getApi()->getAmount($order);
        $address = $order->getPayment()->getAdditionalInformation('address');
        if (!$address) {
            $address = $this->getApi()->getBitcoin()->getNewAddress($order);
        }

        // Save re-usable information
        $order->getPayment()->setAdditionalInformation('address', $address);
        $order->getPayment()->setAdditionalInformation('amount', $amount);
        $order->getPayment()->setAdditionalInformation('confirmations', -1);
        $order->getPayment()->setAdditionalInformation('minimum_confirmations', $this->getApi()->getConfigData('confirmations'));

        // Send (optional) order email incl. payment instructions
        if (!$order->getEmailSent() && $this->getApi()->getConfigData('order_email')) {
            $order->sendNewOrderEmail()->setEmailSent(true);
        }
        $order->save();

        $this->loadLayout();
        $this->renderLayout();
    }
}
