<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Store\Model\StoreManagerInterface;
use PMNTS\Gateway\Model\ApplePay\SessionProvider;
use Psr\Log\LoggerInterface;

/**
 * Resolver for requesting an Apple Pay session from Fat Zebra
 */
class RequestApplePaySession implements ResolverInterface
{
    /**
     * @param SessionProvider $sessionProvider
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param GetCartForUser $getCartForUser
     * @param Json $json
     */
    public function __construct(
        private readonly SessionProvider $sessionProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly GetCartForUser $getCartForUser,
        private readonly Json $json
    ) {
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
    ): array {
        $cartId = $args['input']['cart_id'] ?? null;
        $validationUrl = $args['input']['validation_url'] ?? null;
        if (empty($cartId)) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        if (empty($validationUrl)) {
            throw new GraphQlInputException(__('Required parameter "validation_url" is missing'));
        }
        $storeId = (int)$this->storeManager->getStore()->getId();
        // Validate the cart using GetCartForUser
        $this->getCartForUser->execute($cartId, $context->getUserId(), $storeId);
        try {
            $this->logger->info("ApplePaySession requested for cart: $cartId");
            return [
                'session' =>$this->json->serialize($this->sessionProvider->requestSession($validationUrl, $storeId))
            ];
        } catch (\Exception $e) {
            $this->logger->error('ApplePaySession GraphQL error: ' . $e->getMessage());
            throw new GraphQlInputException(__('An error occurred while requesting the Apple Pay session.'));
        }
    }
}
