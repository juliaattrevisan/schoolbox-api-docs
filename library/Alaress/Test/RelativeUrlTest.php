<?php

namespace Alaress\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Url;

/**
 * RelativeUrlTest
 *
 * Test cases for Url class. In each of the tests, you can
 * override with domain, http_host and hostname with whatever
 * values you require, so you don't need to use Cfg
 */
class RelativeUrlTest extends TestCase
{
    // Test __construct()
    //
    //    /**
    //     * @expectedException DomainException
    //     * TODO Uncomment for PHP7
    //     */
    //    public function testNoSiteHostImplicit()
    //    {
    //        $input = 'www.example.com';
    //        new Url($input);
    //    }
    //
    //    /**
    //     * @expectedException DomainException
    //     * TODO Uncomment for PHP7
    //     */
    //    public function testNoSiteHostDefaultImplicit()
    //    {
    //        $site = null;
    //        $input = 'www.example.com';
    //        new Url($input, $site);
    //    }
    /**
     * @param mixed $url
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_invalidUrlType')]
    public function test_constructor_invalidUrlType($url)
    {
        $this->expectException(InvalidArgumentException::class);
        $siteHost = 'www.example.com';
        new Url($url, $siteHost);
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validUrlType')]
    public function test_constructor_validUrlType($url)
    {
        $siteHost = 'www.example.com';
        new Url($url, $siteHost);
        $this->assertTrue(true);
    }

    /**
     * @param string|null $siteHost
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validSiteHost')]
    public function test_constructor_validSiteHost($siteHost)
    {
        $url = "http://google.com";
        new Url($url, $siteHost);
        $this->assertTrue(true);
    }

    public function testNoSiteHostExplicit()
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            unset($_SERVER['HTTP_HOST']);
        }

        $input = 'www.example.com';
        $url = new Url($input, false);

        // We should be able to retrieve the hostName with no siteHost or serverHost context
        $this->assertEquals($input, $url->getHostname());
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable')]
    public function testIsParsableUrl($url)
    {
        $urlFormatter = new Url($url, 'www.example.com');
        $isLocalUrl = $urlFormatter->isLocalUrl();
        $isUrl = $urlFormatter->isUrl();
        $urlFormatter->prependScheme();
        $isUrlAfterProtocolPrepended = $urlFormatter->isUrl();
        $isParsableUrl = $isLocalUrl || $isUrl || $isUrlAfterProtocolPrepended;
        $this->assertTrue($isParsableUrl, "Failed to assert that '$url' is a parsable URL");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_notParsable')]
    public function testNotParsableUrl($url)
    {
        $urlFormatter = new Url($url, 'www.example.com');
        $isLocalUrl = $urlFormatter->isLocalUrl();
        $isUrl = $urlFormatter->isUrl();
        $urlFormatter->prependScheme();
        $isUrlAfterProtocolPrepended = $urlFormatter->isUrl();
        $isParsableUrl = $isLocalUrl || $isUrl || $isUrlAfterProtocolPrepended;
        $this->assertFalse($isParsableUrl, "'$url' is a parsable URL");
    }


    // Test isUrl()
    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_isUrl')]
    public function testIsUrl($url)
    {
        $siteHost = self::getLocalSiteHost();
        $urlFormatter = new Url($url, $siteHost);
        $this->assertTrue($urlFormatter->isUrl(), "Failed asserting that '$url' is a Url");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_notUrl')]
    public function testIsNotUrl($url)
    {
        $siteHost = self::getLocalSiteHost();
        $urlFormatter = new Url($url, $siteHost);
        $this->assertFalse($urlFormatter->isUrl(), "Failed asserting that '$url' is not a Url");
    }

    // Test getHostname()
    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_cannotParseHostname')]
    public function testCanNotGetHostname($url)
    {
        $expected = false;
        $siteHost = self::getLocalSiteHost();

        $urlFormatter = new Url($url, $siteHost);
        $actual = $urlFormatter->getHostname();
        $message = "Failed asserting equality. Input: '$url', Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param string $url
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_parsableHostnames')]
    public function testCanGetHostname($url, $expected)
    {
        $urlFormatter = new Url($url, self::getLocalSiteHost());
        $actual = $urlFormatter->getHostname();
        $message = "Failed asserting equality. Input: '$url', Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }

    // Test normalise()
    /**
     * @string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_notParsable')]
    public function test_normalise_unParsable($url)
    {
        $this->expectException(InvalidArgumentException::class);
        $urlFormatter = new Url($url, false);
        $urlFormatter->normalise();
    }

    /**
     * @param string $url
     * @param string $localSiteHost
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_normaliseLocalUrls')]
    public function testNormaliseLocalUrl($url, $localSiteHost, $expected)
    {
        $urlFormatter = new Url($url, $localSiteHost);
        $actual = $urlFormatter->normalise()->getUrl();
        $message = "Failed asserting equality. Input: '$url', Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param string $url
     * @param string $localSiteHost
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_normaliseNonLocalUrls')]
    public function testNormaliseNotLocalUrl($url, $localSiteHost, $expected)
    {
        $_SERVER['HTTP_HOST'] = $localSiteHost;
        $urlFormatter = new Url($url, $localSiteHost);
        $actual = $urlFormatter->normalise()->getUrl();

        $message = "Failed asserting equality. Input: '$url', Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $actual, $message);
    }


    // Test getSlug()
    /**
     * @param string $url
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_getSlugMapping')]
    public function testGetSlug($url, $expected)
    {
        $urlFormatter = new Url($url, self::getLocalSiteHost());
        $actual = $urlFormatter->getSlug();
        $message = "Failed asserting equality. Input: '$url', Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($actual, $expected, $message);
    }

    // Test prependScheme()
    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_notUrl_notRelative')]
    public function testNoProtocolUrl($url)
    {
        $urlFormatter = new Url($url, self::getLocalSiteHost());
        $this->assertFalse($urlFormatter->isUrl(), "Failed to fail isURL() before prepending scheme: '$url'");

        // With protocol
        $urlFormatter->prependScheme();
        $this->assertTrue($urlFormatter->isUrl(), "Failed to generate URL by prepending scheme: '$url'");
    }


    // Test isLocalUrl()
    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_isRelative')]
    public function test_IsLocalSite_IsLocalServer_IsRelative($url)
    {
        $site = self::getLocalSiteHost();
        $_SERVER['HTTP_HOST'] = $site;
        $urlFormatter = new Url($url, $site);
        $this->assertTrue($urlFormatter->isLocalUrl(), "Failed asserting that '$url' is a local Url");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_notUrl_notRelative_isLocal')]
    public function test_IsLocalSite_IsLocalServer_NotRelative($url)
    {
        $site = self::getLocalSiteHost();
        $_SERVER['HTTP_HOST'] = $site;
        $urlFormatter = new Url($url, $site);
        $this->assertTrue($urlFormatter->isLocalUrl(), "Failed asserting that '$url' is a local Url");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_isRelative')]
    public function test_IsLocalSite_NotLocalServer_IsRelative($url)
    {
        $site = self::getLocalSiteHost();
        $_SERVER['HTTP_HOST'] = self::getNotLocalSiteHost();
        $urlFormatter = new Url($url, $site);
        $this->assertTrue($urlFormatter->isLocalUrl(), "Failed asserting that '$url' is a local Url");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_notUrl_notRelative_isLocal')]
    public function test_IsLocalSite_NotLocalServer_NotRelative($url)
    {
        $site = self::getLocalSiteHost();
        $_SERVER['HTTP_HOST'] = self::getNotLocalSiteHost();
        $urlFormatter = new Url($url, $site);
        $this->assertTrue($urlFormatter->isLocalUrl(), "Failed asserting that '$url' is a local Url");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_isRelative')]
    public function test_NotLocalSite_IsLocalServer_IsRelative($url)
    {
        $site = self::getNotLocalSiteHost();
        $_SERVER['HTTP_HOST'] = self::getLocalSiteHost();
        $urlFormatter = new Url($url, $site);
        $this->assertTrue($urlFormatter->isLocalUrl(), "Failed asserting that '$url' is a local Url");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_notUrl_notRelative_isLocal')]
    public function test_NotLocalSite_IsLocalServer_NotRelative($url)
    {
        $site = self::getNotLocalSiteHost();
        $_SERVER['HTTP_HOST'] = self::getLocalSiteHost();
        $urlFormatter = new Url($url, $site);
        $this->assertTrue($urlFormatter->isLocalUrl(), "Failed asserting that '$url' is a local Url");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_isRelative')]
    public function test_NotLocalSite_NotLocalServer_IsRelative($url)
    {
        $site = self::getNotLocalSiteHost();
        $_SERVER['HTTP_HOST'] = $site;
        $urlFormatter = new Url($url, $site);
        $this->assertTrue($urlFormatter->isLocalUrl(), "Failed asserting that '$url' is a local Url");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_notRelative')]
    public function test_NotLocalSite_NotLocalServer_NotRelative($url)
    {
        $site = 'abc.com.some.unlikely.value';
        $_SERVER['HTTP_HOST'] = $site;
        $urlFormatter = new Url($url, $site);
        $this->assertFalse($urlFormatter->isLocalUrl(), "Failed asserting that '$url' is not a local Url");
    }


    // Test isFTPURL()
    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isFtpUrl')]
    public function testIsFtpUrl($url)
    {
        $site = self::getLocalSiteHost();
        $urlFormatter = new Url($url, $site);
        $this->assertTrue($urlFormatter->isFtpUrl(), "Failed asserting that '$url is a FTP Url");
    }

    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable_notFtpUrl')]
    public function testIsNotFtpUrl($url)
    {
        $site = 'sbvagrant.alaress.com.au';
        $urlFormatter = new Url($url, $site);
        $this->assertFalse($urlFormatter->isFtpUrl(), "Failed asserting that '$url is not a FTP Url");
    }


    // Test getRelativeURL()
    /**
     * @param string $url
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_relativeUrlMapping')]
    public function testGetRelativeUrl($url, $expected)
    {
        $site = self::getLocalSiteHost();
        $urlFormatter = new Url($url, $site);
        $actual = $urlFormatter->getRelativeURL();
        $msg = "Failed asserting equality. Input: '$url', Expected: '$expected', Actual: '$actual'";
        $this->assertEquals($expected, $urlFormatter->getRelativeURL(), $msg);
    }


    // test getBase64EncodedRedirect()
    /**
     * @param string $url
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isParsable')]
    public function testRedirectUrl($url)
    {
        $site = self::getLocalSiteHost();

        $expected = '/redir.php?url=' . urlencode(base64_encode(trim($url)));
        $urlFormatter = new Url($url, $site);
        $actual = $urlFormatter->getBase64EncodedRedirect();
        $msg = "Failed asserting equality. Input: '$url', Expected: '$expected', Actual: '$actual";
        $this->assertEquals($expected, $actual, $msg);
    }


    // ================ Data Providers ======================

    public static function provider_isLocal()
    {
        return array_merge(
            self::provider_isParsable_isRelative(),
            self::provider_isParsable_isLocal_notRelative()
        );
    }

    public static function provider_isParsable_isLocal_notRelative()
    {
        return array_merge(
            self::provider_isUrl_isLocal(),
            self::provider_isParsable_notUrl_notRelative_isLocal()
        );
    }

    public static function provider_isParsable_isLocal_isRelative()
    {
        return self::provider_isParsable_isRelative();
    }

    public static function provider_isParsable_notLocal_notRelative()
    {
        return array_merge(
            self::provider_isUrl_notLocal(),
            self::provider_isParsable_notUrl_notRelative_notLocal()
        );
    }

    public static function provider_isParsable_notRelative()
    {
        return array_merge(
            self::provider_isParsable_isUrl(),
            self::provider_isParsable_notUrl_notRelative()
        );
    }

    public static function provider_isFtpUrl()
    {
        return array_merge(
            self::provider_isParsable_isUrl_isLocal_isFtp(),
            self::provider_isParsable_isUrl_notLocal_isFtp()
        );
    }

    public static function provider_getSlugMapping()
    {
        return [
            ['/path/to/file.ext', '-path-to-fileext'],
            ['mongy mongy mongy', 'mongy-mongy-mongy'],
            ['this is my file that I have given unto you.txt', 'this-is-my-file-that-i-have-given-unto-youtxt'],
            ["I'M SHOUTING", 'im-shouting'],
        ];
    }

    public static function provider_normaliseNonLocalUrls()
    {
        $localSiteHost = self::getLocalSiteHost();
        $otherSiteHost = self::getNotLocalSiteHost();
        return [
            ["$otherSiteHost", $localSiteHost, "http://$otherSiteHost"],
            ["$otherSiteHost/", $localSiteHost, "http://$otherSiteHost/"],
            ["$otherSiteHost:8080", $localSiteHost, "http://$otherSiteHost:8080"],
            ["$otherSiteHost:8080/", $localSiteHost, "http://$otherSiteHost:8080/"],
            ["http://{$otherSiteHost}u", $localSiteHost, "http://{$otherSiteHost}u"],
            ["http://$otherSiteHost/", $localSiteHost, "http://$otherSiteHost/"],
            ["http://$otherSiteHost:8080", $localSiteHost, "http://$otherSiteHost:8080"],
            ["http://$otherSiteHost:8080/", $localSiteHost, "http://$otherSiteHost:8080/"],
            ["http://$otherSiteHost/homepage/43", $localSiteHost, "http://$otherSiteHost/homepage/43"],
            ["//$otherSiteHost/homepage/43", $localSiteHost, "http://$otherSiteHost/homepage/43"],
        ];
    }

    public static function provider_normaliseLocalUrls()
    {
        $localSiteHost = self::getLocalSiteHost();
        return [
            ['         ', $localSiteHost, ''],
            ['', $localSiteHost, ''],
            ["\t\t\t\t", $localSiteHost, ''],
            ["\n", $localSiteHost, ''],
            ['sbvagrant.alaress.com.au', $localSiteHost, '/'],
            ['sbvagrant.alaress.com.au/', $localSiteHost, '/'],
            ['sbvagrant.alaress.com.au:8080', $localSiteHost, '/'],
            ['sbvagrant.alaress.com.au:8080/', $localSiteHost, '/'],
            ['http://sbvagrant.alaress.com.au', $localSiteHost, '/'],
            ['http://sbvagrant.alaress.com.au/', $localSiteHost, '/'],
            ['http://sbvagrant.alaress.com.au:8080', $localSiteHost, '/'],
            ['http://sbvagrant.alaress.com.au:8080/', $localSiteHost, '/'],
            ['http://sbvagrant.alaress.com.au/homepage/43', $localSiteHost, '/homepage/43'],
            ['//sbvagrant.alaress.com.au/homepage/43', $localSiteHost, '/homepage/43'],
            ['/homepage/43', $localSiteHost, '/homepage/43'],
        ];
    }

    public static function provider_relativeUrlMapping()
    {
        return [
            ['             ', ''],
            ['\t\t\t', '/\t\t\t'],
            ["\t\t\t", ''],
            ["\n", ''],
            ["&%20", '/&%20'],
            ['', ''],
            ['www.google.com', '/'],
            ['/', '/'],
            ['#', '#'],
            ['#tags', '#tags'],
            ['?key', '?key'],
            ['?key=val', '?key=val'],
            ['?key=val&key2=val2', '?key=val&key2=val2'],
            ['/path/to/file.ext', '/path/to/file.ext'],
            ['//www.google.com/path/to/file.ext', '/path/to/file.ext'],
            ['www.google.com/path/to/file.ext', '/path/to/file.ext'],
            ['localhost/path/to/file.ext', '/path/to/file.ext'],
            ['sbvagrant.alaress.com.au/path/to/file.ext', '/path/to/file.ext'],
            ['ftp://www.google.com/path/to/file.ext', '/path/to/file.ext'],
            ['ftps://www.google.com/path/to/file.ext', '/path/to/file.ext'],
            ['http://www.google.com/path/to/file.ext', '/path/to/file.ext'],
            ['https://www.google.com/path/to/file.ext', '/path/to/file.ext'],
            ['http://www.google.com/path/to/file.ext#tag', '/path/to/file.ext#tag'],
            ['http://www.google.com/path/to/file.ext?key', '/path/to/file.ext?key'],
            ['http://www.google.com/path/to/file.ext?key=val', '/path/to/file.ext?key=val'],
        ];
    }

    public static function provider_invalidUrlType()
    {
        return [
            [null],
            [false],
            [true],
            [1],
            [0],
            [1.1],
            [['www.google.com']],
            [new \stdClass()],
        ];
    }

    public static function provider_validUrlType()
    {
        return array_merge(
            self::provider_notParsable(),
            self::provider_isParsable()
        );
    }

    public static function provider_isParsable()
    {
        return array_merge(
            self::provider_isParsable_isRelative(),
            self::provider_isParsable_isUrl(),
            self::provider_isParsable_notUrl_notRelative()
        );
    }

    public static function provider_validSiteHost()
    {
        // Everything except for null
        return [
            [false],
            [true],
            [''],
            [1],
            [1.111],
            ['www.example.com'],
            ['sdf sdf sdf'],
        ];
    }

    public static function provider_notParsable()
    {
        return [
            ['-www.google.com'],
            ['goog le.com'],
            ['goog*le.com/'],
            ['://google.com/'],
            [':://localhost:8080'],
            [':ftp//localhost:8080/'],
            ['\t\t\t'],
            ['://www.google.com'],
            ['-localhost/path/to/file.ext'],
            ["&%20"],
            ['a b'],
            ['http://a b'],
            ['http://google.com/sfd sdf'],
            ['goog%%le.com'],
            ['http://goo$$gle.com'],
            ['...'],
            ['////'],
            ['I am the destroyer of worlds'],
            [rawurlencode(' ')],
            ['https://schoolbox.atlassian.net/browse/SD-10?jql=text and sandwiches'],
            ['a%20'],
            ['<iframe src="http://www.google.com"></iframe>'],
        ];
    }

    public static function provider_isParsable_isRelative()
    {
        return [
            [''],
            ['/'],
            ['/path'],
            ['/path/'],
            ['/path/to/file'],
            ['/path/to/file.ext'],
            ['/path/to/file.ext#'],
            ['/path/to/file.ext#tags'],
            ['/path/to/file.ext?key'],
            ['/path/to/file.ext?key=val'],
            ['/path/to/file.ext?key=val&key2=val2'],
            ['#'],
            ['#tags'],
            ['?'],
            ['?key=val'],
            ['                             '],
            ['####'],
            ['# # # # # #'],
            ['#24#32#111'],
            ['#11 #22 #33 #44'],
            ['                                                    #'],
            ['#%20'],
            ['#<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<'],
            ["\t\t\t\t\t"],
            ["\n"],
        ];
    }

    public static function provider_isParsable_isUrl_isLocal_isFtp()
    {
        $sitehost = self::getLocalSiteHost();
        return [
            ["ftp://$sitehost"],
            ["ftp://$sitehost/"],
            ["ftp://$sitehost/path"],
            ["ftp://$sitehost/path/"],
            ["ftp://$sitehost/path/file"],
            ["ftp://$sitehost/path/file.ext"],
            ["ftp://$sitehost/path/file.ext#tag"],
            ["ftp://$sitehost/path/file.ext?key"],
            ["ftp://$sitehost/path/file.ext?key=val"],
            ["ftp://$sitehost/path/file.ext?key=val&key2=val2"],
            ["ftps://$sitehost/"],
            ["ftps://$sitehost/path"],
            ["ftps://$sitehost/path/"],
            ["ftps://$sitehost/path/file"],
            ["ftps://$sitehost/path/file.ext"],
            ["ftps://$sitehost/path/file.ext#tag"],
            ["ftps://$sitehost/path/file.ext?key"],
            ["ftps://$sitehost/path/file.ext?key=val"],
            ["ftps://$sitehost/path/file.ext?key=val&key2=val2"],
        ];
    }

    public static function provider_isUrl_isLocal_notFtp()
    {
        $sitehost = self::getLocalSiteHost();
        return [
            ["http://$sitehost"],
            ["http://$sitehost/"],
            ["http://$sitehost/path"],
            ["http://$sitehost/path/"],
            ["http://$sitehost/path/file"],
            ["http://$sitehost/path/file.ext"],
            ["http://$sitehost/path/file.ext#tag"],
            ["http://$sitehost/path/file.ext?key"],
            ["http://$sitehost/path/file.ext?key=val"],
            ["http://$sitehost/path/file.ext?key=val&key2=val2"],
            ["https://$sitehost/"],
            ["https://$sitehost/path"],
            ["https://$sitehost/path/"],
            ["https://$sitehost/path/file"],
            ["https://$sitehost/path/file.ext"],
            ["https://$sitehost/path/file.ext#tag"],
            ["https://$sitehost/path/file.ext?key"],
            ["https://$sitehost/path/file.ext?key=val"],
            ["https://$sitehost/path/file.ext?key=val&key2=val2"],
        ];
    }

    public static function provider_isParsable_isUrl_notLocal_isFtp()
    {
        $localSiteHost = self::getLocalSiteHost();
        $nonLocalSiteHost = self::getNotLocalSiteHost();
        return [
            ["ftp://www.$localSiteHost"],
            ["ftp://$localSiteHost.com"],
            ["ftp://$nonLocalSiteHost"],
            ["ftp://$nonLocalSiteHost/"],
            ["ftp://$nonLocalSiteHost/path"],
            ["ftp://$nonLocalSiteHost/path/"],
            ["ftp://$nonLocalSiteHost/path/file"],
            ["ftp://$nonLocalSiteHost/path/file.ext"],
            ["ftp://$nonLocalSiteHost/path/file.ext#tag"],
            ["ftp://$nonLocalSiteHost/path/file.ext?key"],
            ["ftp://$nonLocalSiteHost/path/file.ext?key=val"],
            ["ftp://$nonLocalSiteHost/path/file.ext?key=val&key2=val2"],
            ["ftps://www.$nonLocalSiteHost/"],
            ["ftps://$nonLocalSiteHost.com/"],
            ["ftps://$nonLocalSiteHost/"],
            ["ftps://$nonLocalSiteHost/path"],
            ["ftps://$nonLocalSiteHost/path/"],
            ["ftps://$nonLocalSiteHost/path/file"],
            ["ftps://$nonLocalSiteHost/path/file.ext"],
            ["ftps://$nonLocalSiteHost/path/file.ext#tag"],
            ["ftps://$nonLocalSiteHost/path/file.ext?key"],
            ["ftps://$nonLocalSiteHost/path/file.ext?key=val"],
            ["ftps://$nonLocalSiteHost/path/file.ext?key=val&key2=val2"],
        ];
    }

    public static function provider_isUrl_notLocal_notFtp()
    {
        $localSiteHost = self::getLocalSiteHost();
        $nonLocalSiteHost = self::getNotLocalSiteHost();
        return [
            ["http://$localSiteHost.au"],
            ["http://www.$localSiteHost"],
            ["http://$nonLocalSiteHost"],
            ["xxx://$nonLocalSiteHost"],
            ["http://$nonLocalSiteHost:8080"],
            ["http://$nonLocalSiteHost"],
            ["http://$nonLocalSiteHost.really.long"],
            ["http://$nonLocalSiteHost/"],
            ["http://$nonLocalSiteHost:8080/"],
            ["http://$nonLocalSiteHost/path"],
            ["http://$nonLocalSiteHost/path/to/"],
            ["http://$nonLocalSiteHost/path/to/file"],
            ["http://$nonLocalSiteHost/path/to/file.ext"],
            ["xxx://$nonLocalSiteHost/path/to/file.ext"],
            ['https://www.google.com.au/maps/@-16.444564,167.78601,15.79z'],
            ['https://www.google.com.au/search?q=port+sandwich&source=lnms&tbm=isch&sa=X&ved=0ahUKEwiBwY3pqIvPAhWKdT4KHdQMAnoQ_AUICSgC&biw=1440&bih=2440'],
            ['https://www.google.com.au/search?q=port+sandwich&source=lnms&tbm=isch&sa=X&ved=0ahUKEwiBwY3pqIvPAhWKdT4KHdQMAnoQ_AUICSgC&biw=1440&bih=2440#tbm=isch&q=amri+sannang'],
            ['https://r4---sn-hp57knlr.googlevideo.com/videogoodput?id=o-AHvq-LuViir31d8PVjfR03aK-PNK5sIqzXA6yp9YVo1z&source=goodput&range=0-4999&expire=1473738350&ip=115.70.195.253&ms=pm&mm=35&pl=24&nh=IgpwcjA0Lm1pYTA0Kgw3Ny42Ny43MS4xMTM&sparams=id,source,range,expire,ip,ms,mm,pl,nh&signature=83ACEE13CA7D5FEBB0032BCFA8141D13C3D789A8.5BC8828E42BA3D3382DFFCD57EE56AE8C9A673E0&key=cms1&cpn=UXM9r8K_nTA6vBu6'],
        ];
    }

    public static function provider_isParsable_notUrl_notRelative_isLocal()
    {
        $localSiteHost = self::getLocalSiteHost();
        return [
            ["$localSiteHost"],
            ["$localSiteHost:8080/"],
            ["$localSiteHost/"],
            ["$localSiteHost/path"],
            ["$localSiteHost/path/to/file.ext"],
            ["$localSiteHost?"],
            ["$localSiteHost?key"],
            ["$localSiteHost?key=val"],
            ["$localSiteHost?key=val&key2=val2"],
            ["$localSiteHost#"],
            ["$localSiteHost#tag"],
            ["$localSiteHost#tag#df"],
            ["//$localSiteHost"],
            ["//$localSiteHost:8080/"],
            ["//$localSiteHost/"],
            ["//$localSiteHost/path"],
            ["//$localSiteHost/path/to/file.ext"],
            ["//$localSiteHost?"],
            ["//$localSiteHost?key"],
            ["//$localSiteHost?key=val"],
            ["//$localSiteHost?key=val&key2=val2"],
            ["//$localSiteHost#"],
            ["//$localSiteHost#tag"],
            ["//$localSiteHost#tag#df"],
        ];
    }

    public static function provider_isParsable_notUrl_notRelative_notLocal()
    {
        $localSiteHost = self::getLocalSiteHost();
        $nonLocalSiteHost = self::getNotLocalSiteHost();
        return [
            ['ftp'],
            ['ftp.example.com'],
            ['ftp//example]'],
            ['ftp//:example'],
            ['ftps//example'],
            ['ftps//:example'],
            ['http//example]'],
            ['https//:example'],
            ['http//example'],
            ['https//:example'],
            ["$localSiteHost.au"],
            ["www.$localSiteHost"],
            ["$nonLocalSiteHost:8080/"],
            ["$nonLocalSiteHost/"],
            ["$nonLocalSiteHost/path"],
            ["$nonLocalSiteHost/path/to/file.ext"],
            ["$nonLocalSiteHost?"],
            ["$nonLocalSiteHost?key"],
            ["$nonLocalSiteHost?key=val"],
            ["$nonLocalSiteHost?key=val&key2=val2"],
            ["$nonLocalSiteHost#"],
            ["$nonLocalSiteHost#tag"],
            ["$nonLocalSiteHost#tag#df"],
            ["//$localSiteHost.au"],
            ["//www.$localSiteHost"],
            ["//$nonLocalSiteHost:8080/"],
            ["//$nonLocalSiteHost/"],
            ["//$nonLocalSiteHost/path"],
            ["//$nonLocalSiteHost/path/to/file.ext"],
            ["//$nonLocalSiteHost?"],
            ["//$nonLocalSiteHost?key"],
            ["//$nonLocalSiteHost?key=val"],
            ["//$nonLocalSiteHost?key=val&key2=val2"],
            ["//$nonLocalSiteHost#"],
            ["//$nonLocalSiteHost#tag"],
            ["//$nonLocalSiteHost#tag#df"],
            ['xxxxxxxxxxxxxxxxxxxxxxxxx'],
            ['//r4---sn-hp57knlr.googlevideo.com/videogoodput?id=o-AHvq-LuViir31d8PVjfR03aK-PNK5sIqzXA6yp9YVo1z&source=goodput&range=0-4999&expire=1473738350&ip=115.70.195.253&ms=pm&mm=35&pl=24&nh=IgpwcjA0Lm1pYTA0Kgw3Ny42Ny43MS4xMTM&sparams=id,source,range,expire,ip,ms,mm,pl,nh&signature=83ACEE13CA7D5FEBB0032BCFA8141D13C3D789A8.5BC8828E42BA3D3382DFFCD57EE56AE8C9A673E0&key=cms1&cpn=UXM9r8K_nTA6vBu6'],
            ['r4---sn-hp57knlr.googlevideo.com/videogoodput?id=o-AHvq-LuViir31d8PVjfR03aK-PNK5sIqzXA6yp9YVo1z&source=goodput&range=0-4999&expire=1473738350&ip=115.70.195.253&ms=pm&mm=35&pl=24&nh=IgpwcjA0Lm1pYTA0Kgw3Ny42Ny43MS4xMTM&sparams=id,source,range,expire,ip,ms,mm,pl,nh&signature=83ACEE13CA7D5FEBB0032BCFA8141D13C3D789A8.5BC8828E42BA3D3382DFFCD57EE56AE8C9A673E0&key=cms1&cpn=UXM9r8K_nTA6vBu6'],

        ];
    }

    // Aggregate Data Providers

    public static function provider_isParsable_notUrl_notRelative()
    {
        return array_merge(
            self::provider_isParsable_notUrl_notRelative_isLocal(),
            self::provider_isParsable_notUrl_notRelative_notLocal()
        );
    }


    public static function provider_isParsable_isUrl()
    {
        return array_merge(
            self::provider_isUrl_isLocal(),
            self::provider_isUrl_notLocal()
        );
    }


    public static function provider_isUrl_notLocal()
    {
        return array_merge(
            self::provider_isParsable_isUrl_notLocal_isFtp(),
            self::provider_isUrl_notLocal_notFtp()
        );
    }

    public static function provider_isUrl_isLocal()
    {
        return array_merge(
            self::provider_isParsable_isUrl_isLocal_isFtp(),
            self::provider_isUrl_isLocal_notFtp()
        );
    }

    public static function provider_isParsable_notFtpUrl()
    {
        return array_merge(
            self::provider_isParsable_isRelative(),
            self::provider_isUrl_isLocal_notFtp(),
            self::provider_isUrl_notLocal_notFtp(),
            self::provider_isParsable_notUrl_notRelative_isLocal(),
            self::provider_isParsable_notUrl_notRelative_notLocal()
        );
    }

    public static function provider_isParsable_notUrl()
    {
        return array_merge(
            self::provider_isParsable_isRelative(),
            self::provider_isParsable_notUrl_notRelative()
        );
    }

    public static function provider_cannotParseHostname()
    {
        return array_merge(
            self::provider_isMalformedHostname(),
            self::provider_isParsable_isRelative()
        );
    }

    public static function provider_isMalformedHostname()
    {
        return [
            ['protocol://www.example.com.'],
            ['...'],
            [''],
            ['w3.com-'],
            ['middle..dots'],
            ['.start.dot'],
            ['end.dot.'],
            ['.start.and.end.dot.'],
            ['.start.dot/'],
            ['.start.dot#'],
            ['http://.start.dot#'],
            ['-localhost'],
            ['.-localhost'],
            ['.-.localhost'],
        ];
    }

    public static function provider_parsableHostnames()
    {
        return [
            ['protocol://www.example.com', 'www.example.com'],
            ['//www.example.com', 'www.example.com'],
            ['//www.example.com/', 'www.example.com'],
            ['www.example.com', 'www.example.com'],
            ['www.example.com/path/to/file.ext', 'www.example.com'],
            ['localhost/and.some.extra', 'localhost'],
            ['localhost:8080', 'localhost'],
            ['localhost:8080/path/file.ext', 'localhost'],
            ['localhost#tag', 'localhost'],
            ['localhost?key', 'localhost'],
            ['localhost?key=val', 'localhost'],
        ];
    }

    private static function getLocalSiteHost()
    {
        return 'sbvagrant.alaress.com.au';
    }

    private static function getNotLocalSiteHost()
    {
        return 'some.non.local.site';
    }
}
