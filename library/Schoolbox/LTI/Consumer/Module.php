<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LTI\Consumer;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Component_Homepage_Factory;
use Component_InstanceMapper;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use SitePage;

class Module extends BaseModule
{
    /** @var string $basename */
    protected $basename = 'ltiConsumer';

    /**
     * {@inheritdoc}
     */
    public function registerRoutes()
    {
        $this->registerRoutesArray([
            'GET'  => [
                '/component/{componentId}/provider/{providerId}/folder/{folderId}/launch' => 'getLaunch',
                '/component/{componentId}/callback'                                       => 'getComponentCallback',
            ],
            'POST' => [
                '/component/{componentId}/provider/{providerId}' => 'postComponentProvider',
                '/component/{componentId}/picker'                => 'postComponentPicker',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Preload required data for the various routes

        $folderMapper            = $this->getMapperFactory()->get(FolderMapper::class);
        $providerMapper          = $this->getMapperFactory()->get(ProviderMapper::class);
        $componentInstanceMapper = $this->getMapperFactory()->get(Component_InstanceMapper::class);

        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) use ($folderMapper, $providerMapper, $componentInstanceMapper) {
                $returnArray = [];

                // Preload LTI provider record

                if (array_key_exists('providerId', $params)) {
                    $returnArray['provider'] = $providerMapper->findByIdWithFallback($params['providerId']);
                }

                // Preload homepage component

                if (array_key_exists('componentId', $params)) {

                    /** @var SitePage $page */
                    $page = $this->injector->get('sitePage');

                    $factory        = new Component_Homepage_Factory(new User(), $page);
                    $componentModel = $componentInstanceMapper->findByInstanceId($params['componentId']);
                    $component      = $factory->buildComponent($componentModel);
                    $component->setup($folderMapper->findById($componentModel->getIdentifier()));

                    $returnArray['component'] = $component;
                }

                // Preload folder

                if (array_key_exists('folderId', $params)) {
                    $returnArray['folder'] = $folderMapper->findById($params['folderId']);
                }

                // Return preloads and parameters passed

                return array_merge($params, $returnArray);
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->injector->get('lti.logger'));
    }

    /**
     * {@inheritdoc}
     *
     * @param IChainLoader $templateLoader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
