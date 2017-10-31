<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Role;
use Twig_Error_Loader;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'rubric';


    /**
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addPostRoute($router, 'create', $basePath, "");
        $this->addPostRoute($router, 'download', $basePath, "/csv");
        $this->addPostRoute($router, 'import', $basePath, "/import/{storageTuple}");

        $this->addGetRoute($router, 'testGeneric', $basePath, "/test");
        $this->addGetRoute($router, 'testEmpty',   $basePath, "/test/empty");
        $this->addGetRoute($router, 'testBox',     $basePath, "/test/box/{boxId}");
        $this->addGetRoute($router, 'testCSV',     $basePath, "/test/csv/{boxId}");
        $this->addGetRoute($router, 'testFolder',  $basePath, "/test/folder/{folderId}");
    }


    /**
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            ));
    }


    /**
     * Initializes any preloads on routes for this module.
     *
     * Preloads adjust route params before they are passed to the controller.
     * Usually, they're used to load a single item into the route's params,
     * which is then passed directly as a parameter to the action.
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the item to params, if ID is in the route.
        $this->addRoutePreload(
            $matcher, 'import',
            function ($params) {
                return array(
                    'file' => (new CollectorFactory())->build([ $params['storageTuple'] ])->getFirst()
                );
            }
        );

    }


    /**
     * @param IChainLoader $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
        );

        // dependencies
        $injector->setters[Controller::class]['setFactory'] = $injector->lazyNew(Factory::class);
        $injector->setters[Controller::class]['setMapper']  = $this->getMapperFactory()->getLazy(RubricMapper::class);

    }


}
