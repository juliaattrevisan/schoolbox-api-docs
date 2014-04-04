<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Alaress_Image_Transformation_Icon extends Alaress_Image_Transformation_Abstract {

	/**
	 * Create the list of valid transforms
	 */
	function __construct($icon, $size = 72) {

		$validSizes = array(16,24,32,48,64,72);
		$validCrops = array(
			// ensure this list accurately reflects the sprite file!
			'pin','doc','clock','tick','link','bolt','grade','poll','chat','comment','cross','reload','work','alert','upload','wiki'
		);

		if(!in_array($size, $validSizes)) {
			throw new InvalidArgumentException("Invalid size given: $size");
		}

		if(!in_array($icon, $validCrops)) {
			throw new InvalidArgumentException("Invalid icon given: $icon");
		}

		$crops = array_flip($validCrops);
		$index = $crops[$icon];
		$count = count($crops);
		$step  = 100 / $count;


		$top = ($index * $step);
		$bottom = (($index + 1) * $step);


		// PERCENDAGES!
		$coords = array(
			// always take full width
			'x0' => 0, 'x1' => 100,
			'y0' => $top, 'y1' => $bottom,
		);

		$this->allTransformations = array(
			'Icon' => array(
				array(
					'method' => 'Crop',
					'args' => array(
						'coordinates' => $coords
					)
				),
				array(
					'method' => 'Square',
					'args' => array(
						'width' => $size,
					)
				),
			),
		);
	}

} 