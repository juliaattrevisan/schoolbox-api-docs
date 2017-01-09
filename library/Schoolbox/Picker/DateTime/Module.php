<?php
/**
 * Module definition for datetime picker
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Picker\DateTime;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;

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
                '/type/{type}/display/{display}/database/{database}/id/{id}/value/{value}' => 'getPicker',
                '/date/{date}' => 'getByDate',
                '/campus/{campusId}/term/{termId}/cycle/{cycle}/day/{day}' => 'getByTimetableDay',
                '/date/{date}/class/{classCode}' => 'getByClassCode',
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
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class),
                'termMapper' => $this->getMapperFactory()->getLazy(TermMapper::class)
            ])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
