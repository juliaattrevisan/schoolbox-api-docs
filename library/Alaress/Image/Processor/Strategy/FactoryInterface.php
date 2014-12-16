<?php
/**
 * Defines an interface for image processors.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Processor\Strategy;

interface FactoryInterface
{
    /**
     * Creates a processor strategy.
     *
     * @param  string $strategy
     * @param  array  $strategyArgs
     * @return ProcessorInterface
     * @throws \Exception_Schoolbox if image isn't recognised
     */
    public function build($strategy, $strategyArgs = null);
}
