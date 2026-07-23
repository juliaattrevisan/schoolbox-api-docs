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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception;

class Controller extends ConfigController
{
    /**
     * Basically the same appearance as the usual system settings pages
     */
    public function getIndex(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->routeGenerator->generate('adminZenda.postOptions'),
        );

        $this->response = $this->responseFromTemplate('@adminZenda/index.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminZenda.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminZenda.getIndex')),
            'configContext' => $configContext,
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

        /* This is a generally not-recommended way to do this, but the evolution of Zenda is yet to be determined.
         * This will suffice for the time being until we know more about the configuration requirements for Cognita
         */
        $configs = $this->getConfigMapper()->findByColumn('module', ConfigMapper::MODULE_ZENDA);

        foreach ($configs as $config) {
            if (isset($postData[$config->getName()])) {
                /* If this config var was in the post payload, update it
                 */
                $config->setValue($postData[$config->getName()]);

                $this->getConfigMapper()->save($config);
            }
        }

        /* Go back to the place from whence you came
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminZenda.getIndex'));
    }


    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return \Alaress\Database\Predicate\Builder
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function buildConfigPredicate(): \Alaress\Database\Predicate\Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_ZENDA);
    }
}
