<?php

namespace Unirest;

class Response
{
    public $code;
    public $raw_body;
    public $body;
    public $headers;

    /**
     * @param int    $code      response code of the cURL request
     * @param string $raw_body  the raw body of the cURL response
     * @param array  $headers   parsed headers array from cURL response
     * @param array  $json_args arguments to pass to json_decode function
     */
    public function __construct($code, $raw_body, $headers, $json_args = array())
    {
        $this->code     = $code;
        $this->headers  = $headers;
        $this->raw_body = $raw_body;
        $this->body     = $raw_body;

        // make sure raw_body is the first argument
        array_unshift($json_args, $raw_body);

        if (function_exists('json_decode')) {
            $json = call_user_func_array('json_decode', $json_args);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->body = $json;
            }
        }
    }
}
