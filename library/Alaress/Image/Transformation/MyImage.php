<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

use Alaress\Image\Transform\Constrain;
use OutOfBoundsException;

/**
 * Accepts dynamic url parameters to create a dynamic transformation list
 */
class MyImage implements ITransformation
{
    /** @var int */
    private $width;
    /** @var int */
    private $height;

    /**
     * Accept arguments in transformation list
     *
     * @param int $width
     * @param int $height
     */
    public function __construct(int $width, int $height)
    {
        $this->width  = $width;
        $this->height = $height;
    }

    /** @inheritDoc */
    public function retrieve(string $key): array
    {
        if ($key === 'Constrain') {
            return [new Constrain($this->width, $this->height)];
        }
        throw new OutOfBoundsException('Could not load transformation list.');
    }
}
