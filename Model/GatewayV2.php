<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use PMNTS\Gateway\Helper\ApiV2Helper as ApiV2Helper;
use Psr\Log\LoggerInterface;

/**
 * Handles communication with Fat Zebra's v2 API
 */
class GatewayV2
{
    /**
     * @param ApiV2Helper $apiV2Helper
     * @param CurlFactory $curlFactory
     * @param Json $jsonSerializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        readonly private ApiV2Helper $apiV2Helper,
        readonly private CurlFactory $curlFactory,
        readonly private Json $jsonSerializer,
        readonly private LoggerInterface $logger
    ) {
    }

    /**
     * Make an HTTP request to the Fat Zebra API and return the parsed response.
     *
     * @param string $method HTTP method (GET, POST, PUT)
     * @param string $endpoint API endpoint (e.g. '/apple_pay/payment_session')
     * @param array|null $payload Request payload or query params
     * @param int|null $storeId Store context for config
     * @return array Parsed response data
     * @throws LocalizedException
     */
    public function makeRequest(string $method, string $endpoint, ?array $payload, ?int $storeId): array
    {
        try {
            // Build the full URL (including query params for GET)
            $url = $this->buildUrl($endpoint, $method, $payload, $storeId);
            $finalPayload = $this->preparePayload($method, $payload, $this->apiV2Helper->isSandboxMode($storeId));

            // Configure the cURL client and send the request
            $curl = $this->curlFactory->create();
            $this->setupCurl(
                $curl,
                $this->apiV2Helper->getUsername($storeId),
                $this->apiV2Helper->getPassword($storeId)
            );
            $this->sendCurlRequest($curl, $method, $url, $finalPayload);
            $responseBody = $curl->getBody();
            $httpStatusCode = $curl->getStatus();
            // Parse or handle error
            if ($httpStatusCode >= 200 && $httpStatusCode < 300) {
                return $this->parseResponse($responseBody);
            } else {
                $this->logger->error('Fat Zebra API V2 Error: ' . $responseBody);
            }
        } catch (\Exception $e) {
            $this->logger->error('Gateway V2 Error: ' . $e->getMessage());
        }
        throw new LocalizedException(__('Error communicating with Fat Zebra API.'));
    }

    /**
     * Build the Fat Zebra API URL, including query params for GET requests.
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array|null $payload Request payload or query params
     * @param int|null $storeId Store context for config
     * @return string Full API URL
     */
    private function buildUrl(string $endpoint, string $method, ?array $payload, ?int $storeId): string
    {
        $apiUrl = $this->apiV2Helper->getApiUrl($storeId);
        if (strpos($endpoint, '/') !== 0) {
            $endpoint = '/' . $endpoint;
        }
        $url = $apiUrl . $endpoint;
        if ($method === 'GET' && !empty($payload)) {
            $url .= '?' . http_build_query($payload);
        }
        return $url;
    }

    /**
     * Prepare the request payload. Adds the 'test' flag for POST/PUT requests.
     *
     * @param string $method HTTP method
     * @param array|null $payload Request payload
     * @param bool $isSandbox Sandbox mode flag
     * @return array Prepared payload
     */
    private function preparePayload(string $method, ?array $payload, bool $isSandbox): array
    {
        $finalPayload = $payload ?? [];
        if ($method === 'POST' || $method === 'PUT') {
            $finalPayload['test'] = $isSandbox;
        }
        return $finalPayload;
    }

    /**
     * Configure the cURL client with authentication and headers.
     *
     * @param Curl $curl
     * @param string $username API username
     * @param string $password API password
     */
    private function setupCurl(Curl $curl, string $username, string $password): void
    {
        $curl->setOption(CURLOPT_SSLVERSION, 6); // CURLOPT_SSLVERSION_TLSv1_2
        $curl->setOption(CURLOPT_TIMEOUT, 50); // Match Gateway V1 config
        $curl->setCredentials($username, $password);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('User-Agent', 'FatZebra Magento2 Library 2.0.0');
    }

    /**
     * Send the HTTP request using the cURL client.
     *
     * @param Curl $curl
     * @param string $method HTTP method
     * @param string $url Full API URL
     * @param array $payload Request payload
     */
    private function sendCurlRequest(Curl $curl, string $method, string $url, array $payload): void
    {
        if ($method === 'GET') {
            $curl->get($url);
        } elseif ($method === 'POST' || $method === 'PUT') {
            if ($method === 'PUT') {
                $curl->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
            }
            $curl->post($url, $this->jsonSerializer->serialize($payload));
        } else {
            throw new LocalizedException(__('Unsupported request method: %1', $method));
        }
    }

    /**
     * Parse and validate the successful API response.
     *
     * Throws if the response is empty or invalid.
     *
     * @param string $responseBody Response body
     * @return array Parsed response data
     * @throws LocalizedException
     */
    private function parseResponse(string $responseBody): array
    {
        $responseData = $this->jsonSerializer->unserialize($responseBody);
        if (empty($responseData)) {
            throw new LocalizedException(__('Received empty response data from Fat Zebra.'));
        }
        return $responseData;
    }
}
