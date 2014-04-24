<?php
/**
 * SocialStream_Post_Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace SocialStream\Post\Factory;

class Display extends AbstractFactory {

	/**
	 * Constructor
	 *
	 * @param $url
	 */
	public function __construct($type) {
		$this->setUrl(trim($type));
	}

	/**
	 * Produce an instance of SocialStream_Post_Abstract subclass
	 *
	 * @param null $data
	 * @return mixed|\SocialStream_Post_Url|\SocialStream_Post_Video
	 */
	public function produce($data = null) {

        $type = $this->getUrl();

        $objectName = '\SocialStream_Post_'.ucwords($type);
        if(class_exists($objectName)) {
            $object = new $objectName();
            $object->setType($type);
            return $object;
        }

		/* If it was not a video, link to the internet, then assume it's
		 * just a plain old text message
		 */
		$object = new \SocialStream_Post_String();
		$object->setType('string');

		return $object;
	}
}