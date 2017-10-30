<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Proxy\Test;

use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

class ProxyServerTest extends TestCase
{

    // default internal Alaress proxy test settings
    const HOST = '192.168.1.50';
    const PORT = '8888';
    const USER = 'test';
    const PASS = 'test123';

    const HTTP_URL = 'http://graph.facebook.com/oauth';
    const HTTPS_URL = 'https://graph.facebook.com/oauth';

    const EXPECTED_RESPONSE = '{"about":"OAuth is a simple way to publish and interact with protected data. Learn more about OAuth: http:\/\/oauth.net\/","awards":"\u2022 CNET Webware 100 award in the Editors\u2019 Choice Most Important Technology category\n\u2022 Best New\/Improved Standard in IAM & GRC, European Identity Conference","category":"Internet\/software","description":"OAuth is a simple way to publish and interact with protected data. It\'s also a safer and more secure way for people to give you access. We\'ve kept it simple to save you time.","founded":"2007","is_published":true,"mission":"In developing OAuth, we sought to invent as little as possible, following the Microformats approach to pave existing cowpaths and relying on conventions already established in protocols like Google\u2019s AuthSub, aol\u2019s OpenAuth, Yahoo\u2019s BBAuth and FlickrAuth and Facebook\u2019s FacebookAuth.\n\nWhile we wanted the best protocol we could design, we also wanted one that people would use and that would be compatible with existing authentication methods, inherit from existing RFCs and reuse web standards wherever possible. In this way, we ended up leaving a lot out of the spec that seemed interesting but ultimately didn\u2019t belong.\n\nThe protocol is flexible in that it is adjustable to the actual security needs of different sites, and extensible through different signing algorithms and security features. It is also designed to work on mobile devices and explicitly supports desktop applications.\n\nFinally, we worked hard to keep our focus on real-world scenarios and to be conscious of straying towards the impractical or utterly theoretical. Our discipline resulted in a taut protocol that can be easily read and implemented without a great deal of effort. To support this end, we hope to offer a number of libraries that will greatly reduce the amount of work necessary to Do the Right Thing \u2122 or to migrate from existing api protocols.","products":"OAuth 1.0\nOAuth 2.0","talking_about_count":178,"username":"oauth","website":"http:\/\/oauth.net\/","were_here_count":0,"id":"28729009753","name":"OAuth","link":"http:\/\/www.facebook.com\/oauth","likes":6132}';
    const EXPECTED_RESPONSE_SUBSTR_64 = '{"about":"OAuth is a simple way to publish and interact with pro';

    const CURL_DNC = 0;
    const HTTP_OK = 200;
    const HTTP_PROXY_DENY = 407;


    /**
     * @var \Proxy\Server
     */
    protected $proxyServer;

    protected function setUp()
    {
        $this->markTestSkipped('Proxy Server tests are currently skipped due to external dependencies');
    }

    protected function tearDown()
    {
        parent::tearDown();
        // destroy the object so another test can't accidentally use it
        unset($this->proxyServer);
    }


