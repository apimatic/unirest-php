<?php

declare(strict_types=1);

namespace Unirest;

use CoreDesign\Core\Request\RequestInterface;
use CoreDesign\Core\Request\RequestMethod;
use CoreDesign\Core\Response\ResponseInterface;
use CoreDesign\Http\HttpClientInterface;
use CoreDesign\Http\HttpConfigurations;
use CoreDesign\Http\RetryOption;
use DateTime;
use Unirest\Request\Request;

class HttpClient implements HttpClientInterface
{
    private $handle = null;
    protected $totalNumberOfConnections = 0;

    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @param HttpConfigurations|null $configurations
     */
    public function __construct(?HttpConfigurations $configurations = null)
    {
        if ($configurations instanceof Configuration) {
            $this->config = $configurations;
            return;
        }
        $this->config = Configuration::init();
        if (is_null($configurations)) {
            return;
        }
        $this->config = $this->config
            ->timeout($configurations->getTimeout())
            ->enableRetries($configurations->shouldEnableRetries())
            ->maxNumberOfRetries($configurations->getNumberOfRetries())
            ->retryOnTimeout($configurations->shouldRetryOnTimeout())
            ->retryInterval($configurations->getRetryInterval())
            ->maximumRetryWaitTime($configurations->getMaximumRetryWaitTime())
            ->backoffFactor($configurations->getBackOffFactor())
            ->httpStatusCodesToRetry($configurations->getHttpStatusCodesToRetry())
            ->httpMethodsToRetry($configurations->getHttpMethodsToRetry());
    }

    public function execute(RequestInterface $request): ResponseInterface
    {
        if ($this->handle == null) {
            $this->initializeHandle();
        } else {
            curl_reset($this->handle);
        }
        $this->setCurlOptions($this->handle, $request);
        $retryCount      = 0;    // current retry count
        $waitTime        = 0.0;  // wait time in secs before current api call
        $allowedWaitTime = $this->config->getMaximumRetryWaitTime(); // remaining allowed wait time in seconds
        $httpCode        = null;
        $headers         = array();
        do {
            // If Retrying i.e. retryCount >= 1
            if ($retryCount > 0) {
                $this->sleep($waitTime);
                // calculate remaining allowed wait Time
                $allowedWaitTime -= $waitTime;
            }

            // Execution of api call
            $response  = curl_exec($this->handle);
            $error     = curl_error($this->handle);
            $info      = $this->getInfo();
            if (empty($error)) {
                $header_size = $info['header_size'];
                $httpCode    = $info['http_code'];
                $headers     = $this->parseHeaders(substr($response, 0, $header_size));
            }

            if ($this->shouldRetryRequest($request)) {
                // calculate wait time for retry, and should not retry when wait time becomes 0
                $waitTime = $this->getRetryWaitTime($httpCode, $headers, $error, $allowedWaitTime, $retryCount);
                $retryCount++;
            }
        } while ($waitTime > 0.0);

        if (!empty($error) || !isset($header_size, $headers, $httpCode)) {
            throw $request->toApiException($error);
        }
        // get response body
        $body = substr($response, $header_size);

        $this->totalNumberOfConnections += $this->getInfo(CURLINFO_NUM_CONNECTS);

        return new Response($httpCode, $body, $headers, $this->config->getJsonOpts());
    }

    protected function initializeHandle()
    {
        $this->handle = curl_init();
        $this->totalNumberOfConnections = 0;
    }

