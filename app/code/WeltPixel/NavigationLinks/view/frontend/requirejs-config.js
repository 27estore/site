var config = {
    map: {
        '*': {
            navigationJs: 'WeltPixel_NavigationLinks/js/navigation_js'
        }
    },
    config: {
        mixins: {
            'mage/menu': {
                'WeltPixel_NavigationLinks/js/menu-mixin': true
            },
            'mage/tabs': {
                'WeltPixel_NavigationLinks/js/tabs-mixin': true
            },
            'Magento_Theme/js/view/breadcrumbs': {
                'WeltPixel_NavigationLinks/js/breadcrumbs-mixin': true
            }
        }
    }
};
