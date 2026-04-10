<?php

namespace SabitAhmad\SteadFast\Services;

use Exception;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Psr\Log\LoggerInterface;
use SabitAhmad\SteadFast\DTO\FraudCheckResponse;
use SabitAhmad\SteadFast\Exceptions\SteadfastException;

class SteadfastFraudChecker
{
    public function __construct(
        protected Factory $http,
        protected SteadfastLogger $logger,
        protected LoggerInterface $fallbackLogger,
        protected array $config = []
    ) {
        $this->config = $config ?: config('steadfast');
    }

    /**
     * @throws SteadfastException
     */
    public function check(string $phoneNumber): FraudCheckResponse
    {
        if (! $this->isEnabled()) {
            throw SteadfastException::fraudCheckerNotEnabled();
        }

        $phoneNumber = $this->validatePhoneNumber($phoneNumber);
        $loginCookies = null;

        try {
            $loginPageResponse = $this->client()->get('/login');

            if (! $loginPageResponse->successful()) {
                throw SteadfastException::fraudCheckerError('Failed to access Steadfast login page');
            }

            $csrfToken = $this->extractCsrfToken($loginPageResponse->body());

            if (! $csrfToken) {
                throw SteadfastException::fraudCheckerError('CSRF token not found on login page');
            }

            $cookies = $this->convertCookies($loginPageResponse->cookies());

            $loginResponse = $this->client()
                ->withCookies($cookies, 'steadfast.com.bd')
                ->asForm()
                ->post('/login', [
                    '_token' => $csrfToken,
                    'email' => $this->config['fraud_checker']['email'],
                    'password' => $this->config['fraud_checker']['password'],
                ]);

            if (! ($loginResponse->successful() || $loginResponse->redirect())) {
                $this->log($phoneNumber, null, 'Login failed');

                throw SteadfastException::fraudCheckerError('Login to Steadfast failed. Please check your credentials.');
            }

            $loginCookies = $this->convertCookies($loginResponse->cookies());
            $fraudResponse = $this->client()
                ->withCookies($loginCookies, 'steadfast.com.bd')
                ->get("/user/frauds/check/{$phoneNumber}");

            if (! $fraudResponse->successful()) {
                throw SteadfastException::fraudCheckerError('Failed to fetch fraud data from Steadfast');
            }

            $fraudData = $fraudResponse->collect()->toArray();

            $result = [
                'success' => $fraudData['total_delivered'] ?? 0,
                'cancel' => $fraudData['total_cancelled'] ?? 0,
                'total' => ($fraudData['total_delivered'] ?? 0) + ($fraudData['total_cancelled'] ?? 0),
                'phone_number' => $phoneNumber,
            ];

            $this->log($phoneNumber, $result);

            return FraudCheckResponse::fromArray($result);
        } catch (SteadfastException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->log($phoneNumber, null, $e->getMessage());

            throw SteadfastException::fraudCheckerError($e->getMessage(), $e);
        } finally {
            if (is_array($loginCookies)) {
                $this->performLogout($loginCookies);
            }
        }
    }

    private function isEnabled(): bool
    {
        if (! ($this->config['fraud_checker']['enabled'] ?? false)) {
            return false;
        }

        return ! empty($this->config['fraud_checker']['email'])
            && ! empty($this->config['fraud_checker']['password']);
    }

    /**
     * @throws SteadfastException
     */
    private function validatePhoneNumber(string $phoneNumber): string
    {
        $originalPhone = $phoneNumber;
        $phoneNumber = preg_replace('/[\s\-]+/', '', $phoneNumber);
        $phoneNumber = preg_replace('/^(\+?88)/', '', $phoneNumber);

        if (! preg_match('/^01[3-9][0-9]{8}$/', $phoneNumber)) {
            throw SteadfastException::invalidPhoneNumber($originalPhone);
        }

        return $phoneNumber;
    }

    private function extractCsrfToken(string $html): ?string
    {
        if (preg_match('/<input type="hidden" name="_token" value="(.*?)"/', $html, $matches)) {
            return $matches[1];
        }

        if (preg_match('/<meta name="csrf-token" content="(.*?)"/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function convertCookies($cookieJar): array
    {
        $cookies = [];

        foreach ($cookieJar->toArray() as $cookie) {
            $cookies[$cookie['Name']] = $cookie['Value'];
        }

        return $cookies;
    }

    private function performLogout(array $cookies): void
    {
        try {
            $logoutPageResponse = $this->client()
                ->withCookies($cookies, 'steadfast.com.bd')
                ->get('/user/frauds/check');

            if (! $logoutPageResponse->successful()) {
                return;
            }

            $csrfToken = $this->extractCsrfToken($logoutPageResponse->body());

            if (! $csrfToken) {
                return;
            }

            $this->client()
                ->withCookies($cookies, 'steadfast.com.bd')
                ->asForm()
                ->post('/logout', [
                    '_token' => $csrfToken,
                ]);
        } catch (Exception $e) {
            $this->fallbackLogger->warning('Steadfast fraud checker logout failed: '.$e->getMessage());
        }
    }

    private function client(): PendingRequest
    {
        return $this->http
            ->baseUrl('https://steadfast.com.bd')
            ->timeout($this->config['timeout'] ?? 30)
            ->connectTimeout($this->config['connect_timeout'] ?? 10)
            ->retry(2, 250);
    }

    private function log(string $phoneNumber, ?array $result = null, ?string $error = null): void
    {
        $this->logger->log([
            'type' => 'fraud_check',
            'request' => ['phone_number' => $phoneNumber],
            'response' => $result,
            'endpoint' => '/user/frauds/check',
            'status_code' => $error ? 500 : 200,
            'error' => $error,
        ]);
    }
}
