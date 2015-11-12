<?php
/**
 * An interface for module loaders.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Module;

interface LoaderInterface
{
    /**
     * If module $name hasn't yet been loaded, attempt to load it.
     *
     * @param string $moduleString A string to extract a module name from somehow.
     */
    public function loadModule($moduleString);
}
