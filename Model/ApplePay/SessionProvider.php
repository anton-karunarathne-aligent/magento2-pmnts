<?php

declare(strict_types=1);

namespace PMNTS\Gateway\Model\ApplePay;

use PMNTS\Gateway\Model\GatewayV2;

class SessionProvider
{
    private const APPLE_PAY_SESSION_ENDPOINT = '/apple_pay/payment_session';

    /**
     * @inheritDoc
     */
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly GatewayV2 $gateway
    ) {
    }

    /**
     * @inheritDoc
     */
    public function requestSession(string $validationUrl, ?int $storeId = null): array
    {
        $payload = [
            'url' => $validationUrl,
            'domain_name' => $this->configProvider->getDomainName(),
            'display_name' => $this->configProvider->getStoreTitle()
        ];
        return $this->gateway->makeRequest(
            'GET',
            self::APPLE_PAY_SESSION_ENDPOINT,
            $payload,
            $storeId
        );
    }
}
