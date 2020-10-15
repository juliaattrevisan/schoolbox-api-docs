<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\Image\Transform;

use Alaress\Image\ITransform;
use Imagine\Image\ImageInterface;

/**
 * Applies a sequence of transforms
 */
class Sequence implements ITransform
{
    /** @var ITransform[] */
    private $transforms;

    /**
     * @param ITransform ...$transforms
     */
    public function __construct(ITransform...$transforms)
    {
        $this->transforms = $transforms;
    }

    /** @inheritDoc */
    public function manipulate(ImageInterface $image): ImageInterface
    {
        foreach ($this->transforms as $transform) {
            $image = $transform->manipulate($image);
        }
        return $image;
    }
}
