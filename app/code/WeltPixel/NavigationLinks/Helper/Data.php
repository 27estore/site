<?php

namespace WeltPixel\NavigationLinks\Helper;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null) {
        return $this->scopeConfig->getValue(
            'weltpixel_megamenu/megamenu/enable',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );


    }

    /**
     * @param null $storeId
     * @return int|mixed
     */
    public function getWidthThreshold($storeId = null)
    {
        if (!$storeId) $storeId = $this->getStoreId();

        $widthThreshold = (int) $this->scopeConfig->getValue(
            'weltpixel_megamenu/megamenu/width_threshold',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '';
        $widthThreshold = trim($widthThreshold) ? (int) $widthThreshold : 767;
        $mobileThreshold = $this->getMobileThreshold() - 1;

        if ($mobileThreshold > $widthThreshold) {
            return $mobileThreshold;
        }

        return $widthThreshold;
    }

    /**
     * @param null $storeId
     * @return int
     */
    public function getMobileThreshold($storeId = null)
    {
        if (
            $this->isModuleEnabled('WeltPixel_FrontendOptions') &&
            $this->isOutputEnabled('WeltPixel_FrontendOptions')
        ) {
            if (!$storeId) $storeId = $this->getStoreId();

            return (int) $this->scopeConfig->getValue(
                'weltpixel_frontend_options/breakpoints/screen__m',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }

        return 768;
    }

    /**
     * Whether a module is enabled in the configuration or not
     *
     * @param string $moduleName Fully-qualified module name
     * @return boolean
     */
    public function isModuleEnabled($moduleName)
    {
        return $this->_moduleManager->isEnabled($moduleName);
    }

    /**
     * Whether a module output is permitted by the configuration or not
     *
     * @param string $moduleName Fully-qualified module name
     * @return boolean
     */
    public function isOutputEnabled($moduleName)
    {
        return $this->_moduleManager->isOutputEnabled($moduleName);
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function isDesignSettingsEnabled($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuBackgroundColor($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/background_mm_color', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuFontColor($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/font_mm_color', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuLinksHoverOption($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_mm_hover_options', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuLinksHoverColor($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/font_mm_hover_color', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuLinksHoverUnderline($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_mm_hover_underline', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuContinuity($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_mm_top_level_continuity', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuOverlayOption($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_mm_overlay_focus', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getSubMenuPadding($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_mm_padding', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getSubMenuSubItemsPadding($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/sub_links_mm_padding', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function isTextTransformEnabled($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_mm_text_transform', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuFontSizeL0($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_mm_font_size_l0', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuFontSizeL1($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_mm_font_size_l1', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuFontSizeL2($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_mm_font_size_l2', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuLinksPaddingLevel0($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_level0_mm_padding', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuPromoLabelBorderRadius($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/label_mm_border_radius', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getMegaMenuCategoryTitlePadding($storeId = null) {
        return $this->scopeConfig->getValue('weltpixel_megamenu/megamenu_design_settings/links_level1_mm_padding', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) ?? '';
    }

    /**
     * @return false|\Magento\Csp\Helper\CspNonceProvider
     */
    public function getCspNonceProvider()
    {
        if (class_exists(\Magento\Csp\Helper\CspNonceProvider::class)) {
            return  \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Csp\Helper\CspNonceProvider::class);
        }

        return false;
    }

}
