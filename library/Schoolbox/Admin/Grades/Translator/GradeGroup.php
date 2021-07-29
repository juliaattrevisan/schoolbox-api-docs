<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Grades\Translator;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\User;

/**
 * A translator class used to convert a grade group to a JSON representation.
 */
class GradeGroup
{
    /**
     * @var Router required for generating links
     */
    private Router $router;

    /**
     * @var User currently logged in user needed for authorizer
     */
    private User $currentUser;

    /**
     * @var Authorizer confirm user is authorized to access routes
     */
    private Authorizer $authorizer;

    /**
     * Constructor
     *
     * @param Router     $router
     * @param Authorizer $authorizer
     * @param User       $currentUser
     */
    public function __construct(Router $router, Authorizer $authorizer, User $currentUser)
    {
        $this->router      = $router;
        $this->authorizer  = $authorizer;
        $this->currentUser = $currentUser;
    }

    public function __invoke(GradeLevel\Group\Model $gradeGroup): array
    {
        return [
            'id'        => $gradeGroup->getId(),
            'name'      => $gradeGroup->getName(),
            'levels'    => $gradeGroup->getGradeLevels()->map(
                function (GradeLevel\Model $gradeLevel) {
                    return [
                        'id'         => $gradeLevel->getId(),
                        'lowerBound' => $gradeLevel->getLowerBound(),
                        'rawGrade'   => $gradeLevel->getRawGrade(),
                        'caption'    => $gradeLevel->getCaption(),
                        '_links'     => [
                            'delete' => $this->getRouteIfAuthorized('adminGrades.deleteGradeLevel', ['levelId' => $gradeLevel->getId()]),
                        ],
                    ];
                }
            ),
            '_links'    => [
                'delete' => $this->getRouteIfAuthorized('adminGrades.deleteGradeGroup', ['groupId' => $gradeGroup->getId()]),
            ],
        ];
    }

    /**
     * Helper function lifted from the base controller
     *
     * @param string $route
     * @param array  $params
     * @return string|null
     * @noinspection PhpDocMissingThrowsInspection `$route` should never be missing
     */
    private function getRouteIfAuthorized(string $route, array $params): ?string
    {
        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $this->currentUser)) {
            /** @noinspection PhpUnhandledExceptionInspection `$route` should never be missing */
            return $this->router->generate($route, $params);
        }

        return null;
    }
}
