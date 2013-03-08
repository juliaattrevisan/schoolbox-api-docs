<?php
/**
 * RelativeUrlTest
 *
 * Test cases for Url class. In each of the tests, you can
 * override with domain, http_host and hostname with whatever
 * values you require, so you don't need to use Cfg
 */
class RelativeUrlTest extends PHPUnit_Framework_TestCase {

	public function testCompleteUrl() {
		$input = 'http://www.google.com';
		$expected = true;

		$url = new Url($input, false);

		$result = $url->isUrl();

		$this->assertEquals($result, $expected);
	}

	public function testIncompleteUrl() {
		$input = 'google';
		$expected = false;

		$url = new Url($input, false);

		$result = $url->isUrl();

		$this->assertEquals($result, $expected);
	}

	public function testRedirectUrl() {
		$input = 'http://www.google.com';
		$expected = '/redir.php?url=' . base64_encode($input);

		$url = new Url($input, false);

		$result = $url->getBase64EncodedRedirect();

		$this->assertEquals($result, $expected);
	}

    /*
     * isLocalURL tests
     */

    public function testIsLocalURLHostname() {
        $input = "http://portal/test.php";

        $url = new Url($input, false);

        $url->setServerHost('portal.test.local');
        $url->setSiteDomain('portal');
        $url->setSiteHostname('test.local');

        $this->assertTrue($url->isLocalURL());

    }

    public function testIsLocalURLFQDN() {
        $input = "http://portal.test.local/test.php";

        $url = new Url($input, false);

        $url->setServerHost('portal.test.local');
        $url->setSiteDomain('portal');
        $url->setSiteHostname('test.local');

        $this->assertTrue($url->isLocalURL());

    }

    public function testIsLocalURLAnotherDomain() {
        $input = "http://portal.another.server/test.php";

        $url = new Url($input, false);

        $url->setServerHost('portal.test.local');
        $url->setSiteDomain('portal');
        $url->setSiteHostname('test.local');

        $this->assertFalse($url->isLocalURL());

    }

    public function testIsLocalURLAnotherHost() {
        $input = "http://www.google.com/test.php";

        $url = new Url($input, false);

        $url->setServerHost('portal.test.local');
        $url->setSiteDomain('portal');
        $url->setSiteHostname('test.local');

        $this->assertFalse($url->isLocalURL());

    }

    public function testIsLocalURLRelative() {
        $input = "/test.php";

        $url = new Url($input, false);

        $url->setServerHost('portal.test.local');
        $url->setSiteDomain('portal');
        $url->setSiteHostname('test.local');

        $this->assertTrue($url->isLocalURL());

    }

//	public function testRelativeUrl() {
//		$input = '/ilearn';
//		$expected = 'http://ilearn.joeys.org';
//
//		$url = new Url($input, false);
//		$url->setSiteHostname('http://joeys.org');
//		$url->setSiteDomain('joeys');
//		$url->setServerHost('http://joeys.org.au');
//
//		$result = $url->getRelativeUrl();
//
//		$this->assertEquals($result, $expected);
//	}
}
