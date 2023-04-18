<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Manifest;

use Alaress\Web\Controller\Base;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\MobileApp\ServiceMapper;

class Controller extends Base
{
    /**
     * @var ServiceMapper
     */
    private $serviceMapper;

    /**
     * Maps notification providers to application stores they are available in
     * https://github.com/w3c/manifest/wiki/Platforms
     * @var array
     */
    private $relatedAppsMap = ['APNS' => 'itunes', 'GCM' => 'play', 'Digistorm' => 'digistorm'];

    /**
     * Controller constructor.
     *
     * @param ServiceMapper $serviceMapper
     */
    public function __construct(ServiceMapper $serviceMapper)
    {
        $this->serviceMapper = $serviceMapper;
    }


    /**
     * Schoolbox PWA manifest file, can be accessed with and without a session
     * @api   GET /manifest
     * @since 23.0
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getManifest(): void
    {
        $cdnPrefixGenerator = new PrefixGenerator($this->getConfigLoader());
        $manifest = [
            'id'                          => INSTANCE_ID,
            'name'                        => $this->getConfigLoader()->get('title'),
            'short_name'                  => $this->getConfigLoader()->get('title'),
            'theme_color'                 => $this->getConfigLoader()->get('skin_colour_navigation_background'),
            'background_color'            => $this->getConfigLoader()->get('skin_colour_navigation_background'),
            'display'                     => 'minimal-ui',
            'Scope'                       => '/',
            'start_url'                   => '/',
            'icons'                       => [
                [
                    'src'     => '/images/logo.php?logo=skin_favicon&size=apple72',
                    'sizes'   => '72x72',
                    'type'    => 'image/png',
                    'purpose' => 'maskable any',
                ],
                [
                    'src'     => '/images/logo.php?logo=skin_favicon&size=google96',
                    'sizes'   => '96x96',
                    'type'    => 'image/png',
                    'purpose' => 'maskable any',

                ],
                [
                    'src'     => '/images/logo.php?logo=skin_favicon&size=google128',
                    'sizes'   => '128x128',
                    'type'    => 'image/png',
                    'purpose' => 'maskable any',
                ],
                [
                    'src'     => '/images/logo.php?logo=skin_favicon&size=apple144',
                    'sizes'   => '144x144',
                    'type'    => 'image/png',
                    'purpose' => 'maskable any',
                ],
                [
                    'src'     => '/images/logo.php?logo=skin_favicon&size=apple152',
                    'sizes'   => '152x152',
                    'type'    => 'image/png',
                    'purpose' => 'maskable any',
                ],
                [
                    'src'     => '/images/logo.php?logo=skin_favicon&size=google192',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'maskable any',
                ],
                [
                    'src'     => '/images/logo.php?logo=skin_favicon&size=google384',
                    'sizes'   => '384x384',
                    'type'    => 'image/png',
                    'purpose' => 'maskable any',
                ],
                [
                    'src'     => '/images/logo.php?logo=skin_favicon&size=google512',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable any',
                ],
            ],
            'splash_pages'                => null,
            'shortcuts'     => [
                [
                    'name'          => 'Timetable',
                    'short_name'    => 'Timetable',
                    'description'   => 'View your timetable',
                    'url'           => '/timetable',
                    'icons'         => [
                        [
                            "src" => $cdnPrefixGenerator->generate() . "/images/icons/192x192/timetable.png",
                            "sizes" => "192x192"
                        ],
                    ]
                ],
                [
                    'name'          => 'News',
                    'short_name'    => 'News',
                    'description'   => 'Open the News',
                    'url'           => '/news',
                    'icons'         => [
                        [
                            "src" => $cdnPrefixGenerator->generate() . "/images/icons/192x192/news.png",
                            "sizes" => "192x192"
                        ],
                    ]
                ],
                [
                    'name'          => 'Calendar',
                    'short_name'    => 'Calendar',
                    'description'   => 'View your Calendar',
                    'url'           => '/calendar',
                    'icons'         => [
                        [
                            "src" => $cdnPrefixGenerator->generate() . "/images/icons/192x192/calendar.png",
                            "sizes" => "192x192"
                        ],
                    ]
                ],
            ],
        ];

        $serviceList = $this->serviceMapper->findAll();

        if (count($serviceList)) {
            $manifest['prefer_related_applications'] = true;
            foreach ($serviceList as $application) {
                $url = '';

                if ($application->getProviderName() === 'GCM') {
                    $url = 'https://play.google.com/store/apps/details?id=' . $application->getAppIdentifier();
                }

                $manifest['related_applications'][] = [
                    'platform' => $this->relatedAppsMap[$application->getProviderName()],
                    'url'       => $url,
                    'id'        => $application->getAppIdentifier(),
                ];
            }
        }

        // Don't store to the history
        $this->setAllowBackToTheFuture(false);
        $this->getResponse()->content->set($this->buildJsonOutput($manifest));
    }

    /**
     * Schoolbox PWA offline page, can be accessed with and without a session
     * @api   GET /manifest/offline
     * @since 23.0
     *
     * @return void
     */
    public function getOffline(): void
    {
        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@manifest/offline.twig', []));
    }

    /**
     * Schoolbox PWA service worker file, can be accessed with and without a session
     *
     * This needs to exist as close to the web root as it can, so it can service the full system (everything below it)
     * We also need to use the `CDN_PREFIX` to link to the correct fail whale image
     *
     * @see https://web.dev/learn/pwa/service-workers/#scope
     *
     * @api   GET /service-worker.js
     * @since 23.0
     *
     * @return void
     */
    public function getServiceWorker(): void
    {
        // Don't store to the history
        $this->setAllowBackToTheFuture(false);
        $this->getResponse()->headers->set('Content-Type', 'application/javascript');
        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@manifest/service-worker.js', []));
    }
}
