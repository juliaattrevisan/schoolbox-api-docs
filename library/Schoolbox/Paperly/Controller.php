<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Paperly;

use Alaress\Web\Controller\Base;
use Twig\Error\Error;
use Alaress\Codec\ICodec;

class Controller extends Base implements ICodec
{
    private string $paperlyHostName;
    private string $dummyPayload;
    private array $paperlyPayload;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        //TODO replace with the Paperly hostname
        $this->paperlyHostName = "https://url";

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
     * Encode the path to the iframe by striping the hostname to be prepended by trusted Paperly hostname
     *
     * @param   mixed $rawValue
     * @return  array
     */
    public function encode(mixed $rawValue): array
    {
        $parsedUrl = parse_url($rawValue['url']);

        return array_map(
            'urlencode',
            [
                'title' => $rawValue['title'],
                'url' => base64_encode(
                    $parsedUrl['path']
                )
            ]
        );
    }
    /**
     * Decode the path and prepend with trusted Paperly hostname
     *
     * @param   mixed $encodedValue
     * @return  array
     */
    public function decode(mixed $encodedValue): array
    {
        $urlDecodedValue = array_map('urldecode', $encodedValue);
        return [
            'title' => $urlDecodedValue['title'],
            'url' => preg_replace(
                '/(?<!:)\/\//',
                '/',
                $this->paperlyHostName . base64_decode($urlDecodedValue['url'])
            )
        ];
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
            $featureTitle = $feature['title'];
            $path = $this->encode($feature);
            $paperlyLinks['features'][][$featureTitle] =
                $this->routeGenerator
                    ->generate(
                        'paperly.getPaperlyViewIframe',
                        [
                            'pageTitle' => $path['title'],
                            'encodedUrl' => $path['url']
                        ]
                    );
        }

        $upcomingActions = [];
        foreach ($this->paperlyPayload['upcoming'] as $order => $upcoming) {
            $path = $this->encode($upcoming);
            $upcoming['url'] = $this->routeGenerator
                ->generate(
                    'paperly.getPaperlyViewIframe',
                    [
                        'pageTitle' => $path['title'],
                        'encodedUrl' => $path['url']
                    ]
                );
            $upcomingActions[$order] = $upcoming;
        }
        $paperlyLinks['upcoming'] = $upcomingActions;

        $this->response = $this->responseAsJson($paperlyLinks);
    }

    /**
     * Load an iframe for Paperly
     *
     * @param string $encodedUrl
     * @param string $pageTitle
     * @throws Error
     */
    public function getPaperlyViewIframe(string $encodedUrl, string $pageTitle): void
    {
        $path = $this->decode(
            [
                'title' => $pageTitle,
                'url' => $encodedUrl
            ]
        );

        $this->response = $this->responseFromTemplate('@paperly/iframe.twig', [
            'url' => $path['url']
        ], "Paperly " . $path['title']);
    }
}
