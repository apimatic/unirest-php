<?php

namespace Unirest;

class Request
{
    private $cookie = null;
    private $cookieFile = null;
    private $curlOpts = array();
    private $handle = null;
    private $jsonOpts = array();
    private $socketTimeout = null;
    private $enableRetries = false;       // should we enable retries feature
    private $maxNumberOfRetries = 3;      // total number of allowed retries
    private $retryOnTimeout = false;      // Should we retry on timeout?
    private $retryInterval = 1.0;         // Initial retry interval in seconds, to be increased by backoffFactor
    private $maximumRetryWaitTime = 120;  // maximum retry wait time (commutative)
    private $backoffFactor = 2.0;         // backoff factor to be used to increase retry interval
    private $httpStatusCodesToRetry = array(408, 413, 429, 500, 502, 503, 504, 521, 522, 524);
    private $httpMethodsToRetry = array("GET", "PUT");
    private $overrideRetryForNextRequest = OverrideRetry::USE_GLOBAL_SETTINGS;
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

    protected $totalNumberOfConnections = 0;

    /**
     * Set JSON decode mode
     *
     * @param bool $assoc When TRUE, returned objects will be converted into associative arrays.
     * @param integer $depth User specified recursion depth.
     * @param integer $options Bitmask of JSON decode options. Currently only JSON_BIGINT_AS_STRING is supported (default is to cast large integers as floats)
     * @return array
     */
    public function jsonOpts($assoc = false, $depth = 512, $options = 0)
    {
        return $this->jsonOpts = array($assoc, $depth, $options);
    }

    /**
     * Verify SSL peer
     *
     * @param bool $enabled enable SSL verification, by default is true
     * @return bool
     */
    public function verifyPeer($enabled)
    {
        return $this->verifyPeer = $enabled;
    }

    /**
     * Verify SSL host
     *
     * @param bool $enabled enable SSL host verification, by default is true
     * @return bool
     */
    public function verifyHost($enabled)
    {
        return $this->verifyHost = $enabled;
    }

    /**
     * Set a timeout
     *
     * @param integer $seconds timeout value in seconds
     * @return integer
     */
    public function timeout($seconds)
    {
        return $this->socketTimeout = $seconds;
    }

    /**
     * Should we enable retries feature
     *
     * @param bool $enableRetries
     * @return bool
     */
    public function enableRetries($enableRetries)
    {
        return $this->enableRetries = $enableRetries;
    }

    /**
     * Total number of allowed retries
     *
     * @param integer $maxNumberOfRetries
     * @return integer
     */
    public function maxNumberOfRetries($maxNumberOfRetries)
    {
        return $this->maxNumberOfRetries = $maxNumberOfRetries;
    }

    /**
     * Should we retry on timeout
     *
     * @param bool $retryOnTimeout
     * @return bool
     */
    public function retryOnTimeout($retryOnTimeout)
    {
        return $this->retryOnTimeout = $retryOnTimeout;
    }

    /**
     * Initial retry interval in seconds, to be increased by backoffFactor
     *
     * @param float $retryInterval
     * @return float
     */
    public function retryInterval($retryInterval)
    {
        return $this->retryInterval = $retryInterval;
    }

    /**
     * Maximum retry wait time
     *
     * @param integer $maximumRetryWaitTime
     * @return integer
     */
    public function maximumRetryWaitTime($maximumRetryWaitTime)
    {
        return $this->maximumRetryWaitTime = $maximumRetryWaitTime;
    }

    /**
     * Backoff factor to be used to increase retry interval
     *
     * @param float $backoffFactor
     * @return float
     */
    public function backoffFactor($backoffFactor)
    {
        return $this->backoffFactor = $backoffFactor;
    }

    /**
     * Http status codes to retry against
     *
     * @param integer[] $httpStatusCodesToRetry
     * @return integer[]
     */
    public function httpStatusCodesToRetry($httpStatusCodesToRetry)
    {
        return $this->httpStatusCodesToRetry = $httpStatusCodesToRetry;
    }

