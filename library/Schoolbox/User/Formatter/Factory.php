<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Formatter;

use Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

/**
 * Factory for `\Schoolbox\User\Formatter\Formatter` instances.
 */
class Factory
{
    /**
     * Build a formatter from out of nowhere.
     *
     * This uses the deprecated `Config` singleton, and thus should only be used
     * where an actual config object can't be easily accessed.
     *
     * @deprecated use `buildFromConfig` instead
     * @return Formatter
     */
    public function buildFromNothing(): Formatter
    {
        return new Formatter(Config::get(ConfigMapper::NAME_NAME_FORMAT));
    }

    /**
     * Build a formatter from a config object.
     *
     * @param IConfigLoader $config
     * @return Formatter
     */
    public function buildFromConfig(IConfigLoader $config): Formatter
    {
        return new Formatter($config->get(ConfigMapper::NAME_NAME_FORMAT));
    }
}
