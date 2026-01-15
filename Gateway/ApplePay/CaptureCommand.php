<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Gateway\ApplePay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Info as PaymentInfo;
use PMNTS\Gateway\Gateway\AbstractCommand;
use PMNTS\Gateway\Helper\Data as PmntsHelper;
use PMNTS\Gateway\Model\Gateway;
use PMNTS\Gateway\Model\GatewayFactory;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Exception;

class CaptureCommand extends AbstractCommand implements CommandInterface
{
    public const APPLE_PAY_WALLET_PAYLOAD_KEY = 'applepay_wallet_payload';

    private const FATZEBRA_APPLEPAY_CODE = 'APPLEPAYWEB';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param PmntsHelper $pmntsHelper
     * @param GatewayFactory $gatewayFactory
     * @param LoggerInterface $logger
     * @param Json $jsonSerializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PmntsHelper $pmntsHelper,
        GatewayFactory $gatewayFactory,
        LoggerInterface $logger,
        private Json $jsonSerializer
    ) {
        parent::__construct($scopeConfig, $pmntsHelper, $gatewayFactory, $logger);
    }

    /**
     * Capture an Apple Pay payment with the Fat Zebra Wallet API
     *
     * @param array $commandSubject
     * @return void
     * @throws LocalizedException|Exception
     */
    public function execute(array $commandSubject)
    {
        /** @var PaymentInfo $payment */
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();
        $amount = $commandSubject['amount'];
        $reference = $this->pmntsHelper->getOrderReference($order);

        // Validate required data
        $this->validatePaymentData($payment);
        $this->validateTransactionData($amount, $reference);

        // Process payment
        $gateway = $this->getGateway($storeId);
        $result = $this->processWalletPayment($gateway, $payment, $order, $amount, $reference);

        if ($result && isset($result['response'])) {
            if (empty($result['response']['successful'])) {
                $this->handleFailedResponse($result, $order);
            } else {
                $this->handleSuccessfulResponse($result, $payment);
            }
        } else {
            throw new CommandException(__('Payment gateway error, please contact customer service.'));
        }
    }

    /**
     * Validate payment data has required Apple Pay token
     *
     * @param PaymentInfo $payment
     * @return void
     * @throws CommandException
     */
    private function validatePaymentData(PaymentInfo $payment): void
    {
        $applePayToken = $payment->getAdditionalInformation(self::APPLE_PAY_WALLET_PAYLOAD_KEY);
        if (empty($applePayToken)) {
            $this->logger->critical('Apple Pay Capture Error: Token data missing from payment additional information.');
            throw new CommandException(__('Payment gateway error, please contact customer service.'));
        }
    }

    /**
     * Validate transaction data has required fields
     *
     * @param float|null $amount
     * @param string|null $reference
     * @return void
     * @throws CommandException
     */
    private function validateTransactionData($amount, $reference): void
    {
        if ($amount === null || $amount <= 0) {
            throw new CommandException(__("Amount is a required field."));
        }
        if ($reference === null || strlen($reference) === 0) {
            throw new CommandException(__("Reference is a required field."));
        }
    }

    /**
     * Process the wallet payment through the gateway
     *
     * @param Gateway $gateway
     * @param PaymentInfo $payment
     * @param Order $order
     * @param float $amount
     * @param string $reference
     * @return array
     * @throws Exception
     */
    private function processWalletPayment(
        Gateway $gateway,
        PaymentInfo $payment,
        Order $order,
        float $amount,
        string $reference
    ): array {
        try {
            $this->logger->info(sprintf(
                'Apple Pay Capture: Attempting capture for order %s, amount %s %s.',
                $reference,
                $amount,
                $order->getBaseCurrencyCode()
            ));

            return $gateway->wallet_purchase(
                $amount,
                $reference,
                self::FATZEBRA_APPLEPAY_CODE,
                $this->getApplePayToken($payment),
                $order->getBaseCurrencyCode()
            );
        } catch (Exception $e) {
            $this->logger->critical('Apple Pay Gateway Exception: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * Get the Apple Pay token data as an array
     *
     * @param PaymentInfo $payment
     * @return array
     * @throws CommandException
     */
    private function getApplePayToken(PaymentInfo $payment): array
    {
        $applePayTokenJson = $payment->getAdditionalInformation(self::APPLE_PAY_WALLET_PAYLOAD_KEY);
        try {
            return $this->jsonSerializer->unserialize($applePayTokenJson);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Failed to decode Apple Pay token: ' . $e->getMessage());
            throw new CommandException(
                __('Payment gateway error, please contact customer service.')
            );
        }
    }

    /**
     * Handle a failed gateway response
     *
     * @param array $result
     * @param Order $order
     * @return void
     * @throws CommandException
     */
    private function handleFailedResponse(array $result, Order $order): void
    {
        $errors = $result['errors'] ?? ['Gateway error'];
        $this->logger->critical(
            __(
                'Apple Pay error (Order #%1): %2',
                $order->getIncrementId(),
                implode('. ', $errors)
            )
        );
        throw new CommandException(__('Payment gateway error, please contact customer service.'));
    }

    /**
     * Handle a successful gateway response
     *
     * @param array $result
     * @param PaymentInfo $payment
     * @return void
     */
    private function handleSuccessfulResponse(array $result, PaymentInfo $payment): void
    {
        // For successful responses with an ID, save as transaction ID and set as closed
        if (isset($result['response']['id'])) {
            $payment->setTransactionId($result['response']['id']);
            $payment->setIsTransactionClosed(true);
        } else {
            // Log error but don't throw exception as we don't want to lose the order when payment has been captured
            $this->logger->error('Apple Pay successful response missing transaction ID');
        }
    }
}
