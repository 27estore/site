<?php
/**
 * @category    WeltPixel
 * @package     WeltPixel_LayeredNavigation
 * @copyright   Copyright (c) 2018 Weltpixel
 * @author      Weltpixel TEAM
 */

namespace WeltPixel\LayeredNavigation\Plugin\Category;

use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\UrlInterface;
use WeltPixel\LayeredNavigation\Helper\Data;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;

/**
 * Class View
 * @package WeltPixel\LayeredNavigation\Plugin\Category
 */
class View
{
    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;
    /**
     * @var UrlInterface
     */
    protected $_storeManager;
    /**
     * @var \WeltPixel\LayeredNavigation\Helper\Data
     */
    protected $_wpHelper;

    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * View constructor.
     * @param JsonFactory $resultJsonFactory
     * @param UrlInterface $_storeManager
     * @param Data $wpHelper
     * @param PageFactory $pageFactory
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        UrlInterface $_storeManager,
        Data $wpHelper,
        PageFactory $pageFactory
    )
    {
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_storeManager = $_storeManager;
        $this->_wpHelper = $wpHelper;
        $this->pageFactory = $pageFactory;
    }

    /**
     * @param Action $subject
     * @param $page
     * @return $this|void
     */
    public function afterExecute(Action $subject, $page)
    {
        if ($this->_wpHelper->isEnabled()) {
            $this->_wpHelper->updateSliderBodyClass();
        }
        if(!$this->_wpHelper->isAjaxEnabled() || !$this->_wpHelper->isEnabled()) {
            return $page;
        }

        $response = $page;
        if ($response instanceof Page) {
            if ($subject->getRequest()->getParam('ajax') == 1) {
                $subject->getRequest()->getQuery()->set('ajax', null);
                $requestUri = $subject->getRequest()->getRequestUri();
                $requestUri = preg_replace('/(\?|&)ajax=1/', '', $requestUri);
                $subject->getRequest()->setRequestUri($requestUri);

                $productsList = $response->getLayout()->getBlock('category.products.list');
                $listMode = $subject->getRequest()->getParam('product_list_mode');
                if($listMode) {
                    $productsList->getChildBlock('product_list_toolbar')->setData('_current_grid_mode', $listMode);
                }
                $productsListBlockHtml = $productsList->toHtml();
                $leftNavBlockHtml = $response->getLayout()->getBlock('catalog.leftnav')->toHtml();

                $dataLayerContent = '';
                $dataLayerContentGa4 = '';
                $dataLayerBlock = $response->getLayout()->getBlock('head.additional');
                if ($dataLayerBlock) {
                    $dLBlockHtml = $dataLayerBlock->toHtml();
                    preg_match('/var dlObjects ?= ?(.*?)];/', $dLBlockHtml, $matches);
                    if (count($matches) == 2) {
                        $dataLayerContent = $matches[1] . ']';
                    }
                    preg_match('/var dl4Objects ?= ?(.*?)];/', $dLBlockHtml, $matchesGa4);
                    if (count($matchesGa4) == 2) {
                        $dataLayerContentGa4 = $matchesGa4[1] . ']';
                    }
                }


                $ga4ServerSideViewItemListContent = '';
                $ga4ServerSideViewItemListBlock = $response->getLayout()->getBlock('weltpixel-ga4-server-view-item-list');
                if ($ga4ServerSideViewItemListBlock) {
                    $ga4ServerSideViewItemListBlockHtml = $ga4ServerSideViewItemListBlock->toHtml();
                    preg_match('/<input.*id="wp_ga4_server_side_view_item_list".*value="(.*?)"/', $ga4ServerSideViewItemListBlockHtml, $matchesGa4ServerSide);
                    if (count($matchesGa4ServerSide) == 2) {
                        $ga4ServerSideViewItemListContent = $matchesGa4ServerSide[1];
                    }
                }

                $tags = [];
                $blocksList = [$productsList,$leftNavBlockHtml];
                foreach ($blocksList as $block) {
                    if ($block instanceof \Magento\Framework\DataObject\IdentityInterface) {
                        $tags = array_merge($tags, $block->getIdentities());
                    }
                }
                $tags = array_unique($tags);

                return $this->_resultJsonFactory->create()->setData(
                    [
                        'success' => true,
                        'html' => [
                            'products_list' => $productsListBlockHtml,
                            'filters' => $leftNavBlockHtml,
                            'dataLayer' => $dataLayerContent,
                            'dataLayerGA4' => $dataLayerContentGa4,
                            'ga4ServerSideItemListHash' => $ga4ServerSideViewItemListContent
                        ]
                    ]
                )->setHeader('X-Magento-Tags', implode(',', $tags));
            }
        }
        return $response;
    }

}
