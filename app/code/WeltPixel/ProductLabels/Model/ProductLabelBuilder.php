<?php

namespace WeltPixel\ProductLabels\Model;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use WeltPixel\ProductLabels\Model\Config\FileUploader\FileProcessor as ImageFileProcessor;
use WeltPixel\ProductLabels\Model\ResourceModel\ProductLabels\CollectionFactory as ProductLabelsCollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\CatalogInventory\Api\StockRegistryInterface;


/**
 * Class ProductLabelBuilder
 * @package WeltPixel\ProductLabels\Model
 */
class ProductLabelBuilder
{
    const LABEL_CATEGORY_ENABLED = 'weltpixel_productlabels/general/enable_category_page';
    const LABEL_CATEGORY_DISPLAYMODE = 'weltpixel_productlabels/general/category_page_display_mode';
    const LABEL_PRODUCT_ENABLED = 'weltpixel_productlabels/general/enable_product_page';

    /**
     * Date
     *
     * @var DateTime
     */
    protected $date;

    /**
     * @var string
     */
    protected $indexTableName;

    /**
     * @var AdapterInterface
     */
    protected $connection;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var ProductLabelsCollectionFactory
     */
    protected $productLabelsCollectionFactory;

    /**
     * @var ProductLabelsFactory
     */
    protected $productLabelsFactory;

    /**
     * @var HttpContext
     */
    protected $httpContext;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ImageFileProcessor
     */
    protected $imageFileProcessor;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var PriceHelper
     */
    protected $priceHelper;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $product;

    /**
     * @param ResourceConnection $resource
     * @param ProductLabelsCollectionFactory $productLabelsCollectionFactory
     * @param ProductLabelsFactory $productLabelsFactory
     * @param HttpContext $httpContext
     * @param StoreManagerInterface $storeManager
     * @param ImageFileProcessor $imageFileProcessor
     * @param ScopeConfigInterface $scopeConfig
     * @param DateTime $date
     * @param ProductRepositoryInterface $productRepository
     * @param PriceHelper $priceHelper
     * @param StockRegistryInterface $stockRegistry
     */
    public function __construct(
        ResourceConnection $resource,
        ProductLabelsCollectionFactory $productLabelsCollectionFactory,
        ProductLabelsFactory $productLabelsFactory,
        HttpContext $httpContext,
        StoreManagerInterface $storeManager,
        ImageFileProcessor $imageFileProcessor,
        ScopeConfigInterface $scopeConfig,
        DateTime $date,
        ProductRepositoryInterface $productRepository,
        PriceHelper $priceHelper,
        StockRegistryInterface $stockRegistry
    )
    {
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
        $this->productLabelsCollectionFactory = $productLabelsCollectionFactory;
        $this->productLabelsFactory = $productLabelsFactory;
        $this->httpContext = $httpContext;
        $this->storeManager = $storeManager;
        $this->imageFileProcessor = $imageFileProcessor;
        $this->scopeConfig = $scopeConfig;
        $this->date = $date;
        $this->productRepository = $productRepository;
        $this->priceHelper = $priceHelper;
        $this->stockRegistry = $stockRegistry;
        $this->indexTableName = 'weltpixel_productlabels_rule_idx';
    }

    /**
     * @return string
     */
    public function getIndexTableName()
    {
        return $this->indexTableName;
    }

