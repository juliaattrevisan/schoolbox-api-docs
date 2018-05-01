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
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\User;

class Module extends \Alaress\Module\Module
{

    protected $basename = 'courseMarkbook';


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $canSeeCourseMarkbook = $this->buildAuthRuleCanSeeCourseMarkbook();
        $preloadCourseSpec    = $this->buildPreloadCourseSpec();

        $this->register('GET', '/{courseSpecId:\d}', 'getCourseMarkbook', $canSeeCourseMarkbook, $preloadCourseSpec);
        $this->register('POST', '/{courseSpecId:\d}', 'postCourseMarkbook', $canSeeCourseMarkbook, $preloadCourseSpec);
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
                /** @var Specification\Model $courseSpec */
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
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->getBasename(), $injector->lazyNew(Controller::class));
    }


    /**
     * @return \Schoolbox\Mapper|Specification\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getCourseSpecMapper(): Specification\Mapper
    {
        return $this->getMapperFactory()->get(Specification\Mapper::class);
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
