<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Status;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Project\ResultCache as ProjectResultCache;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\Learning\Assessment\Breadcrumb;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Filter;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\User;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'courseStatus';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $canSeeCourseMarkbook = $this->getCanAccessPopOut();

        // only instructors of all courses can access the publish/enter feedback routes
        $preloadCourseAssessment = function ($params) {
            return $this->buildPreloadCourseAssessment($params);
        };

        // single course level single assessment routes
        $this->register('GET', '/{courseSpecId:\d+}/assessment/{assessmentId:\d+}/status', 'getStatus', $canSeeCourseMarkbook, $preloadCourseAssessment);
        // publish feedback for a single assessment
        $this->register('POST', '/{courseSpecId:\d+}/assessment/{assessmentId:\d+}/publish', 'postPublishFeedback', $canSeeCourseMarkbook, $preloadCourseAssessment);
    }

    /**
     * Only access with the following:
     * markbook is enabled
     * you are logged in
     * you are a staff member (who can view student grades) or superuser
     * if you have write access to all course implementations
     * have write access to the course folder
     *
     * route perm of `\Schoolbox\Assessment\Course\Markbook\Controller::getCanAccessPopOut`
     * @see \Schoolbox\Assessment\Course\Markbook\Controller::getCanAccessPopOut
     *
     * @return IAuthorizationRule
     */
    private function getCanAccessPopOut(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\Callback(function (): void {
                if (!$this->getConfig()->get('course_markbook_enable')) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new InvalidPath('Course markbook module is disabled');
                }
            }),
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\HasRolePermission(Role::PERMISSION_VIEW_STUDENT_GRADES, Role::STAFF),
                new Rule\IsSuperuser()
            ),
            new Rule\Callback(function ($params, ?User $user): void {
                if (null === $user) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new InvalidPath('You must be logged in to access this page');
                }
                // first check if the user has write access to the course
                $hasCourseWriteAccess = $this->getAccessMapper()->getFolderAccess(
                    $user,
                    $params['courseSpec']->getFolder()->getId(),
                ) >= UserAccess::ACCESS_WRITE;
                if (!$hasCourseWriteAccess) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new InvalidPath('You do not have permission to access this course');
                }
                // then check if the user has write access to all the course implementations
                foreach ($params['courseSpec']->getCourseImplementations() as $classImplementation) {
                    $hasAccess = $this->getAccessMapper()->getFolderAccess(
                        $user,
                        $classImplementation->getFolder()->getId()
                    ) >= UserAccess::ACCESS_WRITE;
                    if (!$hasAccess) {
                        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                        throw new InvalidPath('You do not have permission to access this course implementation');
                    }
                }
            })
        );
    }

    /**
     * Preload the course and assessment specs for the controller.
     *
     * @param array $params The parameters from the request.
     * @return array An array containing 'courseSpec' and 'assessmentSpec'.
     * @throws NotFound If the course or assessment cannot be found.
     */
    private function buildPreloadCourseAssessment($params): array
    {
        $courseSpec = $params['courseSpec'] ?? null;
        $assessmentSpec = $params['assessmentSpec'] ?? null;
        if (null === $courseSpec) {
            if (array_key_exists('courseSpecId', $params)) {
                try {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    $courseSpec = $this->getCourseSpecMapper()->findById($params['courseSpecId']);
                } catch (Mapper\Exception $e) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new NotFound("No Course found for id: {$params['courseSpecId']}");
                }
            }
        }
        if (null === $assessmentSpec) {
            if (array_key_exists('assessmentId', $params)) {
                try {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    $assessmentSpec = $this->getAssessmentMapper()->findById($params['assessmentId']);
                } catch (Mapper\Exception $e) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new NotFound("No Assessment found for id: {$params['assessmentId']}");
                }
            }
        }

        // ensure assessment is part of the course
        if ($assessmentSpec->getBaseContext()->getCourse()->getId() !== $courseSpec->getId()) {
            throw new NotFound("Assessment is not part of the course");
        }

        return ['courseSpec' => $courseSpec, 'assessmentSpec' => $assessmentSpec];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $bakery = new Breadcrumb\Factory(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getAccessMapper(),
        );

        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->injector->get('filters.assessmentIdFinder'),
            $this->getAssessmentMapper(),
            $bakery,
            new \Schoolbox\Url\Factory($this->getConfig()),
            (string)$this->getConfig()->get('learnosity_version'),
            (string)$this->getConfig()->get('learnosity_consumer_key'),
            (string)$this->getConfig()->get('learnosity_consumer_secret'),
            $this->getMapperFactory()->get(QuizMapper::class),
            $this->injector->get('filters.factoryFactory'),
            $this->getMapperFactory()->get(Filter\Mapper::class),
            $this->getMapperFactory()->get(Filter\Selection\Mapper::class),
            $this->getMapperFactory()->get(CourseImpl\Mapper::class),
            $this->getMapperFactory()->get(UnitImpl\Mapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->injector->get('responseDataSource'),
            $this->getMapperFactory()->get(ReturnedWorkMapper::class),
            $this->getMapperFactory()->get(ProjectResultCache\Mapper::class),
            $this->injector->get('kamiConnector'),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @return CourseSpec\Mapper
     */
    private function getCourseSpecMapper(): CourseSpec\Mapper
    {
        return $this->getMapperFactory()->get(CourseSpec\Mapper::class);
    }

    /**
     * @return Assessment\MapperProxy
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function getAssessmentMapper(): Assessment\MapperProxy
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * @return Access
     */
    private function getAccessMapper(): Access
    {
        return $this->getMapperFactory()->get(Access::class);
    }
}
