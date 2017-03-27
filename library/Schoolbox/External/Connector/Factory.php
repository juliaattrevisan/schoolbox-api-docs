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
     * @param TimetableInterface $timetableExternal External timetable connector
     * @param string $type Type of external object we want (one of the constants above)
     * @return mixed
     * @throws UnexpectedValueException If nothing can be built
     */
    public function build($config, $databaseExternal, $timetableExternal, $type)
    {
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

                    $key = false;
                    $iv = false;

                    $webServicesKeyFile = '/etc/schoolbox-synergetic/config.json';

                    if (is_readable($webServicesKeyFile) && is_file($webServicesKeyFile)) {
                        $config = json_decode(file_get_contents($webServicesKeyFile), true);
                        $key = base64_decode($config['key']);
                        $iv = base64_decode($config['iv']);
                    } else {
                        // can't read file, that's a warning at least.
                        trigger_error('Cannot read community portal key - password will not be changed', E_USER_WARNING);
                    }

                    return new Synergetic\Base\CommunityPortal($databaseExternal, $key, $iv);

                }

                // Everyone else can just get the offline class

                return new Offline\Base\CommunityPortal();

            default:
                throw new UnexpectedValueException("Unknown external object type requested: '{$type}'");
        }

        if (class_exists($classSchool)) {
            return new $classSchool($config, $databaseExternal, $timetableExternal);
        } elseif (class_exists($classBase)) {
            return new $classBase($config, $databaseExternal, $timetableExternal);
        }

        throw new UnexpectedValueException("Could not located requested external object class: '{$classBase}'");
    }
}
