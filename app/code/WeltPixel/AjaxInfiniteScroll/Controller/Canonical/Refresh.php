<?php


namespace WeltPixel\AjaxInfiniteScroll\Controller\Canonical;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Json\Helper\Data;
use WeltPixel\AjaxInfiniteScroll\Helper\Data as IasData;
use Psr\Log\LoggerInterface;


class Refresh extends \Magento\Framework\App\Action\Action
{

    /**
     * @var Data
     */
    protected $_jsonHelper;

    /**
     * @var IasData
     */
    protected $_iasHelper;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * Refresh constructor.
     * @param Context $context
     * @param Data $jsonHelper
     * @param IasData $iasHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Data $jsonHelper,
        IasData $iasHelper,
        LoggerInterface $logger
    ) {
        $this->_jsonHelper = $jsonHelper;
        $this->_iasHelper = $iasHelper;
        $this->resultFactory = $context->getResultFactory();
        $this->_logger = $logger;

        parent::__construct($context);
    }

    protected $resultFactory;

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $isAjax = false;
        $responseData = [];
        $params = $this->getRequest()->getParams();

        if (isset($params['is_ajax']) && $params['is_ajax']) {
            $isAjax = true;
        }

        if ($isAjax) {
			# 2024-09-29 Dmitrii Fediuk https://github.com/dmitrii-fediuk
			# 1) «Undefined array key "current_url"
			# in app/code/WeltPixel/AjaxInfiniteScroll/Controller/Canonical/Refresh.php on line 63»:
			# https://github.com/27estore/site/issues/62
			# 2)
			#	$.ajax({
			#		<…>
			#		url: window.ajaxCanonicalRefresh,
			#		<…>
			#		data: {
			#			is_ajax: 1,
			#			current_url: window.location.href
			#		},
			# https://github.com/27estore/site/blob/2024-09-29/app/code/WeltPixel/AjaxInfiniteScroll/view/frontend/web/js/ajaxinfinitescroll.js#L491-L498
			$currentUrl = $params['current_url'];
            $currentPageNo = $this->_iasHelper->getCurrentPageNo($currentUrl);
            $prevPageUrl = $this->_iasHelper->getPrevPageUrl($currentPageNo, $currentUrl);
            $nextPageUrl = $this->_iasHelper->getNextPageUrl($currentPageNo, $currentUrl);

            $responseData['errors'] = false;
            $responseData['prev'] = $prevPageUrl;
            $responseData['next'] = $nextPageUrl;
        } else {
            $responseData['errors'] = true;
        }

        try {
            return $this->jsonResponse($responseData);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $this->jsonResponse($e->getMessage());
        } catch (\Exception $e) {
            $this->_logger->critical($e);
            return $this->jsonResponse($e->getMessage());
        }
    }

    /**
     * Create json response
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function jsonResponse($response = '')
    {
        return $this->getResponse()->representJson(
            $this->_jsonHelper->jsonEncode($response)
        );
    }
}
