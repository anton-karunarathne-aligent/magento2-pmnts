<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Api;

use PMNTS\Gateway\Api\Data\ApplePaySessionRequestInterface;

interface ApplePaySessionInterface
{
    /**
     * Request an Apple Pay session from Fat Zebra for a guest user.
     *
     * Apple Pay session is returned as a JSON string to accommodate the opaque nature of the Apple Pay session object.
     * I.e., it has no fixed schema is and is open to change.
     *
     * @param string $cartId
     * @param ApplePaySessionRequestInterface $request
     * @return array
     */
    public function requestSession(string $cartId, ApplePaySessionRequestInterface $request): string;

    /**
     * Request an Apple Pay session from Fat Zebra for a customer account.
     *
     * Apple Pay session is returned as a JSON string to accommodate the opaque nature of the Apple Pay session object.
     * I.e., it has no fixed schema is and is open to change.
     *
     * @param ApplePaySessionRequestInterface $request
     * @return array
     */
    public function requestSessionForCustomer(ApplePaySessionRequestInterface $request): string;
}
