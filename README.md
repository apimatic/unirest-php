# Unirest for PHP

[![version][packagist-version]][packagist-url]
[![Downloads][packagist-downloads]][packagist-url]
[![Tests](https://github.com/apimatic/unirest-php/actions/workflows/php.yml/badge.svg)](https://github.com/apimatic/unirest-php/actions/workflows/php.yml)
[![License][packagist-license]][license-url]

Unirest is a set of lightweight HTTP libraries available in [multiple languages](http://unirest.io).

This fork is maintained by [APIMatic](https://www.apimatic.io) for its Code Generator as a Service.

## Features

* Utility methods to call `GET`, `HEAD`, `POST`, `PUT`, `DELETE`, `CONNECT`, `OPTIONS`, `TRACE`, `PATCH` requests
* Supports form parameters, file uploads and custom body entities
* Supports gzip
* Supports Basic, Digest, Negotiate, NTLM Authentication natively
* Customizable timeout
* Customizable default headers for every request (DRY)
* Automatic JSON parsing into a native object for JSON responses

## Requirements

- PHP 5.6+
- PHP Curl extension

## Installation

To install `apimatic/unirest-php` with Composer, just add the following to your `composer.json` file:

```json
{
    "require": {
        "apimatic/unirest-php": "^3.0.1"
    }
}
```

or by running the following command:

```shell
composer require apimatic/unirest-php
```

## Usage

### Creating a Request
You can create a variable at class level and instantiate it with an instance of `Request`, like:

```php
private $request = new \Unirest\Request(); 
```
And then you can simply use the publicly exposed methods on that instance.

Let's look at a working example:

```php
$headers = array('Accept' => 'application/json');
$query = array('foo' => 'hello', 'bar' => 'world');

$response = $this->request->post('http://mockbin.com/request', $headers, $query);

$response->code;        // HTTP Status code
$response->headers;     // Headers
$response->body;        // Parsed body
$response->raw_body;    // Unparsed body
```

### JSON Requests *(`application/json`)*

A JSON Request can be constructed using the `Unirest\Request\Body::Json` helper:

```php
$headers = array('Accept' => 'application/json');
$data = array('name' => 'ahmad', 'company' => 'mashape');

$body = Unirest\Request\Body::Json($data);

$response = $this->request->post('http://mockbin.com/request', $headers, $body);
```

**Notes:**
- `Content-Type` headers will be automatically set to `application/json` 
- the data variable will be processed through [`json_encode`](http://php.net/manual/en/function.json-encode.php) with default values for arguments.
- an error will be thrown if the [JSON Extension](http://php.net/manual/en/book.json.php) is not available.

### Form Requests *(`application/x-www-form-urlencoded`)*

A typical Form Request can be constructed using the `Unirest\Request\Body::Form` helper:

```php
$headers = array('Accept' => 'application/json');
$data = array('name' => 'ahmad', 'company' => 'mashape');

$body = Unirest\Request\Body::Form($data);

$response = $this->request->post('http://mockbin.com/request', $headers, $body);
```

**Notes:** 
- `Content-Type` headers will be automatically set to `application/x-www-form-urlencoded`
- the final data array will be processed through [`http_build_query`](http://php.net/manual/en/function.http-build-query.php) with default values for arguments.

### Multipart Requests *(`multipart/form-data`)*

A Multipart Request can be constructed using the `Unirest\Request\Body::Multipart` helper:

```php
$headers = array('Accept' => 'application/json');
$data = array('name' => 'ahmad', 'company' => 'mashape');

$body = Unirest\Request\Body::Multipart($data);

$response = $this->request->post('http://mockbin.com/request', $headers, $body);
```

**Notes:** 

- `Content-Type` headers will be automatically set to `multipart/form-data`.
- an auto-generated `--boundary` will be set.

### Multipart File Upload

simply add an array of files as the second argument to to the `Multipart` helper:

```php
$headers = array('Accept' => 'application/json');
$data = array('name' => 'ahmad', 'company' => 'mashape');
$files = array('bio' => '/path/to/bio.txt', 'avatar' => '/path/to/avatar.jpg');

$body = Unirest\Request\Body::Multipart($data, $files);

$response = $this->request->post('http://mockbin.com/request', $headers, $body);
 ```

If you wish to further customize the properties of files uploaded you can do so with the `Unirest\Request\Body::File` helper:

```php
$headers = array('Accept' => 'application/json');
$body = array(
    'name' => 'ahmad', 
    'company' => 'mashape'
    'bio' => Unirest\Request\Body::File('/path/to/bio.txt', 'text/plain'),
    'avatar' => Unirest\Request\Body::File('/path/to/my_avatar.jpg', 'text/plain', 'avatar.jpg')
);

$response = $this->request->post('http://mockbin.com/request', $headers, $body);
 ```

**Note**: we did not use the `Unirest\Request\Body::multipart` helper in this example, it is not needed when manually adding files.
 
### Custom Body

Sending a custom body such rather than using the `Unirest\Request\Body` helpers is also possible, for example, using a [`serialize`](http://php.net/manual/en/function.serialize.php) body string with a custom `Content-Type`:

```php
$headers = array('Accept' => 'application/json', 'Content-Type' => 'application/x-php-serialized');
$body = serialize((array('foo' => 'hello', 'bar' => 'world'));

$response = $this->request->post('http://mockbin.com/request', $headers, $body);
```

### Authentication

First, if you are using [Mashape][mashape-url]:
```php
// Mashape auth
$this->request->setMashapeKey('<mashape_key>');
```

Otherwise, passing a username, password *(optional)*, defaults to Basic Authentication:

```php
// basic auth
$this->request->auth('username', 'password');
```

The third parameter, which is a bitmask, will Unirest which HTTP authentication method(s) you want it to use for your proxy authentication.

If more than one bit is set, Unirest *(at PHP's libcurl level)* will first query the site to see what authentication methods it supports and then pick the best one you allow it to use. *For some methods, this will induce an extra network round-trip.*

**Supported Methods**

| Method               | Description                                                                                                                                                                                                     |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `CURLAUTH_BASIC`     | HTTP Basic authentication. This is the default choice                                                                                                                                                           | 
| `CURLAUTH_DIGEST`    | HTTP Digest authentication. as defined in [RFC 2617](http://www.ietf.org/rfc/rfc2617.txt)                                                                                                                       | 
| `CURLAUTH_DIGEST_IE` | HTTP Digest authentication with an IE flavor. *The IE flavor is simply that libcurl will use a special "quirk" that IE is known to have used before version 7 and that some servers require the client to use.* | 
| `CURLAUTH_NEGOTIATE` | HTTP Negotiate (SPNEGO) authentication. as defined in [RFC 4559](http://www.ietf.org/rfc/rfc4559.txt)                                                                                                           |
| `CURLAUTH_NTLM`      | HTTP NTLM authentication. A proprietary protocol invented and used by Microsoft.                                                                                                                                |
| `CURLAUTH_NTLM_WB`   | NTLM delegating to winbind helper. Authentication is performed by a separate binary application. *see [libcurl docs](http://curl.haxx.se/libcurl/c/CURLOPT_HTTPAUTH.html) for more info*                        | 
| `CURLAUTH_ANY`       | This is a convenience macro that sets all bits and thus makes libcurl pick any it finds suitable. libcurl will automatically select the one it finds most secure.                                               |
| `CURLAUTH_ANYSAFE`   | This is a convenience macro that sets all bits except Basic and thus makes libcurl pick any it finds suitable. libcurl will automatically select the one it finds most secure.                                  |
| `CURLAUTH_ONLY`      | This is a meta symbol. OR this value together with a single specific auth value to force libcurl to probe for un-restricted auth and if not, only that single auth algorithm is acceptable.                     |

```php
// custom auth method
$this->request->proxyAuth('username', 'password', CURLAUTH_DIGEST);
```

Previous versions of **Unirest** support *Basic Authentication* by providing the `username` and `password` arguments:

```php
$response = $this->request->get('http://mockbin.com/request', null, null, 'username', 'password');
```

**This has been deprecated, and will be completely removed in `v.4.0.0` please use the `Unirest\Request::auth()` method instead**

### Cookies

Set a cookie string to specify the contents of a cookie header. Multiple cookies are separated with a semicolon followed by a space (e.g., "fruit=apple; colour=red")

```php
$this->request->cookie($cookie)
```

Set a cookie file path for enabling cookie reading and storing cookies across multiple sequence of requests.

```php
$this->request->cookieFile($cookieFile)
```

`$cookieFile` must be a correct path with write permission.

### Request Object

```php
$this->request->get($url, $headers = array(), $parameters = null);
$this->request->post($url, $headers = array(), $body = null);
$this->request->put($url, $headers = array(), $body = null);
$this->request->patch($url, $headers = array(), $body = null);
$this->request->delete($url, $headers = array(), $body = null);
```
  
- `url` - Endpoint, address, or uri to be acted upon and requested information from.
- `headers` - Request Headers as associative array or object
- `body` - Request Body as associative array or object

You can send a request with any [standard](http://www.iana.org/assignments/http-methods/http-methods.xhtml) or custom HTTP Method:

```php
$this->request->send(Unirest\Method::LINK, $url, $headers = array(), $body);

$this->request->send('CHECKOUT', $url, $headers = array(), $body);
```

### Response Object

Upon receiving a response Unirest returns the result in the form of an Object, this object should always have the same keys for each language regarding to the response details.

- `code` - HTTP Response Status Code (Example `200`)
- `headers` - HTTP Response Headers
- `body` - Parsed response body where applicable, for example JSON responses are parsed to Objects / Associative Arrays.
- `raw_body` - Un-parsed response body

### Advanced Configuration

You can set some advanced configuration to tune Unirest-PHP:

#### Custom JSON Decode Flags

Unirest uses PHP's [JSON Extension](http://php.net/manual/en/book.json.php) for automatically decoding JSON responses.
sometime you may want to return associative arrays, limit the depth of recursion, or use any of the [customization flags](http://php.net/manual/en/json.constants.php).

To do so, simply set the desired options using the `jsonOpts` request method:

```php
$this->request->jsonOpts(true, 512, JSON_NUMERIC_CHECK & JSON_FORCE_OBJECT & JSON_UNESCAPED_SLASHES);
```

#### Timeout

You can set a custom timeout value (in **seconds**):

```php
$this->request->timeout(5); // 5s timeout
```

#### Retries Related

To enable retries feature:

```php
$this->request->enableRetries(true);
```

To set max number of retries:

```php
$this->request->maxNumberOfRetries(10);
```

Should we retry on timeout:

```php
$this->request->retryOnTimeout(false);
```

Initial retry interval in seconds:

```php
$this->request->retryInterval(20);
```

Maximum retry wait time:

```php
$this->request->maximumRetryWaitTime(30);
```

Backoff factor to be used to increase retry interval:

```php
$this->request->backoffFactor(1.1);
```

Http status codes to retry against:

```php
$this->request->httpStatusCodesToRetry([400,401]);
```

Http methods to retry against:

```php
$this->request->httpMethodsToRetry(['POST']);
```

#### Proxy

Set the proxy to use for the upcoming request.

you can also set the proxy type to be one of `CURLPROXY_HTTP`, `CURLPROXY_HTTP_1_0`, `CURLPROXY_SOCKS4`, `CURLPROXY_SOCKS5`, `CURLPROXY_SOCKS4A`, and `CURLPROXY_SOCKS5_HOSTNAME`.

*check the [cURL docs](http://curl.haxx.se/libcurl/c/CURLOPT_PROXYTYPE.html) for more info*.

```php
// quick setup with default port: 1080
$this->request->proxy('10.10.10.1');

// custom port and proxy type
$this->request->proxy('10.10.10.1', 8080, CURLPROXY_HTTP);

// enable tunneling
$this->request->proxy('10.10.10.1', 8080, CURLPROXY_HTTP, true);
```

##### Proxy Authentication

Passing a username, password *(optional)*, defaults to Basic Authentication:

```php
// basic auth
$this->request->proxyAuth('username', 'password');
```

The third parameter, which is a bitmask, will Unirest which HTTP authentication method(s) you want it to use for your proxy authentication. 

If more than one bit is set, Unirest *(at PHP's libcurl level)* will first query the site to see what authentication methods it supports and then pick the best one you allow it to use. *For some methods, this will induce an extra network round-trip.*

See [Authentication](#authentication) for more details on methods supported.

```php
// basic auth
$this->request->proxyAuth('username', 'password', CURLAUTH_DIGEST);
```

#### Default Request Headers

You can set default headers that will be sent on every request:

```php
$this->request->defaultHeader('Header1', 'Value1');
$this->request->defaultHeader('Header2', 'Value2');
```

You can set default headers in bulk by passing an array:

```php
$this->request->defaultHeaders(array(
    'Header1' => 'Value1',
    'Header2' => 'Value2'
));
```

You can clear the default headers anytime with:

```php
$this->request->clearDefaultHeaders();
```

#### Default cURL Options

You can set default [cURL options](http://php.net/manual/en/function.curl-setopt.php) that will be sent on every request:

```php
$this->request->curlOpt(CURLOPT_COOKIE, 'foo=bar');
```

You can set options bulk by passing an array:

```php
$this->request->curlOpts(array(
    CURLOPT_COOKIE => 'foo=bar'
));
```

You can clear the default options anytime with:

```php
$this->request->clearCurlOpts();
```

#### SSL validation

You can explicitly enable or disable SSL certificate validation when consuming an SSL protected endpoint:

```php
$this->request->verifyPeer(false); // Disables SSL cert validation
```

By default is `true`.

#### Utility Methods

```php
// alias for `curl_getinfo`
$this->request->getInfo();

// returns internal cURL handle
$this->request->getCurlHandle();
```

----

Made with &#9829; from the [Mashape][mashape-url] team

[mashape-url]: https://www.mashape.com/

[license-url]: https://github.com/apimatic/unirest-php/blob/master/LICENSE

[travis-url]: https://travis-ci.org/apimatic/unirest-php
[travis-image]: https://img.shields.io/travis/apimatic/unirest-php.svg?style=flat

[packagist-url]: https://packagist.org/packages/apimatic/unirest-php
[packagist-license]: https://img.shields.io/packagist/l/apimatic/unirest-php.svg?style=flat
[packagist-version]: https://img.shields.io/packagist/v/apimatic/unirest-php.svg?style=flat
[packagist-downloads]: https://img.shields.io/packagist/dm/apimatic/unirest-php.svg?style=flat
