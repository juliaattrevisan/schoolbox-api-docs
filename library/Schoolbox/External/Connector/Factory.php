<?php

/**
 * Factory for building external connector classes
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector;

use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\IConfigLoader;
use UnexpectedValueException;

class Factory
{
    // Types of external objects this thing can construct

    public const EXTERNAL_USER = 'User';
    public const EXTERNAL_TIMETABLE = 'Timetable';
    public const EXTERNAL_SYNC = 'Sync';
    public const EXTERNAL_EVENTS = 'ExternalEvents';
    public const EXTERNAL_COMMUNITY_PORTAL = 'CommunityPortal';
    public const EXTERNAL_DOCUMENT = 'Document';

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

        $prefixBase = "\\Schoolbox\\External\\Connector\\" . ucwords($config->get('external_type'));
        $schoolCode = ucwords(strtolower($config->get('site_school_code')));

        $classBase = "{$prefixBase}\\Base\\{$type}";
        if ('tass' === $config->get('external_type') && ExternalConnectorFactory::EXTERNAL_SYNC === $type && $config->get('external_tass_version')) {
            $classVersion = "{$prefixBase}\\{$config->get('external_tass_version')}\\{$type}";
        }
        $classSchool = "{$prefixBase}\\{$schoolCode}\\{$type}";

        // Return in preference:
        // * School-specific connector for external type
        // * Standard connector for external type
        // * Offline connector
        if (class_exists($classSchool)) {
            return new $classSchool($config, $databaseExternal);
        }
        if (isset($classVersion) && class_exists($classVersion)) {
            return new $classVersion($config, $databaseExternal);
        }
        if (class_exists($classBase)) {
            return new $classBase($config, $databaseExternal);
        }
        if (class_exists($classOffline)) {
            return new $classOffline($config, null);
        }

        throw new UnexpectedValueException("Could not located requested external object class: '{$classBase}'");
    }
}
