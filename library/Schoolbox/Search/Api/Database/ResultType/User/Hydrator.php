<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\User;

use Alaress\Authorizer\Authorizer;
use Alaress\Database\Predicate\Builder;
use Alaress\Image\Transformation\Storage;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Eportfolio_Authenticator;
use Schoolbox\IConfigLoader;
use Schoolbox\Role;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;
use Schoolbox\User\Formatter\Formatter;
use Schoolbox\UserMapper;
use Schoolbox\L10n\RosettaStone;

/**
 * Hydrate a set of raw user results into a set of full user results.
 *
 * Restrictions on displaying user results:
 * - if `config.show_disabled_users` is off, do not show users which are not
 *   enabled
 * - guest users do not see parents and students; only staff and guests
 * - non-staff users may only see parents or students if configured to be
 *   allowed (`config.parent_search_enable`, `config.student_search_enable`)
 */
class Hydrator extends BaseHydrator
{
    /**
     * This hydrator's base type.
     */
    public const BASE_TYPE = 'user';

    public const SUB_TYPES = [
        // Filter types and subtypes are defined in ResultTypes.ts.  The subtypes below must match that file.
        'user.guest',
        'user.parent',
        'user.staff',
        'user.student',
    ];

    /**
     * Constructor
     *
     * @param RosettaStone  $rosettaStone      Used to translate terminology.
     * @param UserMapper    $userMapper        Used to fetch users for hydration.
     * @param Formatter     $userNameFormatter Used to format a user's name on output.
     * @param IConfigLoader $config            Used for fetching configuration, which controls user search
     *                                         result availability.
     * @param Generator     $routeGenerator
     * @param Authorizer    $authorizer
     */
    public function __construct(
        private RosettaStone $rosettaStone,
        private UserMapper $userMapper,
        private User\Formatter\Formatter $userNameFormatter,
        private IConfigLoader $config,
        private Generator $routeGenerator,
        private Authorizer $authorizer
    ) {
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all users which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        // Which user IDs?
        $userIds = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($userIds)) {
            return [];
        }

        $users = $this->userMapper->findByPredicates($this->buildPredicateBuilder($userIds, $viewer));

