<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleShoppingFeed\Model\XmlFeed;

use Magefan\GoogleShoppingFeed\Model\Config;
use Magefan\GoogleShoppingFeed\Model\Entity\Attribute\Source\Exclude;
use Magefan\GoogleShoppingFeed\Setup\Patch\Data\AddMfGoogleProductAttribute;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Escaper;
use Magento\Store\Model\App\Emulation;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\CatalogInventory\Helper\Stock;

class GetProducts
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

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
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var array
     */
    private $excludedChildCategoryIds;

    /**
     * @var ProductData
     */
    private $productData;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Stock
     */
    private $catalogInventoryStock;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $productCollectionFactory
     * @param Filesystem $filesystem
     * @param Escaper $escaper
     * @param Emulation $appEmulation
     * @param Config $config
     * @param CategoryFactory $categoryFactory
     * @param ProductData $productData
     * @param ResourceConnection $resourceConnection
     * @param Stock $catalogInventoryStock
     */
    public function __construct(
        StoreManagerInterface       $storeManager,
        CollectionFactory           $productCollectionFactory,
        Filesystem                  $filesystem,
        Escaper                     $escaper,
        Emulation                   $appEmulation,
        Config                      $config,
        CategoryFactory             $categoryFactory,
        ProductData                 $productData,
        ResourceConnection          $resourceConnection,
        Stock                       $catalogInventoryStock
    )
    {
        $this->storeManager = $storeManager;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->filesystem = $filesystem;
        $this->escaper = $escaper;
        $this->appEmulation = $appEmulation;
        $this->config = $config;
        $this->categoryFactory = $categoryFactory;
        $this->productData = $productData;
        $this->resourceConnection = $resourceConnection;
        $this->catalogInventoryStock = $catalogInventoryStock;
    }

    /**
     * @param $store
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute($store): array
    {
        $isIncludeChildProducts = $this->config->isIncludeChildProducts();
        $isIncludeConfigurableProducts = $this->config->isIncludeConfigurableProducts();

        $products = [];

        foreach ($this->getFilteredProducts($store) as $product) {
            if (isset($products[$product->getId()])) {
                continue;
            }

            if ($this->checkExcludedByCategory($product)) {
                continue;
            }

            if ($product->getTypeId() === 'configurable') {
                if (!$this->isProductAvailableForFeed($product)) {
                    continue;
                }

                if ($isIncludeChildProducts) {
                    $childProducts = $product->getTypeInstance()->getUsedProducts($product);

                    foreach ($childProducts as $simpleProduct) {
                        if (isset($products[$simpleProduct->getId()])) {
                            continue;
                        }

                        if (!$this->isProductAvailableForFeed($simpleProduct)) {
                            continue;
                        }

                        $simpleProduct->setMfParentProduct($product);
                        $products[$simpleProduct->getId()] = $simpleProduct;
                    }
                }

                if (!$isIncludeConfigurableProducts) {
                    continue;
                }
            }

            $products[$product->getId()] = $product;
        }

        return $products;
    }

    /**
     * @param $product
     * @return bool
     * @throws NoSuchEntityException
     */
    private function checkExcludedByCategory($product): bool
    {
        $excludedCategory = $this->getExcludedChildCategoryIds();
        if (!$excludedCategory) {
            return false;
        }

        $categoryIds = $product->getCategoryIds();
        $result = array_intersect($categoryIds, $excludedCategory);

        return !empty($result);
    }

    /**
     * Excluded Category's
     *
     * @throws NoSuchEntityException|LocalizedException
     */
    private function getExcludedChildCategoryIds($category = null): array
    {
        $storeId = $this->storeManager->getStore()->getId();
        $key = $storeId . '_' . ($category ? $category->getId() : 0);
        if (isset($this->excludedChildCategoryIds[$key])) {
            return $this->excludedChildCategoryIds[$key];
        }

        $ids = [];

        if (!$category) {
            $childrens = $this->categoryFactory->create();
            $childrens->addAttributeToFilter(
                AddMfGoogleProductAttribute::MF_EXCLUDED_GOOGLE_FEED_CATEGORY,
                ['eq' => Exclude::MF_GOOGLE_EXCLUDE_YES]
            );
        } else {
            $childrens = $category->getChildrenCategories();
        }

        if ($childrens) {
            foreach ($childrens as $children) {
                $resource = $children->getResource();
                $option = $resource->getAttributeRawValue(
                    $children->getId(),
                    AddMfGoogleProductAttribute::MF_EXCLUDED_GOOGLE_FEED_CATEGORY,
                    $storeId
                );

                if ($option == Exclude::MF_GOOGLE_USE_PARENT
                    || $option == Exclude::MF_GOOGLE_EXCLUDE_YES
                    || !$option
                ) {
                    $ids[] = $children->getId();
                    $ids = array_merge(
                        $ids,
                        $this->getExcludedChildCategoryIds($children)
                    );
                }
            }
        }

        $this->excludedChildCategoryIds[$key] = $ids;
        return $this->excludedChildCategoryIds[$key];
    }

    /**
     * @param $store
     * @return Collection
     * @throws LocalizedException
     */
    private function getFilteredProducts($store): Collection
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addStoreFilter($store);

        /* If NOT Include Disabled Products */
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);

        /* Exclude configurable products */
        if (!$this->config->isIncludeConfigurableProducts() && !$this->config->isIncludeChildProducts()) {
            $collection->addFieldToFilter('type_id', ['neq' => 'configurable']);
        }

        /* Include Products Only With Price Greater Than 0 */
        if ($this->config->getProductFilterSetting('price')) {
            $collection->addAttributeToFilter(
                [
                    ['attribute' => 'price', 'gt' => 0],
                    ['attribute' => 'type_id', 'in' => ['configurable', 'grouped', 'bundle']],
                ]
            );
        }

        $msiEnabled = $collection->getConnection()->isTableExists($collection->getTable('inventory_source_item'));

        /* NOT Include Out Of Stock Products */
        /* The filter works only when msi is disabled */
        if (!$this->config->getProductFilterSetting('stock_status')
            && !$msiEnabled) {
            $this->catalogInventoryStock->addInStockFilterToCollection($collection);
        }

        /* Include Only Visible Products */
        $collection->addAttributeToFilter(
            'visibility',
            ['in' => [Visibility::VISIBILITY_BOTH, Visibility::VISIBILITY_IN_CATALOG]]
        );

        /** Filter excluded products */
        $collection->addAttributeToFilter(
            AddMfGoogleProductAttribute::MF_EXCLUDED_GOOGLE_FEED_PRODUCT,
            ['neq' => 2]
        );

        /* NOT Include Out Of Stock Products */
        /* The filter works only when msi is enabled */
        if (!$this->config->getProductFilterSetting('stock_status')
            && $msiEnabled) {

            foreach ($collection as $key => $product) {

                if (!$this->isInStockMsi($product)) {
                    $collection->removeItemByKey($key);
                }
            }
        }

        return $collection;
    }

    /**
     * @param $product
     * @return bool
     */
    private function isInStockMsi($product): bool
    {
        $getSalableQuantityDataBySku = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku::class);

        try {
            $salableQuantityItems = $getSalableQuantityDataBySku->execute($product->getSku());
        } catch (\Exception $e) {
            if ('configurable' === $product->getTypeId()) {
                $childProducts = $product->getTypeInstance()->getUsedProducts($product);

                //if at least one childProduct is in stock,parent is also in stock
                foreach ($childProducts as $childProduct) {
                    if ($this->isInStockMsi($childProduct)) {
                        return true;
                    }
                }

                //if no childProduct is in stock,parent is out of stock
                return false;
            }

            //cannot get data about stock for $product and $product is not configurable
            return true;
        }

        foreach ($salableQuantityItems as $stockData) {
            if (!array_key_exists('manage_stock', $stockData) || !array_key_exists('qty', $stockData)) {
                continue;
            }

            if (($stockData['manage_stock'] == 1 && $stockData['qty'] > 0) || !$stockData['manage_stock']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $product
     * @return bool
     * @throws NoSuchEntityException
     */
    private function isProductAvailableForFeed($product): bool
    {
        if (Status::STATUS_ENABLED != $product->getStatus()) {
            return false;
        }

        $includeProductsOnlyWithPriceGreaterThan0 = $this->config->getProductFilterSetting('price');

        if ($includeProductsOnlyWithPriceGreaterThan0) {

            $finalPriceFloat = (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();

            if (!$finalPriceFloat) {
                return false;
            }
        }

        $includeOutOfStockProducts = $this->config->getProductFilterSetting('stock_status');

        if (!$includeOutOfStockProducts) {
            $msiEnabled = $this->resourceConnection->getConnection()
                ->isTableExists($this->resourceConnection->getTableName('inventory_source_item'));

            if ($msiEnabled) {
                return $this->isInStockMsi($product);
            } else {
                //if product is not out of stock return true i.e product is available
                return !('out_of_stock' == $this->productData->getQuantityAndStockStatus($product));
            }
        }
        return true;
    }
}
