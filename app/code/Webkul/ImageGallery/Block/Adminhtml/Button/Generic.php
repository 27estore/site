<?php

namespace Webkul\ImageGallery\Block\Adminhtml\Button;

use Magento\Backend\Block\Widget\Context;
use Magento\Cms\Api\PageRepositoryInterface;

class Generic
{
    /**
     * @var Context
     */
    protected $context;
    /**
     * @var PageRepositoryInterface
     */
    protected $pageRepository;
    /**
     * @param Context $context
     * @param PageRepositoryInterface $pageRepository
     */
    public function __construct(
        Context $context,
        PageRepositoryInterface $pageRepository
    ) {
        $this->context = $context;
        $this->pageRepository = $pageRepository;
    }
    /**
     * GetUrl
     *
     * @param string $route
     * @param array $params
     * @return array
     */
    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
