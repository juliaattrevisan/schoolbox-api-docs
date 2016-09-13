<?php

/**
 * RelativeUrlTest
 *
 * Test cases for Url class. In each of the tests, you can
 * override with domain, http_host and hostname with whatever
 * values you require, so you don't need to use Cfg
 */
class RelativeUrlTest extends PHPUnit_Framework_TestCase
{
    // Test __construct()

//    /**
//     * @expectedException DomainException
//     * TODO Uncomment for PHP7
//     */
//    public function testNoSiteHostImplicit()
//    {
//        $input = 'www.example.com';
//        new Url($input);
//    }

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

    public function testNoSiteHostExplicit()
    {
        if(isset($_SERVER['HTTP_HOST'])){
            unset($_SERVER['HTTP_HOST']);
        }

        $input = 'www.example.com';
        $url = new Url($input, false);

        // We should be able to retrieve the hostName with no siteHost or serverHost context
        $this->assertEquals($input, $url->getHostname());
    }

    public function testIsParsableUrl()
    {
        $inputs = [
            '',
            '/',
            '/path',
            '/path/',
            '/path/to/file',
            '/path/to/file.ext',
            '/path/to/file.ext#',
            '/path/to/file.ext#tags',
            '/path/to/file.ext?key',
            '/path/to/file.ext?key=val',
            '/path/to/file.ext?key=val&key2=val2',
            '#',
            '#tags',
            '?',
            '?key=val',
            'www.google.com',
            'google.com',
            '//www.google.com',
            'http://www.google.com',
            'google.com/',
            '//google.com/',
            'http://localhost:8080',
            '//localhost:8080/',
            'localhost/path/to/',
            'localhost/path/to/file',
            'localhost/path/to/file.ext',
            'xxx://localhost/path/to/file.ext',
            '                             ',
            null,
            '####',
            'xxxxxxxxxxxxxxxxxxxxxxxxx',
            '# # # # # #',
            '#24#32#111',
            '#11 #22 #33 #44',
            '                                                    #',
            '#%20',
            '#<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<',
            "\t\t\t\t\t",
            'https://www.google.com.au/maps/@-16.444564,167.78601,15.79z',
            'https://www.google.com.au/search?q=port+sandwich&source=lnms&tbm=isch&sa=X&ved=0ahUKEwiBwY3pqIvPAhWKdT4KHdQMAnoQ_AUICSgC&biw=1440&bih=2440',
            'https://www.google.com.au/search?q=port+sandwich&source=lnms&tbm=isch&sa=X&ved=0ahUKEwiBwY3pqIvPAhWKdT4KHdQMAnoQ_AUICSgC&biw=1440&bih=2440#tbm=isch&q=amri+sannang',
            'https://r4---sn-hp57knlr.googlevideo.com/videogoodput?id=o-AHvq-LuViir31d8PVjfR03aK-PNK5sIqzXA6yp9YVo1z&source=goodput&range=0-4999&expire=1473738350&ip=115.70.195.253&ms=pm&mm=35&pl=24&nh=IgpwcjA0Lm1pYTA0Kgw3Ny42Ny43MS4xMTM&sparams=id,source,range,expire,ip,ms,mm,pl,nh&signature=83ACEE13CA7D5FEBB0032BCFA8141D13C3D789A8.5BC8828E42BA3D3382DFFCD57EE56AE8C9A673E0&key=cms1&cpn=UXM9r8K_nTA6vBu6',
            '//r4---sn-hp57knlr.googlevideo.com/videogoodput?id=o-AHvq-LuViir31d8PVjfR03aK-PNK5sIqzXA6yp9YVo1z&source=goodput&range=0-4999&expire=1473738350&ip=115.70.195.253&ms=pm&mm=35&pl=24&nh=IgpwcjA0Lm1pYTA0Kgw3Ny42Ny43MS4xMTM&sparams=id,source,range,expire,ip,ms,mm,pl,nh&signature=83ACEE13CA7D5FEBB0032BCFA8141D13C3D789A8.5BC8828E42BA3D3382DFFCD57EE56AE8C9A673E0&key=cms1&cpn=UXM9r8K_nTA6vBu6',
            'r4---sn-hp57knlr.googlevideo.com/videogoodput?id=o-AHvq-LuViir31d8PVjfR03aK-PNK5sIqzXA6yp9YVo1z&source=goodput&range=0-4999&expire=1473738350&ip=115.70.195.253&ms=pm&mm=35&pl=24&nh=IgpwcjA0Lm1pYTA0Kgw3Ny42Ny43MS4xMTM&sparams=id,source,range,expire,ip,ms,mm,pl,nh&signature=83ACEE13CA7D5FEBB0032BCFA8141D13C3D789A8.5BC8828E42BA3D3382DFFCD57EE56AE8C9A673E0&key=cms1&cpn=UXM9r8K_nTA6vBu6',
        ];


        foreach ($inputs as $input) {
            $url = new Url($input, 'www.example.com');
            $isLocalUrl = $url->isLocalUrl();
            $isUrl = $url->isUrl();
            $url->prependScheme();
            $isUrlAfterProtocolPrepended = $url->isUrl();
            $isParsableUrl = $isLocalUrl || $isUrl || $isUrlAfterProtocolPrepended;
            $this->assertTrue($isParsableUrl, "Failed to assert that '$input' is a parsable URL");
        }
    }