    /**
     * Http methods to retry against
     *
     * @param string[] $httpMethodsToRetry
     * @return string[]
     */
    public function httpMethodsToRetry($httpMethodsToRetry)
    {
        return $this->httpMethodsToRetry = $httpMethodsToRetry;
    }

    /**
     * Enable or disable retries for next request, ignoring httpMethods whitelist.
     *
     * @param string $overrideRetryForNextRequest
     * @return string
     */
    public function overrideRetryForNextRequest($overrideRetryForNextRequest)
    {
        return $this->overrideRetryForNextRequest = $overrideRetryForNextRequest;
    }

    /**
     * Set default headers to send on every request
     *
     * @param array $headers headers array
     * @return array
     */
    public function defaultHeaders($headers)
    {
        return $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
    }

    /**
     * Set a new default header to send on every request
     *
     * @param string $name header name
     * @param string $value header value
     * @return string
     */
    public function defaultHeader($name, $value)
    {
        return $this->defaultHeaders[$name] = $value;
    }

    /**
     * Clear all the default headers
     */
    public function clearDefaultHeaders()
    {
        return $this->defaultHeaders = array();
    }

    /**
     * Set curl options to send on every request
     *
     * @param array $options options array
     * @return array
     */
    public function curlOpts($options)
    {
        return $this->mergeCurlOptions($this->curlOpts, $options);
    }

    /**
     * Set a new default header to send on every request
     *
     * @param string $name header name
     * @param string $value header value
     * @return string
     */
    public function curlOpt($name, $value)
    {
        return $this->curlOpts[$name] = $value;
    }

