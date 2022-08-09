<?php

declare(strict_types=1);

namespace Unirest;

use CoreDesign\Http\HttpConfigurations;

class Configuration implements HttpConfigurations
{
    /**
     * @var string|null
     */
    private $cookie;
    /**
     * @var string|null
     */
    private $cookieFile;
    private $curlOpts = array();
    private $jsonOpts = array();
    private $socketTimeout = 0;
    private $enableRetries = false;       // should we enable retries feature
    private $maxNumberOfRetries = 3;      // total number of allowed retries
    private $retryOnTimeout = false;      // Should we retry on timeout?
    private $retryInterval = 1.0;         // Initial retry interval in seconds, to be increased by backoffFactor
    private $maximumRetryWaitTime = 120;  // maximum retry wait time (commutative)
    private $backoffFactor = 2.0;         // backoff factor to be used to increase retry interval
    private $httpStatusCodesToRetry = array(408, 413, 429, 500, 502, 503, 504, 521, 522, 524);
    private $httpMethodsToRetry = array("GET", "PUT");
    private $verifyPeer = true;
    private $verifyHost = true;
    private $defaultHeaders = array();

    private $auth = array (
        'user' => '',
        'pass' => '',
        'method' => CURLAUTH_BASIC
    );

    private $proxy = array(
        'port' => false,
        'tunnel' => false,
        'address' => false,
        'type' => CURLPROXY_HTTP,
        'auth' => array (
            'user' => '',
            'pass' => '',
            'method' => CURLAUTH_BASIC
        )
    );

    public static function init(): self
    {
        return new self();
    }

    private function __construct()
    {
    }

    /**
     * @param int $socketTimeout Timeout for API calls in seconds.
     * @return Configuration
     */
    public function timeout(int $socketTimeout): self
    {
        $this->socketTimeout = $socketTimeout;
        return $this;
    }

    /**
     * @param bool $enableRetries Whether to enable retries and backoff feature.
     * @return Configuration
     */
    public function enableRetries(bool $enableRetries): self
    {
        $this->enableRetries = $enableRetries;
        return $this;
    }

    /**
     * @param int $maxNumberOfRetries The number of retries to make.
     * @return Configuration
     */
    public function maxNumberOfRetries(int $maxNumberOfRetries): self
    {
        $this->maxNumberOfRetries = $maxNumberOfRetries;
        return $this;
    }

    /**
     * @param bool $retryOnTimeout Whether to retry on timeout
     * @return Configuration
     */
    public function retryOnTimeout(bool $retryOnTimeout): self
    {
        $this->retryOnTimeout = $retryOnTimeout;
        return $this;
    }

    /**
     * @param float $retryInterval The retry time interval between the endpoint calls.
     * @return Configuration
     */
    public function retryInterval(float $retryInterval): self
    {
        $this->retryInterval = $retryInterval;
        return $this;
    }

    /**
     * @param int $maximumRetryWaitTime The maximum wait time in seconds for overall retrying requests.
     * @return Configuration
     */
    public function maximumRetryWaitTime(int $maximumRetryWaitTime): self
    {
        $this->maximumRetryWaitTime = $maximumRetryWaitTime;
        return $this;
    }

    /**
     * @param float $backoffFactor Exponential backoff factor to increase interval between retries.
     * @return Configuration
     */
    public function backoffFactor(float $backoffFactor): self
    {
        $this->backoffFactor = $backoffFactor;
        return $this;
    }

    /**
     * @param int[] $httpStatusCodesToRetry Http status codes to retry against.
     * @return Configuration
     */
    public function httpStatusCodesToRetry(array $httpStatusCodesToRetry): self
    {
        $this->httpStatusCodesToRetry = $httpStatusCodesToRetry;
        return $this;
    }

    /**
     * @param string[] $httpMethodsToRetry Http methods to retry against.
     * @return Configuration
     */
    public function httpMethodsToRetry(array $httpMethodsToRetry): self
    {
        $this->httpMethodsToRetry = $httpMethodsToRetry;
        return $this;
    }

    /**
     * Set JSON decode mode
     *
     * @param bool $assoc When TRUE, returned objects will be converted into associative arrays.
     * @param int $depth User specified recursion depth.
     * @param int $options Bitmask of JSON decode options. Currently only JSON_BIGINT_AS_STRING is supported
     *                     (default is to cast large integers as floats)
     * @return Configuration
     */
    public function jsonOpts(bool $assoc = false, int $depth = 512, int $options = 0): self
    {
        $this->jsonOpts = array($assoc, $depth, $options);
        return $this;
    }

    /**
     * Verify SSL peer
     *
     * @param bool $enabled enable SSL verification, by default is true
     * @return Configuration
     */
    public function verifyPeer(bool $enabled): self
    {
        $this->verifyPeer = $enabled;
        return $this;
    }