    /**
     * When a custom config loader is imported, its settings override the Config singleton
     */
    public function testDependencyInjection()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('proxy.alaress.com.au', '8080',
            'matt.day', 'l4mp'));

        $this->assertEquals('proxy.alaress.com.au:8080', $this->proxyServer->getURI());
        $this->assertEquals(base64_encode('matt.day:l4mp'), $this->proxyServer->getUserPwd(true));
    }

    /**
     * When Host and Port are set, usesProxy() returns true
     */
    public function testUsingProxy()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, '', ''));
        $this->assertTrue($this->proxyServer->usesProxy());
    }

    /**
     * When Host and Port are unset, usesProxy() returns false
     */
    public function testNoProxy()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('', '', '', ''));
        $this->assertFalse($this->proxyServer->usesProxy());
    }

    /**
     * When Username and Password are not provided, connection is anonymous
     */
    public function testIsAnonymous()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, '', ''));
        $this->assertTrue($this->proxyServer->isAnonymous());
    }

    /**
     * When Username and Password are provided, connection is authenticated
     */
    public function testIsAuthenticated()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, self::USER,
            self::PASS));
        $this->assertTrue($this->proxyServer->isAuthenticated());

    }


    /**
     * Direct HTTP connection using cURL returns a 200 code with content
     */
    public function testDirectHttpCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('', '', '', ''));
        $data = $this->curl(false);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Direct HTTP connection using streams returns a 200 code with content
     */
    public function testDirectHttpFopen()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('', '', '', ''));
        $data = $this->fopen(false);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Direct HTTPS connection using cURL returns a 200 code with content
     */
    public function testDirectHttpsCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('', '', '', ''));
        $data = $this->curl(true);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Direct HTTP connection using streams returns a 200 code with content
     */
    public function testDirectHttpsFopen()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('', '', '', ''));
        $data = $this->fopen(true);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }


    /**
     * HTTP connection using an invalid server using cURL fails to connect to host
     */
    public function testInvalidServerHttpCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('192.168.1.200', '8080', '', ''));
        $data = $this->curl(false);

        $this->assertEquals(self::CURL_DNC, $data['code']);
    }

    /**
     * HTTP connection using an invalid server using streams fails to connect to host
     *
     * @expectedException Warning
     */
    public function testInvalidServerHttpFopen()
    {

        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('192.168.1.200', '8080', '', ''));
        $data = $this->fopen(false);

        $this->assertEquals(self::CURL_DNC, $data['code']);
    }

    /**
     * HTTPS connection using an invalid server using cURL fails to connect to host
     */
    public function testInvalidServerHttpsCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('192.168.1.200', '8080', '', ''));
        $data = $this->curl(true);

        $this->assertEquals(self::CURL_DNC, $data['code']);
    }

    /**
     * HTTPS connection using an invalid server using streams fails to connect to host
     *
     * @expectedException Warning
     */
    public function testInvalidServerHttpsFopen()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader('192.168.1.200', '8080', '', ''));
        $data = $this->fopen(true);

        $this->assertEquals(self::CURL_DNC, $data['code']);
    }

    /**
     * Anonymous proxied HTTP connection using cURL returns a 200 code with content
     */
    public function testAnonymousHttpCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, '', ''));
        $data = $this->curl(false);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Anonymous proxied HTTP connection using streams returns a 200 code with content
     */
    public function testAnonymousHttpFopen()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, '', ''));
        $data = $this->fopen(false);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Anonymous proxied HTTPS connection using cURL returns a 200 code with content
     */
    public function testAnonymousHttpsCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, '', ''));
        $data = $this->curl(true);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Anonymous proxied HTTPS connection using streams returns a 200 code with content
     */
    public function testAnonymousHttpsFopen()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, '', ''));
        $data = $this->fopen(true);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Unauthorised proxy HTTP connection using cURL returns a 407 code with proxy response
     */
    public function testUnauthorizedHttpCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, 'fail', 'fail'));
        $data = $this->curl(false);

        $this->assertEquals(self::HTTP_PROXY_DENY, $data['code']);
    }

    /**
     * Unauthorised proxy HTTP connection using streams returns a 407 code with proxy response
     *
     * @expectedException Warning
     */
    public function testUnauthorizedHttpFopen()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, 'fail', 'fail'));
        $data = $this->fopen(false);

        $this->assertEquals(self::HTTP_PROXY_DENY, $data['code']);
    }

    /**
     * Unauthorised proxy HTTPS connection using cURL returns a 407 code with proxy response
     */
    public function testUnauthorizedHttpsCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, 'fail', 'fail'));
        $data = $this->curl(true);

        $this->assertEquals(self::CURL_DNC, $data['code']);
    }

    /**
     * Unauthorised proxy HTTPS connection using streams returns a 407 code with proxy response
     *
     * @expectedException Warning
     */
    public function testUnauthorizedHttpsFopen()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, 'fail', 'fail'));
        $data = $this->fopen(true);

        $this->assertEquals(self::HTTP_PROXY_DENY, $data['code']);
    }

    /**
     * Authorised proxy HTTP connection using cURL returns a 200 code with content
     */
    public function testAuthorizedHttpCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, self::USER,
            self::PASS));
        $data = $this->curl(false);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Authorised proxy HTTP connection using streams returns a 200 code with content
     */
    public function testAuthorizedHttpFopen()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, self::USER,
            self::PASS));
        $data = $this->fopen(false);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Authorised proxy HTTPS connection using cURL returns a 200 code with content
     */
    public function testAuthorizedHttpsCurl()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, self::USER,
            self::PASS));
        $data = $this->curl(true);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }

    /**
     * Authorised proxy HTTPS connection using streams returns a 200 code with content
     */
    public function testAuthorizedHttpsFopen()
    {
        $this->proxyServer = new \Proxy\Server(new ProxyServerTestConfigLoader(self::HOST, self::PORT, self::USER,
            self::PASS));
        $data = $this->fopen(true);

        $this->assertEquals(self::HTTP_OK, $data['code']);
        $this->assertEquals(self::EXPECTED_RESPONSE_SUBSTR_64, substr($data['response'], 0, 64));
    }


    protected function curl($https = false)
    {
        $ch = $this->proxyServer->curl_init($https ? self::HTTPS_URL : self::HTTP_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            'code'     => (int)$code,
            'response' => $response,
        ];
    }

    protected function fopen($https = false)
    {
        $ctx = $this->proxyServer->getContext($https ? 'https' : 'http');
        if ($ctx) {
            $fp = fopen($https ? self::HTTPS_URL : self::HTTP_URL, 'r', false, $ctx);
        } else {
            $fp = fopen($https ? self::HTTPS_URL : self::HTTP_URL, 'r');
        }

        $response = '';
        while ($line = fgets($fp)) {
            $response .= $line;
        }
        fclose($fp);

        preg_match('`[0-9]{3}`', $http_response_header[0], $matches);
        $code = $matches[0];

        return [
            'code'     => (int)$code,
            'response' => $response,
        ];

    }
}
