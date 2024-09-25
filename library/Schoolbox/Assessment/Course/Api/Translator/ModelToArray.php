<?php

namespace Schoolbox\Assessment\Course\Api\Translator;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Generator;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\User;
use Schoolbox\Collection;
use Alaress\Authorizer\GetRouteIfAuthorized;

class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
    ) {
    }

    public function __invoke($course)
    {
        return $this->translate($course);
    }

    private function translate($course)
    {
        $folder = $course->getFolder();
        $courseId = $course->getId();

        $result = [
            'id' => $courseId,
            'name' => $folder->getName(),
            'courseFrameworkId' => $course->getTemplate()->getId(),
            'subjectCode' => $course->getSubjectCode(),
        ];

        $result['_links'] = [
            'view' => $this->getRouteIfAuthorized(
                'course.modifyCourse',
                ['courseSpec' => $course, 'id' => $courseId]
            ),
            'delete' => $this->getRouteIfAuthorized(
                'course.deleteCourseSpecification',
                ['courseSpec' => $course, 'id' => $courseId]
            )
        ];

        return $result;
    }
}
