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

namespace Appmerce\Bitcoin\Controller;

use Appmerce\Bitcoin\Model\Api;
use Appmerce\Bitcoin\Model\Process;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;

class Common extends Action
{
    /**
     * @var Process
     */
    protected $_modelProcess;

    /**
     * @var Api
     */
    protected $_modelApi;

    public function __construct(Context $context, 
        Process $modelProcess, 
        Api $modelApi)
    {
        $this->_modelProcess = $modelProcess;
        $this->_modelApi = $modelApi;

        parent::__construct($context);
    }

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
     * Save checkout session
     */
    public function saveCheckoutSession()
    {
        $this->getCheckout()->setBitcoinQuoteId($this->getCheckout()->getLastSuccessQuoteId());
        $this->getCheckout()->setBitcoinOrderId($this->getCheckout()->getLastOrderId(true));
    }

}
