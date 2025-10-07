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
        private FieldStrategyFactory $fieldStrategyFactory,
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
        $textProvider = $this->getConfigLoader()->get(ConfigMapper::NAME_OPENAI_TEXT_ENDPOINT);
        // 26.0 grab the config for text and add in any new or missing configs
        $aiConfig = new Config();

        $textModules = [];
        foreach (Config::ALL_MODULES as $module) {
            $textModules[$module] = $aiConfig->decode($this->getConfigLoader()->get(Config::MODULE_CONFIG[$module]), $textProvider);
        }

        $textModuleContext = $aiConfig->buildTwigConfig(
            $textModules,
            $this->fieldStrategyFactory,
            $this->getConfigLoader()->get('site_version'),
            $this->routeGenerator->generate('adminOpenAi.postTextOptions'),
            [
                ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,
                ModuleConfig::CONTEXT_ISEDITABLE => false,
                'formId' => 'textSettings'
            ]
        );
        $textConfigContext = $this->buildConfigContext(
            $this->buildTextConfigPredicate(),
            $this->routeGenerator->generate('adminOpenAi.postTextOptions'),
            [
                ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,
                ModuleConfig::CONTEXT_ISEDITABLE => false
            ],
        );

        $imageConfigContext = $this->buildConfigContext(
            $this->buildImageConfigPredicate(),
            $this->routeGenerator->generate('adminOpenAi.postImageOptions'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false],
        );

        /* Make islands independent of each other for validation reasons
         */
        $textConfigFormId = 'textSettings';
        $imageConfigContext['formId'] = 'imageSettings';

        $this->response = $this->responseFromTemplate('@adminOpenAi/index.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminOpenAi.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminOpenAi.getIndex')),
            'textModuleContext' => $textModuleContext,
            'textConfigContext' => $textConfigContext,
            'textConfigFormId' => $textConfigFormId,
            'textConfig' => $textModules,
            'imageConfigContext' => $imageConfigContext,
            'endpointTypeOff' => Helper::ENDPOINT_TYPE_OFF,
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
    public function postTextOptions(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        /* Save it
         */
        $this->updateConfig($postData, $this->buildTextConfigPredicate());

        /* Go back to the place from whence you came
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminOpenAi.getIndex'));
    }


    /**
     * Save the configuration for AI Services
     *
     * @return void
     * @throws RouteNotFound
     * @throws Exception
     */
    public function postImageOptions(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        /* Save it
         */
        $this->updateConfig($postData, $this->buildImageConfigPredicate());

        /* Go back to the place from whence you came
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminOpenAi.getIndex'));
    }

    /**
     * Get the blank configuration structure for a given provider
     *
     * @param string $provider
     * @return void
     * @api GET /adminv2/ai-services/blank/OpenAI
     */
    public function getBlankConfig(string $provider): void
    {
        $aiConfig = new Config();
        $blank = Config::generateInitConfig($provider);
        $textModules = [];
        foreach (Config::ALL_MODULES as $module) {
            $textModules[$module] = $blank;
        }

        $textModuleContext = $aiConfig->buildTwigConfig(
            $textModules,
            $this->fieldStrategyFactory,
            $this->getConfigLoader()->get('site_version'),
            $this->routeGenerator->generate('adminOpenAi.postTextOptions'),
            [
                ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,
                ModuleConfig::CONTEXT_ISEDITABLE => false,
                'formId' => 'textSettings'
            ]
        );

        $this->response = $this->responseFromTemplateUnthemed('@adminOpenAi/config.twig', ['textModuleContext' => $textModuleContext]);
    }


    /**
     *
     * Returns a predicate builder for finding main system config for the AI text module
     *
     * @return Builder
     * @throws InvalidColumnException
     */
    private function buildTextConfigPredicate(): Builder
    {
        // as of 26.0 text config is only endpoint and the system prompt
        $pb = $this->getConfigMapper()->getPredicateBuilder();
        return $this->getConfigMapper()->getPredicateBuilder()
            ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_OPENAI_TEXT)
            ->any(
                $pb->equals(ConfigMapper::COL_NAME, ConfigMapper::NAME_OPENAI_TEXT_ENDPOINT)
                    ->equals(ConfigMapper::COL_NAME, ConfigMapper::NAME_OPENAI_TEXT_SYSTEM_PROMPT)
            );
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
