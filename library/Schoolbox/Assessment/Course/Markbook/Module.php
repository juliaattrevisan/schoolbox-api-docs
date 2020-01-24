<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Alaress\Authorizer\Failure\InvalidPath;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;

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
        $this->register('GET', '/export/{courseSpecId:\d+}', 'getMarkbookExport', $canSeeCourseMarkbook, $preloadCourseSpec);
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
            )
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
                'filterFactoryFactory'  => $injector->lazyGet('filters.factoryFactory'),
                'filterMapper'          => $this->getMapperFactory()->getLazy(Filter\Mapper::class),
                'filterSelectionMapper' => $this->getMapperFactory()->getLazy(Filter\Selection\Mapper::class),
                'assessmentIdFinder'    => $injector->lazyGet('filters.assessmentIdFinder'),
                'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource'),
                'courseImplMapper' => $this->getMapperFactory()->getLazy(CourseImpl\Mapper::class),
                'unitImplMapper' => $this->getMapperFactory()->getLazy(UnitImpl\Mapper::class),
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
