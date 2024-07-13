<?php
namespace WeltPixel\ProductLabels\Block\Adminhtml\ProductLabels\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Class DeleteButton
 */
class DuplicateButton extends GenericButton implements ButtonProviderInterface
{

    /**
     * @return array
     */
    public function getButtonData()
    {
        $data = [];
        if ($this->getProductLabelId()) {
            $data = [
                'label' => __('Duplicate Item'),
                'class' => 'duplicate',
                'on_click' => 'deleteConfirm(\'' . __(
                    'Are you sure you want to duplicate this?'
                ) . '\', \'' . $this->getDuplicateUrl() . '\')',
                'sort_order' => 25,
            ];
        }
        return $data;
    }

    /**
     * @return string
     */
    public function getDuplicateUrl()
    {
        return $this->getUrl('*/*/duplicate/forward/1', ['id' => $this->getProductLabelId()]);
    }
}
