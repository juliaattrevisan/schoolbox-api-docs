<?php
/**
 * Processor factory for the Imagine image transform implementation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Rewrite\Imagine\Processor\Strategy;

use Alaress\Image\Filetype\FiletypeInterface;
use Alaress\Image\Processor\Strategy\FactoryInterface;

class Factory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function build($strategy, $strategyArgs, FiletypeInterface $filetype)
    {
        switch ($strategy) {
            case 'ConstrainKeepCanvas':
                return new ConstrainKeepCanvas($strategyArgs);
            case 'Constrain':
                return new Constrain($strategyArgs);
            case 'Crop':
                return new Crop($strategyArgs);
            case 'Original':
                return new Original($strategyArgs);
            case 'Placement':
                return new Placement($strategyArgs);
            case 'Rectangle':
                return new Rectangle($strategyArgs);
            case 'RotateOrientation':
                return new RotateOrientation($strategyArgs);
            case 'Sharpen':
                return new Sharpen($strategyArgs);
            case 'Square':
                return new Square($strategyArgs);
            default:
                $strategyName = '\\Alaress\\Image\\Rewrite\\Imagine\\Processor\\Strategy\\' . $strategy;
                $processor    = new $strategyName($strategyArgs);
                return $processor;
        }
    }
}
