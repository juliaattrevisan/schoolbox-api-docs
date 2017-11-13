<?php
/**
 * Interface for Twig chain loaders.
 *
 * Implemented so that classes don't have to require `IChainLoader`.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Twig\Loader;

use Twig\Loader\LoaderInterface;

/**
 * Class IChainLoader
 */
interface IChainLoader extends LoaderInterface
{
    /**
     * Add a loader
     *
     * @param LoaderInterface $loader
     */
    public function addLoader(LoaderInterface $loader);
}
