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
namespace Webkul\ImageGallery\Block\Adminhtml\Groups\Edit;

class Tabs extends \Magento\Backend\Block\Widget\Tabs
{
    /**
     * Data
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('groups_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Group Information'));
    }

    /**
     * Prepare Layout
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $block = \Webkul\ImageGallery\Block\Adminhtml\Groups\Edit\Tab\Groups::class;
        $galleryBlock = \Webkul\ImageGallery\Block\Adminhtml\Groups\Edit\Tab\Gallery::class;
        $this->addTab(
            'groups',
            [
                'label' => __('Groups'),
                'content' => $this->getLayout()->createBlock($block, 'groups')->toHtml(),
            ]
        );
        $this->addTab(
            'images',
            [
                'label' => __('Galleries'),
                'content' => $this->getLayout()
                            ->createBlock($galleryBlock, 'imagegallery.groups.gallery.grid')->toHtml()
            ]
        );
        return parent::_prepareLayout();
    }
}
