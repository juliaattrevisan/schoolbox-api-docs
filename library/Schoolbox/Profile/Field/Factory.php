<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Profile\Field;

use Schoolbox\Admin\Profile\Editor\Field\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress_Date_Format;
use Calendar_Api_Factory;
use Calendar_Layout_Full_Ajax_JSON;

class Factory
{
    /**
     * Constructor
     *
     * @param Mapper $fieldMapper
     * @param UserMapper $userMapper
     * @param \External_UserInterface $externalUser
     * @param Calendar_Api_Factory $calendarApiFactory
     */
    public function __construct(
        private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper,
        private \Schoolbox\UserMapper $userMapper,
        private \External_UserInterface $externalUser,
        private Calendar_Api_Factory $calendarApiFactory
    ) {
    }


    /**
     * Find the fields that were enabled for $viewee by admin,
     * that $viewer was able to see,
     * ordered by sequence ascending
     *
     * $mutatorIndex is a set of optional callbacks indexed by field name that
     * allows you to fetch its particular value from an expensive place only
     * when it's needed.
     * @param User $viewee
     * @param User $viewer
     * @param array|null $mutatorIndex
     * @return array[]
     * @throws MapperException
     * @throws \DomainException
     * @throws \UnexpectedValueException
     */
    public function produce(\Schoolbox\User $viewee, \Schoolbox\User $viewer, ?array $mutatorIndex = []): array
    {
        $payload = [
            'locale' => null,
            'roleTypes' => [
                'roleType' => $viewee->getRole()->getType(),
                'fields' => [
                    'selected' => []
                ]
            ]
        ];

        /* Data about $viewee that we'll put through the $renderableFields list
         */
        $referenceFields = $this->generateReferenceFields($viewee);

        /* Fields that $viewer can actually see on screen
         */
        $renderableFields = $this->fieldMapper->findRenderableFields($viewee, $viewer);

        /* Override the currentLocation field's value with all the data needed to render it
         * This adds to mutatorIndex so it is only executed if the currentLocation field is renderable
         * to avoid unnecessary generation of calendar data if the field is not visible
         * Don't override if mutatorIndex already has a callback for currentLocation
         */
        if (!isset($mutatorIndex['currentLocation'])) {
            $mutatorIndex['currentLocation'] = function () use ($viewee, $viewer) {
                /* Use the same permission check as the normal calendar view
                 *
                 * Notably this check prevents students or parents from ever seeing a staff's location,
                 * since we have no way to allow them to see a full staff timetable...
                 * The "student" and "parent" options in the profile editor are hence meaningless for staff profiles
                 */
                try {
                    Calendar_Layout_Full_Ajax_JSON::permissionCheck($viewee, $viewer);
                } catch (\Exception $e) {
                    return [];
                }

                // If the viewee's Hide Timetable is set, hide it from everyone (as per the calendar)
                if ($viewee->getHideTimetable()) {
                    return [];
                }
                // if the viewer is staff and the viewer's viewStudentProfile is not set, hide the timetable from them
                if ($viewer->getRole()->isStaff() && !$viewer->getRole()->getViewStudentProfile()) {
                    return [];
                }

                $timetableCalendarApi = $this->calendarApiFactory->produceForTimetableEvents($viewee, $viewer);

                // Find all the events for the current user for today only (getEvents returns the entire day regardless of the timestamps)
                $dayEvents = $timetableCalendarApi->getEvents(
                    Alaress_Date_Format::now()->startOfDay()->getUnixTimeStamp(),
                    Alaress_Date_Format::now()->endOfDay()->getUnixTimeStamp(),
                );

                // Filter down the events to just the ones that are currently happening
                $now = Alaress_Date_Format::now();
                $currentEvents = $dayEvents->filter(function ($event) use ($now): bool {
                    // If it's an all-day event, show it (e.g. sports carnival sort of things?)
                    if ($event->getAllDay()) {
                        return true;
                    }

                    $startIsBeforeNow = $event->getStartTime()->compare($now) <= 0;
                    $endIsAfterNow = $event->getEndTime()->compare($now) >= 0;

                    return $startIsBeforeNow && $endIsAfterNow;
                });

                // Then map to only the data twig needs to render them
                $currentEvents = $currentEvents->map(function ($event) {
                    // There are two possible event types, each has a different set of fields

                    // SUDS timetable event
                    if ($event instanceof \Calendar\Event\Type\SudsTimetable\Event) {
                        return [
                            'title' => $event->getTitle(),
                            'periodName' => $event->getPeriod(),
                            'periodCancelled' => $event->getPeriodCancelled(),
                            'location' => $event->getLocation(),
                            'staff' => $event->getStaff(), // [id => name][]
                            'startTime' => $event->getStartTime(),
                            'endTime' => $event->getEndTime(),
                        ];
                    }
                    // Timetable event
                    if ($event instanceof \Calendar\Event\Type\Timetable\Event) {
                        /* This is a special case for teachers with deleted user accounts.
                         */
                        $staff = [];

                        if (-1 !== $event->getAuthorId() && '' !== trim((string)$event->getAuthorName())) {
                            $staff = [
                                $event->getAuthorId() => $event->getAuthorName()
                            ];
                        }

                        return [
                            'title' => $event->getTitle(),
                            'periodName' => $event->getPeriodName(),
                            'periodCancelled' => $event->getPeriodCancelled(),
                            'location' => $event->getLocation(),
                            // these events only support 1 teacher
                            'staff' => $staff,
                            'startTime' => $event->getStartTime(),
                            'endTime' => $event->getEndTime(),
                        ];
                    }
                    // otherwise, it's an unknown event type (we should never get here)
                    throw new \UnexpectedValueException('Unknown event type: ' . get_class($event));
                });

                return $currentEvents;
            };
        }

        /**
         * Override the iframe field's value with the parsed and sanitised URL and height
         */
        if (!isset($mutatorIndex['iframe'])) {
            $mutatorIndex['iframe'] = function (\Schoolbox\Admin\Profile\Editor\Field\Model $field) use ($viewee) {
                $height = (int)($field->getOptions()['iframeHeight'] ?? 0);
                if ($height < 0) {
                    $height = $field->getOptionsDefault()['iframeHeight'] ?? 0;
                }

                $iframe = '<iframe src="%s" style="height: %dpx"></iframe>';

                /* Look for "remote_service(*)" and replace it with /modules/remote/[BASE64 ENCODED URL]/window
                 * If there's a match, Scrub will remote /modules/remote but the safe iframe list is already validated within that
                 * so we can skip scrubbing it there
                 */
                $url = $field->getOptions()['iframeLink'] ?? $field->getOptionsDefault()['iframeLink'] ?? '';

                /* Possible tokens that will be replaced in the URL before it is parsed */
                $url = strtr($url, [
                    '[username]' => rawurlencode($viewee->getUsername()),
                    '[externalId]' => rawurlencode($viewee->getExternalId() ?? ''),
                    '[email]' => rawurlencode($viewee->getEmail() ?? ''),
                ]);

                if (preg_match('/remote_service\(([^)]+)\)/', $url, $matches)) {
                    $encodedUrl = base64_encode($matches[1]);
                    $url = "/modules/remote/{$encodedUrl}/window";

                    $iframe = sprintf($iframe, $url, $height);
                    return $iframe;
                }

                $iframe = sprintf($iframe, htmlspecialchars($url, ENT_QUOTES), $height);
                return $iframe;
            };
        }

        /** @var \Schoolbox\Admin\Profile\Editor\Field\Model $renderableField */
        foreach ($renderableFields as $renderableField) {
            $node = [
                'field' => $renderableField->getField(),
                'displayAs' => $renderableField->getDisplayAs(),
                'terminology' => $renderableField->getTerm(),
                'icon' => $renderableField->getIcon(),
                'css' => $renderableField->getCss(),
                'clickToReveal' => $renderableField->getOptions()['clickToReveal'] ?? false,
                'value' => $referenceFields[$renderableField->getField()] ?? null
            ];

            /* Some aftermarket data that isn't part of the externals and needs to be fetched
             * from a context outside of this factory. This started with Pastoral Counts that
             * needs various controller-specific instances that are annoying to inject here
             *
             * Find out if $mutator has a callback indexed by the current renderableField's name
             * If there is, use that as the value
             */
            if (isset($mutatorIndex[$renderableField->getField()])) {
                $node['value'] = $mutatorIndex[$renderableField->getField()]($renderableField);
            }

            $payload['roleTypes']['fields']['selected'][] = $node;
        }

        /* Put emergency contacts into this special place for now
         * Parents and staff never use this yet, only students use this;
         * their emergencyContacts node will be null in that case
         *
         * TODO Think about separating these by roletypes somehow
         */
        $payload['roleTypes']['tabs']['emergencyContacts'] = $referenceFields['emergencyContacts'] ?? [];

        /* The preprocessed payload that frontend can just use
         */
        $data = [
            'data' => $payload,
        ];

        if ($viewee->getRole()->isStudent()) {
            $data = array_merge(
                $data,
                [
                    /* Surfacing for email to teachers in student profile
                     */
                    'tutorTeacher' => $referenceFields['tutorTeacher'] ?? null,
                    'formTeacher'  => $referenceFields['formTeacher'] ?? null,
                    'hohTeacher'   => $referenceFields['hohTeacher'] ?? null,
                    'coordTeacher' => $referenceFields['coordTeacher'] ?? null,
                    'studentCoach' => $referenceFields['studentCoach'] ?? null,
                ]
            );
        }
        return $data;
    }


