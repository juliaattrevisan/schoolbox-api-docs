<?php
/**
 * Defines an interface for image processors.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Processor\Strategy;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Alaress\Image\Filetype\FiletypeInterface;

interface FactoryInterface
{
    /**
     * Creates a processor strategy.
     *
     * @param  string $strategy
     * @param  array  $strategyArgs
     * @param  FiletypeInterface $filetype May be required for processing or setting
     *                                     options on processor once built
     * @return StrategyInterface
     * @throws SchoolboxException if image isn't recognised
     */
    public function build($strategy, $strategyArgs, FiletypeInterface $filetype);
}
