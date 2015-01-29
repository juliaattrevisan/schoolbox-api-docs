<?php
/**
 * Module definition for Meriden's custom pastoral page.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule\HasRole;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\External\SubjectMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $router->addGet('customMeridenPastoral.index', $basePath.'/{year}/{id}');
        $router->addGet('customMeridenPastoral.subject', $basePath.'/{year}/{id}/{subjectCode}');
    }

    /**
     * Only superusers may access this page for now.
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $authorizer->add(
            '/^customMeridenPastoral\\.(?:index|subject)/',
            new IsSuperuser()
        );
        $authorizer->add(
            '/^customMeridenPastoral\\.(?:index|subject)/',
            new HasRole(Role::STAFF)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Search for the user
        // TODO move the mappers out of here, inject their dependency
        $matcher->add(
            '/^customMeridenPastoral\\.(?:index|subject)/',
            function($params) {
                $student = UserMapper::getInstance()->findById($params['id']);
                $student->setSubjectCollection(SubjectMapper::getInstance()->findAllForStudent($student));
                return array(
                    'student' => $student,
                );
            }
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/templates', 'customMeridenPastoral');
        $templateLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        // Inject the custom Meriden pastoral database connection too
        // a MSSQL connection should be a service?
        $injector->params['Custom\\Meriden\\Pastoral\\Controller']['database']
            = $injector->lazyNew('Custom\\Meriden\\Pastoral\\Query');
        $injector->params['Custom\\Meriden\\Pastoral\\Query']['database']
            = $injector->lazyNew('MSSQL');
        $injector->params['Custom\\Meriden\\Pastoral\\Query']['categoryMapper']
            = $injector->lazyNew('Schoolbox\\Resource\\CategoryMapper');
        $injector->set(
            'controller:customMeridenPastoral',
            $injector->lazyNew('Custom\\Meriden\\Pastoral\\Controller')
        );
    }
}