    /**
     * This is a hybrid data struct containing all the information about $viewee.
     * This has schoolbox.user specific fields like pronoun and campus etc.
     * combined with whatever the External_UserInterface fields contain. This method
     * returns a loosely-typed array struct that you can map directly to the
     * list of renderable fields as determined by admin.
     *
     * @param User $viewee
     * @return array
     * @throws MapperException
     * @throws \DomainException
     */
    public function generateReferenceFields(\Schoolbox\User $viewee): array
    {
        if ($viewee->getRole()->isStudent()) {
            return $this->generateStudentReferenceFields($viewee);
        }

        if ($viewee->getRole()->isParent()) {
            return $this->generateParentReferenceFields($viewee);
        }

        if ($viewee->getRole()->isStaff()) {
            return $this->generateStaffReferenceFields($viewee);
        }

        throw new \DomainException('Viewee has an unknown role type');
    }


    /**
     * Preprocesses $viewee (a student) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @return array
     * @throws MapperException
     */
    private function generateStudentReferenceFields(\Schoolbox\User $viewee): array
    {
        $externalUser = [];

        if ($viewee->getExternalId()) {
            $externalUser = $this->externalUser->getStudent($viewee->getExternalId());
            if (isset($externalUser['AlertMessage']) && is_array($externalUser['AlertMessage'])) {
                $externalUser['AlertMessages'] = $externalUser['AlertMessage'];
            } else {
                $externalUser['AlertMessages'] = empty($externalUser['AlertMessage']) ? [] : [trim($externalUser['AlertMessage'])];
            }
        }

        /* Form Teacher
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $formTeacher = null;

        if (isset($externalUser['TeacherFormID']) && (string)$externalUser['TeacherFormID'] !== '') {
            try {
                $formTeacher = $this->userMapper->findByExternalId($externalUser['TeacherFormID'], Role::STAFF);
            } catch (MapperException $e) {
                // noop
            }
        }

        if (!$formTeacher && isset($externalUser['StudentFormStaffName']) && (string)$externalUser['StudentFormStaffName'] !== '') {
            $formTeacher = $this->userMapper->createModel();
            $formTeacher->setLastname($externalUser['StudentFormStaffName']);
        }

        /* Tutor
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $tutorTeacher = null;

        if (isset($externalUser['TeacherTutorID']) && (string)$externalUser['TeacherTutorID'] !== '') {
            try {
                $tutorTeacher = $this->userMapper->findByExternalId($externalUser['TeacherTutorID'], Role::STAFF);
            } catch (MapperException $e) {
                // noop
            }
        }

        if (!$tutorTeacher && isset($externalUser['StudentTutorName']) && (string)$externalUser['StudentTutorName'] !== '') {
            $tutorTeacher = $this->userMapper->createModel();
            $tutorTeacher->setLastname($externalUser['StudentTutorName']);
        }

        /* Housemaster, or Head of House
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $hohTeacher = null;

        if (isset($externalUser['StudentHeadOfHouse']) && (string)$externalUser['StudentHeadOfHouse'] !== '') {
            if ($externalUser['HeadOfHouseID']) {
                try {
                    $hohTeacher = $this->userMapper->findByExternalId($externalUser['HeadOfHouseID'], Role::STAFF);
                } catch (MapperException $e) {
                    $hohTeacher = $this->userMapper->createModel();
                    $hohTeacher->setLastname($externalUser['StudentHeadOfHouse']);
                }
            } else {
                $hohTeacher = $this->userMapper->createModel();
                $hohTeacher->setLastname($externalUser['StudentHeadOfHouse']);
            }
        }

        /* Year Level Coordinator
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $coordTeacher = null;

        if (!empty($externalUser['StudentYearLevelCoordinator'])) {
            if (!empty($externalUser['YearLevelCoordID'])) {
                try {
                    $coordTeacher = $this->userMapper->findByExternalId($externalUser['YearLevelCoordID'], Role::STAFF);
                } catch (MapperException $e) {
                    $coordTeacher = $this->userMapper->createModel();
                    $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
                }
            } else {
                $coordTeacher = $this->userMapper->createModel();
                $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
            }
        }

        /* Coaches (This is a custom work somewhere)
         *
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $studentCoach = null;

        if (!empty($externalUser['StudentCoach'])) {
            if (!empty($externalUser['CoachID'])) {
                try {
                    $studentCoach = $this->userMapper->findByExternalId($externalUser['CoachID']);
                } catch (MapperException $e) {
                    $studentCoach = $this->userMapper->createModel();
                    $studentCoach->setLastname($externalUser['StudentCoach']);
                }
            } else {
                $studentCoach = $this->userMapper->createModel();
                $studentCoach->setLastname($externalUser['StudentCoach']);
            }
        }

        /* For convenience, collate the campus names here
         */
        $campusNames = '';

        if ($viewee->getCampusCollection()) {
            $campusNames = implode(', ', $viewee->getCampusCollection()->indexBy('name')->keys());
        }

        /* For convenience, collate the qualification names here
         */
        $qualificationNames = '';

        if ($viewee->getQualificationCollection()) {
            $qualificationNames = implode(', ', $viewee->getQualificationCollection()->indexBy('name')->keys());
        }

        /* Collect Emergency Contacts from the sis, using the awfully-named nodes
         */
        $emergencyContacts = [];

        foreach (['StudentEmergency', 'StudentEmergency2', 'StudentEmergency3'] as $node) {
            /* Go with the usual "if you have a name, you can be on the ui" idea
             * Some connectors may not give us anything for a given contact node (or part of), so check for that
             */
            $name = trim((string)($externalUser[$node . 'Name'] ?? ''));

            if ('' !== $name) {
                $emergencyContacts[] = [
                    'name' => $name,
                    'phone' => $externalUser[$node . 'Phone'] ?? '',
                    'phoneAlt' => $externalUser[$node . 'PhoneAlt'] ?? '',
                ];
            }
        }

        /* Hybrid Theory
         */
        $referenceFields = [
            'pronoun' => (string)$viewee->getPronoun(),
            'email' => $viewee->getEmail(),
            'externalId' => $viewee->getExternalId(),
            'role' => $viewee->getRole()->getName(),
            'positionTitle' => $viewee->getPositionTitle(),
            /* Favour schoolbox's data over the sis' data
             */
            'schoolHouse' => $viewee->getSchoolHouse()?->getHouse(),
            'residentialHouse' => $viewee->getResidentialHouse()?->getHouse(),
            'yearLevel' => $viewee->getYear()?->getName() ?? '',
            'campusCollection' => $campusNames,
            /* This is an arbitrary set of people associated with $viewee somehow
             * They might (or might not) have actual schoolbox user accounts
             */
            'formTeacher' => $formTeacher,
            'tutorTeacher' => $tutorTeacher,
            'hohTeacher' => $hohTeacher,
            'coordTeacher' => $coordTeacher,
            'studentCoach' => $studentCoach,
            /* Pastoral Counts was obtained specially in the controller cos it needs
             * a bunch of little bits and pieces; use the callback to populate it
             */
            'pastoralCounts' => null,
            /* Typically the external id, but could be anything really
             */
            'barcode' => $viewee->getExternalId(),
            /* Emergency Contacts
             */
            'emergencyContacts' => $emergencyContacts,
            /* Differentiation Profile was obtained specially in the controller cos it needs
             * various controller-specific instances; use the callback to populate it
             */
            'differentiationProfile' => null,
            'ibCandidateNumber' => $viewee->getIbCandidateNumber(),
            'qualificationCollection' => $qualificationNames,
        ];

        $referenceFields = array_merge($externalUser, $referenceFields);

        // If the user has Hide Timetable enabled, hide timetable related fields from everyone
        if ($viewee->getHideTimetable()) {
            $referenceFields['currentLocation'] = null;
            $referenceFields['formTeacher'] = null;
            $referenceFields['StudentForm'] = null;
            $referenceFields['StudentTutorDescription'] = null;
            $referenceFields['tutorTeacher'] = null;
        }

        return $referenceFields;
    }


