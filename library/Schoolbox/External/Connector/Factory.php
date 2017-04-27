<?php
/**
 * Factory for building external connector classes
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Alaress\Database\Connector\IConnector;
use Schoolbox\IConfigLoader;
use UnexpectedValueException;

class Factory
{
    // Types of external objects this thing can construct

    const EXTERNAL_USER = 'User';
    const EXTERNAL_TIMETABLE = 'Timetable';
    const EXTERNAL_SYNC = 'Sync';
    const EXTERNAL_EVENTS = 'ExternalEvents';
    const EXTERNAL_COMMUNITY_PORTAL = 'CommunityPortal';

    /**
     * Return the correct version of the requested external object
     *
     * @param IConfigLoader $config
     * @param array|IConnector $databaseExternal External database connection(s)
     * @param string $type Type of external object we want (one of the constants above)
     * @return mixed
     * @throws UnexpectedValueException If nothing can be built
     */
    public function build($config, $databaseExternal, $type)
    {
        $prefixBase = "\\Schoolbox\\External\\Connector\\" . ucwords($config->get('external_type'));
        $schoolCode = ucwords($config->get('site_location'));

        $classBase = "{$prefixBase}\\Base\\{$type}";
        $classSchool = "{$prefixBase}\\{$schoolCode}\\{$type}";

        if (class_exists($classSchool)) {
            return new $classSchool($config, $databaseExternal);
        } elseif (class_exists($classBase)) {
            return new $classBase($config, $databaseExternal);
        }

        throw new UnexpectedValueException("Could not located requested external object class: '{$classBase}'");
    }
}
