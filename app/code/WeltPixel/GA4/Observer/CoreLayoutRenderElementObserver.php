<?php
namespace WeltPixel\GA4\Observer;

use Magento\Framework\Event\ObserverInterface;

class CoreLayoutRenderElementObserver implements ObserverInterface
{
    /**
     * @var \WeltPixel\GA4\Helper\Data
     */
    protected $helper;

    /**
     * @param \WeltPixel\GA4\Helper\Data $helper
     */
    public function __construct(\WeltPixel\GA4\Helper\Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return self
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->helper->isEnabled()) {
            return $this;
        }

        $elementName = $observer->getData('element_name');

        if (!str_contains($elementName, 'weltpixel_gtmga4_head')) {
            return $this;
        }

        $transport = $observer->getData('transport');
        $html = $transport->getOutput();

        $scriptContent = $this->helper->getDataLayerScript();
        $html = $scriptContent . PHP_EOL . $html;

        $transport->setOutput($html);

        return $this;
    }
}
