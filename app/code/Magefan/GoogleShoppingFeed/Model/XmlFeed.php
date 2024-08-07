<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleShoppingFeed\Model;

use Magefan\GoogleShoppingFeed\Model\XmlFeed\ProductData;
use Magefan\GoogleShoppingFeed\Model\XmlFeed\GetProducts;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use Magefan\GoogleShoppingFeed\Model\Config\Source\GoogleAttributes;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Escaper;
use Magento\Store\Model\App\Emulation;
use Magento\Framework\Registry;

/**
 * Google feed logic
 */
class XmlFeed
{
    const MAX_CHARACTER_PRODUCT_TITLE = 150;

    const MAX_CHARACTER_PRODUCT_DESCRIPTION = 5000;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @var Emulation
     */
    private $appEmulation;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ProductData
     */
    private $productData;

    /**
     * @var GetProducts
     */
    private $getProducts;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     * @param Escaper $escaper
     * @param Emulation $appEmulation
     * @param Config $config
     * @param ProductData $productData
     * @param GetProducts $getProducts
     * @param Registry $registry
     */
    public function __construct(
        StoreManagerInterface       $storeManager,
        Filesystem                  $filesystem,
        Escaper                     $escaper,
        Emulation                   $appEmulation,
        Config                      $config,
        ProductData                 $productData,
        GetProducts                 $getProducts,
        Registry                    $registry
    )
    {
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
        $this->escaper = $escaper;
        $this->appEmulation = $appEmulation;
        $this->config = $config;
        $this->productData = $productData;
        $this->getProducts = $getProducts;
        $this->registry = $registry;
    }

    /**
     * @return void
     * @throws FileSystemException|LocalizedException
     */
    public function generate(): void
    {
        ini_set("memory_limit", '-1');

        $stores = $this->storeManager->getStores();

        foreach ($stores as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            $this->appEmulation->startEnvironmentEmulation($store->getId(), \Magento\Framework\App\Area::AREA_FRONTEND, true);
            $currencyCodes[] = $store->getDefaultCurrency()->getCurrencyCode();

            if ($this->config->generationCurrencyType()){
                $currencyCodes = $store->getAvailableCurrencyCodes(true);
            }

            foreach($currencyCodes as $currencyCode) {
                $this->registry->unregister('mf_current_currency');
                $this->registry->register('mf_current_currency', $currencyCode);

                $storeCode = $store->getCode();
                $storeUrl = $store->getBaseUrl();
                if (!$storeUrl) {
                    $storeUrl = '';
                }
                $filePath = 'mfgoogleshoopingfeed/' . $storeCode . '_' . strtolower($currencyCode) . '_tmp.xml';

                $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
                $mediaDir->create('mfgoogleshoopingfeed');
                $stream = $mediaDir->openFile($filePath, 'w+');

                try {
                    $stream->lock();
                    $stream->write($this->getXmlHeader($storeUrl));
                    $this->getProductsXml($stream, $store);
                    $stream->write($this->getXmlFooter());
                    $stream->unlock();
                    $stream->close();

                    try {
                        $destinationFilePath = 'mfgoogleshoopingfeed/' . $storeCode . '_' . strtolower($currencyCode) . '.xml';
                        $mediaDir->copyFile($filePath, $destinationFilePath);
                        $mediaDir->delete($filePath);
                    } catch (\Exteption $e) {}

                } catch (\Exteption $e) {
                    $stream->unlock();
                    $stream->close();
                }

            }

            $this->appEmulation->stopEnvironmentEmulation();
        }
    }

    /**
     * @param string $url
     * @return string
     */
    private function getXmlHeader(string $url): string
    {
        $xml = '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
        $xml .= '<channel>';
        $xml .= '<title>' . $this->escaper->escapeHtml($this->config->getFeedTitle()) . '</title>';
        $xml .= '<link>' . $this->escaper->escapeUrl($url) . '</link>';
        $xml .= '<description>' . $this->escaper->escapeHtml($this->config->getFeedDescription()) . '</description>';

        return $xml;
    }

    /**
     * @return string
     */
    private function getXmlFooter(): string
    {
        return '</channel></rss>';
    }


