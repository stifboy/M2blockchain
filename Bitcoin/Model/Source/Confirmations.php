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

namespace Appmerce\Bitcoin\Model\Source;

class Confirmations
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 1,
                'label' => 1,
            ],
            [
                'value' => 2,
                'label' => 2,
            ],
            [
                'value' => 3,
                'label' => 3,
            ],
            [
                'value' => 4,
                'label' => 4,
            ],
            [
                'value' => 5,
                'label' => 5,
            ],
            [
                'value' => 6,
                'label' => __('6 (network default)'),
            ],
            [
                'value' => 7,
                'label' => 7,
            ],
            [
                'value' => 8,
                'label' => 8,
            ],
            [
                'value' => 9,
                'label' => 9,
            ],
            [
                'value' => 10,
                'label' => 10,
            ],
        ];
    }

}