    public function testNotParsableUrl()
    {
        $inputs = [
            '...',
            '////',
            'I am the destroyer of worlds',
            rawurlencode(' '),
            'https://schoolbox.atlassian.net/browse/SD-10?jql=text and sandwiches',
            'a%20',
            '<iframe src="http://www.google.com"></iframe>',
        ];

        foreach ($inputs as $input) {
            $url = new Url($input, 'www.example.com');
            $isLocalUrl = $url->isLocalUrl();
            $isUrl = $url->isUrl();
            $url->prependScheme();
            $isUrlAfterProtocolPrepended = $url->isUrl();
            $isParsableUrl = $isLocalUrl || $isUrl || $isUrlAfterProtocolPrepended;
            $this->assertFalse($isParsableUrl, "'$input' is a parsable URL");
        }
    }


    // Test isUrl()

    public function testIsUrl()
    {
        $inputs = [
            'http://www.google.com',
            'http://localhost',
            'ftp://www.google.com',
            'http://sbvagrant.alaress.com.au.really.long',
            'http://www.google.com/',
            'http://www.google.com:8080/',
            'http://www.google.com/path',
            'http://localhost/path/to/',
            'http://localhost/path/to/file',
            'http://localhost/path/to/file.ext',
            'xxx://localhost/path/to/file.ext',
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, 'www.example.com');
            $this->assertTrue($url->isUrl(), "Failed asserting that '$input' is a Url");
        }
    }

    public function testIsNotUrl()
    {
        $inputs = [
            '-www.google.com',
            'goog le.com',
            'goog*le.com/',
            '://google.com/',
            ':://localhost:8080',
            ':ftp//localhost:8080/',
            'www.google.com',
            'www.google.com/',
            'www.google.com/index',
            'www.google.com/index.php',
            'google.com',
            '//www.google.com',
            '://www.google.com',
            'google.com/',
            '//google.com/',
            '//localhost:8080',
            'localhost/path/to/',
            'localhost/path/to/file',
            'localhost/path/to/file.ext',
            '-localhost/path/to/file.ext',
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, 'www.example.com');
            $this->assertFalse($url->isUrl(), "Failed asserting that '$input' is not a Url");
        }
    }

    // Test getHostname()

    public function testCanNotGetHostname()
    {
        $inputs = [
            '',
            '/',
            '/path',
            '/path/file.ext',
            '/file.ext#tag',
            '/file.ext?key',
            '/file.ext?key=val',
            'http:///file.ext?key=val',
            '://file.ext?key=val',
        ];
        $expected = false;

        foreach ($inputs as $input) {
            $url = new Url($input, 'sbvagrant.alaress.com.au');
            $actual = $url->getHostname();
            $message = "Failed asserting equality. Input: '$input', Expected: '$expected', Actual: '$actual'";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    public function testCanGetHostname()
    {
        $inputs = [
            'protocol://www.example.com'       => 'www.example.com',
            '//www.example.com'                => 'www.example.com',
            '//www.example.com/'               => 'www.example.com',
            'www.example.com'                  => 'www.example.com',
            'www.example.com/path/to/file.ext' => 'www.example.com',
            'localhost/and.some.extra'         => 'localhost',
            'localhost:8080'                   => 'localhost',
            'localhost:8080/path/file.ext'     => 'localhost',
            'localhost#tag'                    => 'localhost',
            'localhost?key'                    => 'localhost',
            'localhost?key=val'                => 'localhost',
        ];

        foreach ($inputs as $input => $expected) {
            $url = new Url($input, 'sbvagrant.alaress.com.au');
            $actual = $url->getHostname();
            $message = "Failed asserting equality. Input: '$input', Expected: '$expected', Actual: '$actual'";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    // Test normalise()

    public function testNormaliseLocalUrl()
    {
        $inputs = [
            'sbvagrant.alaress.com.au'                    => '/',
            'sbvagrant.alaress.com.au/'                   => '/',
            'sbvagrant.alaress.com.au:8080'               => '/',
            'sbvagrant.alaress.com.au:8080/'              => '/',
            'http://sbvagrant.alaress.com.au'             => '/',
            'http://sbvagrant.alaress.com.au/'            => '/',
            'http://sbvagrant.alaress.com.au:8080'        => '/',
            'http://sbvagrant.alaress.com.au:8080/'       => '/',
            'http://sbvagrant.alaress.com.au/homepage/43' => '/homepage/43',
            '//sbvagrant.alaress.com.au/homepage/43'      => '/homepage/43',
            '/homepage/43'                                => '/homepage/43',
        ];
        foreach ($inputs as $input => $expected) {
            $url = new Url($input, 'sbvagrant.alaress.com.au');
            $actual = $url->normalise()->getUrl();
            $message = "Failed asserting equality. Input: '$input', Expected: '$expected', Actual: '$actual'";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    public function testNormaliseNotLocalUrl()
    {
        $site = 'sbvagrant.alaress.com.au';
        $inputs = [
            'www.google.com'                    => '/',
            'www.google.com/'                   => '/',
            'www.google.com:8080'               => '/',
            'www.google.com:8080/'              => '/',
            'http://www.google.comu'            => '/',
            'http://www.google.com/'            => '/',
            'http://www.google.com:8080'        => '/',
            'http://www.google.com:8080/'       => '/',
            'http://www.google.com/homepage/43' => '/homepage/43',
            '//www.google.com/homepage/43'      => '/homepage/43',
        ];
        foreach ($inputs as $input => $expected) {
            $url = new Url($input, 'sbvagrant.alaress.com.au');
            $actual = $url->normalise()->getUrl();
            $message = "Failed asserting inequality. Input: '$input', Expected: '$expected', Actual: '$actual'";
            $this->assertNotEquals($expected, $actual, $message);
        }
    }


    // Test getSlug()

    public function testGetSlug()
    {
        $input = '/path/to/file.ext';
        $url = new Url($input, 'sbvagrant.alaress.com.au');
        $expected = '-path-to-fileext';
        $this->assertEquals($url->getSlug(), $expected);
    }

    // Test prependScheme()

    public function testNoProtocolUrl()
    {
        // No protocol
        $inputs = [
            'localhost',
            'localhost:8080',
            'www.google.com',
            'www.google.com/',
            'www.google.com/path',
            'www.google.com/path/',
            'www.google.com/path/file.ext',
            'www.google.com/path/file.ext?',
            'www.google.com/path/file.ext?key',
            'www.google.com/path/file.ext?key=val',
        ];

        foreach ($inputs as $input) {
            $url = new Url($input, 'sbvagrant.alaress.com.au');
            $this->assertFalse($url->isUrl(), "Failed to fail isULR() before prepending scheme: '$input'");

            // With protocol
            $url->prependScheme();
            $this->assertTrue($url->isUrl(), "Failed to generate URL by prepending scheme: '$input'");
        }
    }

    // Test isLocalUrl()

    public function test_IsLocalSite_IsLocalServer_IsRelative()
    {
        $site = 'sbvagrant.alaress.com.au';
        $_SERVER['HTTP_HOST'] = $site;
        $inputs = [
            '/',
            '/test',
            '/test/',
            '/test.php',
            '/test/file.ext',
            '/test/file.ext#tag',
            '/test/file.ext?key=val',
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertTrue($url->isLocalUrl(), "Failed asserting that '$input' is a local Url");
        }
    }

    public function test_IsLocalSite_IsLocalServer_NotRelative()
    {
        $site = 'sbvagrant.alaress.com.au';
        $_SERVER['HTTP_HOST'] = $site;
        $inputs = [
            'sbvagrant.alaress.com.au',
            'sbvagrant.alaress.com.au/',
            'http://sbvagrant.alaress.com.au',
            'sbvagrant.alaress.com.au/test.php',
            'http://sbvagrant.alaress.com.au/test.php',
            'http://sbvagrant.alaress.com.au/test.php#tag',
            'http://sbvagrant.alaress.com.au/test.php?key=var'
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertTrue($url->isLocalUrl(), "Failed asserting that '$input' is a local Url");
        }
    }

    public function test_IsLocalSite_NotLocalServer_IsRelative()
    {
        $site = 'sbvagrant.alaress.com.au';
        $_SERVER['HTTP_HOST'] = 'some.other.intranet.alias';
        $inputs = [
            '',
            '/',
            '/test',
            '/test/',
            '/test.php',
            '/test/file.ext',
            '/test/file.ext#tag',
            '/test/file.ext?key=val',
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertTrue($url->isLocalUrl(), "Failed asserting that '$input' is a local Url");
        }
    }

    public function test_IsLocalSite_NotLocalServer_NotRelative()
    {
        $site = 'sbvagrant.alaress.com.au';
        $_SERVER['HTTP_HOST'] = 'some.other.intranet.alias';
        $inputs = [
            'sbvagrant.alaress.com.au',
            'sbvagrant.alaress.com.au/',
            'http://sbvagrant.alaress.com.au',
            'sbvagrant.alaress.com.au/test.php',
            'http://sbvagrant.alaress.com.au/test.php',
            'http://sbvagrant.alaress.com.au/test.php#tag',
            'http://sbvagrant.alaress.com.au/test.php?key=var'
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertTrue($url->isLocalUrl(), "Failed asserting that '$input' is a local Url");
        }
    }

    public function test_NotLocalSite_IsLocalServer_IsRelative()
    {
        $site = 'some.other.intranet.alias';
        $_SERVER['HTTP_HOST'] = 'sbvagrant.alaress.com.au';
        $inputs = [
            '',
            '/',
            '/test',
            '/test/',
            '/test.php',
            '/test/file.ext',
            '/test/file.ext#tag',
            '/test/file.ext?key=val',
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertTrue($url->isLocalUrl(), "Failed asserting that '$input' is a local Url");
        }
    }

    public function test_NotLocalSite_IsLocalServer_NotRelative()
    {
        $site = 'some.other.intranet.alias';
        $_SERVER['HTTP_HOST'] = 'sbvagrant.alaress.com.au';
        $inputs = [
            'sbvagrant.alaress.com.au',
            'sbvagrant.alaress.com.au/',
            '//sbvagrant.alaress.com.au',
            '//sbvagrant.alaress.com.au/test.php',
            'http://sbvagrant.alaress.com.au',
            'sbvagrant.alaress.com.au/test.php',
            'http://sbvagrant.alaress.com.au/test.php',
            'http://sbvagrant.alaress.com.au/test.php#tag',
            'http://sbvagrant.alaress.com.au/test.php?key=var'
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertTrue($url->isLocalUrl(), "Failed asserting that '$input' is a local Url");
        }
    }

    public function test_NotLocalSite_NotLocalServer_IsRelative()
    {
        $site = 'some.other.intranet.alias';
        $_SERVER['HTTP_HOST'] = 'some.other.intranet.alias';
        $inputs = [
            '',
            '/',
            '#',
            '#tags',
            '?key',
            '?key=val',
            '?key=val&key2=val2',
            '/test',
            '/test/',
            '/test.php',
            '/test/file.ext',
            '/test/file.ext#tag',
            '/test/file.ext?key=val',
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertTrue($url->isLocalUrl(), "Failed asserting that '$input' is a local Url");
        }
    }

    public function test_NotLocalSite_NotLocalServer_NotRelative()
    {
        $site = 'sbvagrant.alaress.com.au';
        $_SERVER['HTTP_HOST'] = 'sbvagrant.alaress.com.au';
        $inputs = [
            'portal',
            'some.other.intranet.alias',
            '//some.other.intranet.alias',
            'some.other.intranet.alias/',
            'http://some.other.intranet.alias',
            'some.other.intranet.alias/test.php',
            'http://some.other.intranet.alias/test.php',
            'http://some.other.intranet.alias/test.php#tag',
            'http://some.other.intranet.alias/test.php?key=var'
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertFalse($url->isLocalUrl(), "Failed asserting that '$input' is not a local Url");
        }
    }

    // Test isFTPURL()
    public function testIsFtpUrl()
    {
        $site = 'sbvagrant.alaress.com.au';
        $inputs = [
            'ftp://example',
            'ftp://example.com',
            'ftp://www.example.com.au',
            'ftp://example.com/',
            'ftp://example.com/path',
            'ftp://example.com/path/',
            'ftp://example.com/path/file',
            'ftp://example.com/path/file.ext',
            'ftp://example.com/path/file.ext#tag',
            'ftp://example.com/path/file.ext?key',
            'ftp://example.com/path/file.ext?key=val',
            'ftp://example.com/path/file.ext?key=val&key2=val2',
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertTrue($url->isFtpUrl(), "Failed asserting that '$input is a FTP Url");
        }
    }

    public function testIsNotFtpUrl()
    {
        $site = 'sbvagrant.alaress.com.au';
        $inputs = [
            'ftp//example',
            'ftp//:example',
            'ftp',
            'ftp.example.com',
            'http://example.com',
            'http://example.com?ftp',
            'http://example.com?protocol=ftp',
        ];
        foreach ($inputs as $input) {
            $url = new Url($input, $site);
            $this->assertFalse($url->isFtpUrl(), "Failed asserting that '$input is not a FTP Url");
        }
    }

    // Test getRelativeURL()

    public function testGetRelativeUrl()
    {
        $site = 'sbvagrant.alaress.com.au';
        $inputs = [
            ''                                               => '',
            '/'                                              => '/',
            '#'                                              => '#',
            '#tags'                                          => '#tags',
            '?key'                                           => '?key',
            '?key=val'                                       => '?key=val',
            '?key=val&key2=val2'                             => '?key=val&key2=val2',
            '/path/to/file.ext'                              => '/path/to/file.ext',
            '//www.google.com/path/to/file.ext'              => '/path/to/file.ext',
            'www.google.com/path/to/file.ext'                => '/path/to/file.ext',
            'localhost/path/to/file.ext'                     => '/path/to/file.ext',
            'sbvagrant.alaress.com.au/path/to/file.ext'      => '/path/to/file.ext',
            'ftp://www.google.com/path/to/file.ext'          => '/path/to/file.ext',
            'ftps://www.google.com/path/to/file.ext'         => '/path/to/file.ext',
            'http://www.google.com/path/to/file.ext'         => '/path/to/file.ext',
            'https://www.google.com/path/to/file.ext'        => '/path/to/file.ext',
            'http://www.google.com/path/to/file.ext#tag'     => '/path/to/file.ext#tag',
            'http://www.google.com/path/to/file.ext?key'     => '/path/to/file.ext?key',
            'http://www.google.com/path/to/file.ext?key=val' => '/path/to/file.ext?key=val',
        ];
        foreach ($inputs as $input => $expected) {
            $url = new Url($input, $site);
            $actual = $url->getRelativeURL();
            $msg = "Failed asserting equality. Input: '$input', Expected: '$expected', Actual: '$actual'";
            $this->assertEquals($expected, $url->getRelativeURL(), $msg);
        }
    }

    // test getBase64EncodedRedirect()

    public function testRedirectUrl()
    {
        $site = 'sbvagrant.alaress.com.au';
        $inputs = [
            '',
            '/',
            '/path/file',
            '/path/file.ext',
            'localhost',
            'google.com',
            'google.com.au.long.domain',
            'http://localhost',
            'http://localhost:8080',
            'http://google.com',
            'http://google.com/',
            'http://google.com/path',
            'http://google.com/path/',
            'http://google.com/path/file.ext',
            'http://google.com/path/file.ext#tag',
            'http://google.com/path/file.ext#key=val',
        ];

        foreach ($inputs as $input) {
            $expected = '/redir.php?url=' . urlencode(base64_encode($input));
            $url = new Url($input, $site);
            $actual = $url->getBase64EncodedRedirect();
            $msg = "Failed asserting equality. Input: '$input', Expected: '$expected', Actual: '$actual";
            $this->assertEquals($expected, $actual, $msg);
        }
    }
}
