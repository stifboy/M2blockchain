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

namespace Appmerce\Bitcoin\Model\Api;

use Appmerce\Bitcoin\Model\Api;
use Appmerce\Bitcoin\Model\Process;
use Magento\Framework\DataObject;
use Magento\Framework\Model\DateFactory;
use Magento\Framework\Model\Resource;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

class Pull extends DataObject
{
    /**
     * @var Process
     */
    protected $_modelProcess;

    /**
     * @var Api
     */
    protected $_modelApi;

    /**
     * @var DateFactory
     */
    protected $_modelDateFactory;

    /**
     * @var Resource
     */
    protected $_modelResource;

    /**
     * @var OrderFactory
     */
    protected $_modelOrderFactory;

    public function __construct(array $data = [], 
        Process $modelProcess, 
        Api $modelApi, 
        DateFactory $modelDateFactory, 
        Resource $modelResource, 
        OrderFactory $modelOrderFactory)
    {
        $this->_modelProcess = $modelProcess;
        $this->_modelApi = $modelApi;
        $this->_modelDateFactory = $modelDateFactory;
        $this->_modelResource = $modelResource;
        $this->_modelOrderFactory = $modelOrderFactory;

        parent::__construct($data);
    }

    protected $_code = 'bitcoin';

    /**
     * Return order process instance
     *
     * @return Process
     */
    public function getProcess()
    {
        return $this->_modelProcess;
    }

    /**
     * Return Api instance
     *
     * @return \Appmerce\Bitcoin\Api
     */
    public function getApi()
    {
        return $this->_modelApi;
    }

    /**
     * Cron transaction status check
     *
     * Check orders created in the last 24 hrs.
     * After that manual check is required.
     */
    public function transactionStatusCheck($shedule = null)
    {
        // Time preparations: from -1w untill now
        $gmtStamp = $this->_modelDateFactory->create()->gmtTimestamp();
        $from = date('Y-m-d H:i:s', $gmtStamp - 604800);
        $to = date('Y-m-d H:i:s', $gmtStamp);

        // Database preparations
        $db = $this->_modelResource->getConnection('core_read');
        $orderTable = $this->_modelResource->getTableName('sales_flat_order');
        $orderPaymentTable = $this->_modelResource->getTableName('sales_flat_order_payment');

        $result = $db->query('SELECT sfo.entity_id, sfop.last_trans_id
            FROM ' . $orderTable . ' sfo 
            INNER JOIN ' . $orderPaymentTable . ' sfop 
            ON sfop.parent_id = sfo.entity_id 
            WHERE (sfo.state = "' . Order::STATE_NEW . '" OR sfo.state = "' . Order::STATE_PENDING_PAYMENT . '")
            AND sfo.created_at >= "' . $from . '"
            AND sfo.created_at <= "' . $to . '"
            AND sfop.method = "' . $this->_code . '"');

        if (!$result) {
            return $this;
        }

        // Update order statuses
        $order = $this->_modelOrderFactory->create();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            if (!$row) {
                break;
            }

            $order->reset();
            $order->load($row['entity_id']);

            // Find most recent transactions for address/account
            // In our setup each account represents a single order!
            $address = $order->getPayment()->getAdditionalInformation('address');
            if (!$address) {
                continue;
            }
            $amount = $order->getPayment()->getAdditionalInformation('amount');
            $account = $this->getApi()->getBitcoin()->getAccount($address);
            $transactions = $this->getApi()->getBitcoin()->listTransactions($account);

            // Blockchain handles listtransactions differently
            $host = $this->getApi()->getConfigData('rpc_host');
            if (strpos($host, 'blockchain.info') !== FALSE) {
                $transactions = $transactions['transactions'];
            }

            $minimum_confirmations = (int)$order->getPayment()->getAdditionalInformation('minimum_confirmations');

            // Check transactions / balance
            // There can be more than 1 transaction. So we poll regularly.
            // Total balance is checked with getReceivedByAddress()
            if (isset($transactions[0])) {
                $confirmations = $order->getPayment()->getAdditionalInformation('confirmations');
                $transactionId = $transactions[0]['txid'];

                // Check if full amount was received for this account (=order)
                $balance = $this->getApi()->getBitcoin()->getReceivedByAddress($address, $minimum_confirmations);
                if ($balance >= $amount) {
                    $note = __('Confirmed %1 BTC total balance (%2/%3).', $balance, $transactions[0]['confirmations'], $minimum_confirmations);
                    $this->getProcess()->success($order, $note, $transactionId);
                }

                // Check most recent transaction
                elseif ($transactions[0]['confirmations'] > $confirmations) {
                    $order->getPayment()->setAdditionalInformation('confirmations', $transactions[0]['confirmations']);
                    $order->getPayment()->save();

                    $note = __('Unconfirmed %1 BTC transaction (%2/%3).', $transactions[0]['amount'], $transactions[0]['confirmations'], $minimum_confirmations);
                    $this->getProcess()->pending($order, $note, $transactionId);
                }
            }
        }

        return $this;
    }

}
