<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Paperly;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Iframe\Validator;
use Twig\Error\Error;

class Controller extends Base
{
    private $dummyPayload;
    private $paperlyPayload;

    /**
     * Controller constructor.
     */
    public function __construct(private ErrorMapper $errorMapper)
    {
        //TODO replace with real payloads once we have it

        $this->dummyPayload = '{
            "features": [
                { "Forms": "https://url/to/iframe/create_forms" },
                { "Excursions": "https://url/to/iframe/create_excursions" },
                { "PTI": "https://url/to/iframe/view_pti" }
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

        //TODO optimizing the process and adding caching to the payload
    }

    /**
     * Checking whether the iframe url is valid
     *
     * @param string $iframeUrl
     * @throws Exception
     * @throws Error
     */
    private function validatingIframeEndpoints($iframeUrl) : void
    {
        if (null === $iframeUrl) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        if (!(new Validator($this->getConfigLoader()->get('safe_iframe_source_list')))->isValid($iframeUrl)) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
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
            $key = array_keys($feature)[0];
            $paperlyLinks['features'][][$key] = "/paperly/view/" . strtolower($key);
        }

        $upcomingActions = [];
        foreach ($this->paperlyPayload['upcoming'] as $order => $upcoming) {
            $upcoming['url'] = "/paperly/action/$order";
            $upcomingActions[$order] = $upcoming;
        }
        $paperlyLinks['upcoming'] = $upcomingActions;

        $this->response = $this->responseAsJson($paperlyLinks);
    }

    /**
     * Load an iframe for Paperly feature
     *
     * @param string $feature
     * @throws Exception
     * @throws Error
     */
    public function getPaperlyViewIframe(string $feature): void
    {

        $iframeUrl = null;

        foreach ($this->paperlyPayload['features'] as $feat) {
            $validFeature = array_keys($feat)[0];

            if (strtolower($validFeature) === $feature) {
                $iframeUrl = $feat[$validFeature];
                break; //Exit early if the feature matched
            }
        }

        $this->validatingIframeEndpoints($iframeUrl);

        $this->response = $this->responseFromTemplate('@paperly/iframe.twig', [
            'url' => $iframeUrl
        ], "Paperly " . ucwords($feature));
    }

    /**
     * Load an iframe for Paperly action
     *
     * @param int $order
     * @throws Exception
     * @throws Error
     */
    public function getPaperlyActionIframe(int $order): void
    {
        $iframeUrl = $this->paperlyPayload['upcoming'][$order]['url'] ?? null;

        $this->validatingIframeEndpoints($iframeUrl);

        $this->response = $this->responseFromTemplate('@paperly/iframe.twig', [
            'url' => $iframeUrl
        ], 'Paperly Actions');
    }
}
