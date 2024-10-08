<?php

namespace WeltPixel\GA4\Plugin;

class CookieManagement
{
    /**
     * @var \WeltPixel\GA4\Helper\Data
     */
    protected $helper;

    /**
     * @var \WeltPixel\GA4\Model\CookieManager
     */
    protected $cookieManager;

    /**
     * @param \WeltPixel\GA4\Helper\Data $helper
     * @param \WeltPixel\GA4\Model\CookieManager $cookieManager
     */
    public function __construct(
        \WeltPixel\GA4\Helper\Data $helper,
        \WeltPixel\GA4\Model\CookieManager $cookieManager
    )
    {
        $this->helper = $helper;
        $this->cookieManager = $cookieManager;
    }

    /**
     * @param \Magento\Framework\App\FrontController $subject
     * @param $result
     * @param \Magento\Framework\App\RequestInterface $request
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterDispatch(
        \Magento\Framework\App\FrontController $subject,
        $result,
        \Magento\Framework\App\RequestInterface $request
    ) {
        if (!$this->helper->isEnabled()) {
            return $result;
        }

        /** Elasticsuite Tracker Headers Already Sent Error Compatibility */
        if  (($request->getModuleName() == 'elasticsuite')&& ($request->getActionName() == 'hit'))  {
            return $result;
        }
        /** Elasticsuite Tracker Headers Already Sent Error Compatibility */

        if (headers_sent()) {
            return $result;
        }

        $this->cookieManager->setGA4Cookies();
        return $result;

    }
}
