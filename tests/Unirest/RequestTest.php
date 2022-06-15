<?php

namespace Unirest\Request\Test;

use Unirest\Request as Request;
use Unirest\Exception as Exception;
use Unirest\RequestChild;

require __DIR__ . '/../../src/Unirest.php';
require __DIR__ . '/RequestChild.php';

class UnirestRequestTest extends \PHPUnit\Framework\TestCase
{
    // Generic
    public function testCurlOpts()
    {
        Request::curlOpt(CURLOPT_COOKIE, 'foo=bar');

        $response = Request::get('http://mockbin.com/request');

        $this->assertTrue(property_exists($response->body->cookies, 'foo'));

        Request::clearCurlOpts();
    }

    public function testTimeoutFail()
    {
        Request::timeout(1);
        $message = "Timeout exception not thrown";
        try {
            Request::get('http://mockbin.com/delay/2000');
        } catch (Exception $e) {
            $message = substr($e->getMessage(), 0, 19);
        }
        Request::timeout(null); // Cleaning timeout for the other tests
        $this->assertEquals('Operation timed out', $message);
    }

    public function testDefaultHeaders()
    {
        $defaultHeaders = array(
            'header1' => 'Hello',
            'header2' => 'world'
        );
        Request::defaultHeaders($defaultHeaders);

        $response = Request::get('http://mockbin.com/request');

        $this->assertEquals(200, $response->code);
        $this->assertObjectHasAttribute('header1', $response->body->headers);
        $this->assertEquals('Hello', $response->body->headers->header1);
        $this->assertObjectHasAttribute('header2', $response->body->headers);
        $this->assertEquals('world', $response->body->headers->header2);

        $response = Request::get('http://mockbin.com/request', ['header1' => 'Custom value']);

        $this->assertEquals(200, $response->code);
        $this->assertObjectHasAttribute('header1', $response->body->headers);
        $this->assertEquals('Custom value', $response->body->headers->header1);

        Request::clearDefaultHeaders();

        $response = Request::get('http://mockbin.com/request');

        $this->assertEquals(200, $response->code);
        $this->assertObjectNotHasAttribute('header1', $response->body->headers);
        $this->assertObjectNotHasAttribute('header2', $response->body->headers);
    }

    public function testDefaultHeader()
    {
        Request::defaultHeader('Hello', 'custom');

        $response = Request::get('http://mockbin.com/request');

        $this->assertEquals(200, $response->code);
        $this->assertTrue(property_exists($response->body->headers, 'hello'));
        $this->assertEquals('custom', $response->body->headers->hello);

        Request::clearDefaultHeaders();

        $response = Request::get('http://mockbin.com/request');

        $this->assertEquals(200, $response->code);
        $this->assertFalse(property_exists($response->body->headers, 'hello'));
    }

    public function testConnectionReuse()
    {
        RequestChild::resetHandle();
        $url = "http://httpbin.org/get";

        // test client sending keep-alive automatically
        $res = Request::get($url);
        $this->assertEquals("keep-alive", $res->headers['Connection']);
        $this->assertEquals(1, RequestChild::getTotalNumberOfConnections());

        // test closing connection after response is received
        $res = Request::get($url, [ 'Connection' => 'close' ]);
        $this->assertEquals("close", $res->headers['Connection']);
        $this->assertEquals(1, RequestChild::getTotalNumberOfConnections());

        // test creating a new connection after closing previous one
        $res = Request::get($url);
        $this->assertEquals("keep-alive", $res->headers['Connection']);
        $this->assertEquals(2, RequestChild::getTotalNumberOfConnections());

        // test persisting the new connection
        $res = Request::get($url);
        $this->assertEquals("keep-alive", $res->headers['Connection']);
        $this->assertEquals(2, RequestChild::getTotalNumberOfConnections());
    }

    public function testConnectionReuseForMultipleDomains()
    {
        RequestChild::resetHandle();
        $url1 = "http://httpbin.org/get";
        $url2 = "http://ptsv2.com/t/cedqp-1655183385";
        $url3 = "http://en2hoq5smpha9.x.pipedream.net";
        $url4 = "http://mockbin.com/request";

        Request::get($url1);
        Request::get($url2);
        Request::get($url3);
        // test creating 3 connections by calling 3 domains
        $this->assertEquals(3, RequestChild::getTotalNumberOfConnections());

        Request::get($url1);
        Request::get($url2);
        Request::get($url3);
        // test persisting previous 3 connections
        $this->assertEquals(3, RequestChild::getTotalNumberOfConnections());

        Request::get($url1);
        Request::get($url2);
        Request::get($url3);
        Request::get($url4);
        // test adding a new connection by persisting previous ones using a call to another domain
        $this->assertEquals(4, RequestChild::getTotalNumberOfConnections());
    }

