<?php
/**
 * Event
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;


use Alaress_Date_Format;
use DateTime;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\ISoftDeletable;

class Event implements ISoftDeletable
{

    public const ACCESS_PERSONAL = 1;
    public const ACCESS_GROUP = 2;
    public const ACCESS_CAMPUS = 3;

    public const ATTENDANCE_STATUS_PENDING = 1;
    public const ATTENDANCE_STATUS_APPROVED = 2;
    public const ATTENDANCE_STATUS_REJECTED = 3;

    public const ATTENDANCE_INTENTION_PENDING = 1;
    public const ATTENDANCE_INTENTION_PRESENT = 2;
    public const ATTENDANCE_INTENTION_ABSENT = 3;

    /**
     * Event id
     *
     * @var int
     */
    protected $id = null;

    /**
     * Series Id for recurring events, I think
     *
     * @var int
     */
    protected $seriesId = null;

    /**
     * An instance of \Schoolbox\User
     *
     * The person who spawned the event in the first place
     *
     * @var User
     */
    protected $author = null;

    /**
     * An instance of \Schoolbox\User
     *
     * This denotes to whom the event is relevant for a particular
     * combination of sid and access (I don't remember/understand which)
     *
     * @var User
     */
    protected $owner = null;

    /**
     * An instance of \Schoolbox\Resource\Folder\Model
     *
     * @var Folder
     */
    protected $category = null;

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
    protected $type = null;

    /**
     * Title of the event
     *
     * @var string
     */
    protected $note = '';

    /**
     * A description of the event
     *
     * @var string
     */
    protected $detail = '';

    /**
     * Where the event is being held
     *
     * @var string
     */
    protected $location = '';

    /**
     * Whether or not people are supposed to attend
     *
     * @var int
     */
    protected $attendance = 0;

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
    protected $roleCollection = null;

    /**
     * Any associated users (Type 1)
     *
     * @var null
     */
    protected $userCollection = null;

    /**
     * Any associated categories (Type 2)
     *
     * @var null
     */
    protected $categoryCollection = null;

    /**
     * Any associated campuses (Type 3)
     *
     * @var Collection|Campus[]
     */
    protected $campusCollection = null;

    /**
     * The date when the event was published (made visible to users)
     * An instance Alaress_Date_Format()
     *
     * @var Alaress_Date_Format
     */
    protected $publishDateTime = null;

    /**
     * The datetime when the event starts
     *
     * @var Alaress_Date_Format
     */
    protected $startDateTime = null;

    /**
     * The datetime when the event ends
     *
     * @var Alaress_Date_Format
     */
    protected $endDateTime = null;

    /**
     * Source of import
     *
     * @var string
     */
    protected $source;

    /**
     * Implement soft delete traits
     */
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setUserCollection(new Collection());
        $this->setCategoryCollection(new Collection());
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
    public function isPersonalLevel()
    {
        return $this->getAccess() == self::ACCESS_PERSONAL;
    }

    /**
     * Is this event a group-accesslevel instance?
     *
     * @return bool
     */
    public function isGroupLevel()
    {
        return $this->getAccess() == self::ACCESS_GROUP;
    }

    /**
     * Is this event a campus-accesslevel instance?
     *
     * @return bool
     */
    public function isCampusLevel()
    {
        return $this->getAccess() == self::ACCESS_CAMPUS;
    }

    /* Auxiliary methods which don't belong anywhere else
     */

    /**
     * Derive start and end dates from a date.
     *
     * @param string $date A string in either "d-m-Y" or "d-m-Y H:i" format.
     *
     * @param $date
     */
    public function setupDates($date)
    {
        if (preg_match(
            '/^(?P<d>\\d+)-(?P<m>\\d+)-(?P<y>\\d+)\\s+(?P<h>\\d+):(?P<i>\\d+)/',
            $date,
            $matches
        )
        ) {
            $year = $matches['y'];
            $month = $matches['m'];
            $day = $matches['d'];
            $hour = $matches['h'];
            $minute = $matches['i'];
            $this->setAllDay(false);
        } else {
            list($day, $month, $year) = explode("-", $date);
            $hour = 0;
            $minute = 0;
            $this->setAllDay(true);
        }
        $selectedDate = @mktime($hour, $minute, 0, $month, $day, $year);

        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $startDate = new Alaress_Date_Format($selectedDate, 'U');
        $endDate = new Alaress_Date_Format($selectedDate, 'U');

        $this->setStartDateTime($startDate);
        $this->setEndDateTime($endDate);
    }

    /**
     * TODO: move this. it doesn't belong here.
     *
     * Ensure that the user hasn't modified the URL in an untoward manner.
     * If they have, or it's somehow absent, default to week view.
     *
     * Really, the redirect should be passed as a base-64 encoded paramter
     * (or similar) rather than having to implicitly "guess" where to go next
     *
     * @param string $returnView
     * @param string $returnDate
     * @param int $groupId
     * @param int $userId
     *
     * @return string
     * @internal param $returnTo
     */
    public function setupRedirect($returnView, $returnDate = "", $groupId = null, $userId = null)
    {
        if ($returnView === 'dashboard') {
            return '/';
        } else if ($returnView === 'upcomingEventAttendance') {
            return '/calendar/attendance/upcomingEvents/';
        } else if ($returnView === 'homepage') {
            return '/homepage/' . $groupId;
        } else if ($returnView === 'assessmentCalendar') {
            return '/learning/assessment/calendar/';
        } else {
            if ($returnView === 'calendar') {
                $return = "/calendar/$returnDate";

                if ($userId) {
                    $return .= '/' . $userId;
                }
                return $return;
            }
            elseif ($returnView === 'list') {
                $return = "/calendar/list";
                if ($userId) {
                    $return .= '/'.$userId;
                }
                if ($returnDate) {
                    $return .= '/'.$returnDate;
                }
                return $return;
            }
        }
        //Assuming the complete url has been passed over
        if(strlen(urldecode($returnView)) > 0){
            return urldecode($returnView);
        }
        // Otherwise - guess using Back to the future!
        return '';
    }

    /* Getters/Setters
     */

    /**
     * Set access
     *
     * @param int $access
     * @return self
     */
    public function setAccess($access)
    {
        $this->access = $access;
        return $this;
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
     * Set all day
     *
     * @param bool|int $allDay
     * @return self
     */
    public function setAllDay($allDay)
    {
        $this->allDay = (int)$allDay;
        return $this;
    }

    /**
     * Get all day
     *
     * @return int
     */
    public function getAllDay()
    {
        return $this->allDay;
    }

    /**
     * Set attendance
     *
     * @param int $attendance
     * @return self
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
     * @return self
     */
    public function setAuthor($author)
    {
        $this->author = $author;
        return $this;
    }

    /**
     * Get author
     *
     * @return User
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Set category
     *
     * @param Folder $category
     * @return self
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Get category
     *
     * @return Folder
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set colour
     *
     * @deprecated
     * @param string $colour
     * @return self
     */
    public function setColour($colour)
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
    public function getColour()
    {
        return $this->colour;
    }

    /**
     * Set completed
     *
     * @param bool|int $completed
     * @return self
     */
    public function setCompleted($completed)
    {
        $this->completed = (int)$completed;
        return $this;
    }

    /**
     * Get completed
     *
     * @return int
     */
    public function getCompleted()
    {
        return $this->completed;
    }

    /**
     * Set detail
     *
     * @param string $detail
     * @return self
     */
    public function setDetail($detail)
    {
        $this->detail = $detail;
        return $this;
    }

    /**
     * Get detail
     *
     * @return string
     */
    public function getDetail()
    {
        return $this->detail;
    }

    /**
     * Set end datetime
     *
     * @param Alaress_Date_Format $endDateTime
     * @return self
     */
    public function setEndDateTime($endDateTime)
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
     * @return Alaress_Date_Format
     */
    public function getEndDateTime()
    {
        return $this->endDateTime;
    }

    /**
     * Set id
     *
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set location
     *
     * @param string $location
     * @return self
     */
    public function setLocation($location)
    {
        $this->location = $location;
        return $this;
    }

    /**
     * Get location
     *
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set title
     *
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set owner
     *
     * @param User $owner
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * Get owner
     *
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Set series id
     *
     * @param int $seriesId
     * @return self
     */
    public function setSeriesId($seriesId)
    {
        $this->seriesId = $seriesId;
        return $this;
    }

    /**
     * Get series id
     *
     * @return int
     */
    public function getSeriesId()
    {
        return $this->seriesId;
    }

    public function inSeries()
    {
        return !!$this->seriesId;
    }

    /**
     * Set start datetime
     *
     * @param Alaress_Date_Format $startDateTime
     * @return self
     */
    public function setStartDateTime($startDateTime)
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
     * @return Alaress_Date_Format
     */
    public function getStartDateTime()
    {
        return $this->startDateTime;
    }

    /**
     * Set type
     *
     * @param EventType $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return EventType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set end date
     *
     * @param string $endDate in the format Y-m-d
     * @return self
     */
    public function setEndDate($endDate)
    {
        // preserve time if already set
        list($year, $month, $day) = explode('-',$endDate);
        $this->getEndDateTime()->setDate($year,$month,$day);

        return $this;
    }


    /**
     * Set end time
     *
     * @param string $endTime in the format H:i:s
     * @return self
     */
    public function setEndTime($endTime)
    {
        // preserve date if already set
        list($hour, $min, $sec) = explode(':',$endTime);
        $this->getEndDateTime()->setTime($hour,$min,$sec);

        return $this;
    }


    /**
     * Set start date
     *
     * @param string $startDate
     * @return self
     */
    public function setStartDate($startDate)
    {
        // preserve time if set
        list($year, $month, $day) = explode('-',$startDate);
        $this->getStartDateTime()->setDate($year,$month,$day);
        return $this;
    }

    /**
     * Set start time
     *
     * @param string $startTime
     * @return self
     *
     */
    public function setStartTime($startTime)
    {
        list($hour, $min, $sec) = explode(':',$startTime);
        $this->getStartDateTime()->setTime($hour,$min,$sec);
        return $this;
    }

    /**
     * Set publish datetime
     *
     * @param Alaress_Date_Format|null $publishDateTime
     * @return self
     */
    public function setPublishDateTime($publishDateTime)
    {
        $this->publishDateTime = $publishDateTime;
        return $this;
    }

    /**
     * Get publish datetime
     *
     * @return Alaress_Date_Format|null
     */
    public function getPublishDateTime()
    {
        return $this->publishDateTime;
    }

    /**
     * Set campus collection
     *
     * @param Collection|Relation\Campus[] $campusCollection
     * @return self
     */
    public function setCampusCollection($campusCollection)
    {
        $this->campusCollection = $campusCollection;
        return $this;
    }

    /**
     * Get campus collection
     *
     * @return Collection|Relation\Campus[]
     */
    public function getCampusCollection()
    {
        return $this->campusCollection;
    }

    /**
     * Set role collection
     *
     * @param Collection|Relation\Role[] $roleCollection
     * @return self
     */
    public function setRoleCollection($roleCollection)
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
     * @param boolean $attendanceDirty
     * @return self
     */
    public function setAttendanceDirty($attendanceDirty)
    {
        $this->attendanceDirty = $attendanceDirty;
        return $this;
    }

    /**
     * Get attendance dirty bit
     *
     * @return boolean
     */
    public function getAttendanceDirty()
    {
        return $this->attendanceDirty;
    }

    /**
     * Set category collection
     *
     * @param Collection|Relation\Category[] $categoryCollection
     * @return $this
     */
    public function setCategoryCollection($categoryCollection)
    {
        $this->categoryCollection = $categoryCollection;
        return $this;
    }

    /**
     * Get category collection
     *
     * @return Collection|Relation\Category[]
     */
    public function getCategoryCollection()
    {
        return $this->categoryCollection;
    }

    /**
     * Set user collection
     *
     * @param Collection|Relation\Personal[] $userCollection
     */
    public function setUserCollection($userCollection)
    {
        $this->userCollection = $userCollection;
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
     */
    public function setSource(string $source = null)
    {
        $this->source = $source;
    }

}
