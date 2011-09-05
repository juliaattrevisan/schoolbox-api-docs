<?php

class ScrubTextTest extends PHPUnit_Framework_TestCase {

	/**
	 * @return void
	 */
	public function testText1() {
		$input = '<p onclick=javascript:window.open("Mox.html");>Moog?</p>';
		$expected = scrubTEXT($input);

		$result = Scrub::text($input);

		$this->assertEquals($result, $expected);
	}

	/**
	 * @return void
	 */
	public function testText2() {
		$input = "<strong><b>dog's bollocks</b></strong>";
		$expected = scrubTEXT($input);

		$result = Scrub::text($input);

		$this->assertEquals($result, $expected);
	}
}