    /**
     * Preprocesses $viewee (a parent) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @return array
     */
    private function generateParentReferenceFields(\Schoolbox\User $viewee): array
    {
        $externalUser = [];

        if ($viewee->getExternalId()) {
            $externalUser = $this->externalUser->getParent($viewee->getExternalId());
        }

        /* Only obtain spousal information if it had a chance of being visible
         * SpouseDirectoryIncludeFlag will stop them from being seen
         */
        $spouse = null;

        if (isset($externalUser['SpouseID']) && $externalUser['SpouseDirectoryIncludeFlag']) {
            try {
                $spouse = $this->userMapper->findByExternalId($externalUser['SpouseID'], Role::PARENT);
            } catch (MapperException $e) {
                // noop: spouse not found
            }
        }

        /* Only show a spouse if they have meaningful data
         */
        $spouseNode = null;

        /* Assign schoolbox's version of a spouse's details, but we need multiple data points:
         * The name is formatted with a hyperlink to a user profile screen since this spouse
         * has a schoolbox user account. We need their id and name as a struct...so, it's some
         * special handling but we can do that
         */
        if ($spouse) {
            $spouseValue = "{$spouse->getTitle()} {$spouse->getPreferredName()} {$spouse->getLastname()}";

            if ($spouse->getId() && '' !== trim($spouseValue)) {
                $spouseNode = [
                    'id' => $spouse->getId(),
                    'value' => $spouseValue
                ];
            }
        }

        /* For convenience, collate the campus names here
         */
        $campusNames = '';

        if ($viewee->getCampusCollection()) {
            $campusNames = implode(', ', $viewee->getCampusCollection()->indexBy('name')->keys());
        }

        /* Hybrid Theory
         */
        $referenceFields = [
            'pronoun' => (string)$viewee->getPronoun(),
            'email' => $viewee->getEmail(),
            'externalId' => $viewee->getExternalId(),
            'role' => $viewee->getRole()->getName(),
            'positionTitle' => $viewee->getPositionTitle(),
            /* Favour schoolbox's data over the sis' data
             */
            'campusCollection' => $campusNames,
            'spouseName' => $spouseNode,
        ];

        return array_merge($externalUser, $referenceFields);
    }


