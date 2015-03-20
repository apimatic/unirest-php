<?php

namespace Unirest;

class Request
{
    /**
     * @param HttpMethod $httpMethod is the method for sending the cURL request e.g., GET/POST/PUT/DELETE/PATCH
     */
    protected $method;

    /**
     * @param string $url is for sending the cURL request
     */
    protected $url;

    /**
     * @param mixed $body is the request body
     */
    protected $body = NULL;

    /**
     * @param array $headers is the collection of outgoing finalized headers
     */
    protected $headers = array();

    /**
     * @param string $username is the user name for Basic Authentication
     */
    protected $username = NULL;

    /**
     * @param string $password is the password for Basic Authentication
     */
    protected $password = NULL;

    /**
     * @param HttpMethod $httpMethod HTTP Method for invoking the cURL request
     * @param string $url URL for invoking the cURL request
     * @param string $body raw body for the cURL request
     * @param string $headers raw header string from cURL request
     * @param string $username username for the basic authentication
     * @param string $password password for the basic authentication
     */
    public function __construct($httpMethod, $url, $body = NULL, $headers = array(), $username = NULL, $password = NULL)
    {
        $this->method = $httpMethod;
        $this->url = $url;
        $this->body = $body;		
        $this->headers = $headers;
        $this->username = $username;
        $this->password = $password;
    }
    
    /**
     * 
     * @param type $newHeaders are the new headers that need to be appended
     * @return \Unirest\HttpRequest 
     */
    public function headers($newHeaders)
    {
        $flattennedHeaders = array();
        foreach ($newHeaders as $key => $val) {
            $flattennedHeaders[] = $this->createHeader($key, $val);
        }        
        $this->headers = array_merge($this->headers, $flattennedHeaders);
        return $this;
    }
    /**
     * Create a formatted header from a given key and value
     * @param string $key the key to use for the header
     * @param string $val the value to use for the header
     */
    private static function createHeader($key, $val)
    {
        $key = trim($key);
        return $key . ": " . $val;
    }
    
    /**
     * Return a property of the response if it exists.
     * Possibilities include: code, raw_body, headers, body (if the response is json-decodable)
     * @return mixed
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            //UTF-8 is recommended for correct JSON serialization
            $value = $this->$property;
            if (is_string($value) && mb_detect_encoding($value, "UTF-8", TRUE) != "UTF-8") {
                return utf8_encode($value);
            }
            else {
                return $value;
            }
        }
    }
    
    /**
     * Set the properties of this object
     * @param string $property the property name
     * @param mixed $value the property value
     */
    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            //UTF-8 is recommended for correct JSON serialization
            if (is_string($value) && mb_detect_encoding($value, "UTF-8", TRUE) != "UTF-8") {
                $this->$property = utf8_encode($value);
            }
            else {
                $this->$property = $value;
            }
        }
        return $this;
    }
}
