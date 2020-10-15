<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Translator;

use Closure;
use House;
use HouseResidential;
use Schoolbox\Campus;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\YearLevel\Model as YearLevel;

/**
 * Translator for translating a user model into an array, ready for JSON
 * encoding.
 */
class ModelToArray
{
    /**
     * @param User $user
     * @return array
     */
    public function __invoke(User $user): array
    {
        return [
            'id'     => $user->getId(),
            'campus' => $user->getCampusCollection()
                ? $user->getCampusCollection()->map(Closure::fromCallable([$this, 'convertCampus']))
                : null,
            'role'   => $this->convertRole($user->getRole()),

            // Null for now, may hydrate later or make nullable in API
            'groups' => null,

            'schoolHouse'      => $this->convertSchoolHouse($user->getSchoolHouse()),
            'residentialHouse' => $this->convertResidentialHouse($user->getResidentialHouse()),

            // Null for now
            'guardians'        => null,
            'students'         => null,

            'impersonableUser' => null,

            'email'          => $user->getEmail(),
            '_links'         => [
                'profile' => '/search/user/' . $user->getId(),
                'avatar'  => '/portrait.php?id=' . $user->getId(),
            ],
            'externalId'     => $user->getExternalId(),
            'username'       => $user->getUsername(),
            'superuser'      => $user->isSuperuser(),
            'enabled'        => $user->getEnabled(),
            'title'          => $user->getTitle(),
            'firstName'      => $user->getFirstname(),
            'lastName'       => $user->getLastname(),
            'fullName'       => $user->getFullName(),
            'isEmailDerived' => $user->getLocalEmail(),
            'altEmail'       => $user->getAltEmail(),
            'positionTitle'  => $user->getPositionTitle(),
            'yearLevel'      => $this->convertYearLevel($user->getYear()),

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
     * @return array
     */
    private function convertRole(Role $role): array
    {
        return [
            'id'   => $role->getId(),
            'name' => $role->getName(),
            'type' => $role->getType(),
        ];
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
     * Helper for converting a residential house into an array.
     *
     * @param HouseResidential|null $residentialHouse
     * @return array|null
     */
    private function convertResidentialHouse(?HouseResidential $residentialHouse): ?array
    {
        return $residentialHouse
            ? [
                'id'   => $residentialHouse->getId(),
                'name' => $residentialHouse->getHouse(),
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
}
