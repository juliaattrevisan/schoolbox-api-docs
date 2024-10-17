<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth\Client;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;

/**
 * Controller actions for the frontend for viewing and modifying oAuth clients.
 */
class Controller extends Base
{
    /**
     * Controller action for viewing and modifying oAuth clients.
     *
     * Actual modification and deletion is done via the API:
     * @see Api\Controller
     *
     * @api GET /adminv2/oauth/client
     * @since 24.0
     *
     * @return void
     */
    public function getIndex(): void
    {
        $pageCurrent = $this->routeGenerator->generate('adminOauthClient.getIndex');
        $data = [
            'pageCurrent'    => $pageCurrent,
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($pageCurrent),
        ];

        $this->response = $this->responseFromTemplate(
            '@adminOauthClient/index.twig',
            $data,
            'oAuth Client Configuration',
        );
    }
}
