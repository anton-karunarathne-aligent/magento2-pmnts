<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Api\Data;

interface ApplePaySessionRequestInterface
{
    /**
     * Get validation_url
     *
     * @return string
     */
    public function getValidationUrl(): string;

    /**
     * Set validation_url
     *
     * @param string $validationUrl
     * @return $this
     */
    public function setValidationUrl(string $validationUrl): ApplePaySessionRequestInterface;
}
