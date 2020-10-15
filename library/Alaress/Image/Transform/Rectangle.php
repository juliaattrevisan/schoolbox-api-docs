<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Transform;

use Imagine\Exception as ImagineException;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

/**
 * Constrain an image to fit within a specified width and height which is measured
 * from the center of the source image
 */
class Rectangle extends HasDimensions
{
    /**
     * A placement processor
     *
     * @var Placement
     */
    private $placementTransform;

    /**
     * @param int $width
     * @param int $height
     */
    public function __construct(int $width, int $height)
    {
        parent::__construct($width, $height);
        $this->placementTransform = new Placement($width, $height);
    }

    /**
     * @inheritDoc
     * @throws ImagineException\InvalidArgumentException
     * @throws ImagineException\OutOfBoundsException
     * @throws ImagineException\RuntimeException
     */
    public function manipulate(ImageInterface $image): ImageInterface
    {
        // This processor:
        // resize:
        // * scale down so one dimension fits, if image doesn't fit canvas
        // * scale up to max size that fits within canvas, if it does
        //   (and smallImageUpscales is on)
        // placement:
        // * place resized image in center of canvas
        $currentWidth  = $image->getSize()->getWidth();
        $currentHeight = $image->getSize()->getHeight();

        $currentRatio = $currentWidth / $currentHeight;
        $finalRatio   = $this->getCanvasWidth() / $this->getCanvasHeight();

        $canvasBox = new Box(
            $this->getCanvasWidth(),
            $this->getCanvasHeight()
        );

        if (!$canvasBox->contains($image->getSize())) {
            // Image doesn't fit within rectangle.
            // Resize it down so one dimension does.
            if ($currentRatio > $finalRatio) {
                $image->resize($image->getSize()->heighten($this->getCanvasHeight()));
            } else {
                $image->resize($image->getSize()->widen($this->getCanvasWidth()));
            }
        }

        // And send this to the placement processor
        $image = $this->placementTransform->manipulate($image);
        return $image;
    }
}