    public function testSetMashapeKey()
    {
        Request::setMashapeKey('abcd');

        $response = Request::get('http://mockbin.com/request');

        $this->assertEquals(200, $response->code);
        $this->assertTrue(property_exists($response->body->headers, 'x-mashape-key'));
        $this->assertEquals('abcd', $response->body->headers->{'x-mashape-key'});

        // send another request
        $response = Request::get('http://mockbin.com/request');

        $this->assertEquals(200, $response->code);
        $this->assertTrue(property_exists($response->body->headers, 'x-mashape-key'));
        $this->assertEquals('abcd', $response->body->headers->{'x-mashape-key'});

        Request::clearDefaultHeaders();

        $response = Request::get('http://mockbin.com/request');

        $this->assertEquals(200, $response->code);
        $this->assertFalse(property_exists($response->body->headers, 'x-mashape-key'));
    }

    public function testGzip()
    {
        $response = Request::post('http://mockbin.com/gzip');

        $this->assertEquals('gzip', $response->headers['Content-Encoding']);
    }

    public function testBasicAuthenticationDeprecated()
    {
        $response = Request::get('http://mockbin.com/request', array(), array(), 'user', 'password');

        $this->assertEquals('Basic dXNlcjpwYXNzd29yZA==', $response->body->headers->authorization);
    }

    public function testBasicAuthentication()
    {
        Request::auth('user', 'password');

        $response = Request::get('http://mockbin.com/request');

        $this->assertEquals('Basic dXNlcjpwYXNzd29yZA==', $response->body->headers->authorization);
    }

