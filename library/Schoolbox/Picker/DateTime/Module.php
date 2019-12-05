<?php
/**
 * Module definition for datetime picker
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Picker\DateTime;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'pickerDatetime';

    /**
     * Module constructor.
     * @param ContainerInterface $injector
     * @param string $basename
     */
    public function __construct(ContainerInterface $injector, $basename = '')
    {
        parent::__construct($injector, $basename);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/type/{type}/element/{element}/id/{id}/value/{value}' => 'getPicker',
                '/date/{date}'                                         => 'getByDate',
                '/date/{date}/folder/{folderId}'                       => 'getDueByDate',
                '/heat/{year}/{month}/folder/{folderId}'               => 'getHeat'
            ],
            'POST' => [
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
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*',
            new Rule\All(new Rule\IsLoggedIn())
        );
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher,
            '(getByDate|getDueByDate)',
            function ($params) {
                $returnedParams = [];
                if (array_key_exists('date', $params)) {
                    $returnedParams['date'] = new \Alaress_Date_Format($params['date'], 'Y-m-d');
                }
                if (array_key_exists('folderId', $params)) {
                    $returnedParams['folder'] = $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']);
                }
                return $returnedParams;
            }
        );

        $this->addRoutePreload($matcher, 'getHeat', function ($params) {
            if (array_key_exists('folderId', $params)) {
                // tweak and pass through
                $params['folder'] = $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']);
                unset ($params['folderId']);
            }
            return $params;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'userMapper'       => $this->getMapperFactory()->getLazy(UserMapper::class),
                'termMapper'       => $this->getMapperFactory()->getLazy(TermMapper::class),
                'folderMapper'     => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'userAccessMapper' => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
                'timetableHandler' => $this->getInjector()->lazyGet('externalTimetable'),
            ])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