    /**
     * Preprocesses $viewee (a staff) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @return array
     */
    private function generateStaffReferenceFields(\Schoolbox\User $viewee): array
    {
        $externalUser = [];

        if ($viewee->getExternalId()) {
            $externalUser = $this->externalUser->getStaff($viewee->getExternalId());
        }

        /* For convenience, collate the campus names here
         */
        $campusNames = '';

        if ($viewee->getCampusCollection()) {
            $campusNames = implode(', ', $viewee->getCampusCollection()->indexBy('name')->keys());
        }

        /* Hybrid Theory
         */
        $referenceFields = [
            'pronoun' => (string)$viewee->getPronoun(),
            'email' => $viewee->getEmail(),
            'externalId' => $viewee->getExternalId(),
            'role' => $viewee->getRole()->getName(),
            'positionTitle' => $viewee->getPositionTitle(),
            /* Favour schoolbox's data over the sis' data
             */
            'schoolHouse' => $viewee->getSchoolHouse()?->getHouse(),
            'residentialHouse' => $viewee->getResidentialHouse()?->getHouse(),
            'yearLevel' => $viewee->getYear()?->getName() ?? '',
            'campusCollection' => $campusNames,
            /* Typically the external id, but could be anything really
             */
            'barcode' => $viewee->getExternalId(),
        ];

        return array_merge($externalUser, $referenceFields);
    }
}
