<?php
/**
 * Webkul Software.
 *
 * @category  Webkul
 * @package   Webkul_ImageGallery
 * @author    Webkul
 * @copyright Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
namespace Webkul\ImageGallery\Controller\Adminhtml\Gallery;

use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Webkul\ImageGallery\Model\ResourceModel\Gallery\CollectionFactory;

class MassDisable extends \Magento\Backend\App\Action
{
    /**
     * @var Filter
     */
    protected $_filter;

    /**
     * @var CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory
    ) {
        $this->_filter = $filter;
        $this->_collectionFactory = $collectionFactory;
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Webkul_ImageGallery::gallery');
    }

    /**
     * Return Data
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $collection = $this->_filter->getCollection($this->_collectionFactory->create());
        foreach ($collection as $gallery) {
            $this->updateItem($gallery, 0);
        }
        $this->messageManager->addSuccess(__('Gallery(s) disabled succesfully'));
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * UpdateItem
     *
     * @param object $item
     * @param int $status
     */
    public function updateItem($item, $status)
    {
        $item->setStatus($status)->save();
    }
}
