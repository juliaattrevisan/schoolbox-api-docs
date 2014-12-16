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
        $processor = null;
        switch ($strategy) {
            case 'ConstrainKeepCanvas':
                $processor = new \Alaress_Image_Processor_Strategy_ConstrainKeepCanvas($strategyArgs);
                break;
            case 'Constrain':
                $processor = new \Alaress_Image_Processor_Strategy_Constrain($strategyArgs);
                break;
            case 'Crop':
                $processor = new \Alaress_Image_Processor_Strategy_Crop($strategyArgs);
                break;
            case 'Original':
                $processor = new \Alaress_Image_Processor_Strategy_Original($strategyArgs);
                break;
            case 'Placement':
                $processor = new \Alaress_Image_Processor_Strategy_Placement($strategyArgs);
                break;
            case 'Rectangle':
                $processor = new \Alaress_Image_Processor_Strategy_Rectangle($strategyArgs);
                break;
            case 'RotateOrientation':
                $processor = new \Alaress_Image_Processor_Strategy_RotateOrientation($strategyArgs);
                break;
            case 'Sharpen':
                $processor = new \Alaress_Image_Processor_Strategy_Sharpen($strategyArgs);
                break;
            case 'Square':
                $processor = new \Alaress_Image_Processor_Strategy_Square($strategyArgs);
                break;
            case 'StretchyButton':
                $processor = new \Alaress_Image_Processor_Strategy_StretchyButton($strategyArgs);
                break;
            default:
                $strategyName = '\\Alaress_Image_Processor_Strategy_' . $strategy;
                $processor    = new $strategyName($strategyArgs);
                break;
        }
        $processor->setSupportsAlphaChannel($filetype->supportsAlphaChannel());
        return $processor;
    }
}
