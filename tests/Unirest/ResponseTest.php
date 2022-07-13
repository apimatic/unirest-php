<?php

namespace Unirest\Response\Test;

use Unirest\Request as Request;
use Unirest\Response as Response;

class UnirestResponseTest extends \PHPUnit\Framework\TestCase
{
    private $request;

    /**
     * @before
     */
    public function initializeDependencies()
    {
        $this->request = new Request();
    }

    public function testJSONAssociativeArrays()
    {
        $opts = $this->request->jsonOpts(true);
        $response = new Response(200, '{"a":1,"b":2,"c":3,"d":4,"e":5}', '', $opts);

        $this->assertEquals($response->body['a'], 1);
    }

    public function testJSONAObjects()
    {
        $opts = $this->request->jsonOpts(false);
        $response = new Response(200, '{"a":1,"b":2,"c":3,"d":4,"e":5}', '', $opts);

        $this->assertEquals($response->body->a, 1);
    }

    public function testJSONOpts()
    {
        $opts = $this->request->jsonOpts(false, 512, JSON_NUMERIC_CHECK);
        $response = new Response(200, '{"number": 1234567890}', '', $opts);

        $this->assertSame($response->body->number, 1234567890);
    }
}
