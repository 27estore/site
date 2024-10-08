<?php

namespace WeltPixel\NavigationLinks\Plugin\Block;

use Magento\Catalog\Model\Category;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Tree\Node;

/**
 * Plugin for top menu block
 */
class Topmenu
{
    /**
     * Catalog category
     *
     * @var \Magento\Catalog\Helper\Category
     */
    protected $catalogCategory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Catalog\Model\Layer\Resolver
     */
    private $layerResolver;

    /**
     * Initialize dependencies.
     *
     * @param \Magento\Catalog\Helper\Category $catalogCategory
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer\Resolver $layerResolver
     */
    public function __construct(
        \Magento\Catalog\Helper\Category $catalogCategory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer\Resolver $layerResolver
    ) {
        $this->catalogCategory = $catalogCategory;
        $this->collectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
        $this->layerResolver = $layerResolver;
    }

    /**
     * Build category tree for menu block.
     *
     * @param \Magento\Theme\Block\Html\Topmenu $subject
     * @param string $outermostClass
     * @param string $childrenWrapClass
     * @param int $limit
     * @return void
     * @SuppressWarnings("PMD.UnusedFormalParameter")
     */
    public function beforeGetHtml(
        \Magento\Theme\Block\Html\Topmenu $subject,
        $outermostClass = '',
        $childrenWrapClass = '',
        $limit = 0
    ) {
        $rootId = $this->storeManager->getStore()->getRootCategoryId();
        $storeId = $this->storeManager->getStore()->getId();
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->getCategoryTree($storeId, $rootId);
        $currentCategory = $this->getCurrentCategory();
        $mapping = [$rootId => $subject->getMenu()];  // use nodes stack to avoid recursion
        foreach ($collection as $category) {
            $categoryParentId = $category->getParentId();
            if (!isset($mapping[$categoryParentId])) {
                $parentIds = $category->getParentIds();
                foreach ($parentIds as $parentId) {
                    if (isset($mapping[$parentId])) {
                        $categoryParentId = $parentId;
                    }
                }
            }

            /** @var Node $parentCategoryNode */
            $parentCategoryNode = $mapping[$categoryParentId];

            $categoryNode = new Node(
                $this->getCategoryAsArray(
                    $category,
                    $currentCategory,
                    $category->getParentId() == $categoryParentId
                ),
                'id',
                $parentCategoryNode->getTree(),
                $parentCategoryNode
            );
            $parentCategoryNode->addChild($categoryNode);

            $mapping[$category->getId()] = $categoryNode; //add node in stack
        }
    }

    /**
     * Add list of associated identities to the top menu block for caching purposes.
     *
     * @param \Magento\Theme\Block\Html\Topmenu $subject
     * @return void
     */
    public function beforeGetIdentities(\Magento\Theme\Block\Html\Topmenu $subject)
    {
        $subject->addIdentity(Category::CACHE_TAG);
        $rootId = $this->storeManager->getStore()->getRootCategoryId();
        $storeId = $this->storeManager->getStore()->getId();
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->getCategoryTree($storeId, $rootId);
        $mapping = [$rootId => $subject->getMenu()];  // use nodes stack to avoid recursion
        foreach ($collection as $category) {
            if (!isset($mapping[$category->getParentId()])) {
                continue;
            }
            $subject->addIdentity(Category::CACHE_TAG . '_' . $category->getId());
        }
    }

    /**
     * Get current Category from catalog layer
     *
     * @return \Magento\Catalog\Model\Category
     */
    private function getCurrentCategory()
    {
        $catalogLayer = $this->layerResolver->get();

        if (!$catalogLayer) {
            return null;
        }

        return $catalogLayer->getCurrentCategory();
    }

