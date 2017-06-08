<?php
/**
 * Alaress_Image_Transformation_Schoolbox
 *
 * A list of all the transformations used throughout the site
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Image\Transformation;

class Portrait extends BaseTransformation
{
    /** @var array $allTransformations */
    protected $allTransformations = [
        'square32' => [
            [
                'method' => 'Square',
                'args' => [
                    'width' => 32,
                ]
            ]
        ],
        'square48' => [
            [
                'method' => 'Square',
                'args' => [
                    'width' => 48,
                ]
            ]
        ],
        'square64' => [
            [
                'method' => 'Square',
                'args' => [
                    'width' => 64,
                ]
            ]
        ],
        'square80' => [
            [
                'method' => 'Square',
                'args' => [
                    'width' => 80,
                ]
            ]
        ],
        'constrain128' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 128,
                    'height' => 400
                ]
            ]
        ],
        'square200' => [
            [
                'method' => 'Square',
                'args' => [
                    'width' => 200,
                ]
            ]
        ],
        'constrain200' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 200,
                    'height' => 300
                ]
            ]
        ],
        'constrain64' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 64,
                    'height' => 96
                ]
            ]
        ],
        'profile' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 250,
                    'height' => 300
                ]
            ]
        ],
        'rectangle' => [
            [
                'method' => 'Rectangle',
                'args' => [
                    'width' => 140,
                    'height' => 50
                ]
            ]
        ],
    ];
}
