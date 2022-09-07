<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Translator;

use Alaress\Image\Transformation\Storage;
use Closure;
use House;
use Schoolbox\Campus;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\YearLevel\Model as YearLevel;

/**
 * Translator for translating a user model into an array, ready for JSON
 * encoding.
 *
 * The information returned by this translator may contain private information,
 * such as the user's email address:
 * see `\Schoolbox\User\Translator\Author\ModelToArray` for a variant which is
 * guaranteed to return only public information.
 */
class ModelToArray
{
    /**
     * @param User $user
     * @param GenericCollection|UserAccess[]|array|bool $groups
     * @return array
     */
    public function __invoke(User $user, $groups = null): array
    {
        return [
            'id'     => $user->getId(),
            'campus' => $user->getCampusCollection()
                ? $user->getCampusCollection()
                       ->map(Closure::fromCallable([$this, 'convertCampus']))
                       ->reindex()
                       ->getAll()
                : null,
            'role'   => $this->convertRole($user->getRole()),

            // Null for now, may hydrate later or make nullable in API
            'groups' => $groups,

            'schoolHouse'      => $this->convertSchoolHouse($user->getSchoolHouse()),

            // Null for now
            'guardians'        => $user->getParents(),
            'students'         => $user->getChildren(),

            'impersonableUser' => $user->getLinkedUser(),

            'email'          => $user->getEmail(),
            '_links'         => $this->deriveLinks($user),
            'externalId'     => $user->getExternalId(),
            'username'       => $user->getUsername(),
            'superuser'      => $user->isSuperuser(),
            'enabled'        => $user->getEnabled(),
            'title'          => $user->getTitle(),
            'firstName'      => $user->getFirstname(),
            'preferredName'  => $user->getPreferredName(),
            'lastName'       => $user->getLastname(),
            'fullName'       => $user->getFullName(),
            'isEmailDerived' => $user->getLocalEmail(),
            'altEmail'       => $user->getAltEmail(),
            'positionTitle'  => $user->getPositionTitle(),
            'yearLevel'      => $this->convertYearLevel($user->getYear()),
            'isDeleted'      => $user->isDeleted(),

            // Just cast to bool for now, maybe leave out later?
            'isBoarder'      => (bool)$user->getAbbreviatedBoardingStatus(),
            'flags'          => [
                'showWebmailTab'         => $user->getWebmail(),
                'showContacts'           => !$user->getHideContacts(),
                'enableTimetable'        => !$user->getHideTimetable(),
                'showSideNav'            => !$user->getHideSidenav(),
                'showNotificationsPanel' => !$user->getHideNotificationsTray(),
                'showGradeGraph'         => $user->getDisplayGradeGraph(),
                'enableEportfolio'       => $user->getEportfolioEnable(),
                'useInternalMail'        => $user->getUseInternalMail(),
                'calendarView'           => $user->getCalendarView(),
            ],
        ];
    }


    /**
     * Helper for converting a campus into an array.
     *
     * @param Campus $campus
     * @return array
     */
    private function convertCampus(Campus $campus): array
    {
        return [
            'id'   => $campus->getId(),
            'name' => $campus->getName(),
            'code' => $campus->getExtTimetableCode(),
            'days' => $campus->getTimetableDay(),
        ];
    }


    /**
     * Helper for converting a role into an array.
     *
     * @param Role $role
     * @return array|null
     */
    private function convertRole(Role $role): ?array
    {
        return $role->getId()
            ? [
                'id'   => $role->getId(),
                'name' => $role->getName(),
                'type' => $role->getType(),
            ]
            : null;
    }


    /**
     * Helper for converting a school house into an array.
     *
     * @param House|null $house
     * @return array|null
     */
    private function convertSchoolHouse(?House $house): ?array
    {
        return $house
            ? [
                'id'   => $house->getId(),
                'name' => $house->getHouse(),
            ]
            : null;
    }

    /**
     * Helper for converting a year level into an array.
     *
     * @param YearLevel|null $yearLevel
     * @return array|null
     */
    private function convertYearLevel(?YearLevel $yearLevel): ?array
    {
        return $yearLevel
            ? [
                'id'   => $yearLevel->getId(),
                'name' => $yearLevel->getName(),
            ]
            : null;
    }


    /**
     * Derive links for this user.
     *
     * @param User $user
     * @return string[]
     */
    protected function deriveLinks(User $user): array
    {
        // TODO consider providing default sizes
        return $user->getId()
            ? [
                'profile' => '/search/user/' . $user->getId(),
                'avatar'  =>
                    sprintf(
                        '/portrait.php?id=%d&size=%s',
                        $user->getId(),
                        urlencode(Storage::TRANSFORM_SQUARE64)
                    ),
            ]
            : [];
    }
}
