<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Mail;


use Alaress\Html\ToText as Html2Text;
use Twig_Environment;
use Twig_SimpleFilter;
use Scrub;

class TwigMailer extends AbstractMailer {

	/** @var Twig_Environment */
	protected $twig;

	/** @var array  */
	protected $context = array();

	/** @var string */
	protected $templateFilename;

	/** @var string */
	protected $rendercache = '';

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

        //TODO: Refactor to use the Twig Environment from the bootstrap
        // HTML Purifier filter - known to output safe HTML
        $this->twig->addFilter(
            new Twig_SimpleFilter(
                'purify',
                array('Scrub', 'html'),
                array('is_safe' => array('html'))
            )
        );
        $this->twig->addFilter(
            new Twig_SimpleFilter(
                'purifyAbsoluteUri',
                array('Scrub', 'htmlAbsoluteUri'),
                array('is_safe' => array('html'))
            )
        );

        // HTML2Text filter
        $this->twig->addFilter(
            new Twig_SimpleFilter(
                'html2text',
                array('Scrub', 'html2text')
            )
        );

        // Cut sentence filter
        $this->twig->addFilter(
            new Twig_SimpleFilter(
                'cutSentence',
                array('TextFormatter', 'cutSentence')
            )
        );
	}

	/**
	 * @param array $context
	 * @return self
	 */
	public function setContext($context) {
		if($context) {
			$this->context = array_merge_recursive($this->context, $context);
		}
		else {
			$this->context = array();
		}
		return $this;
	}

	/**
	 * @return array
	 */
	public function getContext() {
		return $this->context;
	}

	/**
	 * Returns the HTML rendering of the templated email constructed by this object.
	 *
	 * @return string
	 */
	protected function composeHTML() {
		return $this->rendercache = $this->twig->render($this->templateFilename, $this->context);
	}


	/**
	 * Returns the plaintext rendering of the templated email constructed by this object.
	 *
	 * @return string
	 */
	protected function composePlain() {
		if($this->rendercache) {
			$converter = new Html2Text($this->rendercache);
			return $converter->getText();
		}
		$converter = new Html2Text($this->composeHTML());
		return $converter->getText();
	}


}
