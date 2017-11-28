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
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use House;
use HouseMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\External\SubjectMapper;
use Schoolbox\Learning\Assessment\DueWork\RubricContext;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    protected $basename = 'customMeridenPastoral';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'currentReport', $basePath, '/{id}');
        $this->addGetRoute($router, 'index', $basePath, '/{id}/{year}');
        $this->addGetRoute($router, 'subject', $basePath, '/{id}/{year}/{subjectCode}');
    }

    /**
     * These pages may be accessed by:
     *
     * * superusers
     * * staff
     * * the child themselves
     * * the child's parents
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $ruleRegexp = '(?:index|subject)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                $ruleRegexp,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'id')
                )
            );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Search for the user

        $this->addRoutePreload(
            $matcher,
            '(?:index|subject|currentReport)',
            function ($params) {
                $student = $this->getUserMapper()->findWithSubjects($params['id']);
                $student->setSubjectCollection(
                    $this->getSubjectMapper()->findAllForStudent($student)
                );
                try {
                    // School house is actually an integer.
                    // It should be a model, and will be for this purpose.
                    // Also make sure it's hydrated.
                    $schoolHouse = $student->getSchoolHouse();
                    if ($schoolHouse instanceof House) {
                        $houseId = $schoolHouse->getId();
                    } else {
                        $houseId = $schoolHouse;
                    }
                    $student->setSchoolHouse($this->getHouseMapper()->findById($houseId));
                } catch (\Exception $e) {
                    // noop - the controller will die at this point anyway
                }
                return [
                    'student' => $student,
                ];
            }
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        // Inject the custom Meriden pastoral database connection too
        // a MSSQL connection should be a service?
        $injector->params[Controller::class]['studentInvolvementFactory']
            = $injector->lazyNew(StudentInvolvement\Factory::class);

        $injector->params[StudentInvolvement\Factory::class]['gateway']
            = $injector->lazyNew(StudentInvolvement\Gateway::class);
        $injector->params[StudentInvolvement\Factory::class]['folderMapper']
            = $this->getMapperFactory()->getLazy(Folder\Mapper::class);
        $injector->params[StudentInvolvement\Factory::class]['userMapper']
            = $this->getMapperFactory()->getLazy(UserMapper::class);
        $injector->params[StudentInvolvement\Factory::class]['assessmentDataSource']
            = $injector->lazyGet('assessmentDataSource');
        $injector->params[StudentInvolvement\Factory::class]['rubricMapper']
            = $this->getMapperFactory()->getLazy(RubricMapper::class);
        $injector->params[StudentInvolvement\Factory::class]['rubricContext']
            = $injector->lazyNew(
                RubricContext::class,
                [$this->getMapperFactory()->getLazy(RubricMapper::class)]
            );
        $injector->params[StudentInvolvement\Factory::class]['currentUser']
            = $injector->lazyGet('user');
        $injector->params[StudentInvolvement\Factory::class]['access'] = Access::getInstance();
        $injector->params[StudentInvolvement\Factory::class]['userAccessMapper']
            = $this->getMapperFactory()->getLazy(UserAccessMapper::class);

        $injector->params[StudentInvolvement\Gateway::class]['database']
            = $injector->lazyGet('databaseExternal');

        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class)
        );
    }


    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return HouseMapper
     */
    protected function getHouseMapper()
    {
        return $this->getMapperFactory()->get(HouseMapper::class);
    }

    /**
     * @return SubjectMapper
     */
    protected function getSubjectMapper()
    {
        return $this->getMapperFactory()->get(SubjectMapper::class);
    }
}
