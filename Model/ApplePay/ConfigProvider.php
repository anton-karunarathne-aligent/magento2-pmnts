<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Model\ApplePay;

use Assert\InvalidArgumentException;
use Laminas\Uri\UriFactory;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\StoreManagerInterface;
use PMNTS\Gateway\Helper\Data;

class ConfigProvider implements ConfigProviderInterface
{
    public const METHOD_CODE = 'fatzebra_applepay';

    /** @var MethodInterface */
    private MethodInterface $baseMethod;

    /** @var MethodInterface */
    private MethodInterface $applePayMethod;

    private const CC_TYPE_MAPPING = [
        'AE' => 'amex',
        'VI' => 'visa',
        'MC' => 'masterCard',
        'JCB' => 'jcb'
    ];

    /**
     * @param PaymentHelper $paymentHelper
     * @param StoreManagerInterface $storeManager
     * @throws LocalizedException
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        private readonly StoreManagerInterface $storeManager
    ) {
        $this->baseMethod = $paymentHelper->getMethodInstance(Data::METHOD_CODE);
        $this->applePayMethod = $paymentHelper->getMethodInstance(self::METHOD_CODE);
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        if (!$this->isActive()) {
            return [];
        }

        return [
            'payment' => [
                'fatzebra_applepay' => $this->getConfigurationOptions()
            ]
        ];
    }

    /**
     * The configuration options for Fat Zebra Apple Pay
     *
     * @return array
     */
    public function getConfigurationOptions()
    {
        return [
            'is_active' => $this->isActive(),
            'supported_networks' => $this->getSupportedCards(),
            'merchant_country' => $this->getMerchantCountyCode(),
            'supported_countries' => $this->getSupportedCountries(),
            'is_sandbox' => $this->isSandbox(),
            'store_title' => $this->getStoreTitle(),
        ];
    }

    /**
     * Whether Fat Zebra Apple Pay is active
     *
     * @return bool whether Fat Zebra Apple Pay is active
     */
    public function isActive()
    {
        return (bool) $this->applePayMethod->getConfigData('active');
    }

    /**
     * Whether Fat Zebra Apple Pay is in sandbox mode
     *
     * @return bool whether Fat Zebra Apple Pay is in sandbox mode
     */
    public function isSandbox(): bool
    {
        return (bool) $this->baseMethod->getConfigData('sandbox_mode');
    }

    /**
     * Gets the supported card types from the parent method's configuration
     *
     * @return string[] the supported card types, mapped to the Apple Pay supportedCardTypes format
     */
    public function getSupportedCards()
    {
        $supportedCards = $this->baseMethod->getConfigData('cctypes');
        if (!$supportedCards) {
            return [];
        }
        return array_map((fn($card) => self::CC_TYPE_MAPPING[$card]), explode(',', $supportedCards));
    }

    /**
     * Gets the supported countries from the parent method's configuration
     *
     * If the method does not set specific countries, this method returns null.
     *
     * @return ?array the supported countries, or null if the parent method does not allow specific countries
     */
    public function getSupportedCountries(): ?array
    {
        if (!$this->baseMethod->getConfigData('allowspecific')) {
            return null;
        }
        $allowedCountries = $this->baseMethod->getConfigData('specificcountry');
        return explode(',', $allowedCountries);
    }

    /**
     * Gets the merchant name from the parent method's configuration
     *
     * @return ?string the merchant name
     */
    public function getStoreTitle(): ?string
    {
        return $this->applePayMethod->getConfigData('merchant_name');
    }

    /**
     * Gets the merchant country code from the parent method's configuration
     *
     * @return ?string the merchant country code
     */
    public function getMerchantCountyCode(): ?string
    {
        return $this->applePayMethod->getConfigData('merchant_country');
    }

    /**
     * Gets the domain name for Apple Pay sessions
     *
     * @return string the domain name for Apple Pay
     */
    public function getDomainName(): string
    {
        try {
            $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK, true);
            return UriFactory::factory($baseUrl)->getHost();
        } catch (InvalidArgumentException $e) {
            throw new LocalizedException(__("Error retrieving domain for Apple Pay configuration"));
        }
    }
}
