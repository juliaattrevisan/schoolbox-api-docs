<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Source;

use InvalidArgumentException;
use Schoolbox\Curriculum\Source\AustralianCurriculum\Browser;

/**
 * Factory for creating browsers out of a curriculum source model.
 *
 * The browser knows how to get learning levels and learning areas out of a
 * source, and also how to get outcomes out of the source for a level and area.
 */
class BrowserFactory
{
    /**
     * @param Model $source
     *
     * @return BrowserInterface
     */
    public function build(Model $source): BrowserInterface
    {
        $browserClass = $source->getClass() . '\\Browser';
        return match ($browserClass) {
            Browser::class => new Browser(),
            default        => throw new InvalidArgumentException("Class \"$browserClass\" does not exist"),
        };
    }
}
