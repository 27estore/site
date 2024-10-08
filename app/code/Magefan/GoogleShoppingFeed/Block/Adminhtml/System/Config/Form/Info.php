<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

namespace Magefan\GoogleShoppingFeed\Block\Adminhtml\System\Config\Form;

class Info extends \Magefan\Community\Block\Adminhtml\System\Config\Form\Info
{
    /**
     * Return extension url
     * @return string
     */
    protected function getModuleUrl()
    {
        return 'https://mage' .
            'fan.com/magento-2-google-shopping-feed-extension';
    }

    /**
     * Return extension title
     * @return string
     */
    protected function getModuleTitle()
    {
        return 'Google Shopping Feed';
    }
}
