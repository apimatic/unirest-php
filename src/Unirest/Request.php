<?php

namespace Unirest;

class Request
{
    /**
     * @param HttpMethod $httpMethod is the method for sending the cURL request e.g., GET/POST/PUT/DELETE/PATCH
     */
    protected $httpMethod;

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
        $this->httpMethod = $httpMethod;
        $this->url = $url;
        $this->body = $body;		
        $this->headers = $headers;
        $this->username = $username;
        $this->password = $password;
    }
}
