<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;

use Alaress_Date_Format;
use DateTime;
use InvalidArgumentException;
use OutOfBoundsException;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\ISoftDeletable;

/**
 * Class Event
 *
 * @package Schoolbox\Calendar
 */
class Event implements ISoftDeletable
{
    /**
     * Implement soft delete traits
     */
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Variant
     *
     * Events are one of two variants: an "event" or a "task" which is a to-do list
     */
    public const VARIANT_EVENT = 'event';
    public const VARIANT_TASK  = 'task';

    /**
     * Event Levels
     */
    public const ACCESS_PERSONAL = 1;
    public const ACCESS_GROUP = 2;
    public const ACCESS_CAMPUS = 3;


    /**
     * Int corresponding to All Day setting set to on
     */
    public const ALL_DAY = 1;


    /**
     * Event id
     *
     * @var int
     */
    protected $id;


    /**
     * Series Id for recurring events, I think
     *
     * @var int
     */
    protected $seriesId;


    /**
     * The person who spawned the event in the first place
     *
     * @var User
     */
    protected $author;


    /**
     * This denotes to whom the event is relevant for a particular
     * combination of sid and access (I don't remember/understand which)
     *
     * @var User
     */
    protected $owner;


    /**
     * @var Folder
     */
    protected $folder;

    /**
     * Access level
     *
     * @var int
     */
    protected $access = 0;

    /**
     * A hex string representing the colour of this event
     *
     * @var string
     */
    protected $colour = '';

    /**
     * Unholy selection of field names: eventType and type
     *
     * eventType is a human-friendly description of an event's type;
     * e.g. Event, Excursion, Report etc. and comes from a lookup table
     *
     * @var EventType
     */
    protected $type;

    /**
     * The variant of the event.
     *
     * Currently there are two variants of event:
     * * `self::VARIANT_EVENT`: standard events (the default)
     * * `self::VARIANT_TASK`: tasks
     *
     * @var string
     */
    private $variant = self::VARIANT_EVENT;

    /**
     * Title of the event
     *
     * @var string|null
     */
    protected $title = '';

    /**
     * A description of the event
     *
     * @var string|null
     */
    protected $detail = '';

    /**
     * Where the event is being held
     *
     * @var string|null
     */
    protected $location = '';

    /**
     * Whether or not people are supposed to attend
     *
     * @var int
     */
    protected $attendance = 0;


    /**
     * Maximum number of people who can be invited to this event
     *
     * null == no limit
     *
     * @var int
     */
    protected $maxAttendees;


    /**
     * Whether to show the attendance guest list
     *
     * @var bool
     */
    protected $showAttendees;


    /**
     * Determine whether the attendance state changed prior to saving
     *
     * This saves us an extra db call in save() where it used to
     * load the old copy of the event, and then compare it to the
     * newly-constructed version of the event, to determine whether
     * existing attendances should be cleared or preserved.
     *
     * @var bool
     */
    protected $attendanceDirty = false;

    /**
     * If this is a task, whether it's completed or not
     * That should have been normalised -_-
     *
     * @var int
     */
    protected $completed = 0;

    /**
     * Whether it's an all-day event
     *
     * @var int
     */
    protected $allDay = 0;

    /**
     * Any associated roles
     *
     * @var Collection|Role[]
     */
    protected $roleCollection;

    /**
     * Any associated users (Type 1)
     *
     * @var null
     */
    protected $userCollection;

    /**
     * Any associated folders (Type 2)
     *
     * @var null
     */
    protected $folderCollection;

    /**
     * Any associated campuses (Type 3)
     *
     * @var Collection|Campus[]
     */
    protected $campusCollection;


    /**
     * Associated guests
     *
     * @var Collection|User[]
     */
    protected $guests;


    /**
     * The date when the event was published (made visible to users)
     * An instance Alaress_Date_Format()
     *
     * @var Alaress_Date_Format
     */
    protected $publishDateTime;

    /**
     * The datetime when the event starts
     *
     * @var Alaress_Date_Format
     */
    protected $startDateTime;

    /**
     * The datetime when the event ends
     *
     * @var Alaress_Date_Format
     */
    protected $endDateTime;

