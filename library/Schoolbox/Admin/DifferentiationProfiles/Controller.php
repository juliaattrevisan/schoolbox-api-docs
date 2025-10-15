<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DifferentiationProfiles;

use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\DifferentiationProfiles\Translator\ModelToArray;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class Controller extends ConfigController
{
    /**
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param Rebuilder            $rebuilder
     * @param CacheInterface       $oidcCache
     * @param Mapper               $differentiationProfilesMapper
     * @param ModelToArray         $modelToArray
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        private Mapper $differentiationProfilesMapper,
        private ModelToArray $modelToArray,
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }

    /**
     * Rendering the differentiation profiles admin config page
     *
     * @return void
     * @throws Deleted
     * @throws Exception
     * @throws RouteNotFound
     * @throws \Twig\Error\Error
     * @api GET /adminv2/pastoral/differentiation-profiles
     */
    public function getIndex(): void
    {
        $pageCurrent = $this->routeGenerator->generate('differentiationProfiles.getIndex');

        /* Get all the configs and sort it by sequence, so it will always be in the sequence order
         */
        $differentiationProfilesConfigs = $this->differentiationProfilesMapper->findAll()->sortBy('sequence', 'ASC');

        $differentiationProfilesConfigsArray = $differentiationProfilesConfigs->reduce(function ($carry, Model $item) {
            $carry[$item->getModule()]['fields'][$item->getName()] = call_user_func($this->modelToArray, $item);
            $carry[$item->getModule()]['isSortable'] = true;
            return $carry;
        }, []);

        $differentiationProfilesEnabled =
            $this->getConfigMapper()
                 ->findOneByColumn(
                     'name',
                     ConfigMapper::NAME_DIFFERENTIATION_PROFILE_ENABLED
                 );

        $this->response = $this->responseFromTemplate(
            '@differentiationProfiles/index.twig',
            [
                'pageCurrent'    => $pageCurrent,
                'allBreadcrumbs' => new Subnav(
                    $this->getConfigLoader(),
                    $this->getUser()
                )->generateBreadcrumbs($this->routeGenerator->generate('differentiationProfiles.getIndex')),
                'returnUrl' => Redirect::encode($pageCurrent),
                'differentiationProfilesEnabled' => $differentiationProfilesEnabled,
                'data' => [
                    'differentiationProfiles' => [
                        'enabled' => (bool)$differentiationProfilesEnabled->getValue(),
                        'modules' => $differentiationProfilesConfigsArray,
                        'headers' => Model::CONFIGS_HEADER
                    ]
                ],
                '_routes' => [
                    'update' => $this->getRouteIfAuthorized('differentiationProfiles.postConfig')
                ],
            ],
            'Differentiation Profiles'
        );
    }

    /**
     * Updating the differentiation profiles configs
     *
     * @throws Exception
     * @throws Deleted
     * @api POST /adminv2/pastoral/differentiation-profiles
     */
    public function postConfig(): void
    {
        $post = $this->requestPost;
        $enabled = (null !== $post->get('enabled')) ? '1' : '0';
        $modules = $post->get('modules') ?? [];
        $returnUrl = $post->get(Redirect::RETURN_URL);
        try {
            $this->differentiationProfilesMapper->getDatabase()->beginTransaction();
            $differentiationProfilesEnabledConfig =
                $this->getConfigMapper()
                     ->findOneByColumn(
                         'name',
                         ConfigMapper::NAME_DIFFERENTIATION_PROFILE_ENABLED
                     );
            $this->getConfigMapper()->save($differentiationProfilesEnabledConfig->setValue($enabled));

            if (!empty($modules)) {
                foreach ($modules as $moduleName => $module) {
                    $index = 0;
                    foreach ($module as $name => $fields) {
                        $moduleConfig = $this->differentiationProfilesMapper
                            ->createModel()
                            ->setId($fields['id'])
                            ->setName($name)
                            ->setTitle($fields['title'])
                            ->setTerminology(trim($fields['terminology']) ?: null)
                            ->setDescription(trim($fields['description']) ?: null)
                            ->setIsEnabled((bool)($fields['is_enabled'] ?? false))
                            ->setModule($moduleName)
                            ->setSequence($index);
                        $this->differentiationProfilesMapper->save($moduleConfig);
                        $index++;
                    }
                }
            }
            $this->differentiationProfilesMapper->getDatabase()->commit();
        } catch (Exception $e) {
            $this->differentiationProfilesMapper->getDatabase()->rollBack();
            //TODO: decide what response to return here, so throw it back out for now
            throw $e;
        }
        $this->response = $this->redirectAfterPost($returnUrl ? Redirect::decode($returnUrl) : $this->getSession()->getHistory());
    }
}
