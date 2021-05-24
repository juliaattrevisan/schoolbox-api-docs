<?php

/**
 * An interface for module loaders.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
