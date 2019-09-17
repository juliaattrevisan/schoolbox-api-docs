<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\UPS;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Config\Loader;
use Schoolbox\IConfigLoader;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * Unlock the ability to provide UPS data in a different way for the same connector
 *
 * @package Schoolbox\External\UPS
 */
class Factory
{

    /**
     * Choose the way in which UPS sources its data
     *
     * If External UPS Provider isn't set to anything, or left on "select an option",
     * its value will be an empty string
     *
     * Or, someone can choose "default" which should prompt the factory to use whatever
     * its External DB setting is - no special case for ups
     */
    private const EXTERNAL_UPS_PROVIDER_DEFAULT = ['', 'default'];


    /**
     * Check if external_ups_provider is set to a non-default value
     *
     * @param string $provider
     * @return bool
     */
    private function hasUPSProvider(string $provider): bool
    {
        return !\in_array($provider, self::EXTERNAL_UPS_PROVIDER_DEFAULT, true);
    }

    /**
     * Return the correct version of the requested external object
     *
     * @param IConfigLoader $config
     * @param null|IConnector $databaseExternal External database connection(s)
     * @param string $type Type of external object we want (one of the constants above)
     * @return mixed
     * @throws UnexpectedValueException If nothing can be built
     */
    public function build(IConfigLoader $config, ?IConnector $databaseExternal, string $type)
    {

        // Check if external DB is unavailable and return offline class if we can't access
        if (!$databaseExternal instanceof IConnector) {
            $classOffline = "\\Schoolbox\\External\\Connector\\Offline\\Base\\{$type}";

            return new $classOffline($config, null);
        }

        /* If config->get('external_ups_provider') is '' or 'default', use the same value as External Db
         * Otherwise, use its value which is one of a set of hardcoded options
         *
         * TODO: Reinstate when required. This might not be be necessary because schoolbox connector
         * now takes prime position; but this does unlock the ability to process ups data differently
         *
         * Originally built for isams so that schoolbox can use the schoolbox connector but UPS could
         * be populated differently via some other mechanism (not the schoolbox connector itself)
         */
//        if ($this->hasUPSProvider($config->get('external_ups_provider'))) {
//            $providerName = ucwords($config->get('external_ups_provider'));
//
//            // spawn and return a new instance, like any other factory
//
//        }

        /* Default: fall back to SIS classes
         */
        return (new \Schoolbox\External\Connector\Factory())->build($config, $databaseExternal, $type);
    }
}
