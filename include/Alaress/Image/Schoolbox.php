<?php
/**
 * Alaress_Image_Schoolbox
 *
 * Facade class for Alaress_Image_Processor and Alaress_Image_Cache implementation
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Schoolbox {

	/**
	 * Constructor
	 *
	 * Sets up the caching implementation, image processor and streams it
	 *
	 * @param $externalUserId
	 * @param $key
	 * @param $transformations
	 */
	public function __construct($externalUserId, $key, $transformations) {

		/* Choose a caching method to use
		 */
		$cache = new Alaress_Image_Cache_Schoolbox($externalUserId, $key);

		/* Process and stream image
		 */
		$imageProcessor = new Alaress_Image_Processor($cache, $transformations);
		$imageProcessor->load()->transformAll()->stream();
	}
}