    /**
     * Clear all curl opts
     */
    public function clearCurlOpts()
    {
        return $this->curlOpts = array();
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
    public function setMashapeKey($key)
    {
        return $this->defaultHeader('X-Mashape-Key', $key);
    }

    /**
     * Set a cookie string for enabling cookie handling
     *
     * @param string $cookie
     */
    public function cookie($cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * Set a cookie file path for enabling cookie handling
     *
     * $cookieFile must be a correct path with write permission
     *
     * @param string $cookieFile - path to file for saving cookie
     */
    public function cookieFile($cookieFile)
    {
        $this->cookieFile = $cookieFile;
    }

    /**
     * Set authentication method to use
     *
     * @param string $username authentication username
     * @param string $password authentication password
     * @param integer $method authentication method
     */
    public function auth($username = '', $password = '', $method = CURLAUTH_BASIC)
    {
        $this->auth['user'] = $username;
        $this->auth['pass'] = $password;
        $this->auth['method'] = $method;
    }

    /**
     * Set proxy to use
     *
     * @param string $address proxy address
     * @param integer $port proxy port
     * @param integer $type (Available options for this are CURLPROXY_HTTP, CURLPROXY_HTTP_1_0 CURLPROXY_SOCKS4, CURLPROXY_SOCKS5, CURLPROXY_SOCKS4A and CURLPROXY_SOCKS5_HOSTNAME)
     * @param bool $tunnel enable/disable tunneling
     */
    public function proxy($address, $port = 1080, $type = CURLPROXY_HTTP, $tunnel = false)
    {
        $this->proxy['type'] = $type;
        $this->proxy['port'] = $port;
        $this->proxy['tunnel'] = $tunnel;
        $this->proxy['address'] = $address;
    }

    /**
     * Set proxy authentication method to use
     *
     * @param string $username authentication username
     * @param string $password authentication password
     * @param integer $method authentication method
     */
    public function proxyAuth($username = '', $password = '', $method = CURLAUTH_BASIC)
    {
        $this->proxy['auth']['user'] = $username;
        $this->proxy['auth']['pass'] = $password;
        $this->proxy['auth']['method'] = $method;
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
    public function get($url, $headers = array(), $parameters = null, $username = null, $password = null)
    {
        return $this->send(Method::GET, $url, $parameters, $headers, $username, $password);
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
    public function head($url, $headers = array(), $parameters = null, $username = null, $password = null)
    {
        return $this->send(Method::HEAD, $url, $parameters, $headers, $username, $password);
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
    public function options($url, $headers = array(), $parameters = null, $username = null, $password = null)
    {
        return $this->send(Method::OPTIONS, $url, $parameters, $headers, $username, $password);
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
    public function connect($url, $headers = array(), $parameters = null, $username = null, $password = null)
    {
        return $this->send(Method::CONNECT, $url, $parameters, $headers, $username, $password);
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
    public function post($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return $this->send(Method::POST, $url, $body, $headers, $username, $password);
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
    public function delete($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return $this->send(Method::DELETE, $url, $body, $headers, $username, $password);
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
    public function put($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return $this->send(Method::PUT, $url, $body, $headers, $username, $password);
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
    public function patch($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return $this->send(Method::PATCH, $url, $body, $headers, $username, $password);
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
    public function trace($url, $headers = array(), $body = null, $username = null, $password = null)
    {
        return $this->send(Method::TRACE, $url, $body, $headers, $username, $password);
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

    protected function initializeHandle()
    {
        $this->handle = curl_init();
        $this->totalNumberOfConnections = 0;
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
    public function send($method, $url, $body = null, $headers = array(), $username = null, $password = null)
    {
        if ($this->handle == null) {
            $this->initializeHandle();
        } else {
            curl_reset($this->handle);
        }

        if ($method !== Method::GET) {
            if ($method === Method::POST) {
                curl_setopt($this->handle, CURLOPT_POST, true);
            } else {
                if ($method === Method::HEAD) {
                    curl_setopt($this->handle, CURLOPT_NOBODY, true);
                }
                curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, $method);
            }

            curl_setopt($this->handle, CURLOPT_POSTFIELDS, $body);
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
            CURLOPT_HTTPHEADER => $this->getFormattedHeaders($headers),
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => $this->verifyPeer,
            //CURLOPT_SSL_VERIFYHOST accepts only 0 (false) or 2 (true). Future versions of libcurl will treat values 1 and 2 as equals
            CURLOPT_SSL_VERIFYHOST => $this->verifyHost === false ? 0 : 2,
            // If an empty string, '', is set, a header containing all supported encoding types is sent
            CURLOPT_ENCODING => ''
        ];

        curl_setopt_array($this->handle, $this->mergeCurlOptions($curl_base_options, $this->curlOpts));

        if ($this->socketTimeout !== null) {
            curl_setopt($this->handle, CURLOPT_TIMEOUT, $this->socketTimeout);
        }

        if ($this->cookie) {
            curl_setopt($this->handle, CURLOPT_COOKIE, $this->cookie);
        }

        if ($this->cookieFile) {
            curl_setopt($this->handle, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($this->handle, CURLOPT_COOKIEJAR, $this->cookieFile);
        }

        // supporting deprecated http auth method
        if (!empty($username)) {
            curl_setopt_array($this->handle, array(
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $username . ':' . $password
            ));
        }

        if (!empty($this->auth['user'])) {
            curl_setopt_array($this->handle, array(
                CURLOPT_HTTPAUTH    => $this->auth['method'],
                CURLOPT_USERPWD     => $this->auth['user'] . ':' . $this->auth['pass']
            ));
        }

        if ($this->proxy['address'] !== false) {
            curl_setopt_array($this->handle, array(
                CURLOPT_PROXYTYPE       => $this->proxy['type'],
                CURLOPT_PROXY           => $this->proxy['address'],
                CURLOPT_PROXYPORT       => $this->proxy['port'],
                CURLOPT_HTTPPROXYTUNNEL => $this->proxy['tunnel'],
                CURLOPT_PROXYAUTH       => $this->proxy['auth']['method'],
                CURLOPT_PROXYUSERPWD    => $this->proxy['auth']['user'] . ':' . $this->proxy['auth']['pass']
            ));
        }

        $retryCount      = 0;                           // current retry count
        $waitTime        = 0.0;                         // wait time in secs before current api call
        $allowedWaitTime = $this->maximumRetryWaitTime; // remaining allowed wait time in seconds
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
            if (!$error) {
                $header_size = $info['header_size'];
                $httpCode    = $info['http_code'];
                $headers     = $this->parseHeaders(substr($response, 0, $header_size));
            }

            if ($this->shouldRetryRequest($method)) {
                // calculate wait time for retry, and should not retry when wait time becomes 0
                $waitTime = $this->getRetryWaitTime($httpCode, $headers, $error, $allowedWaitTime, $retryCount);
                $retryCount++;
            }
        } while ($waitTime > 0.0);

        // reset request level retries check
        $this->overrideRetryForNextRequest = OverrideRetry::USE_GLOBAL_SETTINGS;

        if ($error) {
            throw new Exception($error);
        }
        // get response body
        $body = substr($response, $header_size);

        $this->totalNumberOfConnections += curl_getinfo($this->handle, CURLINFO_NUM_CONNECTS);

        return new Response($httpCode, $body, $headers, $this->jsonOpts);
    }

    /**
     * Halts program flow for given number of seconds, and microseconds
     *
     * @param $seconds float seconds with upto 6 decimal places, here decimal part will be converted into microseconds
     */
    private function sleep($seconds)
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
    private function shouldRetryRequest($method)
    {
        switch ($this->overrideRetryForNextRequest) {
            case OverrideRetry::ENABLE_RETRY:
                return $this->enableRetries;
            case OverrideRetry::USE_GLOBAL_SETTINGS:
                return $this->enableRetries && in_array($method, $this->httpMethodsToRetry);
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
    private function getRetryWaitTime($httpCode, $headers, $error, $allowedWaitTime, $retryCount)
    {
        $retryWaitTime = 0.0;
        $retry_after   = 0;
        if ($error) {
            $retry = $this->retryOnTimeout && curl_errno($this->handle) == CURLE_OPERATION_TIMEDOUT;
        } else {
            // Successful apiCall with some status code or with Retry-After header
            $headers_lower_keys = array_change_key_case($headers);
            $retry_after_val = key_exists('retry-after', $headers_lower_keys) ?
                $headers_lower_keys['retry-after'] : null;
            $retry_after = $this->getRetryAfterInSeconds($retry_after_val);
            $retry       = isset($retry_after_val) || in_array($httpCode, $this->httpStatusCodesToRetry);
        }
        // Calculate wait time only if max number of retries are not already attempted
        if ($retry && $retryCount < $this->maxNumberOfRetries) {
            // noise between 0 and 0.1 secs upto 6 decimal places
            $noise       = rand(0, 100000) / 1000000;
            // calculate wait time with exponential backoff and noise in seconds
            $waitTime    = ($this->retryInterval * pow($this->backoffFactor, $retryCount)) + $noise;
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
    private function getRetryAfterInSeconds($retry_after)
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
     * if PECL_HTTP is not available use a fallback function
     *
     * thanks to ricardovermeltfoort@gmail.com
     * http://php.net/manual/en/function.http-parse-headers.php#112986
     * @param string $raw_headers raw headers
     * @return array
     */
    private function parseHeaders($raw_headers)
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

    public function getCurlHandle()
    {
        return $this->handle;
    }

    public function getFormattedHeaders($headers)
    {
        $formattedHeaders = array();

        $combinedHeaders = array_change_key_case(array_merge($this->defaultHeaders, (array) $headers));

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

    private function getHeaderString($key, $val)
    {
        $key = trim(strtolower($key));
        return $key . ': ' . $val;
    }

    /**
     * @param array $existing_options
     * @param array $new_options
     * @return array
     */
    private function mergeCurlOptions(&$existing_options, $new_options)
    {
        $existing_options = $new_options + $existing_options;
        return $existing_options;
    }
}
