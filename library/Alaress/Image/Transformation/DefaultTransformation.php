<?php
/**
 * Alaress_Image_Transformation_Default
 *
 * A list of all the transformations used throughout the site
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Image\Transformation;

class DefaultTransformation extends BaseTransformation
{
    /** @var array $allTransformations */
    protected $allTransformations = [

        /* These are the preview images that display when you add images to
		 * a cms page via the tinyMCE
		 */
        'AdminPreview' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 300,
                    'height' => 300,
                ]
            ]
        ],

        /* These are the lightboxed images that appear when viewing images
		 * from the asset browser
		 */
        'AdminGallery' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 800,
                    'height' => 600,
                ]
            ]
        ],

        /* Test sequence
		 */
        'Foxtrot' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 800,
                    'height' => 830,
                ]
            ],
            [
                'method' => 'Placement',
                'args' => [
                    'width' => 900,
                    'height' => 900,
                    'position' => 'middle',
                    'bgcolor' => [
                        'red' => 155,
                        'green' => 155,
                        'blue' => 255,
                    ]
                ]
            ]
        ],

        /* Half Canvas
		 */
        'HalfCanvas' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 200,
                    'height' => 200,
                ]
            ],
            [
                'method' => 'Placement',
                'args' => [
                    'width' => 400,
                    'height' => 400,
                    'position' => 'left',
                    'bgcolor' => [
                        'red' => 80,
                        'green' => 128,
                        'blue' => 255,
                    ]
                ]
            ]
        ],

        /* Top Third
		 */
        'TopThird' => [
            [
                'method' => 'Crop',
                'args' => [
                    'coordinates' => [
                        'x0' => 20,
                        'y0' => 0,
                        'x1' => 80,
                        'y1' => 66,
                    ]
                ]
            ],
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 350,
                    'height' => 350,
                ]
            ]
        ],

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
        'StretchyButton' => [
            [
                'method' => 'Placement',
                'args' => [
                    'width' => 2012,
                    'height' => 52,
                    'position' => 'middle',
                ]
            ],
            [
                'method' => 'StretchyButton',
                'args' => [
                    'left' => './img/temp/left.png',
                    'middle' => './img/temp/middle.png',
                    'right' => './img/temp/right.png'
                ]
            ]
        ]
    ];
}