    /**
     * @param $stream
     * @param $store
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getProductsXml($stream, $store)
    {
        foreach ($this->getProducts->execute($store) as $product) {
            $stream->write($this->getProductXml($product));
        }
    }

    /**
     * @param $product
     * @return string
     * @throws NoSuchEntityException
     */
    private function getProductXml($product): string
    {
        $xml = '';
        $shippingXml = '';
        $taxXml = '';
        $_xmlPrice = null;

        foreach (GoogleAttributes::attributes as $attr) {
            if (is_string($attr['value'])) {
                if ($dbFieldData = $this->config->getMapField($attr['value'])) {
                    $_xml = '';

                    foreach ($dbFieldData as $dbField) {
                        $_xml .= $this->getProductXmlValues($dbField, $attr, $product);

                        if ($attr['value'] == 'price') {
                            $_xmlPrice = $_xml;
                        } elseif ($attr['value'] == 'sale_price'
                            && str_replace('g:price', 'g:sale_price', $_xmlPrice) == $_xml) {
                            $_xml = '';
                        }
                    }

                    $xml .= $_xml;
                }
            } elseif (is_array($attr['value'])) {
                $productXml = '';
                foreach ($attr['value'] as $shippingAttr) {
                    if ($dbFieldData = $this->config->getMapField($shippingAttr['value'])) {
                        foreach ($dbFieldData as $dbField) {
                            $productXml .= $this->getProductXmlValues($dbField, $shippingAttr, $product);
                        }
                    }
                }

                if (strlen($productXml) && false !== strpos($attr['label'], 'Shipping [shipping]')) {
                    $shippingXml = '<g:shipping>' . $productXml . '</g:shipping>';
                }elseif (strlen($productXml) && false !== strpos($attr['label'], 'Tax [tax]')){
                    $taxXml = '<g:tax>' . $productXml . '</g:tax>';
                }
            }
        }
        if (($parentProduct = $product->getMfParentProduct())
            && ('configurable' === $parentProduct->getTypeId())) {
            $xml .= $this->createNode('g:item_group_id', $parentProduct->getSku());
        }

        $resultXml = $xml . $shippingXml . $taxXml;
        return $resultXml ? '<item>' . $resultXml . '</item>' : '';
    }

    /**
     * @param $dbField
     * @param $attr
     * @param $product
     * @return string
     * @throws NoSuchEntityException
     */
    private function getProductXmlValues($dbField, $attr, $product): string
    {
        if ($dbField['attr'] == '0') {
            $value = '';
        } else if ($dbField['attr'] == '1') {
            $value = $dbField['value'];
        } else {
            $value =  $this->productData->getData($product, $dbField['attr']);
        }

        if (is_string($value)) {
            if ('title' == $attr['value']) {
                if (mb_strlen($value) > self::MAX_CHARACTER_PRODUCT_TITLE) {
                    $value = mb_substr($value, 0, self::MAX_CHARACTER_PRODUCT_TITLE);
                }
            }

            if ('description' == $attr['value']) {
                if (mb_strlen($value) > self::MAX_CHARACTER_PRODUCT_DESCRIPTION) {
                    $value = mb_substr($value, 0, self::MAX_CHARACTER_PRODUCT_DESCRIPTION);
                }
            }
        }

        if (is_array($value)) {
            $nodes = '';

            if ('category_ids' === $dbField['attr']) {
                foreach ($value as $categoryNames) {
                    $categories = implode(' > ', $categoryNames);
                    $categories = strip_tags(html_entity_decode($categories ?: ''));
                    $nodes .= $this->createNode($attr['tag'], $this->escaper->escapeHtml($categories));
                }
            } else {
                foreach ($value as $item) {
                    $nodes .= $this->createNode($attr['tag'], $this->escaper->escapeHtml($item));
                }
            }

            return $nodes;
        } else {
            $value = strip_tags(html_entity_decode($value ?: ''));
            if ($value) {
                return $this->createNode($attr['tag'], $this->escaper->escapeHtml($value));
            } else {
                return '';
            }
        }
    }

    /**
     * @param $nodeName
     * @param $value
     * @return string
     */
    private function createNode($nodeName, $value): string
    {
        if (empty($nodeName)) {
            return '';
        }

        if (empty($value)) {
            $cDataStart = "";
            $cDataEnd = "";
        } else {
            $cDataStart = "<![CDATA[";
            $cDataEnd = "]]>";
        }

        $node = "<" . $nodeName . ">" . $cDataStart . html_entity_decode($value) . $cDataEnd . "</" . $nodeName . ">";

        return $node;
    }
}
