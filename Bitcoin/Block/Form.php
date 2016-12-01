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
use Magento\Checkout\Model\CartFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Form as BlockForm;

class Form extends BlockForm
{
    /**
     * @var Api
     */
    protected $_modelApi;

    /**
     * @var CartFactory
     */
    protected $_modelCartFactory;

    public function __construct(Context $context, 
        Api $modelApi, 
        CartFactory $modelCartFactory, 
        array $data = [])
    {
        $this->_modelApi = $modelApi;
        $this->_modelCartFactory = $modelCartFactory;

        parent::__construct($context, $data);
    }

    /**
     * Quote amount
     *
     * @var int
     */
    protected $_quoteAmount;

    /**
     * Block construction. Set block template.
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Appmerce_Bitcoin::appmerce/bitcoin/form.phtml');
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
     * Get Bitcoin quote amount from order payment
     *
     * @return string
     */
    public function getQuoteAmount()
    {
        if (is_null($this->_quoteAmount)) {
            $quote = $this->_modelCartFactory->create()->getQuote();
            $this->_quoteAmount = $this->getApi()->getAmount($quote);
        }
        return $this->_quoteAmount;
    }

}
