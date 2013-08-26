<?php
/**
 * Alaress_Image_Processor_Strategy_Sharpen
 *
 * Applies a cheap convolution matrix to sharpen images post-resize
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Strategy_Sharpen extends Alaress_Image_Processor_Strategy_Abstract implements Alaress_Image_Processor_Strategy_Interface {

	/**
	 * Manipulate image
	 *
	 * @param $image
	 * @return mixed
	 */
	public function manipulate($image) {
		$sharpenMatrix = array (
			array(-1.2, -1, -1.2),
			array(-1, 20, -1),
			array(-1.2, -1, -1.2)
		);

		$divisor = array_sum(array_map('array_sum', $sharpenMatrix));

		$offset = 0;

		imageconvolution($image, $sharpenMatrix, $divisor, $offset);

		return $image;
	}
}
