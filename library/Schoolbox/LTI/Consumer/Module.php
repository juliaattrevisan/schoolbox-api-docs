<?php

namespace Schoolbox\LTI\Consumer;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\LTI\Provider\Mapper as ProviderMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Alaress\Web\Router\PathMatcher;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'ltiConsumer';

    /** @var FolderMapper $folderMapper */
    private $folderMapper;

    /** @var ProviderMapper $providerMapper */
    private $providerMapper;

    /** @var \Component_InstanceMapper $componentInstanceMapper */
    private $componentInstanceMapper;

    /**
     * Module constructor.
     * @param ContainerInterface $injector
     * @param string $basename
     */
    public function __construct(ContainerInterface $injector, $basename = '')
    {
        parent::__construct($injector, $basename);

        $this->folderMapper = FolderMapper::getInstance();
        $this->providerMapper = ProviderMapper::getInstance();
        $this->componentInstanceMapper = \Component_InstanceMapper::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/launch/{providerId}/{componentId}/{folderId}' => 'getLaunch'
            ],
            'POST' => [
                '/component/{componentId}/provider/{providerId}' => 'postComponentProvider'
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Preload required data for the various routes

        $folderMapper = $this->folderMapper;
        $providerMapper = $this->providerMapper;
        $componentInstanceMapper = $this->componentInstanceMapper;

        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) use ($folderMapper, $providerMapper, $componentInstanceMapper) {

                $returnArray = [];

                // Load student record

                if (array_key_exists('providerId', $params)) {
                    $returnArray['provider'] = $providerMapper->findById($params['providerId']);
                }

                // Load comment

                if (array_key_exists('componentId', $params)) {

                    $page = new \SitePage();
                    $page->setDi($this->getInjector());

                    $factory       = new \Component_Homepage_Factory(new User(), $page);
                    $componentList = $componentInstanceMapper->findByInstanceId($params['componentId']);
                    $cList         = array_values($componentList);

                    $returnArray['component'] = $factory->buildComponent($cList[0]);
                }

                if (array_key_exists('folderId', $params)) {
                    $returnArray['folder'] = $folderMapper->findById($params['folderId']);
                }

                return $returnArray;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