    /**
     * @return mixed|null
     */
    public function getCustomerGroupId()
    {
        return $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreViewId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * @param int $productId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getLabelsOnCategoryPage($productId)
    {
        return $this->getLabelForProduct($productId, 'category');
    }

    /**
     * @param int $productId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getLabelsOnProductPage($productId)
    {
        return $this->getLabelForProduct($productId, 'product');
    }

    /**
     * @param int $productId
     * @param string $prefix
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getLabelForProduct($productId, $prefix = 'product')
    {
        $isEnabled = true;
        if ($prefix == 'product') {
            $isEnabled = $this->scopeConfig->getValue(self::LABEL_PRODUCT_ENABLED, ScopeInterface::SCOPE_STORE);
        }
        if ($prefix == 'category') {
            $isEnabled = $this->scopeConfig->getValue(self::LABEL_CATEGORY_ENABLED, ScopeInterface::SCOPE_STORE);
        }
        if (!$isEnabled) {
            return '';
        }
        $productLabels = $this->getProductLabels($productId, $prefix);

        return $productLabels;
    }

    protected function updateProductLabelsArray() {

    }

    /**
     * @return array
     */
    protected function getLabelOptionKeys()
    {
        return [
            'page_position',
            'position',
            'image',
            'text',
            'text_bg_color',
            'text_font_size',
            'text_font_color',
            'text_padding',
            'css',
            'priority',
            'valid_to'
        ];
    }

    /**
     * @param array $optionKeys
     * @param array $labelData
     * @param string $prefix
     * @return array
     */
    protected function getLabelDetails($optionKeys, $labelData, $prefix)
    {
        $result = [];
        foreach ($optionKeys as $key) {
            if(in_array($key, ['priority','valid_to'])) {
                $result[$key] = trim($labelData[$key] ?? '');
            } else {
                if($key == 'page_position') {
                    $result[$key] = trim($labelData['product_' . $key] ?? '');
                } else {
                    $result[$key] = trim($labelData[$prefix . '_' . $key] ?? '');
                }
            }
        }

        return $result;
    }

    /**
     * @param int $productId
     * @param string $prefix
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getProductLabels($productId, $prefix)
    {
        $indexTableName = $this->getIndexTableName();
        $indexTable = $this->resource->getTableName($indexTableName);

        $storeId = $this->getStoreViewId();
        $customerGroupId = $this->getCustomerGroupId();
        $currentTime = $this->date->gmtDate();

        $labelsCollection = $this->productLabelsCollectionFactory->create();
        $labelsCollection->addFieldToFilter('main_table.status', 1);
        $labelsCollection->addFieldToFilter(
            ['main_table.store_id', 'main_table.store_id'],
            [
                ['finset' => [$storeId]],
                ['finset' => [0]]
            ]
        );
        $labelsCollection->addFieldToFilter(
            ['main_table.valid_from', 'main_table.valid_from'],
            [
                ['lteq' => $currentTime],
                ['null' =>  true]
            ]
        );
        $labelsCollection->addFieldToFilter(
            ['main_table.valid_to', 'main_table.valid_to'],
            [
                ['gteq' => $currentTime],
                ['null' =>  true]
            ]
        );
        $labelsCollection->addFieldToFilter('main_table.customer_group', ['finset' => [$customerGroupId]]);
        $labelsCollection->addFieldToFilter('idx.product_id', $productId);
        $labelsCollection->addFieldToFilter('idx.store_id', $storeId);
        $labelsCollection->getSelect()
            ->joinLeft(
                ['idx' => $indexTable],
                "main_table.id = idx.rule_id",
                []
            )
            ->order([
                'main_table.' . $prefix . '_position ASC',
                'main_table.priority ASC'
            ]);

        $labelsDetails = [];
        $labelOptionKeys = $this->getLabelOptionKeys();
        foreach ($labelsCollection as $label) {
            $labelsDetails[] = $this->getLabelDetails($labelOptionKeys, $label->getData(), $prefix);
        }

        $labelsToDisplay = $this->filterLabelsForDisplay($labelsDetails, $prefix, $productId);
        return $labelsToDisplay;
    }

    /**
     * @param array $labelsDetails
     * @param string $prefix
     * @param integer $productId
     * @return array
     */
    protected function filterLabelsForDisplay($labelsDetails, $prefix, $productId)
    {
        $labelsForPositions = [];
        $ctr = 0;
        foreach ($labelsDetails as $labelOptions) {
            if (!$labelOptions['image'] && !$labelOptions['text']) continue;

            if($prefix == 'category') {
                if(!$this->_keyExists($labelsForPositions, $labelOptions['position'])) {
                    $labelsForPositions[$ctr][$labelOptions['position']]['html'] = $this->renderLabelHtml($labelOptions, $prefix, $productId);
                    $labelsForPositions[$ctr][$labelOptions['position']]['page-position'] = $labelOptions['page_position'];
                    $labelsForPositions[$ctr][$labelOptions['position']]['priority'] = $labelOptions['priority'];

                    $ctr++;
                }
            } else {
                if($labelOptions['page_position'] == 1) { // label on product image
                    if(!$this->_keyExists($labelsForPositions, $labelOptions['position'])) {
                        $labelsForPositions[$ctr][$labelOptions['position']]['html'] = $this->renderLabelHtml($labelOptions, $prefix, $productId);
                        $labelsForPositions[$ctr][$labelOptions['position']]['page-position'] = $labelOptions['page_position'];
                        $labelsForPositions[$ctr][$labelOptions['position']]['priority'] = $labelOptions['priority'];

                        $ctr++;
                    }

                } else { // label on other position
                    $labelsForPositions[$ctr][$labelOptions['position']]['html'] = $this->renderLabelHtml($labelOptions, $prefix, $productId);
                    $labelsForPositions[$ctr][$labelOptions['position']]['page-position'] = $labelOptions['page_position'];
                    $labelsForPositions[$ctr][$labelOptions['position']]['priority'] = $labelOptions['priority'];

                    $ctr++;
                }
            }


        }

        /* building data array and sorting */
        $imagePositionArr = $otherPositionArr = $productLabelsArr = [];

        foreach($labelsForPositions as $k => $productLabel) {
            $firstElement = reset($productLabel);

            if($prefix == 'category') {
                $imagePositionArr[$k] = $firstElement['html'];
            } else {
                if($firstElement['page-position'] == 1) {
                    $imagePositionArr[$k] = $firstElement['html'];
                } else {
                    $otherPositionArr[$k]['html'] = $firstElement['html'];
                    $otherPositionArr[$k]['priority'] = $firstElement['priority'];
                }
            }
        }


        usort($otherPositionArr, function ($item1, $item2) {
            return $item1['priority'] <=> $item2['priority'];
        });
        $extraPosition = [];
        foreach($otherPositionArr as $key => $data) {
            $extraPosition[] = $data['html'];
        }

        $productLabelsArr['imagePosition'] = implode("", $imagePositionArr);
        $productLabelsArr['otherPosition'] = '<div class="wp-product-label-extra">'.implode("", $extraPosition).'</div>';

        return $productLabelsArr;

    }

    /**
     * @param array $arr
     * @param $key
     * @return bool
     */
    private function _keyExists(array $arr, $key) {

        foreach ($arr as $element) {
            if (is_array($element)) {
                if (array_key_exists($key, $element)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array $labelOptions
     * @param string $prefix
     * @param integer $productId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function renderLabelHtml($labelOptions, $prefix, $productId)
    {
        $positionCssClass = '';
        if ($prefix == 'category') {
            $displayMode = $this->scopeConfig->getValue(self::LABEL_CATEGORY_DISPLAYMODE, ScopeInterface::SCOPE_STORE);
            if ($displayMode == 'hover') {
                $positionCssClass .= ' wp-product-label-hover ';
            }
        }

        if($labelOptions['page_position'] != '1' && $prefix != 'category') {
            $positionCssClass .= 'page-position ';
            switch (($labelOptions['page_position'])) {
                case 2:
                    $positionCssClass .= 'after-description';
                    break;
            }
            $html = "<span class=\"wp-product-label $positionCssClass \">";
            $html .= $this->_renderLabelContent($labelOptions, $productId);
            $html .= "</span>";

            return $html;
        }

        $positionCssClass .= ' wp-product-label-';
        switch ($labelOptions['position']) {
            case 1:
                $positionCssClass .= 'top-left';
                break;
            case 2:
                $positionCssClass .= 'top-center';
                break;
            case 3:
                $positionCssClass .= 'top-right';
                break;
            case 4:
                $positionCssClass .= 'middle-left';
                break;
            case 5:
                $positionCssClass .= 'middle-center';
                break;
            case 6:
                $positionCssClass .= 'middle-right';
                break;
            case 7:
                $positionCssClass .= 'bottom-left';
                break;
            case 8:
                $positionCssClass .= 'bottom-center';
                break;
            case 9:
                $positionCssClass .= 'bottom-right';
                break;
        }

        $html = "<span class=\"wp-product-label $positionCssClass \">";
        $html .= $this->_renderLabelContent($labelOptions, $productId);
        $html .= "</span>";

        return $html;
    }

    /**
     * @param array $labelOptions
     * @param integer $productId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _renderLabelContent($labelOptions, $productId)
    {
        if ($labelOptions['image']) {
            $inlineStyle = '';
            if ($labelOptions['css']) {
                $inlineStyle = "style=\"" . $labelOptions['css'] . "\"";
            }
            $imgSrc = $this->imageFileProcessor->getFinalMediaUrl($labelOptions['image']);
            return '<img ' . $inlineStyle . ' src="' . $imgSrc . '" />';
        }

        $customCss = $labelOptions['css'];
        if ($labelOptions['text_bg_color']) {
            $customCss .= 'background-color: ' . $labelOptions['text_bg_color'] . ';';
        }
        if ($labelOptions['text_font_color']) {
            $customCss .= 'color: ' . $labelOptions['text_font_color'] . ';';
        }
        if ($labelOptions['text_font_size']) {
            $customCss .= 'font-size: ' . $labelOptions['text_font_size'] . ';';
        }
        if ($labelOptions['text_padding']) {
            $customCss .= 'padding: ' . $labelOptions['text_padding'] . ';';
        }

        $inlineStyle = '';
        if ($customCss) {
            $inlineStyle = "style=\"" . $customCss . "\"";
        }

        $labelText = $this->_generateLabelText($labelOptions['text'], $productId, $labelOptions['valid_to']);
        return '<span ' . $inlineStyle . '>' . $labelText . '</span>';
    }

    /**
     * @param string $labelText
     * @param integer $productId
     * @param string $validTo
     * @return mixed
     */
    protected function _generateLabelText($labelText, $productId, $validTo)
    {
        $this->product = null;
        $sku = '';
        $regularPrice = '';
        $finalPrice = '';
        $discountAmount = '';
        $discountPercent = '';
        $stockQty = '';
        $currentTime = $this->date->gmtDate();
        $startDate = strtotime($currentTime);
        $endDate = strtotime($validTo);
        $daysLeft = ($endDate - $startDate > 0) ? round(($endDate - $startDate)/60/60/24) : '-';

        if (preg_match("/({PRICE}|{SPECIAL_PRICE}|{DISCOUNT_PERCENT}|{DISCOUNT_AMOUNT}|{SKU}|{QTY})/i", $labelText)) {
            $product = $this->_getProduct($productId);
            $sku = $product->getSku();
            $regularPrice = $product->getPriceInfo()->getPrice(\Magento\Catalog\Pricing\Price\RegularPrice::PRICE_CODE)->getAmount()->getValue();
            $finalPrice = $product->getPriceInfo()->getPrice(\Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE)->getAmount()->getValue();
            $discountAmount = $this->priceHelper->currency($regularPrice - $finalPrice);
            if ($regularPrice > 0 ) {
                $discountPercent =  round(100 - $finalPrice / $regularPrice * 100) . '%';
            }
            $regularPrice = $this->priceHelper->currency($regularPrice);
            $finalPrice = $this->priceHelper->currency($finalPrice);
        }

        if (preg_match("/({QTY})/i", $labelText)) {
            $product = $this->_getProduct($productId);
            $stockItem = $this->stockRegistry->getStockItem($productId);
            $minStockQty = $stockItem->getMinQty();
            $stockQty = $this->stockRegistry->getStockStatus($productId, $product->getStore()->getWebsiteId())->getQty() - $minStockQty;
        }

        $replacedVariables = [
            '<br/>',
            $daysLeft,
            $regularPrice,
            $finalPrice,
            $discountPercent,
            $discountAmount,
            $sku,
            $stockQty
        ];

        return str_replace($this->getLabelVariables(), $replacedVariables, $labelText);
    }

    /**
     * @return string[]
     */
    protected function getLabelVariables()
    {
        return [
            '{NL}',
            '{DAYSLEFT}',
            '{PRICE}',
            '{SPECIAL_PRICE}',
            '{DISCOUNT_PERCENT}',
            '{DISCOUNT_AMOUNT}',
            '{SKU}',
            '{QTY}',
        ];
    }

    /**
     * @param $productId
     * @return \Magento\Catalog\Api\Data\ProductInterface|\Magento\Catalog\Model\Product
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _getProduct($productId)
    {
        if (!$this->product) {
            $this->product = $this->productRepository->getById($productId);
        }
        return $this->product;
    }

}
