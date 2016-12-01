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

class Period
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 3600,
                'label' => __('1 hour'),
            ],
            [
                'value' => 10800,
                'label' => __('3 hours'),
            ],
            [
                'value' => 21600,
                'label' => __('6 hours (default)'),
            ],
            [
                'value' => 86400,
                'label' => __('24 hours'),
            ],
            [
                'value' => 604800,
                'label' => __('7 days'),
            ],
        ];
    }

}
