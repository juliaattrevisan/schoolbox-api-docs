<?php
/**
 * Factory for building external database sync classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2016 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

class CommunityPortalFactory
{
    /**
     * Choose which variant to spawn
     *
     * @return CommunityPortalInterface
     * @throws \UnexpectedValueException if nothing can be built
     */
    public function build()
    {
        // TODO inject config
        $connector      = ucwords(\Config::get('external_type'));
        $webServicesUrl = \Config::get('external_web_services_url');

        // Synergetic only
        if ($connector != 'Synergetic' || empty($webServicesUrl)) {
            return new Offline\Base\CommunityPortal();
        } else {
            // TODO: store real keys somewhere (NOT CODE) and use them here
            $key = 'NOT A REAL KEY';
            $iv  = 'NOT A REAL IV EITHER';
            return new Synergetic\Base\CommunityPortal($key, $iv);
        }
    }
}
