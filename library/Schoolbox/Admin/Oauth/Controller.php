<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth;

use Alaress\Database\Predicate;
use Alaress\Web\Controller\ConfigController;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;

class Controller extends ConfigController
{
    /**
     * Renders the module admin page
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @api   GET /adminv2/oauth
     * @since 190
     */
    public function getAdmin(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->routeGenerator->generate('adminOauth.postAdmin'),
        );

        $data = [
            'pageCurrent' => $this->routeGenerator->generate('adminOauth.postAdmin'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminOauth.postAdmin')),
            'configContext' => $configContext,
        ];

        $this->response = $this->responseFromTemplate(
            '@adminOauth/admin.twig',
            $data,
            "oAuth Provider Configuration"
        );
    }


    /**
     * Updates admin items available on the module admin page
     *
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws Predicate\InvalidColumnException
     * @api   POST /adminv2/oauth
     * @since 190
     */
    public function postAdmin(): void
    {
        $this->updateConfig($this->requestPost->getArrayCopy(), $this->buildConfigPredicate());
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminOauth.getAdmin'));
    }


    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return \Alaress\Database\Predicate\Builder
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function buildConfigPredicate(): Predicate\Builder
    {
        return $this->getConfigMapper()
                    ->getPredicateBuilder()
                    ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_OAUTH);
    }
}
