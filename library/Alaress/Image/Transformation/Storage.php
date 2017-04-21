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

class Storage extends BaseTransformation
{

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
        'square128' => array(
            array(
                'method' => 'Square',
                'args' => array(
                    'width' => 128,
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),
        'square200' => array(
            array(
                'method' => 'Square',
                'args' => array(
                    'width' => 200,
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
                    'width' => 2048,
                    'height' => 2048
                ),
                array(
                    'method' => 'Sharpen'
                )
            )
        ),
        /* The huge cover photo on the ePortfolio Profile page
		 */
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
        /* ePortfolio Recent Photo list needs to be a square
		 */
        'eportfolioRecentPhoto' => array(
            array(
                'method' => 'Rectangle',
                'args' => array(
                    'width' => 300,
                    'height' => 300,
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),

        /* ePortfolio Image Gallery list needs to be a square
		 */
        'eportfolioImageGalleryThumbnail' => array(
            array(
                'method' => 'Rectangle',
                'args' => array(
                    'width' => 80,
                    'height' => 80,
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),

        /* ePortfolio Image Gallery can probably be lightboxed
		 */
        'eportfolioImageGalleryLarge' => array(
            array(
                'method' => 'Constrain',
                'args' => array(
                    'width' => 800,
                    'height' => 800,
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),

        /* Card images are 2:1 ratio.  So this is used on class card */
        'cardImageLarge' => array(
            array(
                'method' => 'Rectangle',
                'args' => array(
                    'width' => 800,
                    'height' => 400,
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),

        /* Card images are 2:1 ratio.  So this is used on class card */
        'cardImageSmall' => array(
            array(
                'method' => 'Rectangle',
                'args' => array(
                    'width' => 200,
                    'height' => 100,
                )
            ),
            array(
                'method' => 'Sharpen'
            ),
        ),

        /* Test to find out whether Constrain skipped canvas creation
		 * when resultant image dimension was the same as the original
		 */
        'cacheTest' => array(
            array(
                'method' => 'Constrain',
                'args' => array(
                    'width' => 247,
                    'height' => 204,
                )
            )
        ),
    );
}
