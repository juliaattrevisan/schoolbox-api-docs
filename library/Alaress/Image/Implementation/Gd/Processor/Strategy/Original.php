<?php
/**
 * Original
 *
 * Returns original image in original sizing
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

namespace Alaress\Image\Implementation\Gd\Processor\Strategy;

class Original extends BaseStrategy {

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
