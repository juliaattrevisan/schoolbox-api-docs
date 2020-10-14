<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

use Alaress\Image\ITransform;
use Alaress\Image\Transform\Constrain;
use Alaress\Image\Transform\Rectangle;
use Alaress\Image\Transform\Square;
use OutOfBoundsException;

/**
 * A list of all the transformations used throughout the site
 */
class Portrait
{
    /**
     * @param string $key
     * @return ITransform
     * @throws OutOfBoundsException
     */
    public function retrieve(string $key): ITransform
    {
        switch ($key) {
            case 'square32':
                return new Square(32);
            case 'square48':
                return new Square(48);
            case 'square64':
                return new Square(64);
            case 'square72':
                return new Square(72);
            case 'square80':
                return new Square(80);
            case 'square200':
                return new Square(200);
            case 'constrain64':
                return new Constrain(64, 96);
            case 'constrain128':
                return new Constrain(128, 400);
            case 'constrain200':
                return new Constrain(200, 300);
            case 'profile':
                return new Constrain(250, 300);
            case 'rectangle':
                return new Rectangle(140, 50);
        }
        throw new OutOfBoundsException('Could not load transformation list.');
    }
}
