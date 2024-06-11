<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Paperly;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Twig\Error\Error;
use Alaress\Codec\Json\Base64;

class Controller extends Base
{
    private string $paperlyHostName;
    private string $dummyPayload;
    private array $paperlyPayload;
    private Base64 $base64Codec;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        //TODO replace with the Paperly hostname
        $this->paperlyHostName = "https://url";

        $this->base64Codec = new Base64();

        //TODO replace with real payloads once we have it

        $this->dummyPayload = '{
            "features": [
                {
                    "title": "Forms",
                    "url": "https://url/to/iframe/create_forms"
                },
                {
                    "title": "Excursions",
                    "url": "https://url/to/iframe/create_excursions"
                },
                {
                    "title": "Parent Teacher Interview",
                    "url": "https://url/to/iframe/view_pti"
                }
            ],
            "upcoming": [
                {
                    "title": "Approve Field Trip: Visit to Science Museum",
                    "status": "Pending Approval",
                    "due": "2024-04-15T08:30:00+00:00",
                    "url": "https://url/to/iframe/complete_action"
                },
                {
                    "title": "Permission Slip: School Play Performance",
                    "status": "Pending Approval",
                    "due": "2024-04-17T10:15:00+00:00",
                    "url": "https://url/to/iframe/complete_action"
                },
                {
                    "title": "Parent-Teacher Conference Sign-Up",
                    "due": "2024-04-20T12:45:00+00:00",
                    "url": "https://url/to/iframe/complete_action"
                },
                {
                    "title": "Approve Budget: Annual School Dance",
                    "status": "Pending Approval",
                    "due": "2024-04-22T09:00:00+00:00",
                    "url": "https://url/to/iframe/complete_action"
                },
                {
                    "title": "Review Curriculum Proposal: New Language Program",
                    "status": "Pending Approval",
                    "due": "2024-04-25T13:30:00+00:00",
                    "url": "https://url/to/iframe/complete_action"
                },
                {
                    "title": "Teacher Workshop Attendance Form",
                    "status": "Pending Approval",
                    "due": "2024-04-27T11:45:00+00:00",
                    "url": "https://url/to/iframe/complete_action"
                },
                {
                    "title": "Approve Student Council Budget Proposal",
                    "status": "Pending Approval",
                    "due": "2024-04-30T14:20:00+00:00",
                    "url": "https://url/to/iframe/complete_action"
                }
            ]
        }';
        $this->paperlyPayload = json_decode($this->dummyPayload, true);
    }

    /**
     * Prepare routes for paperly iframe
     *
     * @param   array $paperlyLink
     * @return  string
     * @throws  RouteNotFound
     */
    private function preparePaperlyRoute($paperlyLink): string
    {
        $parsedUrl = parse_url($paperlyLink['url']);

        $link = $this->base64Codec->encode(
            [
                'pageTitle' => $paperlyLink['title'],
                'url' => $parsedUrl['path'] . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '')
            ]
        );

        return $this->routeGenerator
            ->generate(
                'paperly.getPaperlyViewIframe',
                [
                    'encodedUrl' => urlencode($link)
                ]
            );
    }

    /**
     * Get a list of Paperly links to redirect to the matching iframes
     *
     * Returns data as JSON
     *
     * @api     GET /paperly/viewPaperly
     */
    public function getPaperlyLinks(): void
    {
        $paperlyLinks = [];

        foreach ($this->paperlyPayload['features'] as $feature) {
            $paperlyLinks['features'][][$feature['title']] = $this->preparePaperlyRoute($feature);
        }

        $upcomingActions = [];
        foreach ($this->paperlyPayload['upcoming'] as $order => $upcoming) {
            $upcoming['url'] = $this->preparePaperlyRoute($upcoming);
            $upcomingActions[$order] = $upcoming;
        }
        $paperlyLinks['upcoming'] = $upcomingActions;

        $this->response = $this->responseAsJson($paperlyLinks);
    }

    /**
     * Load an iframe for Paperly
     *
     * @param string $encodedUrl
     * @throws Error
     */
    public function getPaperlyViewIframe(string $encodedUrl): void
    {
        $path = $this->base64Codec->decode(urldecode($encodedUrl));

        $this->response = $this->responseFromTemplate('@paperly/iframe.twig', [
            //Removing trailing forward slash on hostname
            'url' => preg_replace(
                '/(?<!:)\/\//',
                '/',
                $this->paperlyHostName . $path['url']
            )
        ], "Paperly " . $path['pageTitle']);
    }
}
