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
use Alaress\Authorizer\Failure\InvalidPath;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\User;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

class Module extends \Alaress\Module\Module
{

    protected $basename = 'courseMarkbook';


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $preloadCourseSpec    = $this->buildPreloadCourseSpec();
        $canSeeCourseMarkbook = $this->buildAuthRuleCanSeeCourseMarkbook();

        // Convenience redirects to the Course Markbook page
        $this->register('GET', '/{courseSpecId:\d+}/unit/{unitSpecId:\d+}/folder/{folderId:\d+}', 'getRedirect', $canSeeCourseMarkbook, $preloadCourseSpec);

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
            new Rule\Callback(function () {
                if (!$this->getConfig()->get('course_markbook_enable')) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new InvalidPath('Course markbook module is disabled');
                }
            }),
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
     * @param ContainerInterface|Container $injector
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:' . $this->getBasename(),
            $injector->lazyNew(Controller::class, [
                'defaultGradeGroup'     => $this->getInjector()->get('defaultGradeGroup'),
                'filterFactoryFactory'  => $injector->lazy(function () {
                    return new Filter\Filters\Assessment\FactoryFactory($this->getMapperFactory());
                }),
                'filterMapper'          => $this->getMapperFactory()->getLazy(Filter\Mapper::class),
                'filterSelectionMapper' => $this->getMapperFactory()->getLazy(Filter\Selection\Mapper::class),
                'assessmentIdFinder'    => $injector->lazy(function(){
                    /** @var Filter\Mapper $filterMapper */
                    $filterMapper = $this->getMapperFactory()->get(Filter\Mapper::class);
                    return Filter\Filters\Assessment\IdFinder::getInstance(
                        $filterMapper, $this->getMapperFactory()->get(Assessment\Mapper::class)
                    );
                }),
                'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource'),
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
