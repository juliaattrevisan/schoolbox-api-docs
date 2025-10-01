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
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        private Mapper $differentiationProfilesMapper,
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
        $this->response = $this->responseFromTemplate(
            '@differentiationProfiles/index.twig',
            [
                'pageCurrent'    => $pageCurrent,
                'allBreadcrumbs' => new Subnav(
                    $this->getConfigLoader(),
                    $this->getUser()
                )->generateBreadcrumbs($this->routeGenerator->generate('differentiationProfiles.getIndex')),
                'returnUrl' => Redirect::encode($pageCurrent),
                'differentiationProfilesEnabled' =>
                    $this->getConfigMapper()
                         ->findOneByColumn(
                             'name',
                             ConfigMapper::NAME_DIFFERENTIATION_PROFILE_ENABLED
                         ),
                'differentiationProfilesConfig' => $this->differentiationProfilesMapper->findAll()
            ],
            'Differentiation Profiles'
        );
    }

    /**
     * Updating the differentiation profiles configs
     *
     * @throws Exception
     * @throws RouteNotFound
     * @throws Deleted
     * @api POST /adminv2/pastoral/differentiation-profiles
     */
    public function postConfig(): void
    {
        $post = $this->requestPost;
        $enabled = (null !== $post->get('enabled')) ? '1' : '0';
        $returnUrl = $post->get(Redirect::RETURN_URL);
        $differentiationProfilesEnabledConfig =
            $this->getConfigMapper()
                 ->findOneByColumn(
                     'name',
                     ConfigMapper::NAME_DIFFERENTIATION_PROFILE_ENABLED
                 );
        $this->getConfigMapper()->save($differentiationProfilesEnabledConfig->setValue($enabled));
        $this->response = $this->redirectAfterPost($this->getRouteIfAuthorized('differentiationProfiles.getIndex'));
        $this->redirectAfterPost($returnUrl ? Redirect::decode($returnUrl) : $this->getSession()->getHistory());
    }
}
