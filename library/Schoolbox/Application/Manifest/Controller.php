<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Manifest;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Schoolbox policy page, can be accessed with and without a session
     * @api   GET /policy
     * @since 18.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getManifest(): void
    {
        $manifest = [
            'name'             => $this->getConfigLoader()->get('title'),
            'short_name'       => $this->getConfigLoader()->get('title'),
            'theme_color'      => $this->getConfigLoader()->get('skin_colour_navigation_background'),
            'background_color' => $this->getConfigLoader()->get('skin_colour_navigation_background'),
            'display'          => 'minimal-ui',
            'Scope'            => '/',
            'start_url'        => '/',
            'icons'            => [
                [
                    'src'   => '/images/logo.php?logo=skin_logo_square&size=apple72',
                    'sizes' => '72x72',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => '/images/logo.php?logo=skin_logo_square&size=google96',
                    'sizes' => '96x96',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => '/images/logo.php?logo=skin_logo_square&size=google128',
                    'sizes' => '128x128',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => '/images/logo.php?logo=skin_logo_square&size=apple144',
                    'sizes' => '144x144',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => '/images/logo.php?logo=skin_logo_square&size=apple152',
                    'sizes' => '152x152',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => '/images/logo.php?logo=skin_logo_square&size=google192',
                    'sizes' => '192x192',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => '/images/logo.php?logo=skin_logo_square&size=google384',
                    'sizes' => '384x384',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => '/images/logo.php?logo=skin_logo_square&size=google512',
                    'sizes' => '512x512',
                    'type'  => 'image/png',
                ],
            ],
            'splash_pages'     => null,
        ];
        $this->getResponse()->content->set($this->buildJsonOutput($manifest));
    }

    public function getOffline(): void {
        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@manifest/offline.twig', []));
    }
}
