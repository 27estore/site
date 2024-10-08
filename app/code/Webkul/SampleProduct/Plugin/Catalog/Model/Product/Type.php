<?php
/**
 * Webkul Software.
 *
 * @category  Webkul
 * @package   Webkul_SampleProduct
 * @author    Webkul Software Private Limited
 * @copyright Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
namespace Webkul\SampleProduct\Plugin\Catalog\Model\Product;

class Type
{
    /**
     * AfterGetOptionArray
     *
     * @param \Magento\Catalog\Model\Product\Type $subject
     * @param array $options
     */
    public function afterGetOptionArray(
        \Magento\Catalog\Model\Product\Type $subject,
        $options
    ) {
        unset($options['sample']);
        return $options;
    }
}
