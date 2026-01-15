<?php

declare(strict_types=1);

namespace PMNTS\Gateway\Model\Api\Data;

use PMNTS\Gateway\Api\Data\ApplePaySessionRequestInterface;
use Magento\Framework\Api\AbstractSimpleObject;

class ApplePaySessionRequest extends AbstractSimpleObject implements ApplePaySessionRequestInterface
{
    /**
     * @inheritDoc
     */
    public function getValidationUrl(): string
    {
        return (string)$this->_get('validation_url');
    }

    /**
     * @inheritDoc
     */
    public function setValidationUrl(string $validationUrl): ApplePaySessionRequestInterface
    {
        return $this->setData('validation_url', $validationUrl);
    }
}
