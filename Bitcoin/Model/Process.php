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

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Model\ResourceModel\TransactionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

class Process extends DataObject
{
    /**
     * @var Api
     */
    protected $_modelApi;

    /**
     * @var TransactionFactory
     */
    protected $_resourceModelTransactionFactory;

    public function __construct(array $data = [], 
        Api $modelApi, 
        TransactionFactory $resourceModelTransactionFactory)
    {
        $this->_modelApi = $modelApi;
        $this->_resourceModelTransactionFactory = $resourceModelTransactionFactory;

        parent::__construct($data);
    }

    /**
     * Return checkout session
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckout()
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
     * Success process
     * [single-method]
     *
     * Update succesful (paid) orders, send order email, create invoice
     * and send invoice email. Restore quote and clear cart.
     *
     * @param $order object Order
     * @param $note string Backend order history note
     * @param $transactionId string Transaction ID
     * @param $responseCode integer Response code
     * @param $frontend boolean
     */
    public function success(Order $order, $note, $transactionId, $responseCode = 1, $frontend = false)
    {
        $this->check($order);
        if ($order->getId() && $responseCode != $order->getPayment()->getAppmerceResponseCode()) {
            $order->getPayment()->setAppmerceResponseCode($responseCode);
            $order->getPayment()->setTransactionId($transactionId);
            $order->getPayment()->setLastTransId($transactionId);

            // Send order email
            if (!$order->getEmailSent() && $this->getApi()->getConfigData('order_email')) {
                $order->sendNewOrderEmail()->setEmailSent(true);
            }

            // Set processing status
            $processingOrderStatus = $this->getApi()->getProcessingStatus();
            $order->setState(Order::STATE_PROCESSING, $processingOrderStatus, $note, $notified = false);
            $order->save();

            // Create invoice
            if ($this->getApi()->getConfigData('invoice_create')) {
                $this->invoice($order);
            }
        }

        if ($frontend) {
            $this->restore();
            $this->clear();
        }
    }

    /**
     * Create automatic invoice
     * [single-method]
     *
     * @param $order Order
     */
    public function invoice($order)
    {
        $this->check($order);
        if (!$order->hasInvoices() && $order->canInvoice()) {
            $invoice = $order->prepareInvoice();
            if ($invoice->getTotalQty() > 0) {
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
                $invoice->setTransactionId($order->getPayment()->getTransactionId());
                $invoice->register();
                $transaction = $this->_resourceModelTransactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                $transaction->save();
                $invoice->addComment(__('Automatic invoice.'), false);

                // Send invoice email
                if (!$invoice->getEmailSent() && $this->getApi()->getConfigData('invoice_email')) {
                    $invoice->sendEmail()->setEmailSent(true);
                }
                $invoice->save();
            }
        }
    }

    /**
     * Pending process
     *
     * Update orders with explicit payment pending status. Restore quote.
     *
     * @param $order object Order
     * @param $note string Backend order history note
     * @param $transactionId string Transaction ID
     * @param $redirect boolean
     */
    public function pending(Order $order, $note, $transactionId, $responseCode = 1, $frontend = false)
    {
        $this->check($order);
        if ($order->getId() && $responseCode != $order->getPayment()->getAppmerceResponseCode()) {
            # don't set response code, allow for another status update
            $order->getPayment()->setTransactionId($transactionId);
            $order->getPayment()->setLastTransId($transactionId);

            // Set pending_payment state
            $pendingOrderStatus = $this->getApi()->getPendingStatus();
            $order->setState(Order::STATE_PENDING_PAYMENT, $pendingOrderStatus, $note, $notified = false);
            $order->save();
        }

        if ($frontend) {
            $this->restore();
            $this->clear();
        }
    }

    /**
     * Cancel process
     *
     * Update failed, cancelled, declined, rejected etc. orders. Cancel
     * the order and show user message. Restore quote.
     *
     * @param $order object Order
     * @param $note string Backend order history note
     * @param $transactionId string Transaction ID
     * @param $responseCode integer Response code
     * @param $frontend boolean
     */
    public function cancel(Order $order, $note, $transactionId, $responseCode = 1, $frontend = false)
    {
        $this->check($order);
        if ($order->getId() && $responseCode != $order->getPayment()->getAppmerceResponseCode()) {
            $order->getPayment()->setAppmerceResponseCode($responseCode);
            $order->getPayment()->setTransactionId($transactionId);
            $order->getPayment()->setLastTransId($transactionId);

            // Cancel order
            $order->addStatusToHistory($order->getStatus(), $note, $notified = true);
            $order->cancel()->save();
        }

        if ($frontend) {
            $this->repeat();
        }
    }

    /**
     * Check order state
     *
     * If the order state (not status) is already one of:
     * canceled, closed, holded or completed,
     * then we do not update the order status anymore.
     *
     * @param $order object Order
     */
    public function check(Order $order)
    {
        if ($order->getId()) {
            $state = $order->getState();
            switch ($state) {
                case Order::STATE_HOLDED :
                case Order::STATE_CANCELED :
                case Order::STATE_CLOSED :
                case Order::STATE_COMPLETE :
                    exit();
                    break;

                default :
            }
        }
    }

    /**
     * Done processing
     *
     * Restore checkout session and clear cart for success page.
     */
    public function done()
    {
        $this->restore();
        $this->clear();
    }

    /**
     * Restore process
     *
     * Restore checkout session and show payment failed message.
     */
    public function repeat()
    {
        $this->restore();
        $message = __('Payment failed. Please try again.');
        $this->getCheckout()->addError($message);
    }

    /**
     * Clear cart
     */
    public function clear()
    {
        $this->getCheckout()->getQuote()->setIsActive(false)->save();
    }

    /**
     * Restore checkout session
     */
    public function restore()
    {
        $this->getCheckout()->setQuoteId($this->getCheckout()->getBitcoinQuoteId(true));
        $this->getCheckout()->setLastOrderId($this->getCheckout()->getBitcoinOrderId(true));
    }

}
