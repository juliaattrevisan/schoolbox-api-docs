<?php
/**
 * Chain
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Metadata;


class Chain {

	/**
	 * Resultant thumbnail url
	 *
	 * @var string
	 */
	private $result = '';

	/**
	 * Constructor
	 *
	 * @param $curl
	 * @param $newPost
	 */
	public function __construct($curl, $newPost) {

		/* xml the curl result so we can find things better
		 * This allows us to xpath our way around
		 *
		 * Still, improperly formed documents according to
		 * simplexml or domdocument causes it to fatally die
		 * Avoid it by using internal_errors(true)
		 * http://stackoverflow.com/questions/6090667/php-domdocument-errors-warnings-on-html5-tags
		 */
		$document = new \DomDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML($curl->getResult());
		libxml_clear_errors();

		$xml = new \DOMXpath($document);

		/* Set up chain of responsibility
		 */
		$opengraph = new OpenGraph($xml, $newPost);
		$metatag = new MetaTag($xml, $newPost);
		$null = new Null($xml, $newPost);

		/* Try until one of them matches
		 */
		$metatag->setNext($null);
		$opengraph->setNext($metatag);

		$result = $opengraph->fetchMetadata();

		$this->setResult($result);
	}

	/* Getters/Setters
 	 */

	/**
	 * Set result
	 *
	 * @param string $result
	 */
	private function setResult($result) {
		$this->result = $result;
	}

	/**
	 * @return string
	 */
	public function getResult() {
		return $this->result;
	}
}