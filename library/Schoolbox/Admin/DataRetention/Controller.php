<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\ConfigController;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\ConfigMapper;

class Controller extends ConfigController
{
    /**
     * show canvas settings page, and import logs
     */
    public function getSettings(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->routeGenerator->generate('dataRetention.postSave'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,],
        );

        $this->response = $this->responseFromTemplate(
            '@dataRetention/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('dataRetention.getSettings'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('dataRetention.getSettings')),

                'configContext' => $configContext,
            ],
            'Data Retention'
        );
    }


    /**
     * Process the form vars
     */
    public function postSave(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        /* Save it
         */
        $this->updateConfig($postData, $this->buildConfigPredicate());

        /* Go back to the place from whence you came
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('dataRetention.getSettings'));
    }

    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return Builder
     * @throws InvalidColumnException
     */
    private function buildConfigPredicate(): Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_TYPE, ConfigMapper::TYPE_DATA_RETENTION);
    }
}
