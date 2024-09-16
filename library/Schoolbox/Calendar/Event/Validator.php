<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Web\Request\Values as WebRequestValues;
use Alaress_Date_Format;
use Alaress_Date_Locale_Config;
use DateTimeInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Attendance\Model as EventAttendanceModel;
use Schoolbox\Calendar\EventRecurrence;
use Schoolbox\Calendar\EventRecurrenceMapper;
use UnexpectedValueException;

/**
 * Password validator
 *
 * @package Schoolbox\User\Password
 */
class Validator
{
    /**
     * @param Alaress_Date_Locale_Config $locale
     */
    public function __construct(private readonly Alaress_Date_Locale_Config $locale)
    {
    }

    /**
     * Check that $this->requestPost contains valid data for $this->postCreate().
     * If a problem is found, throw a SchoolboxException with a helpful error message.
     *
     * This function should not do referential integrity checks on database ids.
     * This is because the database engine will do that for us when the UPDATE is attempted.
     *
     * This function is intended to provide helpful error messages to API users.
     * API docs: https://api.schoolbox.com.au/#post-/calendar/event/-id-/modify
     *
     * @param WebRequestValues $originalRequestPost
     */
    public function validateForPostCreate(WebRequestValues $originalRequestPost): void
    {
        // These should match the fields marked as required on the calendar/event/create page.
        $requiredKeys = [
            'allDay',
            'title',
            'startDate',
            'endDate',
            'publishDate',
            'type',  // This is in effect required on the calendar/event/create page, because no 'None' option exists.
            'level', // This is in effect required on the calendar/event/create page, because no 'None' option exists.
        ];

        // Note further validation of $this->requestPost is done in $this->postModify().
        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, (array)$originalRequestPost)) {
                throw new SchoolboxException("Missing required key '$requiredKey'.");
            }
        }

        // Conditional required fields depending on the value for 'level'.
        switch ($originalRequestPost['level']) {
            case Event::ACCESS_PERSONAL:
                // Nothing extra required.  Carry on.
                break;
            case Event::ACCESS_GROUP:
                if (!array_key_exists('folders', (array)$originalRequestPost)) {
                    throw new SchoolboxException(
                        "Missing required key 'folders'." .
                        "  This is required because 'level' has value " . Event::ACCESS_GROUP . '.'
                    );
                }
                break;
            case Event::ACCESS_CAMPUS:
                if (!array_key_exists('campuses', (array)$originalRequestPost)) {
                    throw new SchoolboxException(
                        "Missing required key 'campuses'." .
                        "  This is required because 'level' has value " . Event::ACCESS_CAMPUS . '.'
                    );
                }
                break;
            default:
                throw new SchoolboxException("Unexpected value for key 'level'.");
        }
    }

    /**
     * Check that $this->requestPost contains valid data for $this->postModify().
     * If a problem is found, throw a SchoolboxException with a helpful error message.
     *
     * This function should not do referential integrity checks on database ids.
     * This is because the database engine will do that for us when the UPDATE is attempted.
     *
     * This function is intended to provide helpful error messages to API users.
     * API docs: https://api.schoolbox.com.au/#post-/calendar/event/-id-/modify
     *
     * @param WebRequestValues $originalRequestPost
     * @param Event $event - The pre-existing event.  Required because all request parameters are optional, and some
     *                       checks depend on a combination of parameters.
     * @return WebRequestValues
     */
    public function validateForPostModify(Event $event, WebRequestValues $originalRequestPost): WebRequestValues
    {
        $requestPost = clone($originalRequestPost);

        // Note array_key_exists() is used here instead of isset(), because isset() returns false if the array key
        // exists but the value is null.  If the array key exists, then a new value has been supplied, and so we must
        // assume the caller intends for that new value to replace the old value - even if the new value is null.

        // Account for special key 'userId' (posted by the calendar/event/create and modify pages).
        if (array_key_exists('userId', (array)$requestPost) && !array_key_exists('user', (array)$requestPost)) {
            $requestPost['user'] = ['id' => $requestPost['userId']];
            unset($requestPost['userId']);
        }

        // Account for special key 'authorId' (posted by the calendar/event/create and modify pages).
        if (array_key_exists('authorId', (array)$requestPost) && !array_key_exists('author', (array)$requestPost)) {
            $requestPost['author'] = ['id' => $requestPost['authorId']];
            unset($requestPost['authorId']);
        }

        // Account for special case of key 'type' (posted by the calendar/event/create and modify pages).
        if (array_key_exists('type', (array)$requestPost) && !is_array($requestPost['type'])) {
            $requestPost['type'] = ['id' => $requestPost['type']];
        }

        // Account for special case of key 'guests' (posted by the calendar/event/create and modify pages).
        if (array_key_exists('guests', (array)$requestPost) && is_string($requestPost['guests'])) {
            // Convert comma-separated string of ids to array of object stubs.
            $guestIds = (
            strlen($requestPost['guests']) == 0
                ? []
                : array_map(
                    fn($guestId) => (int)$guestId,
                    explode(',', $requestPost['guests'])
                )
            );
            /**
             * Without this hint, PHPStan warns in foreach loop below "string does not accept array<string, int>."
             * @var array $requestPost['guests']
             */
            $requestPost['guests'] = [];
            foreach ($guestIds as $guestId) {
                $requestPost['guests'][] = ['id' => $guestId];
            }
        }

        // Account for special case of key 'maxAttendees' (posted by the calendar/event/create and modify pages).
        if (array_key_exists('maxAttendees', (array)$requestPost) && $requestPost['maxAttendees'] === '') {
            $requestPost['maxAttendees'] = null;
        }

        // Check booleans.
        foreach (['allDay', 'showAttendees'] as $booleanKey) {
            if (array_key_exists($booleanKey, (array)$requestPost)) {
                $value = $requestPost[$booleanKey];
                if ($booleanKey === 'allDay' && $value === '') {
                    // A blank string is sent by the create/modify form.  Interpret it as 0.
                    $value = 0;
                }
                if (!is_bool($value) && !in_array($value, [0, 1])) {
                    throw new SchoolboxException("Value for key '$booleanKey' should be a boolean.");
                }
                $requestPost[$booleanKey] = (bool)$value;
            }
        }

        // Check integers.
        foreach (['level', 'attendance', 'maxAttendees'] as $integerKey) {
            if (array_key_exists($integerKey, (array)$requestPost)) {
                $value = $requestPost[$integerKey];
                // Extra validation for integer key 'maxAttendees'.
                if ($integerKey === 'maxAttendees') {
                    if ($value === null) {
                        continue; // Null is okay for 'maxAttendees'.
                    }
                }
                if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
                    throw new SchoolboxException("Value for key '$integerKey' should be an integer.");
                }
                // Extra validation for integer key 'level'.
                if ($integerKey === 'level') {
                    if (!in_array($value, [Event::ACCESS_PERSONAL, Event::ACCESS_GROUP, Event::ACCESS_CAMPUS])) {
                        throw new SchoolboxException("Unexpected value for key '$integerKey'.");
                    }
                }
                // Extra validation for integer key 'attendance'.
                if ($integerKey === 'attendance') {
                    if (
                        !in_array(
                            $value,
                            [
                                EventAttendanceModel::LEVEL_NO_ATTENDANCE,
                                EventAttendanceModel::LEVEL_INVITE_ONLY,
                                EventAttendanceModel::LEVEL_OPEN_ATTENDANCE,
                            ]
                        )
                    ) {
                        throw new SchoolboxException("Unexpected value for key '$integerKey'.");
                    }
                }
                $requestPost[$integerKey] = (int)$value;
            }
        }

        // Check strings.
        foreach (['efficacy', 'title', 'repeat', 'location', 'detail'] as $stringKey) {
            if (array_key_exists($stringKey, (array)$requestPost)) {
                if (!is_string($requestPost[$stringKey])) {
                    throw new SchoolboxException("Value for key '$stringKey' should be a string.");
                }
                // Extra validation for string keys expected to be non-empty.
                if ($stringKey === 'title' && strlen(trim($requestPost[$stringKey])) === 0) {
                    throw new SchoolboxException("Value for key '$stringKey' should be a non-empty string.");
                }
                // Extra validation for string key 'efficacy'.
                if (
                    $stringKey === 'efficacy' &&
                    !in_array($requestPost[$stringKey], [EventRecurrenceMapper::EFFICACY_SELF, EventRecurrenceMapper::EFFICACY_SERIES])
                ) {
                    throw new SchoolboxException("Unexpected value for key '$stringKey'.");
                }
                // Extra validation for string key 'repeat'.
                if (
                    $stringKey === 'repeat' &&
                    $requestPost[$stringKey] !== '' && // Special value meaning 'no repeat'.
                    !in_array($requestPost[$stringKey], EventRecurrence::getValidRepeatKeys())
                ) {
                    throw new SchoolboxException("Unexpected value for key '$stringKey'.");
                }
            }
        }

        // Check object stubs.
        foreach (['user', 'author', 'type'] as $objectStubKey) {
            if (array_key_exists($objectStubKey, (array)$requestPost)) {
                $objectStubValue = $requestPost[$objectStubKey];
                $requestPost[$objectStubKey] = $this->validateObjectStub($objectStubKey, $objectStubValue);
            }
        }

        // Check arrays.
        foreach (['roles', 'campuses', 'folders', 'guests'] as $arrayKey) {
            if (array_key_exists($arrayKey, (array)$requestPost)) {
                if (!is_array($requestPost[$arrayKey])) {
                    throw new SchoolboxException(
                        "Value for key '$arrayKey' should be an array of object stubs e.g. [{\"id\": 1}, {\"id\": 2}]."
                    );
                }

                $objectStubs = $requestPost[$arrayKey];
                $requestPost[$arrayKey] = [];
                for ($i = 0; $i < count($objectStubs); ++$i) {
                    $objectStub = $objectStubs[$i];

                    // If the object stub is not an array, assume it is a directly supplied id value for now,
                    // (but confirm that assumption in $this->validateObjectStub() below).
                    // This is required because calendar/event/create and modify pages submit ids instead of object stubs.
                    if (!is_array($objectStub)) {
                        $objectStub = ['id' => $objectStub];
                    }

                    try {
                        $requestPost[$arrayKey][] = $this->validateObjectStub($i, $objectStub);
                    } catch (SchoolboxException) {
                        throw new SchoolboxException("Element " . ($i + 1) . " of array for key '$arrayKey' is invalid.");
                    }
                }

                // Note regarding folders and group-level events
                // ---------------------------------------------
                // It may seem appropriate to validate here that all folders are groups.  However, when an event is
                // added using the homepage calendar component, the created event is linked to the homepage folder,
                // regardless of whether it is a group.  So folders need not be groups.
            }
        }

        /**
         * Checks depending on a combination of parameters.
         *
         * Each check here must be done using the parameter supplied in the request (if present), otherwise the
         * parameter from the pre-existing event.  That preference order is important, because we are checking whether
         * the event will be valid after the requested modification(s) have been performed.
         */

        // Check the format of newly supplied dates, taking into account the new or existing value of 'allDay'.
        // Note existing dates can be assumed to have the correct format, since any format can be obtained from existing dates.
        $allDayBool = $requestPost['allDay'] ?? ($event->getAllDay() === Event::ALL_DAY);
        foreach (['startDate', 'endDate', 'publishDate', 'repeat_until'] as $datetimeKey) {
            if (array_key_exists($datetimeKey, (array)$requestPost)) {
                if ($datetimeKey === 'publishDate' && in_array($requestPost[$datetimeKey], [null, ''])) {
                    $requestPost[$datetimeKey] = null;
                    continue; // Allow null and treat empty string as null.
                }
                try {
                    new Alaress_Date_Format(
                        $requestPost[$datetimeKey],
                        $this->getValidFormatsForDateField($datetimeKey, $allDayBool)
                    );
                } catch (InvalidArgumentException) {
                    throw new SchoolboxException("Value for key '$datetimeKey' should be a date in RFC3339 format.");
                }
            }
        }

        // Check the values of 'folders' and 'campuses', taking into account the new or existing value of 'level'.
        $accessLevel = $requestPost['level'] ?? $event->getAccess();
        $campuses = $requestPost['campuses'] ?? $event->getCampusCollection();
        $folders = $requestPost['folders'] ?? $event->getFolderCollection();
        $roles = $requestPost['roles'] ?? $event->getRoleCollection();
        switch ($accessLevel) {
            case Event::ACCESS_PERSONAL:
                // (Do not throw exception here about supplying non-empty campuses/folders/roles, because the modify
                //  event form will trigger it.)

                // Campuses, roles, and folders are irrelevant for personal-level events.
                // Override here in case these fields were not included in the request.
                $requestPost['campuses'] = [];
                $requestPost['folders'] = [];
                $requestPost['roles'] = [];
                break;
            case Event::ACCESS_GROUP:
                // (Do not throw exception here about supplying non-empty campuses/roles, because the modify
                //  event form will trigger it.)

                // Campuses and roles are irrelevant for group-level events.
                // Override here in case these fields were not included in the request.
                $requestPost['campuses'] = [];
                $requestPost['roles'] = [];
                if (count($folders) === 0) {
                    throw new SchoolboxException("Folders must be non-empty for group-level events.");
                }
                break;
            case Event::ACCESS_CAMPUS:
                // (Do not throw exception here about supplying non-empty folders, because the modify
                //  event form will trigger it.)

                // Folders are irrelevant for campus-level events.
                // Override here in case these fields were not included in the request.
                $requestPost['folders'] = [];
                if (count($campuses) === 0 || count($roles) == 0) {
                    throw new SchoolboxException(
                        "For campus-level events, 'roles' and 'campuses' must be non-empty."
                    );
                }
                break;
        }

        return $requestPost;
    }

    /**
     * Return an array of date format names that are valid for the given date field key.
     * The returned array is suitable for use in the Alaress_Date_Format() constructor.
     *
     * @param string $dateFieldKey
     * @param bool   $allDay
     * @return array
     */
    public function getValidFormatsForDateField(string $dateFieldKey, bool $allDay): array
    {
        $acceptedFormats = [];

        switch ($dateFieldKey) {
            case 'startDate': // Fall through.
            case 'endDate':
                // Date only for allDay events, otherwise datetime.
                $acceptedFormats[] = $this->locale->localShortFormat(); // Required by calendar/event/create and modify pages.
                $acceptedFormats[] = DateTimeInterface::RFC3339;
                if (!$allDay) {
                    $acceptedFormats[] = $this->locale->localFormat();  // Required by calendar/event/create and modify pages.
                    $acceptedFormats[] = DateTimeInterface::RFC3339_EXTENDED;
                }
                break;
            case 'repeat_until':
                // Always date only.
                $acceptedFormats[] = $this->locale->localShortFormat(); // Required by calendar/event/create and modify pages.
                $acceptedFormats[] = DateTimeInterface::RFC3339;
                break;
            case 'publishDate':
                // Always datetime.
                $acceptedFormats[] = $this->locale->localFormat();      // Required by calendar/event/create and modify pages.
                $acceptedFormats[] = $this->locale->localShortFormat(); // Required by calendar/event/create and modify pages.
                $acceptedFormats[] = DateTimeInterface::RFC3339;
                $acceptedFormats[] = DateTimeInterface::RFC3339_EXTENDED;
                break;
            default:
                throw new UnexpectedValueException('Unexpected value for $dateFieldKey.');
        }

        return $acceptedFormats;
    }

    /**
     * @param $key
     * @param $value
     * @return array
     */
    private function validateObjectStub($key, $value): array
    {
        if (!is_array($value)) {
            throw new SchoolboxException("Value for key '$key' should be a object stub e.g. {\"id\": 1}.");
        }
        if (!array_key_exists('id', $value)) {
            throw new SchoolboxException("Object stub for key '$key' is missing an 'id' key.");
        }
        $idValue = $value['id'];
        if (!is_int($idValue) && !(is_string($idValue) && ctype_digit($idValue))) {
            throw new SchoolboxException("Value for '$key.id' should be an integer.");
        }
        $value['id'] = (int)$idValue;

        return $value;
    }
}
