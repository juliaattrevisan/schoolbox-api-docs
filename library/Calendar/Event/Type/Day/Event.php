<?php

/**
 * Implementation of our standard day event in Schoolbox.
 * Note this events can be campus, group or personal events.
 *
 * Other event systems also use psuedo versions of this event timetable.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Day;

use Calendar\Event\Type\EventBase;
use Calendar\Event\Type\EventInterface;
use Schoolbox\GenericCollection;
use Schoolbox\ResourceBooking\Booking\Status\Model as StatusModel;
use Schoolbox\ResourceBooking\Booking\Model as ResourceBookingModel;

class Event extends EventBase
{
    /**
     * These constants define the levels of access an day event can be
     */
    const CAMPUS_ACCESS   = 3;
    const GROUP_ACCESS    = 2;
    const PERSONAL_ACCESS = 1;

    /**
     * Event location
     *
     * @var string
     */
    protected $location = '';

    /**
     * Day events have levels based on the above constants
     * Campus/School = 3
     * Group = 2
     * Personal = 1
     *
     * @var int
     */
    protected $access = -1;

    /**
     * Category which the event is related to
     * @var int
     */
    protected $categoryId = -1;

    /**
     * Name of category this event is related to
     * @var string
     */
    protected $categoryName = '';

    /**
     * CSV separated list of campus ids relevant to this event.
     *
     * @var string
     */
    protected $campusList = '';

    /**
     * Associated Groups (Type 2)
     *
     * @var null|GenericCollection
     */
    protected $categoryCollection = null;

    /**
     * Associated resource bookings
     *
     * @var null|GenericCollection<ResourceBookingModel>
     */
    protected ?GenericCollection $bookingCollection = null;

    /**
     * Whether or not this Task has been completed
     *
     * @var bool
     */
    protected $completed = false;

    /**
     * Wether this event is a task
     *
     * @var bool
     */
    protected $isTask = false;

    /**
     * Whether the event can be attended
     * Should be bool, but setting this to mixed to make phpstan happy
     * @var mixed
     */
    private $attendance = false;

    /**
     * URL path to further information or detail for event
     * @var string
     */
    protected $path;

    /**
     * @var int|null
     */
    protected $maxAttendees;

    /**
     * @var int|null
     */
    protected $numAttendees;

    /**
     * Whether the current user is invited to attend an event
     *
     * @var int|null
     */
    protected $attendanceStatus;

    /**
     * @var array<string, array<int, array{id: int, name: string, url: string}>>
     */
    private array $socsMusicParticipants = [
        'staff' => [],
        'students' => [],
    ];

    /**
     * Can someone attend this event
     *
     * @return bool
     */
    public function canAttend()
    {
        return $this->getAttendance();
    }

    /**
     * Event visibility; generates a CSS string
     *
     * @return string
     */
    public function getFilterClasses()
    {
        $eventLevel = '';

        if ($this->isCampusAccess()) {
            if (stristr($this->getCampusList(), ',')) {
                foreach (explode(',', $this->getCampusList()) as $campus) {
                    $eventLevel .= ' campus' . $campus;
                }
            } else {
                $eventLevel .= ' campus' . $this->getCampusList();
            }
        } else {
            if ($this->isGroupAccess()) {
                $allLevels = array();

                foreach ($this->getCategoryCollection() as $currentCategory) {
                    $allLevels[] = 'group' . $currentCategory->getCategoryId();
                }

                $eventLevel = implode(' ', $allLevels);
            } else {
                $eventLevel = 'personal';
            }
        }

        return $eventLevel;
    }

    /* Getters/Setters
     */

    /**
     * Set attendance
     *
     * @param mixed $attendance
     */
    public function setAttendance($attendance)
    {
        $this->attendance = $attendance;
    }

    /**
     * Get attendance
     *
     * @return mixed
     */
    public function getAttendance()
    {
        return $this->attendance;
    }

    /**
     * Is campus access or not
     *
     * @return bool
     */
    protected function isCampusAccess()
    {
        if ($this->getAccess() == self::CAMPUS_ACCESS) {
            return true;
        }

        return false;
    }

    /**
     * is group access or not
     *
     * @return bool
     */
    protected function isGroupAccess()
    {
        if ($this->getAccess() == self::GROUP_ACCESS) {
            return true;
        }

        return false;
    }

    //Getters and Setters

    /**
     * Set location
     *
     * @param string $location
     */
    public function setLocation($location)
    {
        $this->location = $location;
    }

    /**
     * Get location
     *
     * @return string|null
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set access
     *
     * @param int $access
     */
    public function setAccess($access)
    {
        $this->access = $access;
    }

    /**
     * Get access
     *
     * @return int
     */
    public function getAccess()
    {
        return $this->access;
    }

    /**
     * Set campus list, comma separated
     *
     * @param string $campusList
     */
    public function setCampusList($campusList)
    {
        $this->campusList = $campusList;
    }

    /**
     * Get campus list, comma separated
     *
     * @return string
     */
    public function getCampusList()
    {
        return $this->campusList;
    }

    /**
     * Set category id
     *
     * @param int $categoryId
     */
    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;
    }

    /**
     * Get category id
     *
     * @return int
     */
    public function getCategoryId()
    {
        return $this->categoryId;
    }

    /**
     * Set category name
     *
     * @param string $categoryName
     */
    public function setCategoryName($categoryName)
    {
        $this->categoryName = $categoryName;
    }

    /**
     * Get category name
     *
     * @return string
     */
    public function getCategoryName()
    {
        return $this->categoryName;
    }

    /**
     * Set completed
     *
     * @param bool $completed
     */
    public function setCompleted(bool $completed)
    {
        $this->completed = $completed;
    }

    /**
     * Get completed
     *
     * @return bool
     */
    public function getCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * Set category collection
     *
     * @param null|GenericCollection $categoryCollection
     */
    public function setCategoryCollection($categoryCollection)
    {
        $this->categoryCollection = $categoryCollection;
    }

    /**
     * Get category collection
     *
     * @return null|GenericCollection
     */
    public function getCategoryCollection()
    {
        return $this->categoryCollection;
    }

    /**
     * Set bookings collection
     *
     * @param GenericCollection<ResourceBookingModel> $bookingCollection
     */
    public function setBookingCollection(GenericCollection $bookingCollection): void
    {
        $this->bookingCollection = $bookingCollection;
    }

    /**
     * Get bookings collection
     *
     * @return null|GenericCollection<ResourceBookingModel>
     */
    public function getBookingCollection(): ?GenericCollection
    {
        return $this->bookingCollection;
    }

    /**
     * @param boolean $isTask
     */
    public function setIsTask($isTask)
    {
        $this->isTask = $isTask;
    }

    /**
     * @return boolean
     */
    public function isTask()
    {
        return $this->isTask;
    }

    /**
     * @return string
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
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
    public function setMaxAttendees(?int $maxAttendees): EventInterface
    {
        $this->maxAttendees = $maxAttendees;
        return $this;
    }

    /**
     * @param array<int, array{id: int, name: string, url: string}> $staff
     */
    public function setSocsMusicStaff(array $staff): void
    {
        $this->socsMusicParticipants['staff'] = $staff;
    }

    /**
     * @return array<int, array{id: int, name: string, url: string}>
     */
    public function getSocsMusicStaff(): array
    {
        return $this->socsMusicParticipants['staff'];
    }

    /**
     * @param array<int, array{id: int, name: string, url: string}> $students
     */
    public function setSocsMusicStudents(array $students): void
    {
        $this->socsMusicParticipants['students'] = $students;
    }

    /**
     * @return array<int, array{id: int, name: string, url: string}>
     */
    public function getSocsMusicStudents(): array
    {
        return $this->socsMusicParticipants['students'];
    }

    /**
     * @return string[]
     */
    protected function getRenderClasses(): array
    {
        return [
            self::RENDER_FORMAT_JSON => Renderer\Javascript::class,
            self::RENDER_FORMAT_VCAL => Renderer\VObject::class,
            self::RENDER_FORMAT_CSV  => Renderer\Csv::class,
        ];
    }

    /**
     * Determine if this event clashes with another event
     * if it's the same event, return false
     * if different resources, return false
     * if one is a timetable event, check if same category (class) and then times overlap
     * if either is a rejected booking, return false
     * if both are resource bookings, only care if one is pending
     * if both are resource bookings, skip if both pending or same booking id
     *
     * @param self $eventB
     * @return bool
     */
    public function isClash(self $eventB): bool
    {
        $eventA = $this;
        // cache start/end times and event hashes
        $aStart = $eventA->getStartTime();
        $aEnd = $eventA->getEndTime();
        $eventAHash = spl_object_hash($eventA);
        $eventBHash = spl_object_hash($eventB);

        // skip the same object...
        if ($eventAHash === $eventBHash) {
            return false;
        }

        // check this is for the same resource (assume nothing)
        $resourceIdA = ($eventA instanceof \Calendar\Event\Type\ResourceBooking\Event)
            ? $eventA->getBooking()->getResource()->getId()
            : $eventA->getResourceId();
        $resourceIdB = ($eventB instanceof \Calendar\Event\Type\ResourceBooking\Event)
            ? $eventB->getBooking()->getResource()->getId()
            : $eventB->getResourceId();
        if ($resourceIdA !== $resourceIdB) {
            return false;
        }

        // if either event is a rejected booking, skip
        if ($eventA instanceof \Calendar\Event\Type\ResourceBooking\Event) {
            if ($eventA->getBooking()->getStatus()->isRejected()) {
                return false;
            }
        }
        if ($eventB instanceof \Calendar\Event\Type\ResourceBooking\Event) {
            if ($eventB->getBooking()->getStatus()->isRejected()) {
                return false;
            }
        }

        // Only check for status of pending bookings if both are resource bookings
        if (
            $eventA instanceof \Calendar\Event\Type\ResourceBooking\Event &&
            $eventB instanceof \Calendar\Event\Type\ResourceBooking\Event
        ) {
            // skip if same booking
            if ($eventA->getBooking()->getId() === $eventB->getBooking()->getId()) {
                return false;
            }
            // only care if a booking is NOT pending, so we can see conflicts (that we need to action)
            // bookings that are both pending are not a clash
            if (
                $eventB->getBooking()->getStatus()->getId() === StatusModel::STATUS_PENDING &&
                $eventA->getBooking()->getStatus()->getId() === StatusModel::STATUS_PENDING
            ) {
                return false;
            }
        } elseif (
            $eventA instanceof \Calendar\Event\Type\Timetable\Event &&
            $eventB instanceof \Calendar\Event\Type\Timetable\Event
        ) {
            // This must be a timetable event
            // check if for the same homepage (class)
            if (
                ($eventA->getCategoryId() === $eventB->getCategoryId())
                && ($eventA->getCategoryId() !== -1 || $eventB->getCategoryId() !== -1)
            ) {
                return false;
            }
        }
        // Check for time overlap
        $bStart = $eventB->getStartTime();
        $bEnd = $eventB->getEndTime();
        if ($aStart->lt($bEnd) && $aEnd->gt($bStart)) {
            // Add both events to the clash list
            return true;
        } elseif ($bStart->gt($aEnd)) {
            // No overlap: event B starts after event A ends
            return false;
        }
        return false;
    }
}
