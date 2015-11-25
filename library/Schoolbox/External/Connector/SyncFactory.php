<?php
/**
 * Factory for building external database sync classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

class SyncFactory
{
    /**
     * Choose which variant of the sync to spawn
     *
     * @return SyncInterface
     * @throws \LogicException if nothing can be built
     */
    public function build()
    {
        $prefix = '\Schoolbox\External\Connector\\' .  ucwords(\Config::get('external_type'));

        /* School-specific version of the Connector
         */
        $specific = ucwords(\Config::get('site_location'));
        $general = 'Base';
        $specificClassname = "{$prefix}\\{$specific}\\Sync";
        $generalClassname = "{$prefix}\\{$general}\\Sync";

        if (class_exists($specificClassname)) {
            return new $specificClassname();
        }
        else if (class_exists($generalClassname)) {
            return new $generalClassname();
        }

        throw new \LogicException("Unknown classes: {$specificClassname} {$generalClassname}");
    }
}