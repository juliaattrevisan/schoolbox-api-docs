<?php
/**
 * Chain of Responsibility for obtaining urls to a thumbnail image
 * from any given url, whether that's a direct link to a file on the
 * internet, or just some website with an opengraph avatar etc.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;

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
	 * You can optionally pass null as $curl, if you want to bypass
	 * curl usage altogether (this defaults to an empty html doc for
	 * DomDocument parsing)
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
		if (($curl != null) && strlen($curl->getResult())) {
			$data = $curl->getResult();
		}
		/* Use a default xml-parser friendly version of empty string
		 * in case someone links to a nonexistent file or something
		 */
		else {
			$data = '<html></html>';
		}

		$document = new \DomDocument();
		libxml_use_internal_errors(true);

		$document->loadHTML($data);
		libxml_clear_errors();

		$xml = new \DOMXpath($document);

		/* Set up chain of responsibility
		 */
		$direct = new Direct($xml, $newPost);
		$opengraph = new OpenGraph($xml, $newPost);
		$itempropImage = new ItempropImage($xml, $newPost);
		$itempropPhoto = new ItempropPhoto($xml, $newPost);
		$itempropIcon = new ItempropIcon($xml, $newPost);
		$itempropThumbnailUrl = new ItempropThumbnailUrl($xml, $newPost);
		$linkIcon = new LinkIcon($xml, $newPost);
		$linkIconApple = new LinkIconApple($xml, $newPost);
		$linkIconApplePrecompiled = new LinkIconApplePrecompiled($xml, $newPost);
		$arbitraryFile = new ArbitraryFile($xml, $newPost);

		/* Try directly, otherwise try the other meta tag variants in sequence
		 * until one of them matches
		 */
		$linkIconApplePrecompiled->setNext($arbitraryFile);
		$linkIconApple->setNext($linkIconApplePrecompiled);
		$linkIcon->setNext($linkIconApple);
		$itempropThumbnailUrl->setNext($linkIcon);
		$itempropIcon->setNext($itempropThumbnailUrl);
		$itempropPhoto->setNext($itempropIcon);
		$itempropImage->setNext($itempropPhoto);
		$opengraph->setNext($itempropImage);
		$direct->setNext($opengraph);

		$result = $direct->fetchThumbnail();

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