    /**
     * Convert category to array
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param \Magento\Catalog\Model\Category $currentCategory
     * @param bool $isParentActive
     * @return array
     */
    private function getCategoryAsArray($category, $currentCategory, $isParentActive)
    {
        $result =  [
            'name' => $category->getName(),
            'id' => 'category-node-' . $category->getId(),
            'url' => $this->catalogCategory->getCategoryUrl($category),
            'has_active' => in_array((string)$category->getId(), explode('/', $currentCategory->getPath()), true),
            'is_active' => $category->getId() == $currentCategory->getId(),
            'is_category' => true,
            'is_parent_active' => $isParentActive
        ];

        $categoryMenuData = $category->getData();
        $customCategoryUrl = null;

        if (isset($categoryMenuData['weltpixel_category_url'])) {
            $customCategoryUrl = trim($categoryMenuData['weltpixel_category_url']);
        }

        if (isset($customCategoryUrl) && strlen($customCategoryUrl)) {

            if (strpos($customCategoryUrl, 'http://') === 0 || strpos($customCategoryUrl, 'https://') === 0) {
                $result['url'] = $customCategoryUrl;
            } elseif ($customCategoryUrl == '#') {
                $result['url'] = 'javascript:void(0);';
            } else {
                $result['url'] = $this->storeManager->getStore()->getBaseUrl() . ltrim($customCategoryUrl, '//');
            }
        }

        $result['open_in_newtab'] = 0;
        if (isset($categoryMenuData['weltpixel_category_url_newtab']) && $categoryMenuData['weltpixel_category_url_newtab']) {
            $result['open_in_newtab'] = 1;
        }

	    $result['weltpixel_mm_display_mode'] = '';
        if (isset($categoryMenuData['weltpixel_mm_display_mode'])) {
	        $result['weltpixel_mm_display_mode'] = $categoryMenuData['weltpixel_mm_display_mode'];
        }

	    $result['weltpixel_mm_columns_number'] = '';
        if (isset($categoryMenuData['weltpixel_mm_columns_number'])) {
        	$colNumber = (int) $categoryMenuData['weltpixel_mm_columns_number'];
	        $result['weltpixel_mm_columns_number'] = $colNumber;
        }

	    $result['weltpixel_mm_column_width'] = '';
        if (isset($categoryMenuData['weltpixel_mm_column_width'])) {
	        $result['weltpixel_mm_column_width'] = $categoryMenuData['weltpixel_mm_column_width'];
        }

        $result['weltpixel_mm_top_block_type'] = '';
        if (isset($categoryMenuData['weltpixel_mm_top_block_type'])) {
            $result['weltpixel_mm_top_block_type'] = $categoryMenuData['weltpixel_mm_top_block_type'];
        }

        $result['weltpixel_mm_top_block_cms'] = '';
        if (isset($categoryMenuData['weltpixel_mm_top_block_cms'])) {
            $result['weltpixel_mm_top_block_cms'] = $categoryMenuData['weltpixel_mm_top_block_cms'];
        }

        $result['weltpixel_mm_top_block'] = '';
        if (isset($categoryMenuData['weltpixel_mm_top_block'])) {
            $result['weltpixel_mm_top_block'] = $categoryMenuData['weltpixel_mm_top_block'];
        }

        $result['weltpixel_mm_right_block_type'] = '';
        if (isset($categoryMenuData['weltpixel_mm_right_block_type'])) {
            $result['weltpixel_mm_right_block_type'] = $categoryMenuData['weltpixel_mm_right_block_type'];
        }

        $result['weltpixel_mm_right_block_cms'] = '';
        if (isset($categoryMenuData['weltpixel_mm_right_block_cms'])) {
            $result['weltpixel_mm_right_block_cms'] = $categoryMenuData['weltpixel_mm_right_block_cms'];
        }

        $result['weltpixel_mm_right_block'] = '';
        if (isset($categoryMenuData['weltpixel_mm_right_block'])) {
            $result['weltpixel_mm_right_block'] = $categoryMenuData['weltpixel_mm_right_block'];
        }

        $result['weltpixel_mm_bottom_block_type'] = '';
        if (isset($categoryMenuData['weltpixel_mm_bottom_block_type'])) {
            $result['weltpixel_mm_bottom_block_type'] = $categoryMenuData['weltpixel_mm_bottom_block_type'];
        }

        $result['weltpixel_mm_bottom_block_cms'] = '';
        if (isset($categoryMenuData['weltpixel_mm_bottom_block_cms'])) {
            $result['weltpixel_mm_bottom_block_cms'] = $categoryMenuData['weltpixel_mm_bottom_block_cms'];
        }

        $result['weltpixel_mm_bottom_block'] = '';
        if (isset($categoryMenuData['weltpixel_mm_bottom_block'])) {
            $result['weltpixel_mm_bottom_block'] = $categoryMenuData['weltpixel_mm_bottom_block'];
        }

        $result['weltpixel_mm_left_block_type'] = '';
        if (isset($categoryMenuData['weltpixel_mm_left_block_type'])) {
            $result['weltpixel_mm_left_block_type'] = $categoryMenuData['weltpixel_mm_left_block_type'];
        }

        $result['weltpixel_mm_left_block_cms'] = '';
        if (isset($categoryMenuData['weltpixel_mm_left_block_cms'])) {
            $result['weltpixel_mm_left_block_cms'] = $categoryMenuData['weltpixel_mm_left_block_cms'];
        }

        $result['weltpixel_mm_left_block'] = '';
        if (isset($categoryMenuData['weltpixel_mm_left_block'])) {
            $result['weltpixel_mm_left_block'] = $categoryMenuData['weltpixel_mm_left_block'];
        }

        $result['weltpixel_mm_mob_hide_allcat'] = 0;
        if (isset($categoryMenuData['weltpixel_mm_mob_hide_allcat'])) {
            $result['weltpixel_mm_mob_hide_allcat'] = $categoryMenuData['weltpixel_mm_mob_hide_allcat'];
        }

        $result['weltpixel_mm_font_color'] = '';
        if (isset($categoryMenuData['weltpixel_mm_font_color'])) {
            $result['weltpixel_mm_font_color'] = $categoryMenuData['weltpixel_mm_font_color'];
        }

        $result['weltpixel_mm_font_hover_color'] = '';
        if (isset($categoryMenuData['weltpixel_mm_font_hover_color'])) {
            $result['weltpixel_mm_font_hover_color'] = $categoryMenuData['weltpixel_mm_font_hover_color'];
        }

        $result['weltpixel_mm_show_arrows'] = 0;
        if (isset($categoryMenuData['weltpixel_mm_show_arrows']) && $categoryMenuData['weltpixel_mm_show_arrows']) {
            $result['weltpixel_mm_show_arrows'] = 1;
        }

        $result['weltpixel_mm_dynamic_sc_flag'] = 0;
        if (isset($categoryMenuData['weltpixel_mm_dynamic_sc_flag']) && $categoryMenuData['weltpixel_mm_dynamic_sc_flag']) {
            $result['weltpixel_mm_dynamic_sc_flag'] = 1;
        }

        $result['weltpixel_mm_dynamic_sc_opts'] = '';
        if (isset($categoryMenuData['weltpixel_mm_dynamic_sc_opts']) && $categoryMenuData['weltpixel_mm_dynamic_sc_opts']) {
            $result['weltpixel_mm_dynamic_sc_opts'] = $categoryMenuData['weltpixel_mm_dynamic_sc_opts'];
        }

        $result['weltpixel_mm_image_enable'] = 0;
        if (isset($categoryMenuData['weltpixel_mm_image_enable']) && $categoryMenuData['weltpixel_mm_image_enable']) {
            $result['weltpixel_mm_image_enable'] = 1;
        }

        $result['weltpixel_mm_image_height'] = '';
        if (isset($categoryMenuData['weltpixel_mm_image_height'])) {
            $result['weltpixel_mm_image_height'] = $categoryMenuData['weltpixel_mm_image_height'];
        }

        $result['weltpixel_mm_image_width'] = '';
        if (isset($categoryMenuData['weltpixel_mm_image_width'])) {
            $result['weltpixel_mm_image_width'] = $categoryMenuData['weltpixel_mm_image_width'];
        }

        $result['weltpixel_mm_image_name_align'] = 'center';
        if (isset($categoryMenuData['weltpixel_mm_image_name_align'])) {
            $result['weltpixel_mm_image_name_align'] = $categoryMenuData['weltpixel_mm_image_name_align'];
        }

        $result['weltpixel_mm_image'] = '';
        if (isset($categoryMenuData['weltpixel_mm_image'])) {
            $result['weltpixel_mm_image'] = $categoryMenuData['weltpixel_mm_image'];
        }

        $result['weltpixel_mm_label_text'] = '';
        if (isset($categoryMenuData['weltpixel_mm_label_text'])) {
            $result['weltpixel_mm_label_text'] = $categoryMenuData['weltpixel_mm_label_text'];
        }

        if (isset($categoryMenuData['weltpixel_mm_category_text']) && strlen(trim($categoryMenuData['weltpixel_mm_category_text']))) {
            $result['name'] = trim($categoryMenuData['weltpixel_mm_category_text']);
        }

        $result['weltpixel_mm_label_font_color'] = '';
        if (isset($categoryMenuData['weltpixel_mm_label_font_color'])) {
            $result['weltpixel_mm_label_font_color'] = $categoryMenuData['weltpixel_mm_label_font_color'];
        }

        $result['weltpixel_mm_label_background_color'] = '';
        if (isset($categoryMenuData['weltpixel_mm_label_background_color'])) {
            $result['weltpixel_mm_label_background_color'] = $categoryMenuData['weltpixel_mm_label_background_color'];
        }

        $result['weltpixel_mm_image_alt'] = '';
        if (isset($categoryMenuData['weltpixel_mm_image_alt'])) {
            $result['weltpixel_mm_image_alt'] = $categoryMenuData['weltpixel_mm_image_alt'];
        }

        $result['weltpixel_mm_label_position'] = '';
        if (isset($categoryMenuData['weltpixel_mm_label_position'])) {
            $result['weltpixel_mm_label_position'] = $categoryMenuData['weltpixel_mm_label_position'];
        }

        $result['weltpixel_mm_image_radius'] = '';
        if (isset($categoryMenuData['weltpixel_mm_image_radius'])) {
            $result['weltpixel_mm_image_radius'] = $categoryMenuData['weltpixel_mm_image_radius'];
        }

        $result['weltpixel_mm_image_position'] = '';
        if (isset($categoryMenuData['weltpixel_mm_image_position'])) {
            $result['weltpixel_mm_image_position'] = $categoryMenuData['weltpixel_mm_image_position'];
        }

        return $result;
    }

