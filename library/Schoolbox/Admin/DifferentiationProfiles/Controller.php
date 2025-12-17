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
use Crell\ApiProblem\ApiProblem;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\DifferentiationProfiles\Translator\ModelToArray;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Symfony\Component\HttpFoundation\Response;

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
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \Twig\Error\Error
     * @api GET /adminv2/differentiation/differentiation-profiles
     */
    public function getIndex(): void
    {
        $pageCurrent = $this->routeGenerator->generate('adminDifferentiationProfiles.getIndex');

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
            '@adminDifferentiationProfiles/index.twig',
            [
                'pageCurrent'    => $pageCurrent,
                'allBreadcrumbs' => new Subnav(
                    $this->getConfigLoader(),
                    $this->getUser()
                )->generateBreadcrumbs($this->routeGenerator->generate('adminDifferentiationProfiles.getIndex')),
                'returnUrl' => Redirect::encode($pageCurrent),
                'data' => [
                    'differentiationProfiles' => [
                        'featureToggle' => [
                            'enabled' => (bool)$differentiationProfilesEnabled->getValue(),
                            'required' => $differentiationProfilesEnabled->getRequired() ?? false,
                            'title' => $differentiationProfilesEnabled->getTitle() ?? '',
                            'description' => $differentiationProfilesEnabled->getDescription() ?? '',
                        ],
                        'modules' => $differentiationProfilesConfigsArray,
                        'headers' => Model::CONFIGS_HEADER
                    ]
                ],
                '_routes' => [
                    'update' => $this->getRouteIfAuthorized('adminDifferentiationProfiles.postConfig')
                ],
            ],
            'Differentiation Profiles'
        );
    }

    /**
     * Updating the differentiation profiles configs
     *
     * @throws \Exception
     * @throws MapperException
     * @api POST /adminv2/differentiation/differentiation-profiles
     */
    public function postConfig(): void
    {
        $post = $this->requestPost;
        $enabled = $post->getBool('enabled', false) ? '1' : '0';
        $modules = $post->get('modules') ?? [];
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
                    foreach ($module['fields'] as $name => $fields) {
                        $moduleConfig = $this->differentiationProfilesMapper
                            ->createModel()
                            ->setId($fields['id'])
                            ->setName($name)
                            ->setTitle($fields['title'])
                            ->setTerminology(!empty($fields['terminology']) ? trim($fields['terminology']) : null)
                            ->setDescription(!empty($fields['description']) ? trim($fields['description']) : null)
                            ->setIsEnabled((bool)($fields['is_enabled'] ?? false))
                            ->setModule($moduleName)
                            ->setSequence($fields['sequence']);
                        $this->differentiationProfilesMapper->save($moduleConfig);
                    }
                }
            }
            $this->differentiationProfilesMapper->getDatabase()->commit();
            $this->response = $this->responseAsJson(['success' => true]);
        } catch (MapperException $e) {
            $this->differentiationProfilesMapper->getDatabase()->rollBack();
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($status);
            $this->response = $this->responseAsJson($problem->asArray())->withStatus($status);
        } catch (\Exception $e) {
            $this->differentiationProfilesMapper->getDatabase()->rollBack();
            $status = Response::HTTP_BAD_REQUEST;
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($status);
            $this->response = $this->responseAsJson($problem->asArray())->withStatus($status);
        }
    }
}
