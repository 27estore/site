<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleShoppingFeed\Block\Adminhtml\System\Config\Form;

use Magefan\GoogleShoppingFeed\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;

class Regenerate extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var string
     */
    protected $_template = 'Magefan_GoogleShoppingFeed::system/config/button.phtml';

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config  $config,
        array   $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }


    /**
     * @return string
     * @throws LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData(
            [
                'id' => 'mf-google-shopping-feed',
                'label' => __('Regenerate'),
                'onclick' => 'window.location ="' . $this->getUrl('mf_google_feed/regenerate/index') . '";'
            ]
        );
        return $button->toHtml();
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getAsyncButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData(
            [
                'id' => 'mf-google-shopping-feed-async',
                'label' => __('Regenerate Asynchronously'),
                'onclick' => 'window.location ="' . $this->getUrl('mf_google_feed/regenerate/index') . '?async=1' . '";'
            ]
        );
        return $button->toHtml();
    }

    /**
     * @return array
     * @throws FileSystemException
     */
    public function getFeedByStore(): array
    {
        $feedList = [];
        $mediaDirectory = $this->_filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $feedDirectory = $mediaDirectory->isDirectory(Config::MF_FEED_FOLDER_NAME);

        if (!$feedDirectory) {
            return $feedList;
        }

        foreach ($this->_storeManager->getStores() as $store) {
            $currencyCodes = [];
            $currencyCodes[] = $store->getDefaultCurrency()->getCurrencyCode();

            if ($this->config->generationCurrencyType()){
                $currencyCodes = $store->getAvailableCurrencyCodes(true);
            }

            foreach ($currencyCodes as $currencyCode) {
                $codeLower = strtolower($currencyCode);
                $filePath = $mediaDirectory->getAbsolutePath() . Config::MF_FEED_FOLDER_NAME . '/'. $store->getCode() . '_' . $codeLower . '.xml';

                if ($store->getIsActive() && $this->directory->isExist($filePath)) {
                    $websiteLink = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
                    $fileLink = $websiteLink . Config::MF_FEED_FOLDER_NAME . '/'. $store->getCode() . '_' . $codeLower . '.xml';

                    $feedList[] = [
                        'name' => $store->getCode() . '_' . strtolower($currencyCode),
                        'link' => $fileLink,
                        'lastUpdatedDate' => date('F j, Y ',filemtime($filePath))
                    ];
                }
            }
        }

        return $feedList;
    }
}