    /**
     * Verify SSL host
     *
     * @param bool $enabled enable SSL host verification, by default is true
     * @return Configuration
     */
    public function verifyHost(bool $enabled): self
    {
        $this->verifyHost = $enabled;
        return $this;
    }

    /**
     * Set default headers to send on every request
     *
     * @param array $headers headers array
     * @return Configuration
     */
    public function defaultHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * Set a new default header to send on every request
     *
     * @param string $name header name
     * @param string $value header value
     * @return Configuration
     */
    public function defaultHeader(string $name, string $value): self
    {
        $this->defaultHeaders[$name] = $value;
        return $this;
    }

    /**
     * Set curl options to send on every request
     *
     * @param array $options options array
     * @return Configuration
     */
    public function curlOpts(array $options): self
    {
        $this->curlOpts = array_merge($this->curlOpts, $options);
        return $this;
    }

    /**
     * Set a new default header to send on every request
     *
     * @param string $name header name
     * @param string $value header value
     * @return Configuration
     */
    public function curlOpt(string $name, string $value): self
    {
        $this->curlOpts[$name] = $value;
        return $this;
    }

    /**
     * Set a cookie string for enabling cookie handling
     *
     * @param string $cookie
     * @return Configuration
     */
    public function cookie(string $cookie): self
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * Set a cookie file path for enabling cookie handling
     *
     * $cookieFile must be a correct path with write permission
     *
     * @param string $cookieFile - path to file for saving cookie
     * @return Configuration
     */
    public function cookieFile(string $cookieFile): self
    {
        $this->cookieFile = $cookieFile;
        return $this;
    }

    /**
     * Set authentication method to use
     *
     * @param string $username authentication username
     * @param string $password authentication password
     * @param integer $method authentication method
     * @return Configuration
     */
    public function auth(string $username = '', string $password = '', int $method = CURLAUTH_BASIC): self
    {
        $this->auth['user'] = $username;
        $this->auth['pass'] = $password;
        $this->auth['method'] = $method;
        return $this;
    }

    /**
     * Set proxy to use
     *
     * @param string $address proxy address
     * @param integer $port proxy port
     * @param integer $type (Available options for this are CURLPROXY_HTTP, CURLPROXY_HTTP_1_0 CURLPROXY_SOCKS4,
     *                      CURLPROXY_SOCKS5, CURLPROXY_SOCKS4A and CURLPROXY_SOCKS5_HOSTNAME)
     * @param bool $tunnel enable/disable tunneling
     * @return Configuration
     */
    public function proxy(string $address, int $port = 1080, int $type = CURLPROXY_HTTP, bool $tunnel = false): self
    {
        $this->proxy['type'] = $type;
        $this->proxy['port'] = $port;
        $this->proxy['tunnel'] = $tunnel;
        $this->proxy['address'] = $address;
        return $this;
    }

    /**
     * Set proxy authentication method to use
     *
     * @param string $username authentication username
     * @param string $password authentication password
     * @param integer $method authentication method
     * @return Configuration
     */
    public function proxyAuth(string $username = '', string $password = '', int $method = CURLAUTH_BASIC): self
    {
        $this->proxy['auth']['user'] = $username;
        $this->proxy['auth']['pass'] = $password;
        $this->proxy['auth']['method'] = $method;
        return $this;
    }

    public function getTimeout(): int
    {
        return $this->socketTimeout;
    }

    public function shouldEnableRetries(): bool
    {
        return $this->enableRetries;
    }

    public function getNumberOfRetries(): int
    {
        return $this->maxNumberOfRetries;
    }

    public function getRetryInterval(): float
    {
        return $this->retryInterval;
    }

    public function getBackOffFactor(): float
    {
        return $this->backoffFactor;
    }

    public function getMaximumRetryWaitTime(): int
    {
        return $this->maximumRetryWaitTime;
    }

    public function shouldRetryOnTimeout(): bool
    {
        return $this->retryOnTimeout;
    }

    public function getHttpStatusCodesToRetry(): array
    {
        return $this->httpStatusCodesToRetry;
    }

    public function getHttpMethodsToRetry(): array
    {
        return $this->httpMethodsToRetry;
    }

    public function getCookie(): ?string
    {
        return $this->cookie;
    }

    public function getCookieFile(): ?string
    {
        return $this->cookieFile;
    }

    public function getCurlOpts(): array
    {
        return $this->curlOpts;
    }

    public function getJsonOpts(): array
    {
        return $this->jsonOpts;
    }

    public function shouldVerifyPeer(): bool
    {
        return $this->verifyPeer;
    }

    public function shouldVerifyHost(): bool
    {
        return $this->verifyHost;
    }

    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    public function getAuth(): array
    {
        return $this->auth;
    }

    public function getProxy(): array
    {
        return $this->proxy;
    }
}
