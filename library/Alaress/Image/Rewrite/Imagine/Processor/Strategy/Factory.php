<?php
/**
 * Processor factory for the Imagine image transform implementation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Rewrite\Imagine\Processor\Strategy;

use Alaress\Image\Processor\Strategy\FactoryInterface;
use Alaress\Image\Processor\Strategy\ProcessorInterface;

class Factory implements FactoryInterface
{
    /**
     * Creates a filetype strategy.
     *
     * @param  string $strategy
     * @param  array  $strategyArgs
     * @return ProcessorInterface
     * @throws \Exception_Schoolbox if image isn't recognised
     */
    public function build($strategy, $strategyArgs = null)
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
                // The Imagine instance needs to be injected
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
