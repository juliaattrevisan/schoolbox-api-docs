<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Transform;

use Imagine\Exception as ImagineException;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Imagine\Image\PointInterface;

/**
 * Place an image on a canvas at one of the nine cardinal locations. Defaults to 'topleft'
 *
 * topleft, top, topright
 * left, middle, right
 * bottomleft, bottom, bottomright
 */
class Placement extends HasDimensions
{
    /**
     * Positioning of the image within the canvas, eg 'topleft'
     *
     * @var string
     */
    private $position;

    /**
     * @param int    $width
     * @param int    $height
     * @param string $position
     */
    public function __construct(int $width, int $height, string $position = 'topleft')
    {
        parent::__construct($width, $height);
        $this->position = $position;
    }

    /**
     * @inheritDoc
     * @throws ImagineException\InvalidArgumentException
     * @throws ImagineException\OutOfBoundsException
     * @throws ImagineException\RuntimeException
     */
    public function manipulate(ImageInterface $image): ImageInterface
    {
        $canvasBox = new Box(
            $this->getCanvasWidth(),
            $this->getCanvasHeight()
        );

        // If canvas is smaller than image, then crop image to canvas
        if (!$canvasBox->contains($image->getSize())) {
            $cropBox = new Box(
                min($canvasBox->getWidth(), $image->getSize()->getWidth()),
                min($canvasBox->getHeight(), $image->getSize()->getHeight())
            );
            $image->crop(
                $this->getImageBoxPoint($image->getSize(), $cropBox, $this->position),
                $cropBox
            );
        }
        return $image;
    }


    /**
     * Gets the top left point of $imageBox when it is aligned at
     * $alignment within $canvasBox.
     *
     * @param BoxInterface $canvasBox
     * @param BoxInterface $imageBox
     * @param string       $alignment
     *
     * @return PointInterface
     * @throws ImagineException\InvalidArgumentException
     */
    private function getImageBoxPoint(
        BoxInterface $canvasBox,
        BoxInterface $imageBox,
        string $alignment = 'center'
    ) {
        $xLeft   = 0;
        $xRight  = $canvasBox->getWidth() - $imageBox->getWidth();
        $xCenter = (int)($xRight / 2);
        $yTop    = 0;
        $yBottom = $canvasBox->getHeight() - $imageBox->getHeight();
        $yCenter = (int)($yBottom / 2);
        switch (strtolower($alignment)) {
            case 'top':
                $xPoint = $xCenter;
                $yPoint = $yTop;
                break;
            case 'top-right':
                $xPoint = $xRight;
                $yPoint = $yTop;
                break;
            case 'right':
                $xPoint = $xRight;
                $yPoint = $yCenter;
                break;
            case 'bottom-right':
                $xPoint = $xRight;
                $yPoint = $yBottom;
                break;
            case 'bottom':
                $xPoint = $xCenter;
                $yPoint = $yBottom;
                break;
            case 'bottom-left':
                $xPoint = $xLeft;
                $yPoint = $yBottom;
                break;
            case 'left':
                $xPoint = $xLeft;
                $yPoint = $yCenter;
                break;
            case 'top-left':
                $xPoint = $xLeft;
                $yPoint = $yTop;
                break;
            case 'center':
            case 'middle':
            default:
                $xPoint = $xCenter;
                $yPoint = $yCenter;
                break;
        }
        return new Point($xPoint, $yPoint);
    }
}