    protected function setCurlOptions($handle, RequestInterface $request): void
    {
        $queryUrl = $request->getQueryUrl();
        if ($request->getHttpMethod() !== RequestMethod::GET) {
            if ($request->getHttpMethod() === RequestMethod::POST) {
                curl_setopt($handle, CURLOPT_POST, true);
            } else {
                if ($request->getHttpMethod() === RequestMethod::HEAD) {
                    curl_setopt($handle, CURLOPT_NOBODY, true);
                }
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, strtoupper($request->getHttpMethod()));
            }
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->getBody());
        } elseif (is_array($request->getBody())) {
            if (strpos($queryUrl, '?') !== false) {
                $queryUrl .= '&';
            } else {
                $queryUrl .= '?';
            }
            $queryUrl .= urldecode(http_build_query(Request::buildHTTPCurlQuery($request->getBody())));
        }

        $curl_base_options = [
            CURLOPT_URL => $queryUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTPHEADER => $this->getFormattedHeaders($request->getHeaders()),
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => $this->config->shouldVerifyPeer(),
            // CURLOPT_SSL_VERIFYHOST accepts only 0 (false) or 2 (true). Future versions of libcurl
            // will treat values 1 and 2 as equals
            CURLOPT_SSL_VERIFYHOST => $this->config->shouldVerifyHost() === false ? 0 : 2,
            // If an empty string, '', is set, a header containing all supported encoding types is sent
            CURLOPT_ENCODING => ''
        ];

        curl_setopt_array($handle, $this->mergeCurlOptions($curl_base_options, $this->config->getCurlOpts()));

        if ($this->config->getTimeout() > 0) {
            curl_setopt($handle, CURLOPT_TIMEOUT, $this->config->getTimeout());
        }

        if ($this->config->getCookie() !== null) {
            curl_setopt($handle, CURLOPT_COOKIE, $this->config->getCookie());
        }

        if ($this->config->getCookieFile() !== null) {
            curl_setopt($handle, CURLOPT_COOKIEFILE, $this->config->getCookieFile());
            curl_setopt($handle, CURLOPT_COOKIEJAR, $this->config->getCookieFile());
        }

        if (!empty($this->config->getAuth()['user'])) {
            curl_setopt_array($handle, array(
                CURLOPT_HTTPAUTH    => $this->config->getAuth()['method'],
                CURLOPT_USERPWD     => $this->config->getAuth()['user'] . ':' . $this->config->getAuth()['pass']
            ));
        }

        if ($this->config->getProxy()['address'] !== false) {
            $proxy = $this->config->getProxy();
            curl_setopt_array($handle, array(
                CURLOPT_PROXYTYPE       => $proxy['type'],
                CURLOPT_PROXY           => $proxy['address'],
                CURLOPT_PROXYPORT       => $proxy['port'],
                CURLOPT_HTTPPROXYTUNNEL => $proxy['tunnel'],
                CURLOPT_PROXYAUTH       => $proxy['auth']['method'],
                CURLOPT_PROXYUSERPWD    => $proxy['auth']['user'] . ':' . $proxy['auth']['pass']
            ));
        }
    }

    /**
     * Halts program flow for given number of seconds, and microseconds
     *
     * @param float $seconds Seconds with upto 6 decimal places, here decimal part will be converted into microseconds
     */
    protected function sleep(float $seconds)
    {
        $secs = (int) $seconds;
        // the fraction part of the $seconds will always be less than 1 sec, extracting micro seconds
        $microSecs  = (int) (($seconds - $secs) * 1000000);
        sleep($secs);
        usleep($microSecs);
    }

    /**
     * Check if retries are enabled at global and request level,
     * also check whitelisted httpMethods, if retries are only enabled globally.
     *
     * @param RequestInterface $request
     * @return bool
     */
    protected function shouldRetryRequest(RequestInterface $request): bool
    {
        switch ($request->getRetryOption()) {
            case RetryOption::ENABLE_RETRY:
                return $this->config->shouldEnableRetries();
            case RetryOption::USE_GLOBAL_SETTINGS:
                return $this->config->shouldEnableRetries()
                    && in_array($request->getHttpMethod(), $this->config->getHttpMethodsToRetry());
            case RetryOption::DISABLE_RETRY:
                return false;
        }
        return false;
    }

    /**
     * Generate calculated wait time, and 0.0 if api should not be retried
     *
     * @param $httpCode        int           Http status code in response
     * @param $headers         array         Response headers
     * @param $error           string        Error returned by server
     * @param $allowedWaitTime int           Remaining allowed wait time
     * @param $retryCount      int           Attempt number
     * @return float  Wait time before sending the next apiCall
     */
    protected function getRetryWaitTime(
        int $httpCode,
        array $headers,
        string $error,
        int $allowedWaitTime,
        int $retryCount
    ): float {
        $retryWaitTime = 0.0;
        $retry_after   = 0;
        if (empty($error)) {
            // Successful apiCall with some status code or with Retry-After header
            $headers_lower_keys = array_change_key_case($headers);
            $retry_after_val = key_exists('retry-after', $headers_lower_keys) ?
                $headers_lower_keys['retry-after'] : null;
            $retry_after = $this->getRetryAfterInSeconds($retry_after_val);
            $retry       = isset($retry_after_val) || in_array($httpCode, $this->config->getHttpStatusCodesToRetry());
        } else {
            $retry = $this->config->shouldRetryOnTimeout() && curl_errno($this->handle) == CURLE_OPERATION_TIMEDOUT;
        }
        // Calculate wait time only if max number of retries are not already attempted
        if ($retry && $retryCount < $this->config->getNumberOfRetries()) {
            // noise between 0 and 0.1 secs upto 6 decimal places
            $noise       = rand(0, 100000) / 1000000;
            // calculate wait time with exponential backoff and noise in seconds
            $waitTime    = ($this->config->getRetryInterval() * pow($this->config->getBackOffFactor(), $retryCount))
                + $noise;
            // select maximum of waitTime and retry_after
            $waitTime    = floatval(max($waitTime, $retry_after));
            if ($waitTime <= $allowedWaitTime) {
                // set retry wait time for next api call, only if its under allowed time
                $retryWaitTime = $waitTime;
            }
        }
        return $retryWaitTime;
    }

    /**
     * Returns the number of seconds by extracting them from $retry-after header
     *
     * @param $retry_after mixed could be some numeric value in seconds, or it could be RFC1123
     *                     formatted datetime string
     * @return int Number of seconds specified by retry-after param
     */
    protected function getRetryAfterInSeconds($retry_after): int
    {
        if (isset($retry_after)) {
            if (is_numeric($retry_after)) {
                return (int)$retry_after; // if value is already in seconds
            } else {
                // if value is a date time string in format RFC1123
                $retry_after_date = DateTime::createFromFormat('D, d M Y H:i:s O', $retry_after);
                // retry_after_date could either be undefined, or false, or a DateTime object (if valid format string)
                return !$retry_after_date ? 0 : $retry_after_date->getTimestamp() - time();
            }
        }
        return 0;
    }

    /**
     * if PECL_HTTP is not available use a fallback function
     *
     * thanks to ricardovermeltfoort@gmail.com
     * http://php.net/manual/en/function.http-parse-headers.php#112986
     * @param string $raw_headers raw headers
     * @return array
     */
    private function parseHeaders(string $raw_headers): array
    {
        if (function_exists('http_parse_headers')) {
            return http_parse_headers($raw_headers);
        } else {
            $key = '';
            $headers = array();

            foreach (explode("\n", $raw_headers) as $i => $h) {
                $h = explode(':', $h, 2);

                if (isset($h[1])) {
                    if (!isset($headers[$h[0]])) {
                        $headers[$h[0]] = trim($h[1]);
                    } elseif (is_array($headers[$h[0]])) {
                        $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
                    } else {
                        $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
                    }

                    $key = $h[0];
                } else {
                    if (substr($h[0], 0, 1) == "\t") {
                        $headers[$key] .= "\r\n\t".trim($h[0]);
                    } elseif (!$key) {
                        $headers[0] = trim($h[0]);
                    }
                }
            }

            return $headers;
        }
    }

    public function getInfo($opt = false)
    {
        if ($opt) {
            $info = curl_getinfo($this->handle, $opt);
        } else {
            $info = curl_getinfo($this->handle);
        }

        return $info;
    }

    protected function getFormattedHeaders(array $headers): array
    {
        $formattedHeaders = array();

        $combinedHeaders = array_change_key_case(array_merge($this->config->getDefaultHeaders(), $headers));

        foreach ($combinedHeaders as $key => $val) {
            $formattedHeaders[] = $this->getHeaderString($key, $val);
        }

        if (!array_key_exists('user-agent', $combinedHeaders)) {
            $formattedHeaders[] = 'user-agent: unirest-php/2.0';
        }

        if (!array_key_exists('expect', $combinedHeaders)) {
            $formattedHeaders[] = 'expect:';
        }

        return $formattedHeaders;
    }

    private function getHeaderString(string $key, $val): string
    {
        $key = trim(strtolower($key));
        return $key . ': ' . $val;
    }

    private function mergeCurlOptions(array &$existing_options, array $new_options): array
    {
        $existing_options = $new_options + $existing_options;
        return $existing_options;
    }
}
