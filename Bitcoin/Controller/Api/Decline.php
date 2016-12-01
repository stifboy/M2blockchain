<?php

namespace Appmerce\Bitcoin\Controller\Api;



class Decline extends AbstractApi
{
    /**
     * Decline action
     *
     * @see bitcoin/api/decline
     */
    public function execute()
    {
        $this->getProcess()->repeat();
        $this->_redirect('checkout/cart', ['_secure' => true]);
    }
}
