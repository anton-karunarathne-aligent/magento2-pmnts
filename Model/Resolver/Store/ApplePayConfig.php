<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Model\Resolver\Store;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PMNTS\Gateway\Model\ApplePay\ConfigProvider as ApplePayConfigProvider;

/**
 * Resolver for Fat Zebra Apple Pay configuration for StoreConfig
 */
class ApplePayConfig implements ResolverInterface
{
    /**
     * @var ApplePayConfigProvider
     */
    private $applePayConfigProvider;

    /**
     * @param ApplePayConfigProvider $applePayConfigProvider
     */
    public function __construct(
        ApplePayConfigProvider $applePayConfigProvider
    ) {
        $this->applePayConfigProvider = $applePayConfigProvider;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!$this->applePayConfigProvider->isActive()) {
            // Return default empty values when inactive
             return [ 'is_active' => false ];
        }

        return $this->applePayConfigProvider->getConfigurationOptions();
    }
}
