<?php

/**
 * Module definition for Meriden's custom pastoral page.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Ms\Pastoral;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Router;
use House;
use HouseMapper;
use Psr\Container\ContainerInterface;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\External\SubjectMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    protected $basename = 'customMeridenPastoral';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'currentReport', $basePath, '/{id}')
             ->addTokens(['id' => '\\d+']);
        $this->addGetRoute($router, 'index', $basePath, '/{id}/{year}')
             ->addTokens(['id' => '\\d+', 'year' => '\\d+']);
        $this->addGetRoute($router, 'subject', $basePath, '/{id}/{year}/{subjectCode}')
             ->addTokens(['id' => '\\d+', 'year' => '\\d+']);
        $this->addGetRoute($router, 'printableReport', $basePath, '/{id}/print/{year}')
             ->addTokens(['id' => '\\d+', 'year' => '\\d+']);
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
        $ruleRegexp = '(?:index|subject|printableReport)';
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
            '(?:index|subject|currentReport|printableReport)',
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
     * @inheritdoc
     */
    public function buildController(ContainerInterface $container): Base
    {
        return new Controller(
            new StudentInvolvement\Report\Factory(
                new StudentInvolvement\Gateway($container->get('databaseExternal')),
                $this->getMapperFactory()->get(UserMapper::class),
                $this->getMapperFactory()->get(Folder\Mapper::class),
                $container->get('assessmentDataSource'),
                $this->getMapperFactory()->get(RubricDataSource::class),
                $container->get('user'),
                $this->getMapperFactory()->get(UserAccessMapper::class)
            )
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
