<?php
/**
 * Scrub
 *
 * Noob filter. Note that these methods are particularly aggressive
 * and can, on occasion, actually introduce undesirables into your
 * output. For example, apostrophes (single quotes) can sometimes
 * appear as &#0039 or something like that.
 *
 * This is intended as a global singleton class
 *
 * Originally floating functions scrubTEXT() and scrubHTML().
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Scrub {

	/**
	 * Cleans plain text of all nasty stuff
	 * Can be used on input to database or output
	 *
	 * @param  $text
	 * @return string
	 */
	static public function text($text) {
		/* This in built function encode html chars
		 * the ENT_QUOTES converts all talking marks
		 */
		$text = htmlentities($text, ENT_QUOTES, "UTF-8");

		/* Get rid of any possible bad chars
		 * $text = scrubHTML($text);
		 */
		return $text;
	}

	/**
	 * Cleans crap out of input using HTMLPurifier
	 *
	 * @param  $html
	 * @return
	 */
	static public function html($html) {
		require_once dirname(__FILE__) . '/HTMLPurifier/HTMLPurifier.auto.php';

		$config = HTMLPurifier_Config::createDefault();
		//$config->set('Filter', 'YouTube', true);
		$config->set('Filter', 'Custom', array(
			new HTMLPurifier_Filter_Quicktime(),
			new HTMLPurifier_Filter_Shockwave(),
			new HTMLPurifier_Filter_Flash(),
			new HTMLPurifier_Filter_WindowsMedia(),
			new HTMLPurifier_Filter_Real(),
            new HTMLPurifier_Filter_IFrame()
		));
		$config->set('Attr', 'AllowedFrameTargets', array(
			'_blank',
			'_self',
			'_parent',
			'_top'
		));


		$purifier = HTMLPurifier::instance();

        /* HTML Purifier hack to prevent authentication information from being stripped out of URL */
        $registry = HTMLPurifier_URISchemeRegistry::instance();
        $registry->register('http', new Alaress_HTMLPurifier_URIScheme_Http);

        return $purifier->purify($html, $config);
	}
}
