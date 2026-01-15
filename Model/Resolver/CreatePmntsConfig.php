<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PMNTS\Gateway\Model\Config\PmntsConfigProvider;

class CreatePmntsConfig implements ResolverInterface
{
    /**
     * @param PmntsConfigProvider $pmntsConfigProvider
     */
    public function __construct(
        private readonly PmntsConfigProvider $pmntsConfigProvider
    ) {
    }

    /**
     * Resolver for creating pmnts config
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array<mixed>|null $value
     * @param array<mixed>|null $args
     * @return array[]|Value|mixed
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $config = $this->pmntsConfigProvider->getConfig();
        $pmntsConfig = $config['payment']['pmntsGateway'];
        return [
            'iframe_src' => $pmntsConfig['iframeSrc'],
            'fraud_fingerprint_src' => $pmntsConfig['fraudFingerprintSrc'],
            'is_sandbox' => $pmntsConfig['isSandbox'],
            'can_save_card' => $pmntsConfig['canSaveCard'],
            'allowed_card_types' => $pmntsConfig['allowedCardTypes'],
            'cc_vault_code' => $pmntsConfig['ccVaultCode'],
        ];
    }
}
