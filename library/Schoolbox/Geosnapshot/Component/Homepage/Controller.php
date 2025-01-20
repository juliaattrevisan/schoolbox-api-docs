<?php

/**
 * @copyright   Copyright 2021, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Geosnapshot\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Geosnapshot\Api;
use Schoolbox\Geosnapshot\ValidGeosnapshotAvailable;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Class Controller
 *
 * Display an iframe of Geosnapshot galleries that matched a search string
 * specified in the component's configuration
 *
 * @package Schoolbox\Geosnapshot\Component\Homepage
 */
class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @var string|null
     */
    private ?string $url;

    /**
     * @var int|null
     */
    private ?int $height;

    /**
     * Only render this component if Geosnapshot has been set up
     *
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     */
    public function execute(): bool
    {
        $this->url = null;

        /* If the component has a search term, ask Geosnapshot for an answer
         */
        if (
            (new ValidGeosnapshotAvailable())->isConfigured(
                $this->getConfigLoader()->get('geosnapshot_api_key'),
                $this->getConfigLoader()->get('geosnapshot_enabled')
            )
        ) {
            /* Set up guzzle with the authentication pieces
             *
             * If it dies, it dies (throw out a generic error screen when that happens)
             */
            try {
                $api = new Api($this->getConfigLoader()->get('geosnapshot_api_key'));

                $response = $api->retrieveEventPhotos($this->getConfig('eventName'), $this->getUser());
            } catch (\RuntimeException | \DomainException $e) {
                /* DomainException is thrown when json can't be decoded
                 */
                return false;
            }

            /* Get the iframe and shank it into our component, if successful
             */
            $this->url = $response['url'];

            /* Get the height set in the component's configuration settings
             */
            $this->height = $this->getConfig('height');

            return true;
        }

        /* Give write-accessors a chance to understand why this component might be broken
         */
        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            return true;
        }

        /* Component not ready for usage; hide it
         */
        return false;
    }


    /**
     * Render the component's template
     *
     * @param string $templateHandle
     * @return bool
     * @throws \Exception
     */
    public function renderTemplate($templateHandle): bool
    {
        return $this->renderTwigTemplate($templateHandle, '@geosnapshotHomepage/component.twig', [
            'url' => $this->url,
            'height' => $this->height
        ]);
    }
}
