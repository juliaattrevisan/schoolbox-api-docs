<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\User;
use Schoolbox\Filter;

class Module extends \Alaress\Module\Module
{

    protected $basename = 'courseMarkbook';


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $canSeeUnitMarkbook   = $this->buildAuthRuleCanSeeUnitMarkbook();
        $preloadUnitSpec      = $this->buildPreloadUnitAndCourseSpec();

        $canSeeCourseMarkbook = $this->buildAuthRuleCanSeeCourseMarkbook();
        $preloadCourseSpec    = $this->buildPreloadCourseSpec();

        // Convenience redirects to the Course Markbook page
        $this->register('GET', '/redirect/unit/{unitSpecId:\d+}', 'getUnitRedirect', $canSeeUnitMarkbook, $preloadUnitSpec);
        $this->register('GET', '/redirect/{courseSpecId:\d+}', 'getCourseRedirect', $canSeeCourseMarkbook, $preloadCourseSpec);

        // Course Markbook page and filtered report
        $this->register('GET', '/{courseSpecId:\d+}', 'getMarkbook', $canSeeCourseMarkbook, $preloadCourseSpec);
        $this->register('POST', '/{courseSpecId:\d+}', 'postMarkbook', $canSeeCourseMarkbook, $preloadCourseSpec);
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * To visit the course markbook for a single unit, you must be:
     * * Logged in
     * * A staff member or SU
     * * Have READ+ perm to the requested course and unit
     * Note that when on the page, you will only be able to see assessments from folders where you have READ+ perm.
     * This latter rule is enforced when generating the filter panel, and again when generating the filtered report.
     *
     * @return Rule\IAuthorizationRule
     * @throws \InvalidArgumentException
     */
    private function buildAuthRuleCanSeeUnitMarkbook(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\HasRole(Role::STAFF),
                new Rule\IsSuperuser()
            ),
            new Rule\Callback(function (array $params, User $user) {
                if (!\array_key_exists('unitSpec', $params)) {
                    throw new \InvalidArgumentException('No UnitSpec found in route params');
                }
                /** @var UnitSpec\Model $unitSpec */
                $unitSpec = $params['unitSpec'];
                if ($this->getAccess()->getFolderAccess($user, $unitSpec->getFolder()->getId()) < UserAccess::ACCESS_READ) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new Unauthorized('You do not have permission to view this unit');
                }
                if ($this->getAccess()->getFolderAccess($user, $unitSpec->getCourse()->getFolder()->getId()) < UserAccess::ACCESS_READ) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new Unauthorized("You do not have permission to view this unit's course");
                }
            })
        );
    }


    /**
     * To visit the course markbook, you must be:
     * * Logged in
     * * A staff member or SU
     * * Have READ+ perm to the requested course
     * Note that when on the page, you will only be able to see assessments from folders where you have READ+ perm.
     * This latter rule is enforced when generating the filter panel, and again when generating the filtered report.
     *
     * @return Rule\IAuthorizationRule
     * @throws \InvalidArgumentException
     */
    private function buildAuthRuleCanSeeCourseMarkbook(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\HasRole(Role::STAFF),
                new Rule\IsSuperuser()
            ),
            new Rule\Callback(function (array $params, User $user) {
                if (!\array_key_exists('courseSpec', $params)) {
                    throw new \InvalidArgumentException('No CourseSpec found in route params');
                }
                /** @var CourseSpec\Model $courseSpec */
                $courseSpec = $params['courseSpec'];
                if ($this->getAccess()->getFolderAccess($user, $courseSpec->getFolder()->getId()) < UserAccess::ACCESS_READ) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new Unauthorized('You do not have permission to view this course');
                }
            })
        );
    }


    /**
     * @inheritdoc
     * @param Container $injector
     * @throws \InvalidArgumentException If unable to find mapper class
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:' . $this->getBasename(),
            $injector->lazyNew(Controller::class, [
                'filterFactoryFactory'  => $injector->lazy(function () {
                    return new Filter\Filters\Assessment\FactoryFactory($this->getMapperFactory());
                }),
                'filterMapper'          => $this->getMapperFactory()->getLazy(Filter\Mapper::class),
                'filterSelectionMapper' => $this->getMapperFactory()->getLazy(Filter\Selection\Mapper::class),
            ]));
    }


    /**
     * @return \Schoolbox\Mapper|CourseSpec\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getCourseSpecMapper(): CourseSpec\Mapper
    {
        return $this->getMapperFactory()->get(CourseSpec\Mapper::class);
    }

    /**
     * @return \Schoolbox\Mapper|UnitSpec\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getUnitSpecMapper(): UnitSpec\Mapper
    {
        return $this->getMapperFactory()->get(UnitSpec\Mapper::class);
    }


    /**
     * @return Access|Mapper
     * @throws \InvalidArgumentException
     */
    private function getAccess(): Access
    {
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * Returns a function that preloads a CourseSpec model
     *
     * @return callable
     */
    private function buildPreloadUnitAndCourseSpec(): callable
    {
        return function (array $params): array {
            if (array_key_exists('unitSpecId', $params)) {
                try {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    $unitSpec = $this->getUnitSpecMapper()->findById($params['unitSpecId']);
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    return [
                        'courseSpec' => $this->getCourseSpecMapper()->findById($unitSpec->getCourse()->getId()),
                        'unitSpec'   => $unitSpec,
                    ];
                } catch (Mapper\Exception $e) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new NotFound("No Unit found for id: {$params['unitSpecId']}");
                }
            }
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new NotFound('No Unit id found in route');
        };
    }


    /**
     * Returns a function that preloads a CourseSpec model
     *
     * @return callable
     */
    private function buildPreloadCourseSpec(): callable
    {
        return function (array $params): array {
            if (array_key_exists('courseSpecId', $params)) {
                try {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    return ['courseSpec' => $this->getCourseSpecMapper()->findById($params['courseSpecId'])];
                } catch (Mapper\Exception $e) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new NotFound("No Course found for id: {$params['courseSpecId']}");
                }
            }
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            throw new NotFound('No Course id found in route');
        };
    }
}
