<?php

namespace Unirest\Test;

use CoreInterfaces\Core\Request\RequestMethod;
use Exception;
use PHPUnit\Framework\TestCase;
use Unirest\Configuration;
use Unirest\HttpClient;
use Unirest\Request\Body;
use Unirest\Request\Request;
use Unirest\Test\Mocking\HttpClientChild;
use Unirest\Test\MockServer;

class RequestTest extends TestCase
{
    private $httpClient;

    public static function setUpBeforeClass(): void
    {
        MockServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        MockServer::stop();
    }

    protected function setUp(): void
    {
        $this->httpClient = new HttpClient();
    }

    private function executeRequest($url, $method = RequestMethod::GET, $headers = [], $body = null)
    {
        return $this->httpClient->execute(new Request($url, $method, $headers, $body));
    }

    public function testCurlOpts()
    {
        $httpClient = new HttpClient(Configuration::init()->curlOpt(CURLOPT_COOKIE, 'foo=bar'));
        $response = $httpClient->execute(new Request('http://localhost:8000/request'));
        $this->assertTrue(property_exists($response->getBody()->cookies, 'foo'));
    }

    public function testTimeoutFail()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Operation timed out');
        $httpClient = new HttpClient(Configuration::init()->timeout(1));
        $httpClient->execute(new Request('http://localhost:8000/delay/2000'));
    }

    public function testDefaultHeaders()
    {
        $httpClient = new HttpClient(Configuration::init()
            ->defaultHeaders([
                'header1' => 'Hello',
                'header2' => 'world'
            ]));
        $response = $httpClient->execute(new Request('http://localhost:8000/request'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello', $response->getBody()->headers->header1);
        $this->assertEquals('world', $response->getBody()->headers->header2);
        $response = $httpClient->execute(new Request(
            'http://localhost:8000/request',
            RequestMethod::GET,
            ['header1' => 'Custom value']
        ));
        $this->assertEquals('Custom value', $response->getBody()->headers->header1);
    }

    public function testDefaultHeader()
    {
        $httpClient = new HttpClient(Configuration::init()
            ->defaultHeader('Hello', 'custom'));
        $response = $httpClient->execute(new Request('http://localhost:8000/request'));
        $this->assertEquals('custom', $response->getBody()->headers->hello);
    }

    public function testConnectionReuse()
    {
        $httpClientChild = new HttpClientChild();
        $url = "http://localhost:8000/get";

        $res = $httpClientChild->execute(new Request($url));
        $this->assertConnectionHeader($res, 'keep-alive');
        $this->assertEquals(1, $httpClientChild->getTotalNumberOfConnections());

        $res = $httpClientChild->execute(new Request($url, RequestMethod::GET, ['Connection' => 'close']));
        $this->assertConnectionHeader($res, 'close');
        $this->assertEquals(2, $httpClientChild->getTotalNumberOfConnections());

        $res = $httpClientChild->execute(new Request($url));
        $this->assertConnectionHeader($res, 'keep-alive');
        $this->assertEquals(3, $httpClientChild->getTotalNumberOfConnections());

        $res = $httpClientChild->execute(new Request($url));
        $this->assertConnectionHeader($res, 'keep-alive');
        $this->assertEquals(4, $httpClientChild->getTotalNumberOfConnections());
    }

    private function assertConnectionHeader($response, $expected)
    {
        $connectionHeader = $response->getHeaders()['Connection'];
        if (is_array($connectionHeader)) {
            $connectionHeader = implode(',', $connectionHeader);
        }
        $this->assertStringContainsString($expected, $connectionHeader);
    }

    public function testConnectionReuseForMultipleDomains()
    {
        $httpClientChild = new HttpClientChild();
        $url1 = "http://localhost:8000/get";
        $url2 = "http://localhost:8000/t/cedqp-1655183385";
        $url3 = "http://localhost:8000/en2hoq5smpha9.x.pipedream.net";

        $httpClientChild->execute(new Request($url1));
        $httpClientChild->execute(new Request($url2));
        $httpClientChild->execute(new Request($url3));
        $this->assertEquals(3, $httpClientChild->getTotalNumberOfConnections());

        $httpClientChild->execute(new Request($url1));
        $httpClientChild->execute(new Request($url2));
        $httpClientChild->execute(new Request($url3));
        $this->assertEquals(6, $httpClientChild->getTotalNumberOfConnections());
    }

    public function testSetMashapeKey()
    {
        $httpClient = new HttpClient(Configuration::init()->defaultHeader('x-mashape-key', 'abcd'));
        $response = $httpClient->execute(new Request('http://localhost:8000/request'));
        $this->assertEquals('abcd', $response->getBody()->headers->{'x-mashape-key'});
    }

    public function testGzip()
    {
        $response = $this->executeRequest('http://localhost:8000/gzip', RequestMethod::POST);
        $this->assertEquals('gzip', $response->getHeaders()['Content-Encoding']);
    }

    public function testBasicAuthentication()
    {
        $httpClient = new HttpClient(Configuration::init()->auth('user', 'password'));
        $response = $httpClient->execute(new Request('http://localhost:8000/request'));
        $this->assertEquals('Basic dXNlcjpwYXNzd29yZA==', $response->getBody()->headers->authorization);
    }

    public function testCustomHeaders()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::GET, [
            'user-agent' => 'unirest-php',
        ]);
        $this->assertEquals('unirest-php', $response->getBody()->headers->{'user-agent'});
    }

    public function testGet()
    {
        $response = $this->executeRequest('http://localhost:8000/request?name=Mark', RequestMethod::GET, [
            'Accept' => 'application/json'
        ], [
            'nick' => 'thefosk'
        ]);
        $this->assertEquals('Mark', $response->getBody()->queryString->name);
        $this->assertEquals('thefosk', $response->getBody()->queryString->nick);
    }

    public function testGetMultidimensionalArray()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::GET, [
            'Accept' => 'application/json'
        ], [
            'key' => 'value',
            'items' => ['item1', 'item2']
        ]);
        $this->assertEquals('value', $response->getBody()->queryString->key);
        $this->assertEquals('item1', $response->getBody()->queryString->{'items[0]'});
        $this->assertEquals('item2', $response->getBody()->queryString->{'items[1]'});
    }

    public function testGetWithDots()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::GET, [
            'Accept' => 'application/json'
        ], [
            'user.name' => 'Mark',
            'nick' => 'thefosk'
        ]);
        $this->assertEquals('Mark', $response->getBody()->queryString->{'user.name'});
        $this->assertEquals('thefosk', $response->getBody()->queryString->nick);
    }

    public function testGetWithDotsAlt()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::GET, [
            'Accept' => 'application/json'
        ], [
            'user.name' => 'Mark Bond',
            'nick' => 'thefosk'
        ]);
        $this->assertEquals('Mark Bond', $response->getBody()->queryString->{'user.name'});
        $this->assertEquals('thefosk', $response->getBody()->queryString->nick);
    }

    public function testGetWithEqualSign()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::GET, [
            'Accept' => 'application/json'
        ], ['name' => 'Mark=Hello']);
        $this->assertEquals('Mark=Hello', $response->getBody()->queryString->name);
    }

    public function testGetWithEqualSignAlt()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::GET, [
            'Accept' => 'application/json'
        ], ['name' => 'Mark=Hello=John']);
        $this->assertEquals('Mark=Hello=John', $response->getBody()->queryString->name);
    }

    public function testGetWithComplexQuery()
    {
        $response = $this->executeRequest(
            'http://localhost:8000/request?query=[{"type":"/music/album","name":null,"artist":' .
            '{"id":"/en/bob_dylan"},"limit":3}]&cursor'
        );
        $this->assertEquals('', $response->getBody()->queryString->cursor);
        $this->assertEquals(
            '[{"type":"/music/album","name":null,"artist":{"id":"/en/bob_dylan"},"limit":3}]',
            $response->getBody()->queryString->query
        );
    }

    public function testGetArray()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::GET, [], [
            'name[0]' => 'Mark',
            'name[1]' => 'John'
        ]);
        $this->assertEquals('Mark', $response->getBody()->queryString->{'name[0]'});
        $this->assertEquals('John', $response->getBody()->queryString->{'name[1]'});
    }

    public function testHead()
    {
        $response = $this->executeRequest('http://localhost:8000/request?name=Mark', RequestMethod::HEAD, [
            'Accept' => 'application/json'
        ]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPost()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json'
        ], [
            'name' => 'Mark',
            'nick' => 'thefosk'
        ]);
        $this->assertEquals('Mark', $response->getBody()->postData->params->name);
        $this->assertEquals('thefosk', $response->getBody()->postData->params->nick);
    }

    public function testPostForm()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json'
        ], Body::Form([
            'name' => 'Mark',
            'nick' => 'thefosk'
        ]));
        $this->assertEquals('application/x-www-form-urlencoded', $response->getBody()->headers->{'content-type'});
        $this->assertEquals('Mark', $response->getBody()->postData->params->name);
    }

    public function testPostMultipart()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json',
        ], Body::Multipart([
            'name' => 'Mark',
            'nick' => 'thefosk'
        ]));
        $contentType = explode(';', $response->getBody()->headers->{'content-type'})[0];
        $this->assertEquals('multipart/form-data', trim($contentType));
        $this->assertEquals('Mark', $response->getBody()->postData->params->name);
    }

    public function testPostWithEqualSign()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json'
        ], Body::Form(['name' => 'Mark=Hello']));
        $this->assertEquals('Mark=Hello', $response->getBody()->postData->params->name);
    }

    public function testPostArray()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json'
        ], [
            'name[0]' => 'Mark',
            'name[1]' => 'John'
        ]);
        $this->assertEquals('Mark', $response->getBody()->postData->params->name[0]);
        $this->assertEquals('John', $response->getBody()->postData->params->name[1]);
    }

    public function testPostWithDots()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json'
        ], [
            'user.name' => 'Mark',
            'nick' => 'thefosk'
        ]);
        $this->assertEquals('Mark', $response->getBody()->postData->params->user_name);
        $this->assertEquals('thefosk', $response->getBody()->postData->params->nick);
    }

    public function testRawPost()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ], json_encode(['author' => 'Sam Sullivan']));
        $this->assertEquals('Sam Sullivan', json_decode($response->getBody()->postData->text)->author);
    }

    public function testPostMultidimensionalArray()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json'
        ], Body::Form([
            'key' => 'value',
            'items' => ['item1', 'item2']
        ]));
        $this->assertEquals('value', $response->getBody()->postData->params->key);
        $this->assertEquals('item1', $response->getBody()->postData->params->items[0]);
        $this->assertEquals('item2', $response->getBody()->postData->params->items[1]);
    }

    public function testPut()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::PUT, [
            'Accept' => 'application/json'
        ], [
            'name' => 'Mark',
            'nick' => 'thefosk'
        ]);
        $postData = $response->getBody()->postData->text;
        $this->assertStringContainsString('name', $postData);
        $this->assertStringContainsString('Mark', $postData);
        $this->assertStringContainsString('nick', $postData);
        $this->assertStringContainsString('thefosk', $postData);
    }

    public function testPatch()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::PATCH, [
            'Accept' => 'application/json'
        ], [
            'name' => 'Mark',
            'nick' => 'thefosk'
        ]);
        $postData = $response->getBody()->postData->text;
        $this->assertStringContainsString('name', $postData);
        $this->assertStringContainsString('Mark', $postData);
        $this->assertStringContainsString('nick', $postData);
        $this->assertStringContainsString('thefosk', $postData);
    }

    public function testDelete()
    {
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::DELETE, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded'
        ], [
            'name' => 'Mark',
            'nick' => 'thefosk'
        ]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpload()
    {
        $fixture = __DIR__ . '/Mocking/upload.txt';
        $response = $this->executeRequest(
            'http://localhost:8000/request',
            RequestMethod::POST,
            ['Accept' => 'application/json'],
            Body::multipart(['name' => 'ahmad'], ['file' => $fixture])
        );
        $this->assertEquals('ahmad', $response->getBody()->postData->params->name);
        $this->assertEquals('This is a test', trim($response->getBody()->postData->params->file));
    }

    public function testUploadWithoutHelper()
    {
        $fixture = __DIR__ . '/Mocking/upload.txt';
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json'
        ], [
            'name' => 'Mark',
            'file' => Body::File($fixture)
        ]);
        $this->assertEquals('Mark', $response->getBody()->postData->params->name);
        $this->assertEquals('This is a test', trim($response->getBody()->postData->params->file));
    }

    public function testUploadIfFilePartOfData()
    {
        $fixture = __DIR__ . '/Mocking/upload.txt';
        $response = $this->executeRequest('http://localhost:8000/request', RequestMethod::POST, [
            'Accept' => 'application/json'
        ], [
            'name' => 'Mark',
            'files[owl.gif]' => Body::File($fixture)
        ]);
        $this->assertEquals('Mark', $response->getBody()->postData->params->name);
        $this->assertEquals('This is a test', trim($response->getBody()->postData->params->files[0]));
    }
}
