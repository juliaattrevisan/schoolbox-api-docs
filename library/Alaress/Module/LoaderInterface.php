<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Module;

/**
 * An interface for module loaders.
 */
interface LoaderInterface
{
    /**
     * If module $name hasn't yet been loaded, attempt to load it.
     *
     * @param string $moduleString A string to extract a module name from somehow.
     */
    public function loadModule(string $moduleString);
}
