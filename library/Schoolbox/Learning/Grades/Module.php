<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Grades;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Psr\Container\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    public function __construct(ContainerInterface $injector, string $basename = '')
    {
        if ('' === $basename) {
            $basename = 'learningGrades';
        }
        parent::__construct($injector, $basename);
    }


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If cannot find a specified mapper class
     * @throws NotFound If the specified user is not found
     */
    public function registerRoutes()
    {
        $basicPermissions = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsConfigOn(ConfigMapper::NAME_GRADES_ENABLED, $this->getConfig())
        );
        $permissions      = new Rule\LazyCallback(function () use ($basicPermissions) {
            return new Rule\All(
                $basicPermissions,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRolePermission(Role::PERMISSION_VIEW_STUDENT_GRADES, Role::STAFF),
                    new Rule\IsUser('userId'),
                    new Rule\All(
                        new Rule\IsTrue(!$this->getConfig()->get('restrict_parent_grades')),
                        new Rule\IsParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
                    )
                )
            );
        });

        $subjectPermissions = new Rule\LazyCallback(function () use ($permissions) {
            return new Rule\All(
                $permissions,
                new Rule\LazyCallback(
                    function () {
                        return new Rule\Callback(function (array $params): bool {
                            if (
                                isset($params['subject'])
                                && $params['subject'] instanceof Folder\Model
                                && $params['subject']->getYearLevels() !== null
                                && $params['subject']->isGradePageEnabled()
                            ) {
                                return true;
                            }
                            // when year level collection is null, it may have not been hydrated.
                            if (isset($params['subjectId'])) {
                                try {
                                    $folder = $this->getFolderMapper()->findById($params['subjectId']);
                                    if ($folder->isGradePageEnabled()) {
                                        return true;
                                    }
                                } catch (Mapper\Exception $e) {
                                    // fall back to unauthorized
                                }
                            }
                            throw new Unauthorized('Grade page is not available for this folder.');
                        });
                    }
                )
            );
        });

        $loadViewee = function ($params) {
            try {
                return ['viewee' => $this->getUserMapper()->findById($params['userId'])];
            } catch (Mapper\Exception $e) {
                throw new NotFound("No user found for id: {$params['userId']}");
            }
        };

        $loadTerm = function ($params) {
            if (null === $params['termCode']) {
                $user = $this->getUserMapper()->findById($params['userId']);

                if ($user->getYear()) {
                    $term = $this->getTermMapper()->findForDateAndYearLevel(\Alaress_Date_Format::now(), $user->getYear());
                } else {
                    $term = $this->getTermMapper()->findForToday();
                }

                return ['term' => $term];
            }
            try {
                return ['term' => $this->getTermMapper()->findOneByColumn('code', $params['termCode'])];
            } catch (Mapper\Exception $e) {
                throw new NotFound("No term found for code: {$params['termCode']}");
            }
        };

        $loadFolder = function ($params) {
            try {
                $folder = $this->getFolderMapper()->findById($params['subjectId']);
                if (!$folder->isGradePageEnabled()) {
                    throw new NotFound("Grade is not available for id: {$params['subjectId']}");
                }
                return ['subject' => $folder];
            } catch (Mapper\Exception $e) {
                throw new NotFound("No folder found for id: {$params['subjectId']}");
            }
        };

        $loadVieweeTerm = function ($params) use ($loadViewee, $loadTerm) {
            return array_merge($loadViewee($params), $loadTerm($params));
        };

        $loadVieweeTermFolder = function ($params) use ($loadViewee, $loadTerm, $loadFolder) {
            return array_merge($loadViewee($params), $loadTerm($params), $loadFolder($params));
        };

        $this->register('GET', '/grades-year/{userId}/year/{year}', 'getGradesYear', $permissions, $loadViewee);

        // Overview and single class pages
        $this->register('GET', '', 'getGradesForCurrentUser', $basicPermissions);
        $this->register('GET', '/{userId:\d+}{/termCode}', 'getGrades', $permissions, $loadVieweeTerm);
        $this->register('GET', '/{userId:\d+}/{termCode}/{subjectId:\d+}', 'getGradesForSubject', $subjectPermissions, $loadVieweeTermFolder);

        // Filtered routes (todo merge GET + POST and distinguish via sync vs. ajax)
        $this->register('POST', '/{userId:\d+}/{termCode}/{subjectId:\d+}', 'postReport', $subjectPermissions, $loadVieweeTermFolder);
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(Folder\Mapper::class),
            $this->getMapperFactory()->get(Term\Mapper::class),
            $this->getMapperFactory()->get(RubricDataSource::class),
            $this->getMapperFactory()->get(FilterSelection\Mapper::class),
            $this->getMapperFactory()->get(Access::class),
            $this->injector->get('filters.assessmentIdFinder'),
            $this->injector->get('filters.factoryFactory'),
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|Folder\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }

    /**
     * @return Term\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(Term\Mapper::class);
    }

    /**
     * @return Mapper|Error\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }
}
