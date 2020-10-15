<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Transform;

use Alaress\Image\ITransform;

/**
 * Common implementation of Transforms that have a width + height
 */
abstract class HasDimensions implements ITransform
{
    /** @var int */
    private $canvasWidth;

    /** @var int */
    private $canvasHeight;

    /**
     * @param int $canvasWidth
     * @param int $canvasHeight
     */
    public function __construct(int $canvasWidth, int $canvasHeight)
    {
        $this->canvasWidth  = $canvasWidth;
        $this->canvasHeight = $canvasHeight;
    }

    /** @return int */
    protected function getCanvasWidth(): int
    {
        return $this->canvasWidth;
    }

    /** @return int */
    protected function getCanvasHeight(): int
    {
        return $this->canvasHeight;
    }
}
