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
use External_UserInterface;
use House;
use HouseMapper;
use Schoolbox\Assessment\Grades;
use Schoolbox\External\SubjectMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
    /**
     * Dependency on external user database.
     *
     * Required for checking that the requesting user is the student's parent.
     *
     * @var External_UserInterface
     */
    protected $externalDb;

    /**
     * Dependency on user mapper.
     *
     * Required for checking that the requesting user is the student's parent.
     *
     * @var UserMapper
     */
    protected $userMapper;

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
            ->addRoutePermissionForBasename($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermissionForBasename(
                $authorizer,
                $ruleRegexp,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsUserOrParentOfUser($this->externalDb, $this->userMapper, 'id')
                )
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Search for the user
        // TODO move the mappers out of here, inject their dependency
        $userMapper = $this->userMapper;
        $this->addRoutePreloadForBasename(
            $matcher,
            '(?:index|subject|currentReport)',
            function ($params) use ($userMapper) {
                $student = $userMapper->findWithSubjects($params['id']);
                $student->setSubjectCollection(
                    SubjectMapper::getInstance()->findAllForStudent($student)
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
                    $student->setSchoolHouse(HouseMapper::getInstance()->findById($houseId));
                } catch (\Exception $e) {
                    // noop - the controller will die at this point anyway
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
        $this->addTemplatePathForBasename($templateLoader, __DIR__ . '/templates');
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
            = $injector->lazyNew(Folder\Mapper::class);
        $injector->params['Custom\\Meriden\\Pastoral\\StudentInvolvement\\Factory']['userMapper']
            = $injector->lazyNew('Schoolbox\\UserMapper');
        $injector->params['Custom\\Meriden\\Pastoral\\StudentInvolvement\\Factory']['gradesService']
            = Grades::getInstance();

        $injector->params['Custom\\Meriden\\Pastoral\\StudentInvolvement\\Gateway']['database']
            = $injector->lazyNew('MSSQL');

        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew('Custom\\Meriden\\Pastoral\\Controller')
        );
    }

    /**
     * @param  External_UserInterface $externalDb
     *
     * @return $this
     */
    public function setExternalDb($externalDb)
    {
        $this->externalDb = $externalDb;
        return $this;
    }

    /**
     * @param  UserMapper $userMapper
     *
     * @return $this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }
}
