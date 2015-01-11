<?php

use Unirest\HttpMethod;
use Unirest\HttpRequest;

class Unirest
{
    
    
    /**
     * Send a GET request to a URL
     * @param string $url URL to send the GET request to
     * @param array $headers additional headers to send
     * @param mixed $parameters parameters to send in the querystring
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public static function get($url, $headers = array(), $parameters = NULL, $username = NULL, $password = NULL)
    {
        return Unirest::request(HttpMethod::GET, $url, $parameters, $headers, $username, $password);
    }
    
    /**
     * Send POST request to a URL
     * @param string $url URL to send the POST request to
     * @param array $headers additional headers to send
     * @param mixed $body POST body data
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public static function post($url, $headers = array(), $body = NULL, $username = NULL, $password = NULL)
    {
        return Unirest::request(HttpMethod::POST, $url, $body, $headers, $username, $password);
    }
    
    /**
     * Send DELETE request to a URL
     * @param string $url URL to send the DELETE request to
     * @param array $headers additional headers to send
     * @param mixed $body DELETE body data
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public static function delete($url, $headers = array(), $body = NULL, $username = NULL, $password = NULL)
    {
        return Unirest::request(HttpMethod::DELETE, $url, $body, $headers, $username, $password);
    }
    
    /**
     * Send PUT request to a URL
     * @param string $url URL to send the PUT request to
     * @param array $headers additional headers to send
     * @param mixed $body PUT body data
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public static function put($url, $headers = array(), $body = NULL, $username = NULL, $password = NULL)
    {
        return Unirest::request(HttpMethod::PUT, $url, $body, $headers, $username, $password);
    }
    
    /**
     * Send PATCH request to a URL
     * @param string $url URL to send the PATCH request to
     * @param array $headers additional headers to send
     * @param mixed $body PATCH body data
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public static function patch($url, $headers = array(), $body = NULL, $username = NULL, $password = NULL)
    {
        return Unirest::request(HttpMethod::PATCH, $url, $body, $headers, $username, $password);
    }
    
    /**
     * Prepares a file for upload. To be used inside the parameters declaration for a request.
     * @param string $path The file path
     */
    public static function file($path)
    {
        if (function_exists("curl_file_create")) {
            return curl_file_create($path);
        } else {
            return "@" . $path;
        }
    }
    
    /**
     * This function is useful for serializing multidimensional arrays, and avoid getting
     * the "Array to string conversion" notice
     */
    public static function http_build_query_for_curl($arrays, &$new = array(), $prefix = null)
    {
        if (is_object($arrays)) {
            $arrays = get_object_vars($arrays);
        }
        
        foreach ($arrays AS $key => $value) {
            $k = isset($prefix) ? $prefix . '[' . $key . ']' : $key;
            if (!$value instanceof \CURLFile AND (is_array($value) OR is_object($value))) {
                Unirest::http_build_query_for_curl($value, $new, $k);
            } else {
                $new[$k] = $value;
            }
        }
    }
    
    /**
     * Send a cURL request
     * @param string $httpMethod HTTP method to use (based off \Unirest\HttpMethod constants)
     * @param string $url URL to send the request to
     * @param mixed $body request body
     * @param array $headers additional headers to send
     * @param string $username  Basic Authentication username
     * @param string $password  Basic Authentication password
     * @throws Exception if a cURL error occurs
     * @return HttpRequest
     */
    private static function request($httpMethod, $url, $body = NULL, $headers = array(), $username = NULL, $password = NULL)
    {
        if ($headers == NULL)
            $headers = array();

        return new HttpRequest($httpMethod, $url, $body, $headers, $username, $password);
    }
}

?>
