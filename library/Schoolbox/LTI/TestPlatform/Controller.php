<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\TestPlatform;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Index for test platform.
     *
     * @api GET /lti-test-platform
     * @since 21.1.0
     */
    public function getIndex(): void
    {
        $this->response->content->set($this->buildHtmlOutput('@ltiTestPlatform/index.twig', []));
    }
}
