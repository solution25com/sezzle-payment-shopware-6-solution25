<?php
declare(strict_types=1);
namespace Sezzle\Services;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Random\RandomException;
use Sezzle\Exception\SezzleApiException;
use Sezzle\Exception\SezzleAuthException;
use Sezzle\Library\Constants\EnvironmentUrl;
class SezzleClientService extends Endpoints
{
    private ?Client $client = null;
    private ConfigService $configs;
    private ?string $currentBaseUrl = null;
    private array $tokenCache = [];
    public function __construct(ConfigService $configs)
    {
        $this->configs = $configs;
    }
    public function requestAuthToken(string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        if (isset($this->tokenCache[$salesChannelId])) {
            $cached = $this->tokenCache[$salesChannelId];
            if ($cached['expiresAt'] > time() + 5) {
                return [
                    'token' => $cached['token'],
                    'merchantUuid' => $cached['merchantUuid'],
                ];
            }
        }
        $endpoint = self::getEndpoint(self::AUTH_TOKEN);
        $mode = $this->configs->getConfig('mode', $salesChannelId);
        $isProd = $mode === 'live';
        $publicKey = $isProd 
            ? $this->configs->getConfig('apiKeyLive', $salesChannelId)
            : $this->configs->getConfig('apiKeySandbox', $salesChannelId);
        $privateKey = $isProd
            ? $this->configs->getConfig('apiPasswordLive', $salesChannelId)
            : $this->configs->getConfig('apiPasswordSandbox', $salesChannelId);
        try {
            $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'public_key' => $publicKey,
                    'private_key' => $privateKey,
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            $token = $data['token'] ?? '';
            $merchantUuid = $data['merchant_uuid'] ?? '';
            if (!is_string($token) || $token === '' || !is_string($merchantUuid) || $merchantUuid === '') {
                throw new SezzleAuthException('Failed to obtain token from Sezzle');
            }
            $expiresAt = $this->checkExpireTimestamp($token) ?? (time() + 55 * 60);
            $this->tokenCache[$salesChannelId] = [
                'token' => $token,
                'merchantUuid' => $merchantUuid,
                'expiresAt' => $expiresAt
            ];
            return [
                'token' => $token,
                'merchantUuid' => $merchantUuid,
            ];
        } catch (GuzzleException $e) {
            throw new SezzleAuthException('Sezzle auth request failed', ['reason' => $e->getMessage()]);
        }
    }
    public function createSession(array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::CREATE_SESSION);
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                    'json' => $body
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle create session failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function getSession(string $sessionToken, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::GET_SESSION);
        $url = str_replace('{sessionToken}', $sessionToken, $endpoint['url']);
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($url, $endpoint) {
            try {
                $response = $this->client->request($endpoint['method'], $url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle get session failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function releaseOrder(string $orderUuid, array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildOrderUrl($orderUuid, self::getEndpoint(self::RELEASE_ORDER));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                    'json' => $body,
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle release order failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function deleteCheckout(string $orderUuid, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildOrderUrl($orderUuid, self::getEndpoint(self::DELETE_CHECKOUT));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle delete checkout failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function createCustomer(array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::CREATE_CUSTOMER);
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                    'json' => $body
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle create customer failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function getCustomer(string $customerUuid, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildCustomerUrl($customerUuid, self::getEndpoint(self::GET_CUSTOMER));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle get customer failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function listCustomers(array $queryParams = [], string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::LIST_CUSTOMERS);
        $url = $endpoint['url'];
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $url) {
            try {
                $response = $this->client->request($endpoint['method'], $url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle list customers failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function deleteCustomer(string $customerUuid, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildCustomerUrl($customerUuid, self::getEndpoint(self::DELETE_CUSTOMER));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle delete customer failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function chargeCustomer(string $customerUuid, array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildCustomerUrl($customerUuid, self::getEndpoint(self::CHARGE_CUSTOMER));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                    'json' => $body,
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle charge customer failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function createWebhook(array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::WEBHOOK_CREATE);
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'json' => $body,
                ]);
                $statusCode = $response->getStatusCode();
                $contents = $response->getBody()->getContents();
                if ($statusCode >= 400) {
                    $errorData = json_decode($contents, true);
                    if ($statusCode === 409) {
                        $errorMessage = is_array($errorData) && isset($errorData[0]['message']) 
                            ? $errorData[0]['message'] 
                            : 'Webhook already exists';
                        throw new SezzleApiException('Webhook already registered: ' . $errorMessage, [
                            'statusCode' => $statusCode,
                            'response' => $errorData,
                            'isDuplicate' => true,
                        ]);
                    }
                    $errorMessage = is_array($errorData) && isset($errorData[0]['message'])
                        ? $errorData[0]['message']
                        : ($errorData['message'] ?? $errorData['error'] ?? 'Unknown error');
                    throw new SezzleApiException('Sezzle webhook creation failed: ' . $errorMessage, [
                        'statusCode' => $statusCode,
                        'response' => $errorData,
                    ]);
                }
                return $contents;
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle create webhook failed: ' . $e->getMessage(), [
                    'reason' => $e->getMessage(),
                ]);
            }
        });
        return json_decode($contents, true);
    }
    public function listWebhooks(string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::WEBHOOK_LIST);
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle list webhooks failed: ' . $e->getMessage(), [
                    'reason' => $e->getMessage(),
                ]);
            }
        });
        return json_decode($contents, true);
    }
    public function testWebhook(array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::WEBHOOK_TEST);
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'json' => $body,
                ]);
                $statusCode = $response->getStatusCode();
                $contents = $response->getBody()->getContents();
                if ($statusCode >= 400) {
                    $errorData = json_decode($contents, true);
                    $errorMessage = is_array($errorData) && isset($errorData[0]['message'])
                        ? $errorData[0]['message']
                        : 'Webhook test failed';
                    throw new SezzleApiException('Webhook test failed: ' . $errorMessage, [
                        'statusCode' => $statusCode,
                        'response' => $errorData,
                    ]);
                }
                return $contents;
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle test webhook failed: ' . $e->getMessage(), [
                    'reason' => $e->getMessage(),
                ]);
            }
        });
        return json_decode($contents, true);
    }
    public function deleteWebhook(string $webhookUuid, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildWebhookUrl($webhookUuid, self::getEndpoint(self::WEBHOOK_DELETE));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle delete webhook failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function createOrder(array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::CREATE_ORDER);
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                    'json' => $body
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle create order failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function getOrder(string $orderUuid, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildOrderUrl($orderUuid, self::getEndpoint(self::GET_ORDER));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle get order failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function captureOrder(string $orderUuid, array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildOrderUrl($orderUuid, self::getEndpoint(self::CAPTURE_ORDER));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                    'json' => $body,
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle capture order failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function refundOrder(string $orderUuid, array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildOrderUrl($orderUuid, self::getEndpoint(self::REFUND_ORDER));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                    'json' => $body,
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle refund order failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function updateCheckout(string $orderUuid, array $body, string $salesChannelId = ''): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::buildOrderUrl($orderUuid, self::getEndpoint(self::UPDATE_CHECKOUT));
        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $merchantUuid, string $requestId) use ($endpoint, $body) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'X-Sezzle-Request-Id' => $requestId,
                    ],
                    'json' => $body,
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new SezzleApiException('Sezzle update checkout failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
        return json_decode($contents, true);
    }
    public function testConnection(?string $salesChannelId = null): bool
    {
        try {
            $this->requestAuthToken($salesChannelId ?? '');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
    private function requestWithAuth(string $salesChannelId, callable $doRequest): string
    {
        $requestId = bin2hex(random_bytes(8));
        $authData = $this->requestAuthToken($salesChannelId);
        $token = $authData['token'];
        $merchantUuid = $authData['merchantUuid'];
        try {
            return $doRequest($token, $merchantUuid, $requestId);
        } catch (SezzleApiException $e) {
            unset($this->tokenCache[$salesChannelId]);
            $authData = $this->requestAuthToken($salesChannelId);
            $token = $authData['token'];
            $merchantUuid = $authData['merchantUuid'];
            return $doRequest($token, $merchantUuid, $requestId);
        }
    }
    private function checkExpireTimestamp(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!is_array($payload) || !isset($payload['exp'])) {
            return null;
        }
        $exp = (int) $payload['exp'];
        return $exp > 0 ? $exp : null;
    }
    private function setupClient(string $salesChannelId = ''): void
    {
        $mode = $this->configs->getConfig('mode', $salesChannelId);
        $isProd = $mode === 'live';
        $baseUrl = $isProd ? EnvironmentUrl::PROD : EnvironmentUrl::SANDBOX;
        if ($this->client === null || $this->currentBaseUrl !== $baseUrl) {
            $this->client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 10.0,
            ]);
            $this->currentBaseUrl = $baseUrl;
        }
    }
}
