<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Observer\ApplePay;

use Magento\Payment\Observer\AbstractDataAssignObserver;
use PMNTS\Gateway\Gateway\ApplePay\CaptureCommand as ApplePayCaptureCommand;
use Magento\Quote\Api\Data\PaymentInterface;

class AssignDataObserver extends AbstractDataAssignObserver
{
    /**
     * Assign data required for capturing an Apple Pay payment
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $data = $this->readDataArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        $paymentInfo = $this->readPaymentModelArgument($observer);

        if (!is_array($additionalData)) {
             return;
        }

        # Apple Pay Token Payload
        if (array_key_exists(ApplePayCaptureCommand::APPLE_PAY_WALLET_PAYLOAD_KEY, $additionalData)) {
            $paymentInfo->setAdditionalInformation(
                ApplePayCaptureCommand::APPLE_PAY_WALLET_PAYLOAD_KEY,
                $additionalData[ApplePayCaptureCommand::APPLE_PAY_WALLET_PAYLOAD_KEY]
            );
        }
    }
}
