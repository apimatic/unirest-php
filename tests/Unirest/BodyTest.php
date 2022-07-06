<?php

namespace Unirest\Request\Body\Test;

use Unirest\Request as Request;
use Unirest\Request\Body as Body;

require_once __DIR__ . '/../../src/Unirest.php';

class BodyTest extends \PHPUnit\Framework\TestCase
{
    private $request;
    private $body;

    private function initializeDependencies()
    {
        $this->request = new Request();
        $this->body = new Body($this->request);
    }

    public function testCURLFile()
    {
        $this->initializeDependencies();

        $fixture = __DIR__ . '/fixtures/upload.txt';

        $file = $this->body->File($fixture);

        if (PHP_MAJOR_VERSION === 5 && PHP_MINOR_VERSION === 4) {
            $this->assertEquals($file, sprintf('@%s;filename=%s;type=', $fixture, basename($fixture)));
        } else {
            $this->assertTrue($file instanceof \CURLFile);
        }
    }

    public function testHttpBuildQueryWithCurlFile()
    {
        $this->initializeDependencies();

        $fixture = __DIR__ . '/fixtures/upload.txt';

        $file = $this->body->File($fixture);
        $body = array(
            'to' => 'mail@mailinator.com',
            'from' => 'mail@mailinator.com',
            'file' => $file
        );

        $result = $this->request->buildHTTPCurlQuery($body);
        $this->assertEquals($result['file'], $file);
    }

    public function testJson()
    {
        $this->initializeDependencies();

        $body = $this->body->Json(array('foo', 'bar'));

        $this->assertEquals($body, '["foo","bar"]');
    }

    public function testForm()
    {
        $this->initializeDependencies();

        $body = $this->body->Form(array('foo' => 'bar', 'bar' => 'baz'));

        $this->assertEquals($body, 'foo=bar&bar=baz');

        // try again with a string
        $body = $this->body->Form($body);

        $this->assertEquals($body, 'foo=bar&bar=baz');
    }

    public function testMultipart()
    {
        $this->initializeDependencies();

        $arr = array('foo' => 'bar', 'bar' => 'baz');

        $body = $this->body->Multipart((object) $arr);

        $this->assertEquals($body, $arr);

        $body = $this->body->Multipart('flat');

        $this->assertEquals($body, array('flat'));
    }

    public function testMultipartFiles()
    {
        $this->initializeDependencies();

        $fixture = __DIR__ . '/fixtures/upload.txt';

        $data = array('foo' => 'bar', 'bar' => 'baz');
        $files = array('test' => $fixture);

        $body = $this->body->Multipart($data, $files);

        $this->assertEquals($body, array(
            'foo' => 'bar',
            'bar' => 'baz',
            'test' => $this->body->File($fixture)
        ));
    }
}
