<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Zenda;

use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Mapper\Exception;

class Controller extends ConfigController
{
    /**
     * Basically the same appearance as the usual system settings pages
     */
    public function getIndex(): void
    {
        $this->response = $this->responseFromTemplate('@adminZenda/index.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminZenda.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminZenda.getIndex')),
        ], 'Zenda');
    }


    /**
     * Save the configuration
     *
     * @return void
     * @throws RouteNotFound
     */
    public function postOptions(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        /* Go back to the place from whence you came
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminZenda.getIndex'));
    }
}
