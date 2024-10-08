<?php
namespace WeltPixel\Sitemap\Model\ResourceModel\Catalog;

use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Store\Model\Store;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Helper\Product as HelperProduct;
use Magento\Framework\App\ObjectManager;

/**
 * Class Product
 * @package WeltPixel\Sitemap\Model\ResourceModel\Product
 */
class Product extends \Magento\Sitemap\Model\ResourceModel\Catalog\Product
{
    /**
     * Scope Config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Get category collection array
     *
     * @param null|string|bool|int|Store $storeId
     * @return array|bool
     */
    public function getCollection($storeId)
    {
        $products = [];

        /* @var $store Store */
        $store = $this->_storeManager->getStore($storeId);
        if (!$store) {
            return false;
        }

        $useCanonicalUrlForProduct = $this->isCanonicalUrlForProducts($storeId);
        $connection = $this->getConnection();
        $urlsConfigCondition = '';
        if ($this->isCategoryProductURLsConfig($storeId)) {
            $urlsConfigCondition = 'NOT ';
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  \Magento\Framework\App\ProductMetadataInterfac $productMetadata */
        $productMetadata = $objectManager->get(\Magento\Framework\App\ProductMetadataInterface::class);
        $magentoVersion = $productMetadata->getVersion();
        if ($magentoVersion > '2.4.3') {
            $urlsConfigCondition = '';
        }

        $this->_select = $connection->select()->from(
            ['e' => $this->getMainTable()],
            [$this->getIdFieldName(), $this->_productResource->getLinkField(), 'updated_at']
        )->joinInner(
            ['w' => $this->getTable('catalog_product_website')],
            'e.entity_id = w.product_id',
            []
        )->joinLeft(
            ['url_rewrite' => $this->getTable('url_rewrite')],
            'e.entity_id = url_rewrite.entity_id AND url_rewrite.is_autogenerated = 1 AND url_rewrite.metadata IS '
            . $urlsConfigCondition . 'NULL'
            . $connection->quoteInto(' AND url_rewrite.store_id = ?', $store->getId())
            . $connection->quoteInto(' AND url_rewrite.entity_type = ?', ProductUrlRewriteGenerator::ENTITY_TYPE),
            ['url' => 'request_path']
        )->where(
            'w.website_id = ?',
            $store->getWebsiteId()
        );

        $this->_addFilter($store->getId(), 'visibility', $this->_productVisibility->getVisibleInSiteIds(), 'in');
        $this->_addFilter($store->getId(), 'status', $this->_productStatus->getVisibleStatusIds(), 'in');
        $this->_addFilter($store->getId(), 'weltpixel_exclude_from_sitemap', 0, '=');

        // Join product images required attributes
        $imageIncludePolicy = $this->_sitemapData->getProductImageIncludePolicy($store->getId());
        if (\Magento\Sitemap\Model\Source\Product\Image\IncludeImage::INCLUDE_NONE != $imageIncludePolicy) {
            $this->_joinAttribute($store->getId(), 'name', 'name');
            if (\Magento\Sitemap\Model\Source\Product\Image\IncludeImage::INCLUDE_ALL == $imageIncludePolicy) {
                $this->_joinAttribute($store->getId(), 'thumbnail', 'thumbnail');
            } elseif (\Magento\Sitemap\Model\Source\Product\Image\IncludeImage::INCLUDE_BASE == $imageIncludePolicy) {
                $this->_joinAttribute($store->getId(), 'image', 'image');
            }
        }

        if ($useCanonicalUrlForProduct) {
            $this->_joinAttribute($store->getId(), 'wp_enable_canonical_url', 'wp_enable_canonical_url');
            $this->_joinAttribute($store->getId(), 'wp_canonical_url', 'wp_canonical_url');
        } else {
            $this->_joinAttribute($store->getId(), 'wp_use_canonical_url_in_sitemap', 'wp_use_canonical_url_in_sitemap');
            $this->_joinAttribute($store->getId(), 'wp_canonical_url', 'wp_canonical_url');
        }

        $query = $connection->query($this->prepareSelectStatement($this->_select));
        while ($row = $query->fetch()) {
            if ($useCanonicalUrlForProduct) {
                if ($row['wp_enable_canonical_url']) {
                    $canonicalUrl = $this->_parseCanonicalUrl($row['wp_canonical_url']);
                    if ($canonicalUrl) {
                        $row['url'] = $canonicalUrl;
                    }
                }
            } else {
                if ($row['wp_use_canonical_url_in_sitemap']) {
                    $canonicalUrl = $this->_parseCanonicalUrl($row['wp_canonical_url']);
                    if ($canonicalUrl) {
                        $row['url'] = $canonicalUrl;
                    }
                }
            }

            $product = $this->_prepareProduct($row, $store->getId());
            $products[$product->getId()] = $product;
        }

        return $products;
    }

    /**
     * Return Use Categories Path for Product URLs config value
     *
     * @param null|string $storeId
     *
     * @return bool
     */
    private function isCategoryProductURLsConfig($storeId)
    {
        $scopeConfig = $this->getScopeConfig();
        return $scopeConfig->isSetFlag(
            HelperProduct::XML_PATH_PRODUCT_URL_USE_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private function getScopeConfig() {
        if (!$this->scopeConfig) {
            $this->scopeConfig = ObjectManager::getInstance()
                ->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        }
        return $this->scopeConfig;
    }

    /**
     * @param $storeId
     * @return bool
     */
    private function isCanonicalUrlForProducts($storeId)
    {
        $scopeConfig = $this->getScopeConfig();
        return $scopeConfig->getValue(
            'weltpixel_sitemap/general/use_canonical_url_for_product',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param string $canonicalUrl
     * @return string
     */
    private function _parseCanonicalUrl($canonicalUrl) {
        $canonicalUrl = $canonicalUrl ?? '';
        $urlParts = parse_url($canonicalUrl);
        $canonicalUrl = isset($urlParts['path']) ? $urlParts['path'] : '';
        $canonicalUrl .= isset($urlParts['query']) ? "?".$urlParts['query'] : '';

        return $canonicalUrl;
    }

}
