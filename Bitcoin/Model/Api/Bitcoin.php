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
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem;

class Bitcoin extends DataObject
{
    /**
     * @var Api
     */
    protected $_modelApi;

    /**
     * @var Filesystem
     */
    protected $_frameworkFilesystem;

    public function __construct(array $data = [], 
        Api $modelApi, 
        Filesystem $frameworkFilesystem)
    {
        $this->_modelApi = $modelApi;
        $this->_frameworkFilesystem = $frameworkFilesystem;

        parent::__construct($data);
    }

    /**
     * Client
     */
    private $_client;

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
     * Get Client through JSON-RPC 2.0
     */
    protected function getClient()
    {
        if (!$this->_client) {
            $ptcl = $this->getApi()->getConfigData('rpc_ptcl') == 1 ? 'https://' : 'http://';
            $user = $this->getApi()->getConfigData('rpc_user');
            $pass = $this->getApi()->getConfigData('rpc_pass');
            $host = $this->getApi()->getConfigData('rpc_host');
            $port = $this->getApi()->getConfigData('rpc_port');
            $uri = $ptcl . $user . ':' . $pass . '@' . $host . ':' . $port . '/';

            try {
                require_once $this->_frameworkFilesystem->getDirectoryWrite('lib')->getAbsolutePath() . '/Appmerce/Bitcoin/JsonRpcClient.php';
                $this->_client = new jsonRPCClient($uri);
            }
            catch (\Exception $e) {
                throw throw new \Exception(__('JSON-RPC could not be reached: %1.', $e->getMessage()));
            }
        }
        return $this->_client;
    }

    /**
     * Get info
     */
    public function getInfo()
    {
        return $this->getClient()->getinfo();
    }

    /**
     * Unlock wallet
     * @important: passphrase must be without special chars like: %, $, ?
     */
    public function walletPassPhrase($timeout = 1)
    {
        if ($passphrase = $this->getApi()->getConfigData('wallet_pass')) {
            return $this->getClient()->walletpassphrase((string)$passphrase, $timeout);
        }
    }

    /**
     * Lock wallet
     */
    public function walletLock()
    {
        return $this->getClient()->walletlock();
    }

    /**
     * sendToAddress
     * @note: sendtoaddress may require a txfee of 0.0005 because
     * of its amount, complexity or use of recently received funds
     */
    public function sendToAddress($address, $amount)
    {
        return $this->getClient()->sendtoaddress($address, $amount);
    }

    /**
     * Get unique address.
     *
     * The idea is to generate addresses unique for an order.
     * @todo make account more unique accross multiple Magento installs
     *
     * @param $order Magento Order Object
     * @return string unique Bitcoin address
     */
    public function getNewAddress($order)
    {
        // Set wallet passphrase for 2 seconds
        // Required for automatic keypoolrefill when running out of available Bitcoin addresses
        $this->walletPassPhrase(2);
        $address = $this->getClient()->getnewaddress($order->getIncrementId());
        return $address;
    }

    /**
     * Validate addres
     *
     * The idea is to generate addresses unique for an order.
     * @todo make account more unique accross multiple Magento installs
     *
     * @param $order Magento Order Object
     * @return string unique Bitcoin address
     */
    public function validateAddress($address)
    {
        $validation = $this->getClient()->validateaddress($address);
        return $validation;
    }

    /**
     * Get wallet account by address
     * In our case each account represents a unique Magento order
     */
    public function getAccount($address)
    {
        return $this->getClient()->getaccount($address);
    }

    /**
     * Get balance by account
     * If account is false, returns total balance
     */
    public function getBalance($account = '*', $minimum_confirmations = 6)
    {
        return $this->getClient()->getbalance($account, $minimum_confirmations);
    }

    /**
     * List accounts balances
     * Returns array accounts with balance of minconf
     */
    public function listAccounts($minimum_confirmations = 6)
    {
        return $this->getClient()->listaccounts($minimum_confirmations);
    }

    /**
     * List most recent transactions for an account
     */
    public function listTransactions($account)
    {
        return $this->getClient()->listtransactions($account);
    }

    /**
     * Returns the total amount (BTC) received by $address
     * in transactions with at least $minconf confirmations.
     */
    public function getReceivedByAddress($address, $minimum_confirmations = 6)
    {
        $received = 0;
        if ($address) {
            $received = $this->getClient()->getreceivedbyaddress($address, $minimum_confirmations);
        }
        return $received;
    }

}
