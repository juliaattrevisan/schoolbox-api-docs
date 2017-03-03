<?php
/**
 * Factory for building external database sync classes.
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector;

class ExternalEventsFactory
{
    /**
     * Choose which variant of the sync to spawn
     *
     * @return ExternalEventsInterface
     * @throws \UnexpectedValueException if nothing can be built
     */
    public function build()
    {
        $prefix = '\Schoolbox\External\Connector\\' .  ucwords(\Config::get('external_type'));
        /* School-specific version of the Connector
         */
        $specific = ucwords(\Config::get('site_location'));
        $general = 'Base';
        $specificClassname = "{$prefix}\\{$specific}\\ExternalEvents";
        $generalClassname = "{$prefix}\\{$general}\\ExternalEvents";

        // TODO move instantiation logic out of constructors, and into here
        if (class_exists($specificClassname)) {
            return new $specificClassname();
        } elseif (class_exists($generalClassname)) {
            return new $generalClassname();
        }

        throw new \UnexpectedValueException("Unknown classes: {$specificClassname} {$generalClassname}");
    }
}
