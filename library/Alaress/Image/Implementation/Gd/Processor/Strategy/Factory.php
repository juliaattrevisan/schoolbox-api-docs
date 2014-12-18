<?php
/**
 * Processor strategy factory for the original image transform implementation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Implementation\Gd\Processor\Strategy;

use Alaress\Image\Filetype\FiletypeInterface;
use Alaress\Image\Implementation\Gd\Filetype\BaseFiletype;
use Alaress\Image\Processor\Strategy\FactoryInterface;

class Factory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function build($strategy, $strategyArgs, FiletypeInterface $filetype)
    {
        $processor = null;
        switch ($strategy) {
            case 'ConstrainKeepCanvas':
                $processor = new ConstrainKeepCanvas($strategyArgs);
                break;
            case 'Constrain':
                $processor = new Constrain($strategyArgs);
                break;
            case 'Crop':
                $processor = new Crop($strategyArgs);
                break;
            case 'Original':
                $processor = new Original($strategyArgs);
                break;
            case 'Placement':
                $processor = new Placement($strategyArgs);
                break;
            case 'Rectangle':
                $processor = new Rectangle($strategyArgs);
                break;
            case 'RotateOrientation':
                $processor = new RotateOrientation($strategyArgs);
                break;
            case 'Sharpen':
                $processor = new Sharpen($strategyArgs);
                break;
            case 'Square':
                $processor = new Square($strategyArgs);
                break;
            case 'StretchyButton':
                $processor = new StretchyButton($strategyArgs);
                break;
            default:
                $strategyName = '\\Alaress\\Image\\Implementation\\Gd\\Processor\\Strategy\\' . $strategy;
                $processor    = new $strategyName($strategyArgs);
                break;
        }

        // Set if this supports the alpha channel.
        // Newer processors don't care, but this old one does.
        /** @var BaseStrategy $processor */
        /** @var BaseFiletype $filetype */
        $processor->setSupportsAlphaChannel($filetype->supportsAlphaChannel());
        return $processor;
    }
}
