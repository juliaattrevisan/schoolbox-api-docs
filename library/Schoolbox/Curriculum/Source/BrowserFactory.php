<?php

/**
 * Factory for creating browsers out of a curriculum source model.
 *
 * The browser knows how to get learning levels and learning areas out of a
 * source, and also how to get outcomes out of the source for a level and area.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Source;

use InvalidArgumentException;
use Schoolbox\Mapper as BaseMapper;

class BrowserFactory
{
    /**
     * @param Model $source
     *
     * @return BrowserInterface
     */
    public function build(Model $source)
    {
        $browserClass = $source->getClass() . '\\Browser';
        $interface    = '\\Schoolbox\\Curriculum\\Source\\BrowserInterface';
        // PHP 5.5+
//        $interface    = BrowserInterface::class;
        if (class_exists($browserClass)) {
            if (is_a($browserClass, $interface, true)) {
                return new $browserClass($source);
            }
            throw new InvalidArgumentException("Class \"$browserClass\" exists, but is not a \"$interface\"");
        }
        throw new InvalidArgumentException("Class \"$browserClass\" does not exist");
    }
}
