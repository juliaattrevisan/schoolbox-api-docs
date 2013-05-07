<?php
/**
 * Alaress_Image_Transformation_Schoolbox
 *
 * A list of all the transformations used throughout the site
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Image_Transformation_Storage extends Alaress_Image_Transformation_Abstract {

    protected $allTransformations = array(
        'square32' => array(
            array(
                'method' => 'Square',
                'args' => array(
                    'width' => 32,
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),
        'square64' => array(
            array(
                'method' => 'Square',
                'args' => array(
                    'width' => 64,
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),
        'constrain200' => array(
            array(
                'method' => 'Constrain',
                'args' => array(
                    'width' => 200,
                    'height' => 300
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),
        'rectangle' => array(
            array(
                'method' => 'Rectangle',
                'args' => array(
                    'width' => 140,
                    'height' => 50
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),
        'maxsize' => array(
            array(
                'method' => 'Constrain',
                'args' => array(
                    'width' => 1024,
                    'height' => 1024
                ),
                array(
                    'method' => 'Sharpen'
                )
            )
        ),
		'eportfolioCoverPhoto' => array(
			array(
				'method' => 'Constrain',
				'args' => array(
					'width' => 2000,
					'height' => 452
				),
				array(
					'method' => 'Sharpen'
				)
			)
		),
    );
}
