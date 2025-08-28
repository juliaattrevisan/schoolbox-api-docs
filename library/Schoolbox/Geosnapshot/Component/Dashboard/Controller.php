<?php

/**
 * @copyright   Copyright 2021, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Geosnapshot\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Geosnapshot\Api;
use Schoolbox\Geosnapshot\ValidGeosnapshotAvailable;

/**
 * Class Controller
 *
 * Display an iframe of Geosnapshot galleries that matched a search string
 * specified in the component's configuration
 *
 * @package Schoolbox\Geosnapshot\Component\Dashboard
 */
class Controller extends Component_Dashboard_ControllerAbstract
{
    /**
     * @var string|null
     */
    private ?string $url = null;

    /**
     * @var int|null
     */
    private ?int $height = null;


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

                $response = $api->retrieveEventPhotos(
                    trim((string)$this->getConfig('eventName')),
                    trim((string)$this->getConfig('albumName')),
                    $this->getUser()
                );
            } catch (\RuntimeException | \DomainException $e) {
                /* DomainException is thrown when json can't be decoded
                 */
                return false;
            }

            /* Get the iframe and shank it into our component, if successful
             */
            $this->url = $response['url'];

            /* Get the height set in the component's configuration settings, setting to a min of 500 if not set
             */
            $heightInPixels = $this->getConfig('height');
            $this->height = (is_int($heightInPixels) || ctype_digit($heightInPixels)) ? (int)$heightInPixels : 500;

            return true;
        }

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
        return $this->renderTwigTemplate($templateHandle, '@geosnapshotDashboard/component.twig', [
            'url' => $this->url,
            'height' => $this->height
        ], false, false, true);
    }
}
