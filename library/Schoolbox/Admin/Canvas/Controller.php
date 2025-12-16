<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Canvas;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\ConfigMapper;

class Controller extends ConfigController
{
    /**
     * Controller constructor.
     *
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        private \External\Canvas\Log\Mapper $importLogMapper,

    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }


    /**
     * show canvas settings page, and import logs
     */
    public function getSettings(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->routeGenerator->generate('canvasadmin.postSave'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,],
        );

        $this->response = $this->responseFromTemplate(
            '@canvasadmin/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('canvasadmin.getSettings'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('canvasadmin.getSettings')),

                'configContext' => $configContext,

                'importLogs' => $this->importLogMapper->findLogsWithDuration(),
            ],
            'School Data Import'
        );    }


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
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('canvasadmin.getSettings'));
    }

    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return Builder
     * @throws InvalidColumnException
     */
    private function buildConfigPredicate(): Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_TYPE, 24);
    }

}
