<?php

namespace Unirest;

class Request
{
    private static $cookie = null;
    private static $cookieFile = null;
    private static $curlOpts = array();
    private static $handle = null;
    private static $jsonOpts = array();
    private static $socketTimeout = null;
    private static $enableRetries = false;       // should we enable retries feature
    private static $maxNumberOfRetries = 3;      // total number of allowed retries
    private static $retryOnTimeout = false;      // Should we retry on timeout?
    private static $retryInterval = 1.0;         // Initial retry interval in seconds, to be increased by backoffFactor
    private static $maximumRetryWaitTime = 120;  // maximum retry wait time (commutative)
    private static $backoffFactor = 2.0;         // backoff factor to be used to increase retry interval
    private static $httpStatusCodesToRetry = array(408, 413, 429, 500, 502, 503, 504, 521, 522, 524);
    private static $httpMethodsToRetry = array("GET", "PUT");
    private static $overrideRetryForNextRequest = OverrideRetry::USE_GLOBAL_SETTINGS;
    private static $verifyPeer = true;
    private static $verifyHost = true;
    private static $defaultHeaders = array();

    private static $auth = array (
        'user' => '',
        'pass' => '',
        'method' => CURLAUTH_BASIC
    );

    private static $proxy = array(
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

    protected static $totalNumberOfConnections = 0;

    /**
     * Set JSON decode mode
     *
     * @param bool $assoc When TRUE, returned objects will be converted into associative arrays.
     * @param integer $depth User specified recursion depth.
     * @param integer $options Bitmask of JSON decode options. Currently only JSON_BIGINT_AS_STRING is supported (default is to cast large integers as floats)
     * @return array
     */
    public static function jsonOpts($assoc = false, $depth = 512, $options = 0)
    {
        return self::$jsonOpts = array($assoc, $depth, $options);
    }

    /**
     * Verify SSL peer
     *
     * @param bool $enabled enable SSL verification, by default is true
     * @return bool
     */
    public static function verifyPeer($enabled)
    {
        return self::$verifyPeer = $enabled;
    }

    /**
     * Verify SSL host
     *
     * @param bool $enabled enable SSL host verification, by default is true
     * @return bool
     */
    public static function verifyHost($enabled)
    {
        return self::$verifyHost = $enabled;
    }

    /**
     * Set a timeout
     *
     * @param integer $seconds timeout value in seconds
     * @return integer
     */
    public static function timeout($seconds)
    {
        return self::$socketTimeout = $seconds;
    }

    /**
     * Should we enable retries feature
     *
     * @param bool $enableRetries
     * @return bool
     */
    public static function enableRetries($enableRetries)
    {
        return self::$enableRetries = $enableRetries;
    }

    /**
     * Total number of allowed retries
     *
     * @param integer $maxNumberOfRetries
     * @return integer
     */
    public static function maxNumberOfRetries($maxNumberOfRetries)
    {
        return self::$maxNumberOfRetries = $maxNumberOfRetries;
    }

    /**
     * Should we retry on timeout
     *
     * @param bool $retryOnTimeout
     * @return bool
     */
    public static function retryOnTimeout($retryOnTimeout)
    {
        return self::$retryOnTimeout = $retryOnTimeout;
    }

    /**
     * Initial retry interval in seconds, to be increased by backoffFactor
     *
     * @param float $retryInterval
     * @return float
     */
    public static function retryInterval($retryInterval)
    {
        return self::$retryInterval = $retryInterval;
    }

    /**
     * Maximum retry wait time
     *
     * @param integer $maximumRetryWaitTime
     * @return integer
     */
    public static function maximumRetryWaitTime($maximumRetryWaitTime)
    {
        return self::$maximumRetryWaitTime = $maximumRetryWaitTime;
    }

    /**
     * Backoff factor to be used to increase retry interval
     *
     * @param float $backoffFactor
     * @return float
     */
    public static function backoffFactor($backoffFactor)
    {
        return self::$backoffFactor = $backoffFactor;
    }

    /**
     * Http status codes to retry against
     *
     * @param integer[] $httpStatusCodesToRetry
     * @return integer[]
     */
    public static function httpStatusCodesToRetry($httpStatusCodesToRetry)
    {
        return self::$httpStatusCodesToRetry = $httpStatusCodesToRetry;
    }

    /**
     * Http methods to retry against
     *
     * @param string[] $httpMethodsToRetry
     * @return string[]
     */
    public static function httpMethodsToRetry($httpMethodsToRetry)
    {
        return self::$httpMethodsToRetry = $httpMethodsToRetry;
    }

    /**
     * Enable or disable retries for next request, ignoring httpMethods whitelist.
     *
     * @param string $overrideRetryForNextRequest
     * @return string
     */
    public static function overrideRetryForNextRequest($overrideRetryForNextRequest)
    {
        return self::$overrideRetryForNextRequest = $overrideRetryForNextRequest;
    }

    /**
     * Set default headers to send on every request
     *
     * @param array $headers headers array
     * @return array
     */
    public static function defaultHeaders($headers)
    {
        return self::$defaultHeaders = array_merge(self::$defaultHeaders, $headers);
    }

    /**
     * Set a new default header to send on every request
     *
     * @param string $name header name
     * @param string $value header value
     * @return string
     */
    public static function defaultHeader($name, $value)
    {
        return self::$defaultHeaders[$name] = $value;
    }

    /**
     * Clear all the default headers
     */
    public static function clearDefaultHeaders()
    {
        return self::$defaultHeaders = array();
    }

    /**
     * Set curl options to send on every request
     *
     * @param array $options options array
     * @return array
     */
    public static function curlOpts($options)
    {
        return self::mergeCurlOptions(self::$curlOpts, $options);
    }

    /**
     * Set a new default header to send on every request
     *
     * @param string $name header name
     * @param string $value header value
     * @return string
     */
    public static function curlOpt($name, $value)
    {
        return self::$curlOpts[$name] = $value;
    }

    /**
     * Clear all curl opts
     */
    public static function clearCurlOpts()
    {
        return self::$curlOpts = array();
    }

    /**
     * Set a Mashape key to send on every request as a header
     * Obtain your Mashape key by browsing one of your Mashape applications on https://www.mashape.com
     *
     * Note: Mashape provides 2 keys for each application: a 'Testing' and a 'Production' one.
     *       Be aware of which key you are using and do not share your Production key.
     *
     * @param string $key Mashape key
     * @return string
     */
    public static function setMashapeKey($key)
    {
        return self::defaultHeader('X-Mashape-Key', $key);
    }

    /**
     * Set a cookie string for enabling cookie handling
     *
     * @param string $cookie
     */
    public static function cookie($cookie)
    {
        self::$cookie = $cookie;
    }

    /**
     * Set a cookie file path for enabling cookie handling
     *
     * $cookieFile must be a correct path with write permission
     *
     * @param string $cookieFile - path to file for saving cookie
     */
    public static function cookieFile($cookieFile)
    {
        self::$cookieFile = $cookieFile;
    }

    /**
     * Set authentication method to use
     *
     * @param string $username authentication username
     * @param string $password authentication password
     * @param integer $method authentication method
     */
    public static function auth($username = '', $password = '', $method = CURLAUTH_BASIC)
    {
        self::$auth['user'] = $username;
        self::$auth['pass'] = $password;
        self::$auth['method'] = $method;
    }

    /**
     * Set proxy to use
     *
     * @param string $address proxy address
     * @param integer $port proxy port
     * @param integer $type (Available options for this are CURLPROXY_HTTP, CURLPROXY_HTTP_1_0 CURLPROXY_SOCKS4, CURLPROXY_SOCKS5, CURLPROXY_SOCKS4A and CURLPROXY_SOCKS5_HOSTNAME)
     * @param bool $tunnel enable/disable tunneling
     */
    public static function proxy($address, $port = 1080, $type = CURLPROXY_HTTP, $tunnel = false)
    {
        self::$proxy['type'] = $type;
        self::$proxy['port'] = $port;
        self::$proxy['tunnel'] = $tunnel;
        self::$proxy['address'] = $address;
    }

    /**
     * Set proxy authentication method to use
     *
     * @param string $username authentication username
     * @param string $password authentication password
     * @param integer $method authentication method
     */
    public static function proxyAuth($username = '', $password = '', $method = CURLAUTH_BASIC)
    {
        self::$proxy['auth']['user'] = $username;
        self::$proxy['auth']['pass'] = $password;
        self::$proxy['auth']['method'] = $method;
    }

    /**
     * Send a GET request to a URL
     *
     * @param string $url URL to send the GET request to
     * @param array $headers additional headers to send
     * @param mixed $parameters parameters to send in the querystring
     * @param string $username Authentication username (deprecated)
     * @param string $password Authentication password (deprecated)
     * @return Response
     */
    public static function get($url, $headers = array(), $parameters = null, $username = null, $password = null)
    {
        return self::send(Method::GET, $url, $parameters, $headers, $username, $password);
    }

    /**
     * Send a HEAD request to a URL
     * @param string $url URL to send the HEAD request to
     * @param array $headers additional headers to send
     * @param mixed $parameters parameters to send in the querystring
     * @param string $username Basic Authentication username (deprecated)
     * @param string $password Basic Authentication password (deprecated)
     * @return Response
     */
    public static function head($url, $headers = array(), $parameters = null, $username = null, $password = null)
    {
        return self::send(Method::HEAD, $url, $parameters, $headers, $username, $password);
    }

    /**
     * Send a OPTIONS request to a URL
     * @param string $url URL to send the OPTIONS request to
     * @param array $headers additional headers to send
     * @param mixed $parameters parameters to send in the querystring
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return Response
     */
    public static function options($url, $headers = array(), $parameters = null, $username = null, $password = null)
    {
        return self::send(Method::OPTIONS, $url, $parameters, $headers, $username, $password);
    }

    /**
     * Send a CONNECT request to a URL
     * @param string $url URL to send the CONNECT request to
     * @param array $headers additional headers to send
     * @param mixed $parameters parameters to send in the querystring
     * @param string $username Basic Authentication username (deprecated)
     * @param string $password Basic Authentication password (deprecated)
     * @return Response
     */
    public static function connect($url, $headers = array(), $parameters = null, $username = null, $password = null)
    {
        return self::send(Method::CONNECT, $url, $parameters, $headers, $username, $password);
    }

    /**
     * Send POST request to a URL
     * @param string $url URL to send the POST request to
     * @param array $headers additional headers to send
     * @param mixed $body POST body data
     * @param string $username Basic Authentication username (deprecated)
     * @param string $password Basic Authentication password (deprecated)
     * @return Response response
     */
    public static function post($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return self::send(Method::POST, $url, $body, $headers, $username, $password);
    }

    /**
     * Send DELETE request to a URL
     * @param string $url URL to send the DELETE request to
     * @param array $headers additional headers to send
     * @param mixed $body DELETE body data
     * @param string $username Basic Authentication username (deprecated)
     * @param string $password Basic Authentication password (deprecated)
     * @return Response
     */
    public static function delete($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return self::send(Method::DELETE, $url, $body, $headers, $username, $password);
    }

    /**
     * Send PUT request to a URL
     * @param string $url URL to send the PUT request to
     * @param array $headers additional headers to send
     * @param mixed $body PUT body data
     * @param string $username Basic Authentication username (deprecated)
     * @param string $password Basic Authentication password (deprecated)
     * @return Response
     */
    public static function put($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return self::send(Method::PUT, $url, $body, $headers, $username, $password);
    }

    /**
     * Send PATCH request to a URL
     * @param string $url URL to send the PATCH request to
     * @param array $headers additional headers to send
     * @param mixed $body PATCH body data
     * @param string $username Basic Authentication username (deprecated)
     * @param string $password Basic Authentication password (deprecated)
     * @return Response
     */
    public static function patch($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return self::send(Method::PATCH, $url, $body, $headers, $username, $password);
    }

    /**
     * Send TRACE request to a URL
     * @param string $url URL to send the TRACE request to
     * @param array $headers additional headers to send
     * @param mixed $body TRACE body data
     * @param string $username Basic Authentication username (deprecated)
     * @param string $password Basic Authentication password (deprecated)
     * @return Response
     */
    public static function trace($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return self::send(Method::TRACE, $url, $body, $headers, $username, $password);
    }

    /**
     * This function is useful for serializing multidimensional arrays, and avoid getting
     * the 'Array to string conversion' notice
     * @param array|object $data array to flatten.
     * @param bool|string $parent parent key or false if no parent
     * @return array
     */
    public static function buildHTTPCurlQuery($data, $parent = false)
    {
        $result = array();

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        foreach ($data as $key => $value) {
            if ($parent) {
                $new_key = sprintf('%s[%s]', $parent, $key);
            } else {
                $new_key = $key;
            }

            if (!$value instanceof \CURLFile and (is_array($value) or is_object($value))) {
                $result = array_merge($result, self::buildHTTPCurlQuery($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }

        return $result;
    }

    protected static function initializeHandle()
    {
        self::$handle = curl_init();
        self::$totalNumberOfConnections = 0;
    }

    /**
     * Send a cURL request
     * @param \Unirest\Method|string $method HTTP method to use
     * @param string $url URL to send the request to
     * @param mixed $body request body
     * @param array $headers additional headers to send
     * @param string $username Authentication username (deprecated)
     * @param string $password Authentication password (deprecated)
     * @throws \Unirest\Exception if a cURL error occurs
     * @return Response
     */
    public static function send($method, $url, $body = null, $headers = array(), $username = null, $password = null)
    {
        if (self::$handle == null) {
            self::initializeHandle();
        } else {
            curl_reset(self::$handle);
        }

        if ($method !== Method::GET) {
            if ($method === Method::POST) {
                curl_setopt(self::$handle, CURLOPT_POST, true);
            } else {
                if ($method === Method::HEAD) {
                    curl_setopt(self::$handle, CURLOPT_NOBODY, true);
                }
                curl_setopt(self::$handle, CURLOPT_CUSTOMREQUEST, $method);
            }

            curl_setopt(self::$handle, CURLOPT_POSTFIELDS, $body);
        } elseif (is_array($body)) {
            if (strpos($url, '?') !== false) {
                $url .= '&';
            } else {
                $url .= '?';
            }

            $url .= urldecode(http_build_query(self::buildHTTPCurlQuery($body)));
        }

        $curl_base_options = [
            CURLOPT_URL => self::validateUrl($url),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTPHEADER => self::getFormattedHeaders($headers),
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => self::$verifyPeer,
            //CURLOPT_SSL_VERIFYHOST accepts only 0 (false) or 2 (true). Future versions of libcurl will treat values 1 and 2 as equals
            CURLOPT_SSL_VERIFYHOST => self::$verifyHost === false ? 0 : 2,
            // If an empty string, '', is set, a header containing all supported encoding types is sent
            CURLOPT_ENCODING => ''
        ];

        curl_setopt_array(self::$handle, self::mergeCurlOptions($curl_base_options, self::$curlOpts));

        if (self::$socketTimeout !== null) {
            curl_setopt(self::$handle, CURLOPT_TIMEOUT, self::$socketTimeout);
        }

        if (self::$cookie) {
            curl_setopt(self::$handle, CURLOPT_COOKIE, self::$cookie);
        }

        if (self::$cookieFile) {
            curl_setopt(self::$handle, CURLOPT_COOKIEFILE, self::$cookieFile);
            curl_setopt(self::$handle, CURLOPT_COOKIEJAR, self::$cookieFile);
        }

        // supporting deprecated http auth method
        if (!empty($username)) {
            curl_setopt_array(self::$handle, array(
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $username . ':' . $password
            ));
        }

        if (!empty(self::$auth['user'])) {
            curl_setopt_array(self::$handle, array(
                CURLOPT_HTTPAUTH    => self::$auth['method'],
                CURLOPT_USERPWD     => self::$auth['user'] . ':' . self::$auth['pass']
            ));
        }

        if (self::$proxy['address'] !== false) {
            curl_setopt_array(self::$handle, array(
                CURLOPT_PROXYTYPE       => self::$proxy['type'],
                CURLOPT_PROXY           => self::$proxy['address'],
                CURLOPT_PROXYPORT       => self::$proxy['port'],
                CURLOPT_HTTPPROXYTUNNEL => self::$proxy['tunnel'],
                CURLOPT_PROXYAUTH       => self::$proxy['auth']['method'],
                CURLOPT_PROXYUSERPWD    => self::$proxy['auth']['user'] . ':' . self::$proxy['auth']['pass']
            ));
        }

        $retryCount      = 0;                           // current retry count
        $waitTime        = 0.0;                         // wait time in secs before current api call
        $allowedWaitTime = self::$maximumRetryWaitTime; // remaining allowed wait time in seconds
        $httpCode        = null;
        $headers         = array();
        do {
            // If Retrying i.e. retryCount >= 1
            if ($retryCount > 0) {
                self::sleep($waitTime);
                // calculate remaining allowed wait Time
                $allowedWaitTime -= $waitTime;
            }

            // Execution of api call
            $response  = curl_exec(self::$handle);
            $error     = curl_error(self::$handle);
            $info      = self::getInfo();
            if (!$error) {
                $header_size = $info['header_size'];
                $httpCode    = $info['http_code'];
                $headers     = self::parseHeaders(substr($response, 0, $header_size));
            }

            if (self::shouldRetryRequest($method)) {
                // calculate wait time for retry, and should not retry when wait time becomes 0
                $waitTime = self::getRetryWaitTime($httpCode, $headers, $error, $allowedWaitTime, $retryCount);
                $retryCount++;
            }
        } while ($waitTime > 0.0);

        // reset request level retries check
        self::$overrideRetryForNextRequest = OverrideRetry::USE_GLOBAL_SETTINGS;

        if ($error) {
            throw new Exception($error);
        }
        // get response body
        $body = substr($response, $header_size);

        self::$totalNumberOfConnections += curl_getinfo(self::$handle, CURLINFO_NUM_CONNECTS);

        return new Response($httpCode, $body, $headers, self::$jsonOpts);
    }

    /**
     * Halts program flow for given number of seconds, and microseconds
     *
     * @param $seconds float seconds with upto 6 decimal places, here decimal part will be converted into microseconds
     */
    private static function sleep($seconds)
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
     * @param $method string|Method HttpMethod of request
     * @return bool
     */
    private static function shouldRetryRequest($method)
    {
        switch (self::$overrideRetryForNextRequest) {
            case OverrideRetry::ENABLE_RETRY:
                return self::$enableRetries;
            case OverrideRetry::USE_GLOBAL_SETTINGS:
                return self::$enableRetries && in_array($method, self::$httpMethodsToRetry);
            case OverrideRetry::DISABLE_RETRY:
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
    private static function getRetryWaitTime($httpCode, $headers, $error, $allowedWaitTime, $retryCount)
    {
        $retryWaitTime = 0.0;
        $retry_after   = 0;
        if ($error) {
            $retry = self::$retryOnTimeout && curl_errno(self::$handle) == CURLE_OPERATION_TIMEDOUT;
        } else {
            // Successful apiCall with some status code or with Retry-After header
            $headers_lower_keys = array_change_key_case($headers);
            $retry_after_val = key_exists('retry-after', $headers_lower_keys) ?
                $headers_lower_keys['retry-after'] : null;
            $retry_after = self::getRetryAfterInSeconds($retry_after_val);
            $retry       = isset($retry_after_val) || in_array($httpCode, self::$httpStatusCodesToRetry);
        }
        // Calculate wait time only if max number of retries are not already attempted
        if ($retry && $retryCount < self::$maxNumberOfRetries) {
            // noise between 0 and 0.1 secs upto 6 decimal places
            $noise       = rand(0, 100000) / 1000000;
            // calculate wait time with exponential backoff and noise in seconds
            $waitTime    = (self::$retryInterval * pow(self::$backoffFactor, $retryCount)) + $noise;
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
    private static function getRetryAfterInSeconds($retry_after)
    {
        if (isset($retry_after)) {
            if (is_numeric($retry_after)) {
                return (int)$retry_after; // if value is already in seconds
            } else {
                // if value is a date time string in format RFC1123
                $retry_after_date = \DateTime::createFromFormat('D, d M Y H:i:s O', $retry_after);
                // retry_after_date could either be undefined, or false, or a DateTime object (if valid format string)
                return $retry_after_date == false ? 0 : $retry_after_date->getTimestamp() - time();
            }
        }
        return 0;
    }

    /**
     * if PECL_HTTP is not available use a fall back function
     *
     * thanks to ricardovermeltfoort@gmail.com
     * http://php.net/manual/en/function.http-parse-headers.php#112986
     * @param string $raw_headers raw headers
     * @return array
     */
    private static function parseHeaders($raw_headers)
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

    public static function getInfo($opt = false)
    {
        if ($opt) {
            $info = curl_getinfo(self::$handle, $opt);
        } else {
            $info = curl_getinfo(self::$handle);
        }

        return $info;
    }

    public static function getCurlHandle()
    {
        return self::$handle;
    }

    public static function getFormattedHeaders($headers)
    {
        $formattedHeaders = array();

        $combinedHeaders = array_change_key_case(array_merge(self::$defaultHeaders, (array) $headers));

        foreach ($combinedHeaders as $key => $val) {
            $formattedHeaders[] = self::getHeaderString($key, $val);
        }

        if (!array_key_exists('user-agent', $combinedHeaders)) {
            $formattedHeaders[] = 'user-agent: unirest-php/2.0';
        }

        if (!array_key_exists('expect', $combinedHeaders)) {
            $formattedHeaders[] = 'expect:';
        }

        return $formattedHeaders;
    }

    /**
     * Validates and processes the given Url to ensure safe usage with cURL.
     * @param string $url The given Url to process
     * @return string Pre-processed Url as string
     * @throws Exception
     */
    public static function validateUrl($url)
    {
        //perform parameter validation
        if (!is_string($url)) {
            throw new Exception('Invalid Url.');
        }
        //ensure that the urls are absolute
        $matchCount = preg_match("#^(https?://[^/]+)#", $url, $matches);
        if ($matchCount == 0) {
            throw new Exception('Invalid Url format.');
        }
        //get the http protocol match
        $protocol = $matches[1];

        //remove redundant forward slashes
        $query = substr($url, strlen($protocol));
        $query = preg_replace("#//+#", "/", $query);

        //return process url
        return $protocol . $query;
    }

    private static function getHeaderString($key, $val)
    {
        $key = trim(strtolower($key));
        return $key . ': ' . $val;
    }

    /**
     * @param array $existing_options
     * @param array $new_options
     * @return array
     */
    private static function mergeCurlOptions(&$existing_options, $new_options)
    {
        $existing_options = $new_options + $existing_options;
        return $existing_options;
    }
}
