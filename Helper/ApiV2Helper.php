<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

/**
 * Helper class for Fat Zebra API v2
 */
class ApiV2Helper
{
    private const PRODUCTION_URL = "https://paynow.pmnts.io";
    private const SANDBOX_URL = "https://paynow.pmnts-sandbox.io";

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Gets the correct API base URL based on sandbox mode.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiUrl(?int $storeId = null): string
    {
        $isSandbox = $this->isSandboxMode($storeId);
        $baseUrl = $isSandbox
            ? self::SANDBOX_URL
            : self::PRODUCTION_URL;

        return $baseUrl. '/v2';
    }

    /**
     * Retrieves the username from configuration.
     *
     * @param int|null $storeId
     * @return string
     * @throws LocalizedException
     */
    public function getUsername(?int $storeId = null): string
    {
        $username = $this->scopeConfig->getValue(
            Data::CONFIG_PATH_PMNTS_USERNAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (string)$username;
    }

    /**
     * Retrieves the password from configuration.
     *
     * @param int|null $storeId
     * @return string
     * @throws LocalizedException
     */
    public function getPassword(?int $storeId = null): string
    {
        $password = $this->scopeConfig->getValue(
            Data::CONFIG_PATH_PMNTS_TOKEN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (string)$password;
    }

    /**
     * Check if sandbox mode is enabled.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSandboxMode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            Data::CONFIG_PATH_PMNTS_SANDBOX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
