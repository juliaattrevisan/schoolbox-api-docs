<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform;

use Alaress\Web\Controller\Base;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\LTI\Registration\Repository;

/**
 * Controller for LTI platform stuff.
 */
class Controller extends Base
{
    /**
     * Repository of tool+platform configurations.
     */
    private Repository $repository;

    /**
     * Constructor
     *
     * @param Repository $repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Test endpoint for posting _something_ back to the window which opened
     * this iframe.
     *
     * @api GET /lti-platform/
     * @since 21.1.0
     */
    public function getIndex(): void
    {
        // Test posting a message back to the form in an iframe
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@ltiPlatform/index.twig',
                [],
            )
        );
    }

    /**
     * Generate a deep linking request, which is then sent to the LTI tool.
     *
     * @api GET /lti-platform/deep-linking-request/{toolId}
     * @since 21.1.0
     */
    public function getGenerateDeepLinkingRequest(ToolModel $tool): void
    {
        // Find the tool in the list of registrations
        $repository = $this->repository->find($tool->getDeploymentId());

        // Build a DL request to this tool
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@ltiPlatform/index.twig',
                [],
            )
        );
    }

    public function getOidcAuthenticationRequest(): void
    {
        //
    }

    public function getReceiveDeepLinkingResponse(): void
    {
        // Send a message to the top-level window, to update the:
        // * tool's identifier
        // * URL
        // * custom fields
        // * line item, if provided
    }

    public function postReceiveDeepLinkingResponse(): void
    {
        $this->getReceiveDeepLinkingResponse();
    }
}
