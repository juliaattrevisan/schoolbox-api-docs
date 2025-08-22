<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Report;

use Alaress_Date_Format;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\ReportBuilder\Factory;
use Schoolbox\Pastoral\SubType as PastoralSubType;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Tag as PastoralTag;
use Schoolbox\Pastoral\Type as PastoralType;
use Schoolbox\Pastoral\Action\Model as Action;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use House;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * Key to represent Absolute Dates being used to generate a pastoral report
     */
    public const ABSOLUTE_DATE = 'absolute';

    public const ORDER_STUDENT     = 'student';
    public const ORDER_SEVERITY    = 'severity';
    public const ORDER_CREATED_AT  = 'date';
    public const ORDER_OCCURRED_ON = 'occurredOn';
    public const ORDER_TYPE        = 'type';
    public const ORDER_SUBTYPE     = 'subtype';
    public const ORDER_POINTS      = 'points';
    public const ORDER_YEAR        = 'year';
    public const ORDER_AUTHOR      = 'author';
    public const ORDER_HOUSE       = 'house';
    public const ORDER_CAMPUS      = 'campus';

    public const ORDER_FIELDS = [
        self::ORDER_CREATED_AT  => 'Created Date',
        self::ORDER_OCCURRED_ON => 'Occurred On',
        self::ORDER_STUDENT     => 'Student Name',
        self::ORDER_TYPE        => 'Type',
        self::ORDER_SUBTYPE     => 'Sub-Type',
        self::ORDER_AUTHOR      => 'Author Name',
        self::ORDER_POINTS      => 'Points',
        self::ORDER_YEAR        => 'Year Level',
        self::ORDER_HOUSE       => 'House',
        self::ORDER_CAMPUS      => 'Campus',
    ];

    public const ACTION_STATUS_PENDING   = 'pending';
    public const ACTION_STATUS_COMPLETED = 'completed';

    // Strings that can be converted to actual dates using strtotime
    public const THIS_YEAR        = 'first day of january';
    public const THIS_SCHOOL_YEAR = 'first day of school year';
    public const PAST_FORTNIGHT   = '14 days ago';
    public const THIS_TERM        = 'this term'; // this is the only one that isn't a strtotime string
    public const TODAY            = 'today';
    public const PAST_6_MONTHS    = '6 months ago';
    public const PAST_3_MONTHS    = '3 months ago';
    public const PAST_MONTH       = '1 month ago';
    public const PAST_WEEK        = '7 days ago';

    // Mappings of strings to labels that will be shown in the UI
    public const RELATIVE_DATE_PRESETS = [
        self::THIS_YEAR        => 'This year',
        self::THIS_SCHOOL_YEAR => 'This school year',
        self::THIS_TERM        => 'This term',
        self::PAST_6_MONTHS    => 'Past 6 months',
        self::PAST_3_MONTHS    => 'Past 3 months',
        self::PAST_MONTH       => 'Past month',
        self::PAST_FORTNIGHT   => 'Past fortnight',
        self::PAST_WEEK        => 'Past week',
        self::TODAY            => 'Today',
    ];

    // Mapping for the summary table options
    public const SUMMARY_TABLES_OPTIONS = [
        Factory::STUDENT_BY_TYPE => [
            'label' => 'Student by Type/Sub-Type',
            'type' => 'table',
        ],
        //TODO: future updates for SA-19333
        Factory::TYPE_BY_MONTH => [
            'label' => 'Type/Sub-Type by Month',
            'tooltip' => 'This table will display a maximum of 12 months of past data, counting back from the most recent record\'s date',
            'type' => 'table',
        ],
        Factory::TYPE_BY_WEEK => [
            'label' => 'Type/Sub-Type by Week',
            'tooltip' => 'This table will display a maximum of 12 weeks of past data, counting back from the most recent record\'s date',
            'type' => 'table',
        ],
        Factory::STUDENT_BY_MONTH => [
            'label' => 'Student by Month',
            'tooltip' => 'This table will display a maximum of 12 months of past data, counting back from the most recent record\'s date',
            'type' => 'table',
        ],
        Factory::STUDENT_BY_WEEK => [
            'label' => 'Student by Week',
            'tooltip' => 'This table will display a maximum of 12 weeks of past data, counting back from the most recent record\'s date',
            'type' => 'table',
        ],
    ];

    /** @var bool $dashboard */
    protected bool $dashboard = false;

    /** @var string $name */
    private string $name = '';

    /** @var null|string $description */
    private ?string $description;

    /** @var GenericCollection $years */
    private GenericCollection $years;

    /**
     * Only show records in which the user is the author or an audience member
     *
     * @var bool $involved
     */
    private bool $involved = false;

    /** @var bool $usesRelativeDate */
    private bool $usesRelativeDate = true;

    /** @var string|null $relativeDateString */
    private ?string $relativeDateString = null;

    /**
     * Optional. Can be Null.
     *
     * @var null|Alaress_Date_Format $afterDate
     */
    private ?Alaress_Date_Format $afterDate = null;

    /**
     * Optional. Can be Null.
     *
     * @var null|Alaress_Date_Format $beforeDate
     */
    private ?Alaress_Date_Format $beforeDate = null;

    /** @var User $owner */
    private User $owner;

    private bool $includeArchivedRecords = false;

    // These are the filters stored as CSV strings

    /**
     * Ids of Lookup list 'pastoral types'
     *
     * @var string|null $typesCsv
     */
    private ?string $typesCsv;

    /** @var string|null $subTypesCsv */
    private ?string $subTypesCsv;

    /** @var string|null $housesCsv */
    private ?string $housesCsv;

    /** @var string|null $campusesCsv */
    private ?string $campusesCsv;

    /** Folders CSV */
    private ?string $foldersCsv;

    /** Actions CSV */
    private ?string $actionsCsv = null;

    /** @var string|null $authorsCsv */
    private ?string $authorsCsv;

    /** @var string|null $studentsCsv */
    private ?string $studentsCsv;

    /** @var string|null $tagsCsv */
    private ?string $tagsCsv;

    /** @var string|null $orderCsv */
    private ?string $orderCsv;

    /** @var string|null $yearsCsv */
    private ?string $yearsCsv;

    // These are the collections that get hydrated from the CSV values above

    /** @var Collection $order */
    private Collection $order;

    /** @var GenericCollection $tags */
    private GenericCollection $tags;

    /** @var GenericCollection $students */
    private GenericCollection $students;

    /** @var GenericCollection $authors */
    private GenericCollection $authors;

    /** @var GenericCollection $types */
    private GenericCollection $types;

    /** @var GenericCollection $subTypes */
    private GenericCollection $subTypes;

    /** @var GenericCollection $houses */
    private GenericCollection $houses;

    /** @var GenericCollection $campuses */
    private GenericCollection $campuses;

    /** Folders */
    private GenericCollection $folders;

    /** Actions */
    private GenericCollection $actions;

    /** Summary Tables */
    private ?string $summaryTables = null;

    /**
     * Will be null if severity is turned off in admin config
     *
     * @var null|PastoralSeverity $severity
     */
    private ?PastoralSeverity $severity = null;

    /** @var GenericCollection $access */
    private GenericCollection $access;

    /**
     * Are students without a year level included in this report?
     *
     * @var bool
     */
    private bool $includeStudentsWithoutYear = false;


    /**
     * Are students without a house included in this report?
     *
     * @var bool
     */
    private bool $includeStudentsWithoutHouse = false;

    /**
     * Are records without an action included in this report?
     *
     * @var bool
     */
    private bool $includeRecordsWithoutAction = false;

    /**
     * Are completed actions excluded in this report?
     *
     * @var string|null
     */
    private ?string $actionStatus = null;

    private bool $notificationEnabled = false;

    /**
     * Model constructor
     */
    public function __construct()
    {
        $this->students = new GenericCollection(User::class);
        $this->authors  = new GenericCollection(User::class);
        $this->order    = new Collection();
        $this->tags     = new GenericCollection(PastoralTag\Model::class);
        $this->types    = new GenericCollection(PastoralType\Model::class);
        $this->subTypes = new GenericCollection(PastoralSubType\Model::class);
        $this->houses   = new GenericCollection(House::class);
        $this->campuses = new GenericCollection(Campus::class);
        $this->folders  = new GenericCollection(Folder::class);
        $this->actions  = new GenericCollection(Action::class);
        $this->years    = new GenericCollection(\Schoolbox\YearLevel\Model::class);

        // This is a collection of groups that have access to this report
        $this->access = new GenericCollection(Access\Model::class);
    }

    /**
     * @param PastoralSeverity|null $value
     *
     * @return static
     */
    public function setSeverity(?PastoralSeverity $value = null): self
    {
        $this->severity = $value;

        return $this;
    }

    /**
     * @return null|PastoralSeverity
     */
    public function getSeverity(): ?PastoralSeverity
    {
        return $this->severity;
    }

    /**
     * @param string|null $value
     *
     * @return static
     */
    public function setTypesCsv(?string $value): self
    {
        $this->typesCsv = $value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTypesCsv(): ?string
    {
        return $this->typesCsv;
    }

    /**
     * @param null|string $subTypesCsv
     * @return static
     */
    public function setSubTypesCsv(?string $subTypesCsv): self
    {
        $this->subTypesCsv = $subTypesCsv;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getSubTypesCsv(): ?string
    {
        return $this->subTypesCsv;
    }

    /**
     * @param null|string $housesCsv
     * @return static
     */
    public function setHousesCsv(?string $housesCsv): self
    {
        $this->housesCsv = $housesCsv;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getHousesCsv(): ?string
    {
        return $this->housesCsv;
    }

    /**
     * @param null|string $campusesCsv
     * @return static
     */
    public function setCampusesCsv(?string $campusesCsv): self
    {
        $this->campusesCsv = $campusesCsv;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getCampusesCsv(): ?string
    {
        return $this->campusesCsv;
    }

    /**
     * @param null|string $actionsCsv
     * @return static
     */
    public function setActionsCsv(?string $actionsCsv): self
    {
        $this->actionsCsv = $actionsCsv;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getActionsCsv(): ?string
    {
        return $this->actionsCsv;
    }

    /**
     * @param string|null $value
     *
     * @return static
     */
    public function setAuthorsCsv(?string $value): self
    {
        $this->authorsCsv = $value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAuthorsCsv(): ?string
    {
        return $this->authorsCsv;
    }

    /**
     * @param string|null $value
     *
     * @return static
     */
    public function setStudentsCsv(?string $value): self
    {
        $this->studentsCsv = $value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getStudentsCsv(): ?string
    {
        return $this->studentsCsv;
    }

    /**
     * @param string|null $value
     *
     * @return static
     */
    public function setTagsCsv(?string $value): self
    {
        $this->tagsCsv = $value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTagsCsv(): ?string
    {
        return $this->tagsCsv;
    }

    /**
     * @param string|null $value
     *
     * @return static
     */
    public function setOrderCsv(?string $value): self
    {
        $this->orderCsv = $value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getOrderCsv(): ?string
    {
        return $this->orderCsv;
    }

    /**
     * @param string|null $value
     *
     * @return static
     */
    public function setYearsCsv(?string $value): self
    {
        $this->yearsCsv = $value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getYearsCsv(): ?string
    {
        return $this->yearsCsv;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string|null $description
     *
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param User $owner
     *
     * @return self
     */
    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return User
     */
    public function getOwner(): User
    {
        return $this->owner;
    }

    /**
     * @param bool $usesRelativeDate
     * @return self
     */
    public function setUsesRelativeDate(bool $usesRelativeDate): self
    {
        $this->usesRelativeDate = $usesRelativeDate;

        return $this;
    }

    /**
     * @return null|bool
     */
    public function getUsesRelativeDate(): ?bool
    {
        return $this->usesRelativeDate;
    }

    /**
     * @param string|null $relativeDateString
     * @return self
     */
    public function setRelativeDateString(?string $relativeDateString = null): self
    {
        $this->relativeDateString = $relativeDateString;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRelativeDateString(): ?string
    {
        return $this->relativeDateString;
    }

    /**
     * @param Alaress_Date_Format|null $beforeDate
     *
     * @return self
     */
    public function setBeforeDate(?Alaress_Date_Format $beforeDate = null): self
    {
        $this->beforeDate = $beforeDate;

        return $this;
    }

    /**
     * @return null|Alaress_Date_Format
     */
    public function getBeforeDate(): ?Alaress_Date_Format
    {
        return $this->beforeDate;
    }

    /**
     * @param Alaress_Date_Format|null $afterDate
     *
     * @return self
     */
    public function setAfterDate(?Alaress_Date_Format $afterDate = null): self
    {
        $this->afterDate = $afterDate;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIncludeArchivedRecords(): bool
    {
        return $this->includeArchivedRecords;
    }

    /**
     * @param bool $includeArchivedRecords
     * @return static
     */
    public function setIncludeArchivedRecords(bool $includeArchivedRecords): self
    {
        $this->includeArchivedRecords = $includeArchivedRecords;
        return $this;
    }

    /**
     * @return null|Alaress_Date_Format
     */
    public function getAfterDate(): ?Alaress_Date_Format
    {
        return $this->afterDate;
    }

    /**
     * @param GenericCollection $types
     *
     * @return self
     */
    public function setTypes(GenericCollection $types): self
    {
        $this->types = $types;

        return $this;
    }

    /**
     * @return GenericCollection
     */
    public function getTypes(): GenericCollection
    {
        return $this->types;
    }

    /**
     * @param GenericCollection $subTypes
     *
     * @return self
     */
    public function setSubTypes(GenericCollection $subTypes): self
    {
        $this->subTypes = $subTypes;

        return $this;
    }

    /**
     * @return null|GenericCollection
     */
    public function getSubTypes(): ?GenericCollection
    {
        return $this->subTypes;
    }

    /**
     * @param GenericCollection $houses
     * @return static
     */
    public function setHouses(GenericCollection $houses): self
    {
        $this->houses = $houses;
        return $this;
    }

    /**
     * @return null|GenericCollection
     */
    public function getHouses(): ?GenericCollection
    {
        return $this->houses;
    }

    /**
     * @param GenericCollection $campuses
     * @return static
     */
    public function setCampuses(GenericCollection $campuses): self
    {
        $this->campuses = $campuses;
        return $this;
    }

    /**
     * @return null|GenericCollection
     */
    public function getActions(): ?GenericCollection
    {
        return $this->actions;
    }

    /**
     * @param GenericCollection $actions
     * @return static
     */
    public function setActions(GenericCollection $actions): self
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * @return null|GenericCollection
     */
    public function getCampuses(): ?GenericCollection
    {
        return $this->campuses;
    }

    /**
     * @param GenericCollection $authors
     *
     * @return self
     */
    public function setAuthors(GenericCollection $authors): self
    {
        $this->authors = $authors;

        return $this;
    }

    /**
     * @return GenericCollection
     */
    public function getAuthors(): GenericCollection
    {
        return $this->authors;
    }

    /**
     * @param GenericCollection $students
     *
     * @return self
     */
    public function setStudents(GenericCollection $students): self
    {
        $this->students = $students;

        return $this;
    }

    /**
     * @return GenericCollection
     */
    public function getStudents(): GenericCollection
    {
        return $this->students;
    }

    /**
     * @param GenericCollection $tags
     *
     * @return self
     */
    public function setTags(GenericCollection $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return GenericCollection
     */
    public function getTags(): GenericCollection
    {
        return $this->tags;
    }

    /**
     * @param Collection $order
     *
     * @return self
     */
    public function setOrder(Collection $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getOrder(): Collection
    {
        return $this->order;
    }

    /**
     * @param bool $involved
     *
     * @return self
     */
    public function setInvolved(bool $involved): self
    {
        $this->involved = $involved;

        return $this;
    }

    /**
     * @return bool
     */
    public function getInvolved(): bool
    {
        return $this->involved;
    }

    /**
     * @param GenericCollection $years
     *
     * @return self
     */
    public function setYears(GenericCollection $years): self
    {
        $this->years = $years;

        return $this;
    }

    /**
     * @return GenericCollection
     */
    public function getYears(): GenericCollection
    {
        return $this->years;
    }

    /**
     * @param GenericCollection $access
     *
     * @return self
     */
    public function setAccess(GenericCollection $access): self
    {
        $this->access = $access;

        return $this;
    }

    /**
     * @return GenericCollection
     */
    public function getAccess(): GenericCollection
    {
        return $this->access;
    }

    /**
     * @param bool $dashboard
     *
     * @return self
     */
    public function setDashboard(bool $dashboard): self
    {
        $this->dashboard = $dashboard;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDashboard(): bool
    {
        return $this->dashboard;
    }


    /**
     * @return bool
     */
    public function getIncludeStudentsWithoutYear(): bool
    {
        return $this->includeStudentsWithoutYear;
    }


    /**
     * @param bool $includeStudentsWithoutYear
     * @return static
     */
    public function setIncludeStudentsWithoutYear(bool $includeStudentsWithoutYear): self
    {
        $this->includeStudentsWithoutYear = $includeStudentsWithoutYear;
        return $this;
    }


    /**
     * @return bool
     */
    public function getIncludeStudentsWithoutHouse(): bool
    {
        return $this->includeStudentsWithoutHouse;
    }


    /**
     * @param bool $includeStudentsWithoutHouse
     * @return static
     */
    public function setIncludeStudentsWithoutHouse(bool $includeStudentsWithoutHouse): self
    {
        $this->includeStudentsWithoutHouse = $includeStudentsWithoutHouse;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIncludeRecordsWithoutAction(): bool
    {
        return $this->includeRecordsWithoutAction;
    }

    /**
     * @param bool $includeRecordsWithoutAction
     * @return static
     */
    public function setIncludeRecordsWithoutAction(bool $includeRecordsWithoutAction): self
    {
        $this->includeRecordsWithoutAction = $includeRecordsWithoutAction;
        return $this;
    }

    /**
     * @param string|null $actionStatus
     * @return static
     */
    public function setActionStatus(?string $actionStatus): self
    {
        $this->actionStatus = $actionStatus;
        return $this;
    }

    /**
     * @return ?string
     */
    public function getActionStatus(): string|null
    {
        return $this->actionStatus;
    }

    /**
     * @return bool
     */
    public function getNotificationEnabled(): bool
    {
        return $this->notificationEnabled;
    }

    /**
     * @param bool $notificationEnabled
     * @return static
     */
    public function setNotificationEnabled(bool $notificationEnabled): self
    {
        $this->notificationEnabled = $notificationEnabled;
        return $this;
    }

    /**
     * Whether the given user can edit this report
     * @param User $user
     * @return bool
     */
    public function getCanEdit(User $user): bool
    {
        return in_array($user->getId(), $this->getUserIds(\Schoolbox\Pastoral\Report\Access\Model::WRITE));
    }

    /**
     * @param int<1,2> $accessLevel least required level
     * @return array
     */
    public function getUserIds(int $accessLevel): array
    {
        $userIds  = [];
        $accesses = $this->getAccess();
        /** @var Access\Model $access */
        foreach ($accesses as $access) {
            if ($accessLevel > $access->getAccess()) {
                continue;
            }
            if ($access->getGroup()) {
                $memberIds = $access->getGroup()->getMembers()->map(function ($member) {
                    return $member->getUser()->getId();
                })->getAll();
                $userIds   = array_unique(array_merge($userIds, $memberIds));
            }
            if ($access->getUser() && !in_array($access->getUser()->getId(), $userIds)) {
                $userIds[] = $access->getUser()->getId();
            }
        }
        if (!$this->getOwner()->isDeleted() && !in_array($this->getOwner()->getId(), $userIds)) {
            $userIds[] = $this->getOwner()->getId();
        }
        return $userIds;
    }

    /**
     * @return GenericCollection
     */
    public function getFolders(): GenericCollection
    {
        return $this->folders;
    }

    /**
     * @param GenericCollection $folders
     * @return static
     */
    public function setFolders(GenericCollection $folders): self
    {
        $this->folders = $folders;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFoldersCsv(): ?string
    {
        return $this->foldersCsv;
    }

    /**
     * @param string|null $foldersCsv
     * @return static
     */
    public function setFoldersCsv(?string $foldersCsv): self
    {
        $this->foldersCsv = $foldersCsv;
        return $this;
    }

    /**
     * @param string|null $summaryTables
     * @return $this
     */
    public function setSummaryTables(?string $summaryTables = null): self
    {
        $this->summaryTables = $summaryTables;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSummaryTables(): ?string
    {
        return $this->summaryTables;
    }
}
