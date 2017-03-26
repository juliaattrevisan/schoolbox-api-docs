<?php
/**
 * Factory for building external database sync classes.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Aura\Di\Container as Injector;
use Schoolbox\IConfigLoader;
use UnexpectedValueException;

class TimetableFactory
{
    /**
     * Choose and return the required variant of the external timetable object
     *
     * @param Injector $di
     * @return TimetableInterface
     * @throws UnexpectedValueException If nothing can be built
     */
    public function build($di)
    {
        /** @var IConfigLoader $config */
        $config = $di->get('config');

        $externalType = ucwords($config->get('external_type'));
        $classPrefix = "\\Schoolbox\\External\\Connector\\{$externalType}";

        $siteLocation = ucwords($config->get('site_location'));

        $classSiteSpecific = "{$classPrefix}\\{$siteLocation}\\Timetable";
        $classBase = "{$classPrefix}\\Base\\Timetable";

        if (class_exists($classSiteSpecific)) {
            return new $classSiteSpecific($di);
        } elseif (class_exists($classBase)) {
            return new $classBase($di);
        }

        throw new UnexpectedValueException("Unknown classes: {$classSiteSpecific} & {$classBase}");
    }
}
