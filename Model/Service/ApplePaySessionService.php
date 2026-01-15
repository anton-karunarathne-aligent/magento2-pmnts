<?php

declare(strict_types=1);

namespace PMNTS\Gateway\Model\Service;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use PMNTS\Gateway\Api\ApplePaySessionInterface;
use PMNTS\Gateway\Api\Data\ApplePaySessionRequestInterface;
use PMNTS\Gateway\Model\ApplePay\SessionProvider;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;

class ApplePaySessionService implements ApplePaySessionInterface
{
    /**
     * @param SessionProvider $sessionService
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param UserContextInterface $userContext
     * @param Json $jsonSerializer
     */
    public function __construct(
        private readonly SessionProvider $sessionService,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly GuestCartRepositoryInterface $guestCartRepository,
        private readonly UserContextInterface $userContext,
        private readonly Json $jsonSerializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function requestSession(string $cartId, ApplePaySessionRequestInterface $request): string
    {
        $validationUrl = $request->getValidationUrl();
        if (empty($cartId)) {
            throw new InputException(__('Required parameter "cart_id" is missing'));
        }
        if (empty($validationUrl)) {
            throw new InputException(__('Required parameter "validation_url" is missing'));
        }
        try {
            $quote = $this->guestCartRepository->get($cartId);
            if ($quote->getCustomerId() || !$quote->getIsActive()) {
                throw new InputException(__('Invalid or unavailable guest cart'));
            }
        } catch (NoSuchEntityException $e) {
            throw new InputException(__('Invalid or unavailable guest cart'));
        }
        try {
            $this->logger->info("ApplePaySession requested for cart: $cartId");
            $storeId = (int)$this->storeManager->getStore()->getId();
            $session = $this->sessionService->requestSession($validationUrl, $storeId);
            return $this->jsonSerializer->serialize($session);
        } catch (\Exception $e) {
            $this->logger->error('ApplePaySession REST API error: ' . $e->getMessage());
            throw new LocalizedException(__('An error occurred while requesting the Apple Pay session.'));
        }
    }

    /**
     * @inheritDoc
     */
    public function requestSessionForCustomer(ApplePaySessionRequestInterface $request): string
    {
        $validationUrl = $request->getValidationUrl();
        if (empty($validationUrl)) {
            throw new InputException(__('Required parameter "validation_url" is missing'));
        }
        try {
            $this->logger->info("ApplePaySession requested for customer: " . $this->userContext->getUserId());
            $storeId = (int)$this->storeManager->getStore()->getId();
            $session = $this->sessionService->requestSession($validationUrl, $storeId);
            return $this->jsonSerializer->serialize($session);
        } catch (\Exception $e) {
            $this->logger->error('ApplePaySession REST API error (customer): ' . $e->getMessage());
            throw new LocalizedException(__('An error occurred while requesting the Apple Pay session.'));
        }
    }
}
