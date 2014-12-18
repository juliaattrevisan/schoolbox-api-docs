<?php
/**
 * Processor factory for the Imagine image transform implementation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Implementation\Imagine\Processor\Strategy;

use Alaress\Image\Filetype\FiletypeInterface;
use Alaress\Image\Processor\Strategy\FactoryInterface;
use Alaress\Image\Implementation\Imagine\Filetype\BaseFiletype;

class Factory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     *
     * @var BaseFiletype $filetype
     */
    public function build($strategy, $strategyArgs, FiletypeInterface $filetype)
    {
        $imageManager = $filetype->getImageManager();
        switch ($strategy) {
            case 'ConstrainKeepCanvas':
                return new ConstrainKeepCanvas($strategyArgs, $imageManager);
            case 'Constrain':
                return new Constrain($strategyArgs, $imageManager);
            case 'Crop':
                return new Crop($strategyArgs, $imageManager);
            case 'Original':
                return new Original($strategyArgs, $imageManager);
            case 'Placement':
                return new Placement($strategyArgs, $imageManager);
            case 'Rectangle':
                return new Rectangle($strategyArgs, $imageManager);
            case 'RotateOrientation':
                return new RotateOrientation($strategyArgs, $imageManager);
            case 'Sharpen':
                return new Sharpen($strategyArgs, $imageManager);
            case 'Square':
                return new Square($strategyArgs, $imageManager);
            default:
                $strategyName = '\\Alaress\\Image\\Rewrite\\Imagine\\Processor\\Strategy\\' . $strategy;
                $processor    = new $strategyName($strategyArgs, $imageManager);
                return $processor;
        }
    }
}
