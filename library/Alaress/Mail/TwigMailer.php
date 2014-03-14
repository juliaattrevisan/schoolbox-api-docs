<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Mail;


use Twig_Environment;

class TwigMailer extends AbstractMailer {

	/** @var Twig_Environment */
	protected $twig;

	/** @var array  */
	protected $context = array();

	/** @var string */
	protected $templateFilename;

	/**
	 * @param $template
	 * @param string $subject
	 * @param string $to
	 * @param string $host
	 */
	public function __construct($template, $subject = "", $to = "", $host = "") {
		parent::__construct($template, $subject, $to, $host);

		$this->templateFilename = $template;

		// load up paths in order of preference (start with school-specific)
		$paths = array();
		if (is_dir(($custom = SITE_root . '/templates/' . self::getConfig()->get('site_location') . '/email'))) {
			$paths[] = $custom;
		}
		$paths[] = SITE_root . '/templates/core/email';

		// load up a twig environment that will search for school-specific templates if the directory exists
		$this->twig = new Twig_Environment(new \Twig_Loader_Filesystem($paths));
	}

	/**
	 * @param array $context
	 */
	public function setContext($context) {
		$this->context = $context;
	}

	/**
	 * Returns the HTML rendering of the templated email constructed by this object.
	 *
	 * @return string
	 */
	protected function composeHTML() {
		return $this->twig->render($this->templateFilename, $this->context);
	}


	/**
	 * Returns the plaintext rendering of the templated email constructed by this object.
	 *
	 * @return string
	 */
	protected function composePlain() {
		return strip_tags($this->composeHTML());
	}


} 