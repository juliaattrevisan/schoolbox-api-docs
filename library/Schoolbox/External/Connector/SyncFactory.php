<?php
/**
 * Factory for building external database sync classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Aura\Di\Container as Injector;

class SyncFactory
{
    /**
     * Choose which variant of the sync to spawn
     *
     * @param Injector $di
     * @return SyncInterface
     * @throws \UnexpectedValueException if nothing can be built
     */
    public function build($di)
    {
        $prefix = '\Schoolbox\External\Connector\\' .  ucwords(\Config::get('external_type'));

        /* School-specific version of the Connector
         */
        $specific = ucwords(\Config::get('site_location'));
        $general = 'Base';
        $specificClassname = "{$prefix}\\{$specific}\\Sync";
        $generalClassname = "{$prefix}\\{$general}\\Sync";

        // TODO move instantiation logic out of constructors, and into here
        if (class_exists($specificClassname)) {
            return new $specificClassname($di);
        } elseif (class_exists($generalClassname)) {
            return new $generalClassname($di);
        }

        throw new \UnexpectedValueException("Unknown classes: {$specificClassname} {$generalClassname}");
    }
}