        // Map those users to full results
        $fullResults = array_map(
            function (RawResult $match) use ($users, $viewer) {
                $user = $users->getBy('id', $match->id);
                if (!($user instanceof User)) {
                    return null;
                }

                return new FullResult(
                    $match,
                    $match->id,
                    $this->userNameFormatter->formatObject($user),
                    $user->getRole()->getType() === Role::GUEST ? '' : '/search/user/' . $match->id, // Guest users do not have a profile URL
                    $match->type,
                    $this->getTypeTitleFromType($match->type),
                    $match->relevance,
                    $user->getUpdatedAt(),
                    null,
                    $user->getPreferredTitle(),
                    $this->buildBaseUrl()
                        ->withPath('/portrait.php')
                        ->withQuery(
                            http_build_query([
                                'id'   => $user->getId(),
                                'size' => Storage::TRANSFORM_SQUARE64,
                            ])
                        )
                        ->__toString(),
                    'staff-students',
                    null,
                    null,
                    [],
                    $this->getSearchResultLinks($user, $viewer)
                );
            },
            $acceptedMatches,
        );
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }

    /**
     * Build a predicate builder for the user search.
     *
     * @param array $userIds A list of user IDs to search for.
     * @param User  $viewer  The user viewing the search results: this may
     *                       filter out users from the result set.
     * @return Builder
     */
    private function buildPredicateBuilder(array $userIds, User $viewer): Builder
    {
        $pb = $this->userMapper->getPredicateBuilder()->in('id', $userIds);

        // Exclude disabled users if that config isn't on
        if (!$this->config->get('show_disabled_users')) {
            $pb = $pb->isTrue('enabled');
        }

        // Exclude roles from search
        $excludedRoles = [];
        if ($viewer->getRole()->isGuest()) {
            $excludedRoles[] = Role::PARENT;
            $excludedRoles[] = Role::STUDENT;
        } elseif (!$viewer->getRole()->isStaff()) {
            if (!$this->config->get('parent_search_enable')) {
                $excludedRoles[] = Role::PARENT;
            }
            if (!$this->config->get('student_search_enable')) {
                $excludedRoles[] = Role::STUDENT;
            }
        }

        if (!empty($excludedRoles)) {
            $pb = $pb->notIn('g.group_type', $excludedRoles);
        }
        return $pb;
    }

    /**
     * Return an array of sub-arrays, each sub-array containing info for one link.
     * The links will be in alphabetical order of title.
     *
     * @param User $viewer
     * @param User $searchResultUser
     * @return array array<array{"title": string, "icon": string, "url": string, "action"?: string}>
     */
    private function getSearchResultLinks(User $searchResultUser, User $viewer): array
    {
        $links   = [];
        $route   = 'adminUser.getModify';
        $params  = ['userId' => $searchResultUser->getId(), 'user' => $searchResultUser];
        $links[] = $this->generateLinkIfRouteAuthorized($route, $params, $viewer, 'Modify', 'icon-modify');

        $route   = 'user.impersonate';
        $links[] = $this->generateLinkIfRouteAuthorized($route, $params, $viewer, 'Impersonate', 'icon-staff-students');


        $route  = 'calendar.getCalendar';
        $params = [
            'selectedDate' => date('d-m-Y'),
            'userId' => $searchResultUser->getId(),
            'user' => $searchResultUser
        ];
        $links[] = $this->generateLinkIfRouteAuthorized($route, $params, $viewer, 'Calendar', 'icon-calendar');

        $route  = 'learningUpcoming.getUpcomingForStudent';
        $params = [
            'date' => date('d-m-Y'),
            'studentId' => $searchResultUser->getId(),
            'student' => $searchResultUser
        ];
        if ($searchResultUser->getRole()->isStudent()) {
            $links[] = $this->generateLinkIfRouteAuthorized($route, $params, $viewer, 'Due Work', 'icon-due-work');
        }

        $authenticator = new Eportfolio_Authenticator($viewer, $searchResultUser, null);
        if ($authenticator->canView()) {
            $links[] = [
                'title' => 'ePortfolio',
                'icon' => 'icon-eportfolio',
                'url' => $this->buildBaseUrl()
                    ->withPath("/eportfolio/{$searchResultUser->getId()}/profile")
                    ->__toString(),
            ];
        }

        $route  = 'learningGrades.getGrades';
        $params = ['userId' => $searchResultUser->getId(), 'user' => $searchResultUser];
        if ($searchResultUser->getRole()->isStudent()) {
            $links[] = $this->generateLinkIfRouteAuthorized($route, $params, $viewer, 'Grades', 'icon-approve');
        }

        $route  = 'pastoral.getStudent';
        $params = ['studentId' => $searchResultUser->getId(), 'student' => $searchResultUser];
        if ($searchResultUser->getRole()->isStudent()) {
            $links[] = $this->generateLinkIfRouteAuthorized($route, $params, $viewer, $this->rosettaStone->translate('pastoralCare', false, true), 'icon-pastoral');
        }

        // The timetable link from a search result should work in the same way as the timetable link from the user profile.
        // Function generateLinkIfRouteAuthorized() is not used here because timetable.php is a SitePage page (not Aura).
        if (
            ($searchResultUser->getRole()->isStudent() || $searchResultUser->getRole()->isStaff())
            && !$searchResultUser->getHideTimetable()
        ) {
            $links[] = [
                'title' => 'Timetable',
                'icon'  => 'icon-timetable',
                'url'   => 'timetable/' . $searchResultUser->getId(),
            ];
        }

        return array_values(array_filter($links));
    }

    /**
     * Generate a link, only if its route is authorized.
     *
     * @param string $route
     * @param array  $params
     * @param User   $viewer
     * @param string $title
     * @param string $icon
     * @return array|null
     */
    private function generateLinkIfRouteAuthorized(
        string $route,
        array $params,
        User $viewer,
        string $title,
        string $icon,
    ): ?array {
        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
            try {
                return [
                    'title' => $title,
                    'icon'  => $icon,
                    'url'   => $this->routeGenerator->generate($route, $params),
                ];
            } catch (RouteNotFound) {
                // `isPathAuthorizedForUser` doesn't catch or even detect if the
                // route exists: need to catch when calling `generate`.
                return null;
            }
        }
        return null;
    }

    /**
     * @param string $type
     * @return string
     */
    public function getTypeTitleFromType(string $type): string
    {
        return match ($type) {
            'user' => 'User',
            'user.guest' => 'Guest',
            'user.parent' => 'Parent',
            'user.staff' => 'Staff',
            'user.student' => 'Student',
            default => $type,
        };
    }
}
