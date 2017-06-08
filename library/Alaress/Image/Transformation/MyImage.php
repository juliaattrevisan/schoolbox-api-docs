<?php
/**
 * Alaress_Image_Transformation_MyImage
 *
 * Accept dynamic url parameters to create a dynamic transformation list
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Image\Transformation;

class MyImage extends BaseTransformation
{
    /** @var array $allTransformations */
    protected $allTransformations = [];

    /**
     * Accept arguments in transformation list
     *
     * @param $width
     * @param $height
     */
    public function __construct($width, $height)
    {
        $this->setAllTransformations([
            'Constrain' => [
                [
                    'method' => 'Constrain',
                    'args' => [
                        'width' => $width,
                        'height' => $height,
                    ]
                ],
            ]
        ]);
    }

    /**
     * Dynamically construct a transformation list
     *
     * @param $allTransformations
     */
    private function setAllTransformations($allTransformations)
    {
        $this->allTransformations = $allTransformations;
    }
}
