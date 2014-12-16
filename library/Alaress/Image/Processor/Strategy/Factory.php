<?php
/**
 * Processor strategy factory for the original image transform implementation.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Processor\Strategy;

use Alaress\Image\Filetype\FiletypeInterface;

class Factory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function build($strategy, $strategyArgs, FiletypeInterface $filetype)
    {
        switch ($strategy) {
            case 'ConstrainKeepCanvas':
                return new \Alaress_Image_Processor_Strategy_ConstrainKeepCanvas($strategyArgs);
            case 'Constrain':
                return new \Alaress_Image_Processor_Strategy_Constrain($strategyArgs);
            case 'Crop':
                return new \Alaress_Image_Processor_Strategy_Crop($strategyArgs);
            case 'Original':
                return new \Alaress_Image_Processor_Strategy_Original($strategyArgs);
            case 'Placement':
                return new \Alaress_Image_Processor_Strategy_Placement($strategyArgs);
            case 'Rectangle':
                return new \Alaress_Image_Processor_Strategy_Rectangle($strategyArgs);
            case 'RotateOrientation':
                return new \Alaress_Image_Processor_Strategy_RotateOrientation($strategyArgs);
            case 'Sharpen':
                return new \Alaress_Image_Processor_Strategy_Sharpen($strategyArgs);
            case 'Square':
                return new \Alaress_Image_Processor_Strategy_Square($strategyArgs);
            case 'StretchyButton':
                return new \Alaress_Image_Processor_Strategy_StretchyButton($strategyArgs);
            default:
                $strategyName = '\\Alaress_Image_Processor_Strategy_' . $strategy;
                $processor    = new $strategyName($strategyArgs);
                return $processor;
        }
    }
}
