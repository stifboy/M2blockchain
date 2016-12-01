<?php

namespace Appmerce\Bitcoin\Controller\Api;



class Confirm extends AbstractApi
{
    /**
     * Confirm action
     *
     * @see bitcoin/api/success
     */
    public function execute()
    {
        $this->getProcess()->done();
        $this->_redirect('checkout/onepage/success', ['_secure' => true]);
    }
}
