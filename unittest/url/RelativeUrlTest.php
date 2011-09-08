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

	public function testRelativeUrl() {
		$input = '/ilearn';
		$expected = 'http://ilearn.joeys.org';

		$url = new Url($input, false);
		$url->setSiteHostname('http://joeys.org');
		$url->setSiteDomain('joeys');
		$url->setServerHost('http://joeys.org.au');

		$result = $url->getRelativeUrl();

		$this->assertEquals($result, $expected);
	}
}