    public function testCustomHeaders()
    {
        $response = Request::get('http://mockbin.com/request', array(
            'user-agent' => 'unirest-php',
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('unirest-php', $response->body->headers->{'user-agent'});
    }

    // GET
    public function testGet()
    {
        $response = Request::get('http://mockbin.com/request?name=Mark', array(
            'Accept' => 'application/json'
        ), array(
            'nick' => 'thefosk'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('GET', $response->body->method);
        $this->assertEquals('Mark', $response->body->queryString->name);
        $this->assertEquals('thefosk', $response->body->queryString->nick);
    }

    public function testGetMultidimensionalArray()
    {
        $response = Request::get('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'key' => 'value',
            'items' => array(
                'item1',
                'item2'
            )
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('GET', $response->body->method);
        $this->assertEquals('value', $response->body->queryString->key);
        $this->assertEquals('item1', $response->body->queryString->items[0]);
        $this->assertEquals('item2', $response->body->queryString->items[1]);
    }

    public function testGetWithDots()
    {
        $response = Request::get('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'user.name' => 'Mark',
            'nick' => 'thefosk'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('GET', $response->body->method);
        $this->assertEquals('Mark', $response->body->queryString->{'user.name'});
        $this->assertEquals('thefosk', $response->body->queryString->nick);
    }

    public function testGetWithDotsAlt()
    {
        $response = Request::get('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'user.name' => 'Mark Bond',
            'nick' => 'thefosk'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('GET', $response->body->method);
        $this->assertEquals('Mark Bond', $response->body->queryString->{'user.name'});
        $this->assertEquals('thefosk', $response->body->queryString->nick);
    }
    public function testGetWithEqualSign()
    {
        $response = Request::get('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'name' => 'Mark=Hello'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('GET', $response->body->method);
        $this->assertEquals('Mark=Hello', $response->body->queryString->name);
    }

    public function testGetWithEqualSignAlt()
    {
        $response = Request::get('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'name' => 'Mark=Hello=John'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('GET', $response->body->method);
        $this->assertEquals('Mark=Hello=John', $response->body->queryString->name);
    }

    public function testGetWithComplexQuery()
    {
        $response = Request::get('http://mockbin.com/request?query=[{"type":"/music/album","name":null,"artist":{"id":"/en/bob_dylan"},"limit":3}]&cursor');

        $this->assertEquals(200, $response->code);
        $this->assertEquals('GET', $response->body->method);
        $this->assertEquals('', $response->body->queryString->cursor);
        $this->assertEquals('[{"type":"/music/album","name":null,"artist":{"id":"/en/bob_dylan"},"limit":3}]', $response->body->queryString->query);
    }

    public function testGetArray()
    {
        $response = Request::get('http://mockbin.com/request', array(), array(
            'name[0]' => 'Mark',
            'name[1]' => 'John'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('GET', $response->body->method);
        $this->assertEquals('Mark', $response->body->queryString->name[0]);
        $this->assertEquals('John', $response->body->queryString->name[1]);
    }

    // HEAD
    public function testHead()
    {
        $response = Request::head('http://mockbin.com/request?name=Mark', array(
          'Accept' => 'application/json'
        ));

        $this->assertEquals(200, $response->code);
    }

    // POST
    public function testPost()
    {
        $response = Request::post('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'name' => 'Mark',
            'nick' => 'thefosk'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('Mark', $response->body->postData->params->name);
        $this->assertEquals('thefosk', $response->body->postData->params->nick);
    }

    public function testPostForm()
    {
        $body = Request\Body::Form(array(
            'name' => 'Mark',
            'nick' => 'thefosk'
        ));

        $response = Request::post('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), $body);

        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('application/x-www-form-urlencoded', $response->body->headers->{'content-type'});
        $this->assertEquals('application/x-www-form-urlencoded', $response->body->postData->mimeType);
        $this->assertEquals('Mark', $response->body->postData->params->name);
        $this->assertEquals('thefosk', $response->body->postData->params->nick);
    }

    public function testPostMultipart()
    {
        $body = Request\Body::Multipart(array(
            'name' => 'Mark',
            'nick' => 'thefosk'
        ));

        $response = Request::post('http://mockbin.com/request', (object) array(
            'Accept' => 'application/json',
        ), $body);

        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('multipart/form-data', explode(';', $response->body->headers->{'content-type'})[0]);
        $this->assertEquals('multipart/form-data', $response->body->postData->mimeType);
        $this->assertEquals('Mark', $response->body->postData->params->name);
        $this->assertEquals('thefosk', $response->body->postData->params->nick);
    }

    public function testPostWithEqualSign()
    {
        $body = Request\Body::Form(array(
            'name' => 'Mark=Hello'
        ));

        $response = Request::post('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), $body);

        $this->assertEquals(200, $response->code);
        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('Mark=Hello', $response->body->postData->params->name);
    }

    public function testPostArray()
    {
        $response = Request::post('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'name[0]' => 'Mark',
            'name[1]' => 'John'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('Mark', $response->body->postData->params->{'name[0]'});
        $this->assertEquals('John', $response->body->postData->params->{'name[1]'});
    }

    public function testPostWithDots()
    {
        $response = Request::post('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'user.name' => 'Mark',
            'nick' => 'thefosk'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('Mark', $response->body->postData->params->{'user.name'});
        $this->assertEquals('thefosk', $response->body->postData->params->nick);
    }

    public function testRawPost()
    {
        $response = Request::post('http://mockbin.com/request', array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ), json_encode(array(
            'author' => 'Sam Sullivan'
        )));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('Sam Sullivan', json_decode($response->body->postData->text)->author);
    }

    public function testPostMultidimensionalArray()
    {
        $body = Request\Body::Form(array(
            'key' => 'value',
            'items' => array(
                'item1',
                'item2'
            )
        ));

        $response = Request::post('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), $body);

        $this->assertEquals(200, $response->code);
        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('value', $response->body->postData->params->key);
        $this->assertEquals('item1', $response->body->postData->params->{'items[0]'});
        $this->assertEquals('item2', $response->body->postData->params->{'items[1]'});
    }

    // PUT
    public function testPut()
    {
        $response = Request::put('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'name' => 'Mark',
            'nick' => 'thefosk'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('PUT', $response->body->method);
        $this->assertEquals('Mark', $response->body->postData->params->name);
        $this->assertEquals('thefosk', $response->body->postData->params->nick);
    }

    // PATCH
    public function testPatch()
    {
        $response = Request::patch('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'name' => 'Mark',
            'nick' => 'thefosk'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('PATCH', $response->body->method);
        $this->assertEquals('Mark', $response->body->postData->params->name);
        $this->assertEquals('thefosk', $response->body->postData->params->nick);
    }

    // DELETE
    public function testDelete()
    {
        $response = Request::delete('http://mockbin.com/request', array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded'
        ), array(
            'name' => 'Mark',
            'nick' => 'thefosk'
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('DELETE', $response->body->method);
    }

    // Upload
    public function testUpload()
    {
        $fixture = __DIR__ . '/../fixtures/upload.txt';

        $headers = array('Accept' => 'application/json');
        $files = array('file' => $fixture);
        $data = array('name' => 'ahmad');

        $body = Request\Body::Multipart($data, $files);

        $response = Request::post('http://mockbin.com/request', $headers, $body);

        $this->assertEquals(200, $response->code);
        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('ahmad', $response->body->postData->params->name);
        $this->assertEquals('This is a test', $response->body->postData->params->file);
    }

    public function testUploadWithoutHelper()
    {
        $fixture = __DIR__ . '/../fixtures/upload.txt';

        $response = Request::post('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'name' => 'Mark',
            'file' => Request\Body::File($fixture)
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('Mark', $response->body->postData->params->name);
        $this->assertEquals('This is a test', $response->body->postData->params->file);
    }

    public function testUploadIfFilePartOfData()
    {
        $fixture = __DIR__ . '/../fixtures/upload.txt';

        $response = Request::post('http://mockbin.com/request', array(
            'Accept' => 'application/json'
        ), array(
            'name' => 'Mark',
            'files[owl.gif]' => Request\Body::File($fixture)
        ));

        $this->assertEquals(200, $response->code);
        $this->assertEquals('POST', $response->body->method);
        $this->assertEquals('Mark', $response->body->postData->params->name);
        $this->assertEquals('This is a test', $response->body->postData->params->{'files[owl.gif]'});
    }
}
