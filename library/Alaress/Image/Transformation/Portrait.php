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

namespace Alaress\Image\Transformation;

class Portrait extends BaseTransformation
{

    protected $allTransformations = array(
        'square32' => array(
            array(
                'method' => 'Square',
                'args' => array(
                    'width' => 32,
                )
            )
        ),
        'square48' => array(
            array(
                'method' => 'Square',
                'args' => array(
                    'width' => 48,
                )
            )
        ),
        'square64' => array(
            array(
                'method' => 'Square',
                'args' => array(
                    'width' => 64,
                )
            )
        ),
        'square80' => array(
            array(
                'method' => 'Square',
                'args' => array(
                    'width' => 80,
                )
            )
        ),
        'constrain128' => array(
            array(
                'method' => 'Constrain',
                'args' => array(
                    'width' => 128,
                    'height' => 400
                )
            )
        ),
        'square200' => array(
            array(
                'method' => 'Square',
                'args' => array(
                    'width' => 200,
                )
            )
        ),
        'constrain200' => array(
            array(
                'method' => 'Constrain',
                'args' => array(
                    'width' => 200,
                    'height' => 300
                )
            )
        ),
        'constrain64' => array(
            array(
                'method' => 'Constrain',
                'args' => array(
                    'width' => 64,
                    'height' => 96
                )
            )
        ),
        'profile' => array(
            array(
                'method' => 'Constrain',
                'args' => array(
                    'width' => 250,
                    'height' => 300
                )
            )
        ),
        'rectangle' => array(
            array(
                'method' => 'Rectangle',
                'args' => array(
                    'width' => 140,
                    'height' => 50
                )
            )
        ),
    );
}