    /**
     * Get Category Tree
     *
     * @param int $storeId
     * @param int $rootId
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getCategoryTree($storeId, $rootId)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(array(
        		'name',
		        'weltpixel_category_url',
		        'weltpixel_category_url_newtab',
		        'weltpixel_mm_display_mode',
		        'weltpixel_mm_columns_number',
		        'weltpixel_mm_column_width',
		        'weltpixel_mm_top_block_type',
		        'weltpixel_mm_top_block_cms',
		        'weltpixel_mm_top_block',
		        'weltpixel_mm_right_block',
                'weltpixel_mm_right_block_type',
                'weltpixel_mm_right_block_cms',
                'weltpixel_mm_bottom_block_type',
                'weltpixel_mm_bottom_block_cms',
		        'weltpixel_mm_bottom_block',
                'weltpixel_mm_left_block_type',
                'weltpixel_mm_left_block_cms',
		        'weltpixel_mm_left_block',
		        'weltpixel_mm_mob_hide_allcat',
		        'weltpixel_mm_font_color',
		        'weltpixel_mm_font_hover_color',
		        'weltpixel_mm_show_arrows',
		        'weltpixel_mm_dynamic_sc_flag',
		        'weltpixel_mm_dynamic_sc_opts',
		        'weltpixel_mm_image_enable',
		        'weltpixel_mm_image_height',
		        'weltpixel_mm_image_width',
		        'weltpixel_mm_image_name_align',
		        'weltpixel_mm_image',
                'weltpixel_mm_label_text',
                'weltpixel_mm_category_text',
                'weltpixel_mm_label_font_color',
                'weltpixel_mm_label_background_color',
                'weltpixel_mm_label_position',
                'weltpixel_mm_image_alt',
                'weltpixel_mm_image_radius',
                'weltpixel_mm_image_position'
            )
        );
        $collection->addFieldToFilter('path', ['like' => '1/' . $rootId . '/%']); //load only from store root
        $collection->addAttributeToFilter('include_in_menu', 1);
        $collection->addIsActiveFilter();
        $collection->addNavigationMaxDepthFilter();
        $collection->addUrlRewriteToResult();
        $collection->addOrder('level', Collection::SORT_ORDER_ASC);
        $collection->addOrder('position', Collection::SORT_ORDER_ASC);
        $collection->addOrder('parent_id', Collection::SORT_ORDER_ASC);
        $collection->addOrder('entity_id', Collection::SORT_ORDER_ASC);

        return $collection;
    }

    /**
     * Add active
     *
     * @param \Magento\Theme\Block\Html\Topmenu $subject
     * @param string[] $result
     * @return string[]
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetCacheKeyInfo(\Magento\Theme\Block\Html\Topmenu $subject, array $result)
    {
        $activeCategory = $this->getCurrentCategory();
        if ($activeCategory) {
            $result[] = Category::CACHE_TAG . '_' . $activeCategory->getId();
        }

        return $result;
    }
}
