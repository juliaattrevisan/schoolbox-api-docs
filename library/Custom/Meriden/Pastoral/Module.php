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
use Schoolbox\Assessment\Grades;
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
        $router->addGet('customMeridenPastoral.currentReport', $basePath.'/{id}');
        $router->addGet('customMeridenPastoral.index', $basePath.'/{id}/{year}');
        $router->addGet('customMeridenPastoral.subject', $basePath.'/{id}/{year}/{subjectCode}');
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
            '/^customMeridenPastoral\\.(?:index|subject|currentReport)/',
            function($params) {
                $student = UserMapper::getInstance()->findWithSubjects($params['id']);
                $student->setSubjectCollection(
                    SubjectMapper::getInstance()->findAllForStudent($student)
                );
                try {
                    // School house is actually an integer.
                    // It should be a model, and will be for this purpose.
                    // Also make sure it's hydrated.
                    $schoolHouse = $student->getSchoolHouse();
                    if ($schoolHouse instanceof \House) {
                        $houseId = $schoolHouse->getId();
                    } else {
                        $houseId = $schoolHouse;
                    }
                    $student->setSchoolHouse(\HouseMapper::getInstance()->findById($houseId));
                } catch (\Exception $e) {
                    // noop
                }
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
        $injector->params['Custom\\Meriden\\Pastoral\\Controller']['studentInvolvementFactory']
            = $injector->lazyNew('Custom\\Meriden\\Pastoral\\StudentInvolvement\\Factory');

        $injector->params['Custom\\Meriden\\Pastoral\\StudentInvolvement\\Factory']['gateway']
            = $injector->lazyNew('Custom\\Meriden\\Pastoral\\StudentInvolvement\\Gateway');
        $injector->params['Custom\\Meriden\\Pastoral\\StudentInvolvement\\Factory']['categoryMapper']
            = $injector->lazyNew('Schoolbox\\Resource\\CategoryMapper');
        $injector->params['Custom\\Meriden\\Pastoral\\StudentInvolvement\\Factory']['userMapper']
            = $injector->lazyNew('Schoolbox\\UserMapper');
        $injector->params['Custom\\Meriden\\Pastoral\\StudentInvolvement\\Factory']['gradesService']
            = Grades::getInstance();

        $injector->params['Custom\\Meriden\\Pastoral\\StudentInvolvement\\Gateway']['database']
            = $injector->lazyNew('MSSQL');

        $injector->set(
            'controller:customMeridenPastoral',
            $injector->lazyNew('Custom\\Meriden\\Pastoral\\Controller')
        );
    }
}
