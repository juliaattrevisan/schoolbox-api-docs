<?php
/**
 * Alaress_Image_Transformation_Default
 *
 * A list of all the transformations used throughout the site
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

namespace Alaress\Image\Transformation;

class Default extends Alaress_Image_Transformation_Abstract {

	protected $allTransformations = array(

		/* These are the preview images that display when you add images to
		 * a cms page via the tinyMCE
		 */
		'AdminPreview' => array(
			array(
				'method' => 'Constrain',
				'args' => array(
					'width' => 300,
					'height' => 300,
				)
			),
			array(
				'method' => 'Sharpen'
			),
		),

		/* These are the lightboxed images that appear when viewing images
		 * from the asset browser
		 */
		'AdminGallery' => array(
			array(
				'method' => 'Constrain',
				'args' => array(
					'width' => 800,
					'height' => 600,
				)
			),
			array(
				'method' => 'Sharpen'
			),
		),

		/* Test sequence
		 */
		'Foxtrot' => array(
			array(
				'method' => 'Constrain',
				'args' => array(
					'width' => 800,
					'height' => 830,
				)
			),
			array(
				'method' => 'Placement',
				'args' => array(
					'width' => 900,
					'height' => 900,
					'position' => 'middle',
					'bgcolor' => array(
						'red' => 155,
						'green' => 155,
						'blue' => 255,
					)
				)
			),
			array(
				'method' => 'Sharpen'
			),
		),

		/* Half Canvas
		 */
		'HalfCanvas' => array(
			array(
				'method' => 'Constrain',
				'args' => array(
					'width' => 200,
					'height' => 200,
				)
			),
			array(
				'method' => 'Placement',
				'args' => array(
					'width' => 400,
					'height' => 400,
					'position' => 'left',
					'bgcolor' => array(
						'red' => 80,
						'green' => 128,
						'blue' => 255,
					)
				)
			),
			array(
				'method' => 'Sharpen'
			),
		),

		/* Top Third
		 */
		'TopThird' => array(
			array(
				'method' => 'Crop',
				'args' => array(
					'coordinates' => array(
						'x0' => 20,
						'y0' => 0,
						'x1' => 80,
						'y1' => 66,
					)
				)
			),
			array(
				'method' => 'Constrain',
				'args' => array(
					'width' => 350,
					'height' => 350,
				)
			),
			array(
				'method' => 'Sharpen'
			),
		),

		/* StretchyButtons
		 * Caveat: Only understands png files
		 *
		 * The Placement transformation specifies canvas dimensions. Height
		 * should be the actual height of the middle.png image itself.
		 * Width is the desired width of the button.
		 *
		 * If you need to change the dimensions you should add a Constrain
		 * transformation after you've constructed the button image
		 */
		'StretchyButton' => array(
			array(
				'method' => 'Placement',
				'args' => array(
					'width' => 2012,
					'height' => 52,
					'position' => 'middle',
				)
			),
			array(
				'method' => 'StretchyButton',
				'args' => array(
					'left' => './img/temp/left.png',
					'middle' => './img/temp/middle.png',
					'right' => './img/temp/right.png'
				)
			),
			array(
				'method' => 'Sharpen'
			),
		)
	);
}
