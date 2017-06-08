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

class Storage extends BaseTransformation
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
        'square64' => [
            [
                'method' => 'Square',
                'args' => [
                    'width' => 64,
                ]
            ]
        ],
        'square128' => [
            [
                'method' => 'Square',
                'args' => [
                    'width' => 128,
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
        'rectangle' => [
            [
                'method' => 'Rectangle',
                'args' => [
                    'width' => 140,
                    'height' => 50
                ]
            ]
        ],
        'maxsize' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 2048,
                    'height' => 2048
                ]
            ]
        ],
        /* The huge cover photo on the ePortfolio Profile page
		 */
        'eportfolioCoverPhoto' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 2000,
                    'height' => 452
                ]
            ]
        ],
        /* ePortfolio Recent Photo list needs to be a square
		 */
        'eportfolioRecentPhoto' => [
            [
                'method' => 'Rectangle',
                'args' => [
                    'width' => 300,
                    'height' => 300,
                ]
            ]
        ],

        /* ePortfolio Image Gallery list needs to be a square
		 */
        'eportfolioImageGalleryThumbnail' => [
            [
                'method' => 'Rectangle',
                'args' => [
                    'width' => 80,
                    'height' => 80,
                ]
            ]
        ],

        /* ePortfolio Image Gallery can probably be lightboxed
		 */
        'eportfolioImageGalleryLarge' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 800,
                    'height' => 800,
                ]
            ]
        ],

        /* Card images are 2:1 ratio.  So this is used on class card */
        'cardImageLarge' => [
            [
                'method' => 'Rectangle',
                'args' => [
                    'width' => 800,
                    'height' => 400,
                ]
            ]
        ],

        /* Card images are 2:1 ratio.  So this is used on class card */
        'cardImageSmall' => [
            [
                'method' => 'Rectangle',
                'args' => [
                    'width' => 200,
                    'height' => 100,
                ]
            ]
        ],

        /* Test to find out whether Constrain skipped canvas creation
		 * when resultant image dimension was the same as the original
		 */
        'cacheTest' => [
            [
                'method' => 'Constrain',
                'args' => [
                    'width' => 247,
                    'height' => 204,
                ]
            ]
        ],
    ];
}
