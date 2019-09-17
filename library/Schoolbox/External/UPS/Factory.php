<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\UPS;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Config\Loader;
use Schoolbox\External\Connector\Isams\Base\Api;
use Schoolbox\External\Connector\Isams\Base\MockApi;
use Schoolbox\IConfigLoader;
use UnexpectedValueException;

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
     * ISAMS batch xml reader
     */
    private const PROVIDER_ISAMS_BATCH_XML = 'Batchxml';


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
        $classOffline = "\\Schoolbox\\External\\Connector\\Offline\\Base\\{$type}";

        if (!$databaseExternal instanceof IConnector) {
            return new $classOffline($config, null);
        }

        /* If config->get('external_ups_provider') is '' or 'default', use the same value as External Db
         * Otherwise, use its value which is one of a set of hardcoded options
         */
        if ($this->hasUPSProvider($config->get('external_ups_provider'))) {
            $providerName = ucwords($config->get('external_ups_provider'));

            /* ISAMS batch xml reader
             * TODO: This can probably evolve into a switch-case statement when other providers exist
             */
            if ($providerName === self::PROVIDER_ISAMS_BATCH_XML) {
//                $config = new Loader();
//                $api = new Api($config->get('external_ups_provider_batchxml_host'), $config->get('external_ups_provider_batchxml_key'));
//
//                return new Batchxml($api);
            }
        }

        /* Default: fall back to SIS classes
         */
        return (new \Schoolbox\External\Connector\Factory())->build($config, $databaseExternal, $type);
    }
}
