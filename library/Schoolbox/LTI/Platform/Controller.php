<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform;

use Alaress\Web\Controller\Base;

/**
 * Controller for LTI platform stuff.
 */
class Controller extends Base
{
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
