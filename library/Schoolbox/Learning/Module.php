<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Aura\Router\Map;
use InvalidArgumentException;
use Twig\Error\LoaderError;
use UnexpectedValueException;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Timetable_ColourMapper;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\Resource\Folder;

use function array_key_exists;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learning';

    /**
     * @inheritdoc
     */
    public function initRoutes(string $basePath)
    {
        /** @var string[][] $routes */
        $routes = [
            'GET'  => [
                '/pending'            => 'getPendingAssessments',        // (no preload)
                '/pending/{userId}'   => 'getPendingAssessmentsForUser', // viewee
                '/classes{/termCode}' => 'getClasses',                   // (no preload)
            ],
            'POST' => [
                '/pending/{userId}' => 'postPendingAssessmentsForUser', // viewee
            ],
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($alias, $basePath, $path);
        }
    }

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $preloadFolder = function ($params) {
            return $this->preloadFolder($params);
        };

        $this->register('GET', '/class/lists/{folderId}', 'getStudentListByClassId', $this->canAccessClassLists(), $preloadFolder);

        $this->register('GET', '/class/{folderId}', 'getClassListPage', $this->canAccessClassLists(), $preloadFolder);
        $this->register('GET', '/assessment-audit/{folderId}', 'getAssessmentAudit', new Rule\IsSuperuser(), $preloadFolder);
        $this->register(
            'GET',
            '/assessment-audit/{folderId}/assessment.csv',
            'getAssessmentAuditCsv',
            new Rule\IsSuperuser(),
            $preloadFolder
        );
        $this->register(
            'GET',
            '/assessment-audit/{folderId}/rubric.csv',
            'getAssessmentRubricAuditCsv',
            new Rule\IsSuperuser(),
            $preloadFolder
        );
    }

    /**
     * @return Rule\LazyCallback
     */
    private function canAccessClassLists()
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            );
        });
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '(?:getPendingAssessments|getClasses)', new Rule\IsLoggedIn());

        $this->addRoutePermission(
            $authorizer,
            '(?:getStudentListByClassId|getClassListPage)',
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            )
        );

        // Either a SU or staff viewing their own assessments
        $this->addRoutePermission(
            $authorizer,
            '(?:getPendingAssessmentsForUser|postPendingAssessmentsForUser)',
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\All(
                    new Rule\IsLoggedIn(),
                    new Rule\IsUser('userId'),
                    new Rule\HasRole(Role::STAFF)
                )
            )
        );
    }

    /**
     * @inheritdoc
     * @throws ApplicationException\Exception If Invalid user id specified
     * @throws InvalidArgumentException If mapper definition not found
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:getPendingAssessmentsForUser|postPendingAssessmentsForUser)',
            function () {
                return [
                    'filterFactoryFactory' => $this->injector->get('filters.factoryFactory'),
                ];
            }
        );

        $this->addRoutePreload(
            $matcher,
            '(?:getPendingAssessmentsForUser|postPendingAssessmentsForUser|getClasses)',
            function ($params) {
                if (array_key_exists('userId', $params)) {
                    try {
                        $args['viewee'] = $this->getUserMapper()->findById($params['userId']);
                    } catch (MapperException $e) {
                        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
                    }
                }

                try {
                    if ($params['termCode'] ?? null) {
                        $args['term'] = $this->getTermMapper()->findOneByColumn('code', $params['termCode']);
                    } else {
                        $args['term'] = $this->getTermMapper()->findForToday();
                    }
                } catch (MapperException $e) {
                    /* If nonexistent term is specified, 404
                     */
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                }

                if (array_key_exists('subjectId', $params)) {
                    try {
                        $args['subject'] = $this->getFolderMapper()->findById($params['subjectId']);
                    } catch (MapperException $e) {
                        /* If nonexistent subject is specified, 404
                         */
                        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                    }
                }

                return $args;
            }
        );
    }


    /**
     * @inheritdoc
     * @throws LoaderError If template directory not found
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
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(TermMapper::class),
            $this->getMapperFactory()->get(Timetable_ColourMapper::class),
            $this->getMapperFactory()->get(FilterSelection\Mapper::class),
            $this->injector->get('filters.assessmentIdFinder'),
            new Folder\ClassListFactory($this->injector->get('databaseInternal')),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(Folder\Log\Mapper::class),
            $this->injector->get('externalClass'),
            $this->injector->get('l10n'),
            $this->injector->get('databaseInternal'),
            $this->injector->get('taskd.downloadPagesMessageFactory'),
            $this->injector->get('taskd.messageProducer'),
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|UserMapper
     * @throws InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|FolderMapper
     * @throws InvalidArgumentException If cannot find mapper class
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return Mapper|TermMapper
     * @throws InvalidArgumentException If cannot find mapper class
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }

    /**
     * @return Mapper|ErrorMapper
     * @throws InvalidArgumentException If cannot find mapper class
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * Loads Folder Model instance from route params
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws MapperException
     */
    private function preloadFolder(array $params): array
    {
        $folder   = 'folder';
        $folderId = 'folderId';

        if (($params[$folder] ?? null) instanceof Folder\Model) {
            $preload[$folder] = $params[$folder];
        } elseif (!array_key_exists($folderId, $params)) {
            throw new UnexpectedValueException("No 'folderId' found in route params");
        } else {
            $preload[$folder] = $this->getFolderMapper()->findById($params[$folderId]);
        }

        return $preload;
    }
}
