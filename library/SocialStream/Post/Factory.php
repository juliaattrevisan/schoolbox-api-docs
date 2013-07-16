<?php
/**
 * SocialStream_Post_Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace SocialStream\Post;

class Factory {

	/**
	 * A string corresponding to a type of SocialStream_Post_Abstract
	 *
	 * @var string
	 */
	private $type = '';

	/**
	 * Constructor
	 *
	 * @param $type
	 */
	public function __construct($type) {
		$this->setType($type);
	}

	/**
	 * Produce an instance of SocialStream_Post_Abstract subclass
	 *
	 * @return mixed
	 */
	public function produce() {
		switch($this->getType()) {
			case 'file' : $model = 'SocialStream_Post_File'; break;
			case 'image' : $model = 'SocialStream_Post_Image'; break;
			case 'video' : $model = 'SocialStream_Post_Video'; break;
			case 'website' : $model = 'SocialStream_Post_Website'; break;
			case 'string' : $model = 'SocialStream_Post_String'; break;
			default : $model = 'SocialStream_Post_String'; break;
		}

		return new $model;
	}

	/* Getters/Setters
	 */

	/**
	 * Set type
	 * 
	 * @param string $type
	 */
	private function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	private function getType() {
		return $this->type;
	}
}