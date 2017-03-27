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

    const EXTERNAL_USER = 1;
    const EXTERNAL_TIMETABLE = 2;
    const EXTERNAL_SYNC = 3;
    const EXTERNAL_EVENTS = 4;
    const EXTERNAL_COMMUNITY_PORTAL = 5;

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
        $offlineBase = "\\Schoolbox\\External\\Connector\\Offline\\";
        $prefixBase = "\\Schoolbox\\External\\Connector\\" . ucwords($config->get('external_type')) . "\\";
        $prefixSchool = $prefixBase . ucwords($config->get('site_location')) . "\\";

        switch ($type) {
            case self::EXTERNAL_USER:
                $classBase = "{$prefixBase}User";
                $classSchool = "{$prefixSchool}\\User";
                break;

            case self::EXTERNAL_TIMETABLE:
                $classBase = "{$prefixBase}Timetable";
                $classSchool = "{$prefixSchool}Timetable";
                break;

            case self::EXTERNAL_SYNC:
                $classBase = "{$prefixBase}Sync";
                $classSchool = "{$prefixSchool}Sync";
                break;

            case self::EXTERNAL_EVENTS:
                $classBase = "{$prefixBase}ExternalEvents";
                $classSchool = "{$prefixSchool}ExternalEvents";
                break;

            case self::EXTERNAL_COMMUNITY_PORTAL:

                // Community portal is Synergetic only

                if ($config->get('external_type') == 'Synergetic' && $config->get('synergetic_password_reset')) {

                    $classBase = "{$prefixBase}ExternalEvents";
                    $classSchool = "{$prefixSchool}ExternalEvents";

                } else {

                    // Everyone else can just get the offline class

                    $classBase = "{$offlineBase}ExternalEvents";
                    $classSchool = "{$offlineBase}ExternalEvents";
                }

                break;

            default:
                throw new UnexpectedValueException("Unknown external object type requested: '{$type}'");
        }

        if (class_exists($classSchool)) {
            return new $classSchool($config, $databaseExternal);
        } elseif (class_exists($classBase)) {
            return new $classBase($config, $databaseExternal);
        }

        throw new UnexpectedValueException("Could not located requested external object class: '{$classBase}'");
    }
}
