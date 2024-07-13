<?php
namespace WeltPixel\ProductLabels\Controller\Adminhtml\ProductLabels;

class Duplicate extends \WeltPixel\ProductLabels\Controller\Adminhtml\ProductLabels
{
    /**
     * Duplicate action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');
        $forward = $this->getRequest()->getParam('forward', false);
        if ($id) {
            try {
                // init model and duplicate
                $model = $this->_objectManager->create('WeltPixel\ProductLabels\Model\ProductLabels');
                $model->load($id);
                $duplicate = $this->_objectManager->create('WeltPixel\ProductLabels\Model\ProductLabels');
                $duplicate->setData($model->getData());
                $duplicate->setId(null);
                $duplicate->save();
                // display success message
                $this->messageManager->addSuccess(__('You duplicated the product label.'));
                // go to grid
                if ($forward) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $duplicate->getId(), '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                // display error message
                $this->messageManager->addError($e->getMessage());
                // go back to edit form
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        // display error message
        $this->messageManager->addError(__('We can\'t find the item to duplicate.'));
        // go to grid
        return $resultRedirect->setPath('*/*/');
    }
}
