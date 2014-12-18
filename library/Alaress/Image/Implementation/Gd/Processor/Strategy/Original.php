<?php
/**
 * Alaress_Image_Processor_Strategy_Original
 *
 * Returns original image in original sizing
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Strategy_Original extends Alaress_Image_Processor_Strategy_Abstract implements Alaress_Image_Processor_Strategy_Interface {

	/**
	 * Manipulate image
	 *
	 * @param $image
	 * @return mixed
	 */
	public function manipulate($image) {
		return $image;
	}
}
