<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LTI\Consumer;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
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

    /** @var FolderMapper $folderMapper */
    private $folderMapper;

    /** @var ProviderMapper $providerMapper */
    private $providerMapper;

    /** @var Component_InstanceMapper $componentInstanceMapper */
    private $componentInstanceMapper;

    /**
     * {@inheritdoc}
     *
     * @param ContainerInterface $injector
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        $this->folderMapper            = $this->getMapperFactory()->get(FolderMapper::class);
        $this->providerMapper          = $this->getMapperFactory()->get(ProviderMapper::class);
        $this->componentInstanceMapper = $this->getMapperFactory()->get(Component_InstanceMapper::class);
    }

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

        $folderMapper            = $this->folderMapper;
        $providerMapper          = $this->providerMapper;
        $componentInstanceMapper = $this->componentInstanceMapper;

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

                    $page = new SitePage();
                    $page->setDi($this->getInjector());

                    $factory   = new Component_Homepage_Factory(new User(), $page);
                    $component = $componentInstanceMapper->findByInstanceId($params['componentId']);

                    $returnArray['component'] = $factory->buildComponent($component);
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
     * {@inheritdoc}
     *
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
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