    /**
     * Source of import
     *
     * This string identifies the origin of an event in the db, which is a federated
     * list of events from multiple sources.
     *
     * @var string
     */
    protected $source;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setUserCollection(new Collection());
        $this->setFolderCollection(new Collection());
        $this->setRoleCollection(new Collection());
        $this->setType(new EventType());
        $this->setup();
    }


    /**
     * Hook method
     */
    protected function setup()
    {
    }


    /**
     * Is this event a personal-accesslevel instance?
     *
     * @return bool
     */
    public function isPersonalLevel(): bool
    {
        return $this->getAccess() === self::ACCESS_PERSONAL;
    }


    /**
     * Is this event a group-accesslevel instance?
     *
     * @return bool
     */
    public function isGroupLevel(): bool
    {
        return $this->getAccess() === self::ACCESS_GROUP;
    }


    /**
     * Is this event a campus-accesslevel instance?
     *
     * @return bool
     */
    public function isCampusLevel(): bool
    {
        return $this->getAccess() === self::ACCESS_CAMPUS;
    }


    /**
     * Is this event published as of `$date`?
     *
     * @param Alaress_Date_Format|null $date The date to check, or now if not
     *                                       provided.
     * @return bool
     */
    public function isPublished(?Alaress_Date_Format $date = null): bool
    {
        // An event without a publish date is considered published
        if (!$this->publishDateTime || $this->publishDateTime->isNull()) {
            return true;
        }

        // What date was passed in?
        // (I really hope you didn't pass in a null date)
        if (!$date || $date->isNull()) {
            $date = Alaress_Date_Format::now();
        }
        return $this->publishDateTime->lte($date);
    }

    /* Auxiliary methods which don't belong anywhere else
     */

    /**
     * Derive start and end dates from a date.
     *
     * @param string|null $date A string in either "d-m-Y" or "d-m-Y H:i" format.
     * @throws InvalidArgumentException
     */
    public function setupDates(?string $date): void
    {
        if ($date) {
            try {
                $selectedDate = new Alaress_Date_Format($date, 'd-m-Y H:i');
                $this->setAllDay(false);
            } catch (\Exception $e) {
                try {
                    $selectedDate = new Alaress_Date_Format($date, 'd-m-Y');
                    $this->setAllDay(true);
                } catch (\Exception $e) {
                    // noop
                }
            }
        }

        if (!isset($selectedDate)) {
            $selectedDate = Alaress_Date_Format::now();
        }

        $this->setStartDateTime($selectedDate);
        $this->setEndDateTime(clone $selectedDate); // Clone so that start and end do not refer to the same date object.
    }


    /* Getters/Setters
     */

    /**
     * @return Collection|int[]
     */
    public function getGuests()
    {
        return $this->guests;
    }

    /**
     * @param Collection|int[] $guests
     * @return $this
     */
    public function setGuests($guests): self
    {
        $this->guests = $guests;
        return $this;
    }

    /**
     * Set access
     *
     * @param int $access
     * @return $this
     */
    public function setAccess($access): self
    {
        $this->access = $access;
        return $this;
    }

    /**
     * Get access
     *
     * @return null|int
     */
    public function getAccess(): ?int
    {
        return $this->access;
    }

    /**
     * Set all day
     *
     * @param int $allDay
     * @return $this
     */
    public function setAllDay($allDay): self
    {
        $this->allDay = (int)$allDay;
        return $this;
    }

    /**
     * Get all day
     *
     * @return int
     */
    public function getAllDay(): int
    {
        return $this->allDay;
    }

    /**
     * Set attendance
     *
     * @param int $attendance
     * @return $this
     */
    public function setAttendance($attendance)
    {
        $this->attendance = $attendance;
        return $this;
    }

    /**
     * Get attendance
     *
     * @return int
     */
    public function getAttendance()
    {
        return $this->attendance;
    }

    /**
     * Set author
     *
     * @param User $author
     * @return $this
     */
    public function setAuthor(User $author): self
    {
        $this->author = $author;
        return $this;
    }

    /**
     * Get author
     *
     * @return User
     */
    public function getAuthor(): User
    {
        return $this->author;
    }

    /**
     * Set category
     *
     * @param Folder $folder
     * @return $this
     */
    public function setCategory(Folder $folder): self
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * Get category
     *
     * @return Folder|null
     */
    public function getCategory(): ?Folder
    {
        return $this->folder;
    }

    /**
     * Set colour
     *
     * @deprecated
     * @param string $colour
     * @return $this
     */
    public function setColour($colour): self
    {
        $this->colour = $colour;
        return $this;
    }

    /**
     * Get colour
     *
     * @deprecated
     * @return string
     */
    public function getColour(): string
    {
        return $this->colour;
    }

    /**
     * Set completed
     *
     * @param int $completed
     * @return $this
     */
    public function setCompleted(int $completed): self
    {
        $this->completed = $completed;
        return $this;
    }

    /**
     * Get completed
     *
     * @return int
     */
    public function getCompleted(): int
    {
        return $this->completed;
    }

    /**
     * Set detail
     *
     * @param string|null $detail
     * @return $this
     */
    public function setDetail(?string $detail): self
    {
        $this->detail = $detail;
        return $this;
    }

    /**
     * Get detail
     *
     * @return string|null
     */
    public function getDetail(): ?string
    {
        return $this->detail;
    }

    /**
     * Set end datetime
     *
     * @param Alaress_Date_Format $endDateTime
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setEndDateTime($endDateTime): self
    {
        // enforce casting to ADF
        if ($endDateTime instanceof DateTime) {
            $endDateTime = new Alaress_Date_Format($endDateTime);
        }
        $this->endDateTime = $endDateTime;
        return $this;
    }

    /**
     * Get end datetime
     *
     * @return Alaress_Date_Format|null
     */
    public function getEndDateTime(): ?Alaress_Date_Format
    {
        return $this->endDateTime;
    }

    /**
     * Set id
     *
     * @param int|null $id
     * @return $this
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get id
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set location
     *
     * @param string|null $location
     * @return $this
     */
    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    /**
     * Get location
     *
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * Set title
     *
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set owner
     *
     * @param User $owner
     * @return $this
     */
    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * Get owner
     *
     * @return User
     */
    public function getOwner(): User
    {
        return $this->owner;
    }

    /**
     * Set series id
     *
     * @param int|null $seriesId
     * @return $this
     */
    public function setSeriesId(?int $seriesId): self
    {
        $this->seriesId = $seriesId;
        return $this;
    }

    /**
     * Get series id
     *
     * @return int|null
     */
    public function getSeriesId(): ?int
    {
        return $this->seriesId;
    }

    /**
     * This event is one instance in a series
     *
     * @return bool
     */
    public function inSeries(): bool
    {
        return (bool)$this->seriesId;
    }

    /**
     * Set start datetime
     *
     * @param Alaress_Date_Format $startDateTime
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setStartDateTime($startDateTime): self
    {
        // enforce casting to ADF
        if ($startDateTime instanceof DateTime) {
            $startDateTime = new Alaress_Date_Format($startDateTime);
        }
        $this->startDateTime = $startDateTime;
        return $this;
    }

    /**
     * Get start datetime
     *
     * @return Alaress_Date_Format|null
     */
    public function getStartDateTime(): ?Alaress_Date_Format
    {
        return $this->startDateTime;
    }

    /**
     * Set type
     *
     * @param EventType $type
     * @return $this
     */
    public function setType(EventType $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return EventType
     */
    public function getType(): EventType
    {
        return $this->type;
    }


    /**
     * @return string
     */
    public function getVariant(): string
    {
        return $this->variant;
    }


    /**
     * @param string|null $variant
     * @return $this
     * @throws OutOfBoundsException if `$class` isn't a `self::VARIANT_*` value
     */
    public function setVariant(?string $variant): self
    {
        // Why doesn't PHP have enumerated types yet?
        if (!in_array($variant, [self::VARIANT_EVENT, self::VARIANT_TASK], true)) {
            throw new OutOfBoundsException(
                sprintf('Class must be one of the `%s::VARIANT_*` constants', __CLASS__)
            );
        }
        $this->variant = $variant;
        return $this;
    }


    /**
     * Set end date
     *
     * @param string $endDate in the format Y-m-d
     * @return $this
     */
    public function setEndDate(string $endDate): self
    {
        // preserve time if already set
        [$year, $month, $day] = explode('-', $endDate);
        $this->getEndDateTime()->setDate($year, $month, $day);

        return $this;
    }


    /**
     * Set end time
     *
     * @param string $endTime in the format H:i:s
     * @return $this
     */
    public function setEndTime(string $endTime): self
    {
        // preserve date if already set
        [$hour, $min, $sec] = explode(':', $endTime);
        $this->getEndDateTime()->setTime($hour, $min, $sec);

        return $this;
    }


    /**
     * Set start date
     *
     * @param string $startDate
     * @return $this
     */
    public function setStartDate(string $startDate): self
    {
        // preserve time if set
        [$year, $month, $day] = explode('-', $startDate);
        $this->getStartDateTime()->setDate($year, $month, $day);
        return $this;
    }

    /**
     * Set start time
     *
     * @param string $startTime
     * @return $this
     */
    public function setStartTime(string $startTime): self
    {
        [$hour, $min, $sec] = explode(':', $startTime);
        $this->getStartDateTime()->setTime($hour, $min, $sec);
        return $this;
    }

    /**
     * Set publish datetime
     *
     * @param Alaress_Date_Format|null $publishDateTime
     * @return $this
     */
    public function setPublishDateTime(?Alaress_Date_Format $publishDateTime): self
    {
        $this->publishDateTime = $publishDateTime;
        return $this;
    }

    /**
     * Get publish datetime
     *
     * @return Alaress_Date_Format|null
     */
    public function getPublishDateTime(): ?Alaress_Date_Format
    {
        return $this->publishDateTime;
    }

    /**
     * Set campus collection
     *
     * @param Collection|int[]|Campus[]|Relation\Campus[] $campusCollection
     * @return $this
     */
    public function setCampusCollection($campusCollection): self
    {
        $this->campusCollection = $campusCollection;
        return $this;
    }

    /**
     * Get campus collection
     *
     * @return Collection|int[]|Campus[]|Relation\Campus[]
     */
    public function getCampusCollection()
    {
        return $this->campusCollection;
    }

    /**
     * Set role collection
     *
     * @param Collection|Relation\Role[] $roleCollection
     * @return $this
     */
    public function setRoleCollection($roleCollection): self
    {
        $this->roleCollection = $roleCollection;
        return $this;
    }

    /**
     * Get role collection
     *
     * @return Collection|Relation\Role[]
     */
    public function getRoleCollection()
    {
        return $this->roleCollection;
    }

    /**
     * Set attendance dirty bit
     *
     * @param bool $attendanceDirty
     * @return $this
     */
    public function setAttendanceDirty(bool $attendanceDirty): self
    {
        $this->attendanceDirty = $attendanceDirty;
        return $this;
    }

    /**
     * Get attendance dirty bit
     *
     * @return bool
     */
    public function getAttendanceDirty(): bool
    {
        return $this->attendanceDirty;
    }

    /**
     * Set folder collection
     *
     * @param Collection $folderCollection
     * @return $this
     */
    public function setFolderCollection(Collection $folderCollection): self
    {
        $this->folderCollection = $folderCollection;
        return $this;
    }

    /**
     * Get folder collection
     *
     * @return Collection
     */
    public function getFolderCollection(): Collection
    {
        return $this->folderCollection;
    }

    /**
     * Set user collection
     *
     * @param Collection|Relation\Personal[] $userCollection
     * @return $this
     */
    public function setUserCollection($userCollection): self
    {
        $this->userCollection = $userCollection;
        return $this;
    }

    /**
     * Get user collection
     *
     * @return Collection|Relation\Personal[]
     */
    public function getUserCollection()
    {
        return $this->userCollection;
    }

    /**
     * Stub for event metadata. EOTC uses this, but base event's dont.
     *
     * TODO extricate EOTC events from regular events, and remove this
     *
     * @return null
     */
    public function getMetadata()
    {
        return null;
    }

    /**
     * @return string
     */
    public function getSource(): ?string
    {
        return $this->source;
    }


    /**
     * @param string $source
     * @return $this
     */
    public function setSource(?string $source): self
    {
        $this->source = $source;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getMaxAttendees(): ?int
    {
        return $this->maxAttendees;
    }


    /**
     * @param int|null $maxAttendees
     * @return $this
     */
    public function setMaxAttendees(?int $maxAttendees): self
    {
        $this->maxAttendees = $maxAttendees;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getShowAttendees(): ?bool
    {
        return $this->showAttendees;
    }


    /**
     * @param bool|null $showAttendees
     * @return $this;
     */
    public function setShowAttendees(?bool $showAttendees): self
    {
        $this->showAttendees = $showAttendees;
        return $this;
    }
}
