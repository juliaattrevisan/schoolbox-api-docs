<?php
/**
 * Alaress_Image_Transformation_Schoolbox
 *
 * A list of all the transformations used throughout the site
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

class Storage extends BaseTransformation
{
    // TODO implement and use other transforms
    public const TRANSFORM_SQUARE64 = 'square64';

    /** @var array $allTransformations */
    protected $allTransformations = [
        'square32'                        => [
            [
                'method' => BaseTransformation::METHOD_SQUARE,
                'args'   => [
                    'width' => 32,
                ],
            ],
        ],
        self::TRANSFORM_SQUARE64          => [
            [
                'method' => BaseTransformation::METHOD_SQUARE,
                'args'   => [
                    'width' => 64,
                ],
            ],
        ],
        'square128'                       => [
            [
                'method' => BaseTransformation::METHOD_SQUARE,
                'args'   => [
                    'width' => 128,
                ],
            ],
        ],
        'square200'                       => [
            [
                'method' => BaseTransformation::METHOD_SQUARE,
                'args'   => [
                    'width' => 200,
                ],
            ],
        ],
        'constrain200'                    => [
            [
                'method' => BaseTransformation::METHOD_CONSTRAIN,
                'args'   => [
                    'width'  => 200,
                    'height' => 300,
                ],
            ],
        ],
        'rectangle'                       => [
            [
                'method' => BaseTransformation::METHOD_RECTANGLE,
                'args'   => [
                    'width'  => 140,
                    'height' => 50,
                ],
            ],
        ],
        'maxsize'                         => [
            [
                'method' => BaseTransformation::METHOD_CONSTRAIN,
                'args'   => [
                    'width'  => 2048,
                    'height' => 2048,
                ],
            ],
        ],
        /* The cover photo on the News article page
         */
        'coverPhoto'                      => [
            [
                'method' => BaseTransformation::METHOD_CONSTRAIN,
                'args'   => [
                    'width'  => 2000,
                    'height' => 465,
                ],
            ],
        ],
        /* The huge cover photo on the ePortfolio Profile page
         */
        'eportfolioCoverPhoto'            => [
            [
                'method' => BaseTransformation::METHOD_CONSTRAIN,
                'args'   => [
                    'width'  => 2000,
                    'height' => 452,
                ],
            ],
        ],
        /* ePortfolio Recent Photo list needs to be a square
         */
        'eportfolioRecentPhoto'           => [
            [
                'method' => BaseTransformation::METHOD_RECTANGLE,
                'args'   => [
                    'width'  => 300,
                    'height' => 300,
                ],
            ],
        ],

        /* ePortfolio Image Gallery list needs to be a square
         */
        'eportfolioImageGalleryThumbnail' => [
            [
                'method' => BaseTransformation::METHOD_RECTANGLE,
                'args'   => [
                    'width'  => 80,
                    'height' => 80,
                ],
            ],
        ],

        /* ePortfolio Image Gallery can probably be lightboxed
         */
        'eportfolioImageGalleryLarge'     => [
            [
                'method' => BaseTransformation::METHOD_CONSTRAIN,
                'args'   => [
                    'width'  => 2048,
                    'height' => 2048,
                ],
            ],
        ],

        /* Card images are 2:1 ratio.  So this is used on class card */
        'cardImageLarge'                  => [
            [
                'method' => BaseTransformation::METHOD_RECTANGLE,
                'args'   => [
                    'width'  => 800,
                    'height' => 400,
                ],
            ],
        ],

        /* Card images are 2:1 ratio.  So this is used on class card */
        'cardImageSmall'                  => [
            [
                'method' => BaseTransformation::METHOD_RECTANGLE,
                'args'   => [
                    'width'  => 200,
                    'height' => 100,
                ],
            ],
        ],

        /* Test to find out whether Constrain skipped canvas creation
         * when resultant image dimension was the same as the original
         */
        'cacheTest'                       => [
            [
                'method' => BaseTransformation::METHOD_CONSTRAIN,
                'args'   => [
                    'width'  => 247,
                    'height' => 204,
                ],
            ],
        ],
    ];
}
