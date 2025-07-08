<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\Mapper\Exception;

class Controller extends ConfigController
{
    /**
     * @param ConfigMapper $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param Rebuilder $rebuilder
     * @param CacheInterface $oidcCache
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }


    /**
     * Basically the same appearance as the usual system settings pages, but with special behaviours
     * because microsoft azure does its own thing.
     */
    public function getIndex(): void
    {
        $textConfigContext = $this->buildConfigContext(
            $this->buildTextConfigPredicate(),
            $this->routeGenerator->generate('adminOpenAi.postOpenAiOptions'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false],
        );

        $imageConfigContext = $this->buildConfigContext(
            $this->buildImageConfigPredicate(),
            $this->routeGenerator->generate('adminOpenAi.postOpenAiOptions'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false],
        );

        $this->response = $this->responseFromTemplate('@adminOpenAi/index.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminOpenAi.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex')),
            'textConfigContext' => $textConfigContext,
            'imageConfigContext' => $imageConfigContext,
            'models' => [
                'openai' => Helper::ENDPOINT_TYPE_OPENAI,
                'azure' => Helper::ENDPOINT_TYPE_AZURE,
            ]
        ], 'AI Services');
    }


    /**
     * Save the configuration for AI Services
     *
     * @return void
     * @throws RouteNotFound
     * @throws Exception
     */
    public function postOpenAIOptions(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        /* Save it
         */
        $this->updateConfig($postData, $this->buildTextConfigPredicate());
        $this->updateConfig($postData, $this->buildImageConfigPredicate());

        /* Go back to the place from whence you came
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminOpenAi.getIndex'));
    }


    /**
     *
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return Builder
     * @throws InvalidColumnException
     */
    private function buildTextConfigPredicate(): Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_OPENAI_TEXT);
    }


    /**
     *
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return Builder
     * @throws InvalidColumnException
     */
    private function buildImageConfigPredicate(): Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_OPENAI_IMAGE);
    }
}
