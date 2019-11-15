<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Report;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Pastoral\SubType as PastoralSubType;
use Schoolbox\Pastoral\Group as PastoralGroup;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Tag as PastoralTag;
use Schoolbox\Pastoral\Type as PastoralType;
use Schoolbox\User;
use House;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var bool $dashboard */
    protected $dashboard;

    /** @var string $name */
    private $name;

    /** @var null|string $description */
    private $description;

    /** @var Collection $years */
    private $years;

    /**
     * Only show records in which the user is the author or an audience member
     *
     * @var bool $involved
     */
    private $involved;

    /** @var bool $usesRelativeDate */
    private $usesRelativeDate;

    /** @var string $relativeDate */
    private $relativeDate;

    /**
     * Optional. Can be Null.
     *
     * @var null|Alaress_Date_Format $afterDate
     */
    private $afterDate;

    /**
     * Optional. Can be Null.
     *
     * @var null|Alaress_Date_Format $beforeDate
     */
    private $beforeDate;

    /** @var User $owner */
    private $owner;

    // These are the filters stored as CSV strings

    /**
     * Ids of Lookup list 'pastoral types'
     *
     * @var string $typesCsv
     */
    private $typesCsv;

    /** @var string $subTypesCsv */
    private $subTypesCsv;

    /** @var string $housesCsv */
    private $housesCsv;

    /** @var string $authorsCsv */
    private $authorsCsv;

    /** @var string $studentsCsv */
    private $studentsCsv;

    /** @var string $tagsCsv */
    private $tagsCsv;

    /** @var string $orderCsv */
    private $orderCsv;

    /** @var string $yearsCsv */
    private $yearsCsv;

    // These are the collections that get hydrated from the CSV values above

    /** @var Collection $order */
    private $order;

    /** @var Collection|PastoralTag\Model[] $tags */
    private $tags;

    /** @var Collection|User[] $students */
    private $students;

    /** @var Collection|User[] $authors */
    private $authors;

    /** @var Collection|PastoralType\Model[] $types */
    private $types;

    /** @var Collection|PastoralSubType\Model[] $types */
    private $subTypes;

    /** @var Collection|House[] $types */
    private $houses;

    /**
     * Will be null if severity is turned off in admin config
     *
     * @var null|PastoralSeverity $severity
     */
    private $severity;

    /** @var Collection|PastoralGroup\Model[] $groups */
    private $groups;

    // These are enums in DB, you will need to update column definition if you add to this list
    private const THIS_YEAR = 'first day of january';
    private const PAST_6_MONTHS = '6 months ago';
    private const PAST_3_MONTHS = '3 months ago';
    private const PAST_MONTH = '1 month ago';
    private const PAST_FORTNIGHT = '14 days ago';
    private const PAST_WEEK = '7 days ago';
    private const TODAY = 'today';

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

        // This is a collection of groups that have access to this report
        $this->groups = new GenericCollection(PastoralGroup\Model::class);
    }

    /**
     * @return string[]
     */
    public static function getAllRelativeDateStrings() : array
    {
        return [
            self::THIS_YEAR,
            self::PAST_6_MONTHS,
            self::PAST_3_MONTHS,
            self::PAST_MONTH,
            self::PAST_FORTNIGHT,
            self::PAST_WEEK,
            self::TODAY
        ];
    }

    /**
     * @param null|PastoralSeverity $value
     *
     * @return $this
     */
    public function setSeverity(PastoralSeverity $value = null)
    {
        $this->severity = $value;

        return $this;
    }

    /**
     * @return null|PastoralSeverity
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setTypesCsv($value)
    {
        $this->typesCsv = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getTypesCsv()
    {
        return $this->typesCsv;
    }

    /**
     * @param null|string $subTypesCsv
     * @return $this
     */
    public function setSubTypesCsv(?string $subTypesCsv) : self
    {
        $this->subTypesCsv = $subTypesCsv;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getSubTypesCsv() : ?string
    {
        return $this->subTypesCsv;
    }

    /**
     * @param null|string $housesCsv
     * @return $this
     */
    public function setHousesCsv(?string $housesCsv) : self
    {
        $this->housesCsv = $housesCsv;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getHousesCsv() : ?string
    {
        return $this->housesCsv;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setAuthorsCsv($value)
    {
        $this->authorsCsv = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getAuthorsCsv()
    {
        return $this->authorsCsv;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setStudentsCsv($value)
    {
        $this->studentsCsv = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getStudentsCsv()
    {
        return $this->studentsCsv;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setTagsCsv($value)
    {
        $this->tagsCsv = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getTagsCsv()
    {
        return $this->tagsCsv;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setOrderCsv($value)
    {
        $this->orderCsv = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getOrderCsv()
    {
        return $this->orderCsv;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setYearsCsv($value)
    {
        $this->yearsCsv = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getYearsCsv()
    {
        return $this->yearsCsv;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $description
     *
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param User $owner
     *
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @param bool $usesRelativeDate
     * @return self
     */
    public function setUsesRelativeDate(bool $usesRelativeDate) : self
    {
        $this->usesRelativeDate = $usesRelativeDate;

        return $this;
    }

    /**
     * @return bool
     */
    public function getUsesRelativeDate() : bool
    {
        return $this->usesRelativeDate;
    }

    /**
     * @param string $relativeDate
     * @return self
     */
    public function setRelativeDate(string $relativeDate) : self
    {
        $this->relativeDate = $relativeDate;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRelativeDate() : ?string
    {
        return $this->relativeDate;
    }

    /**
     * @param null|Alaress_Date_Format $beforeDate
     *
     * @return self
     */
    public function setBeforeDate(Alaress_Date_Format $beforeDate = null)
    {
        $this->beforeDate = $beforeDate;

        return $this;
    }

    /**
     * @return null|Alaress_Date_Format
     */
    public function getBeforeDate()
    {
        return $this->beforeDate;
    }

    /**
     * @param null|Alaress_Date_Format $afterDate
     *
     * @return self
     */
    public function setAfterDate(Alaress_Date_Format $afterDate = null)
    {
        $this->afterDate = $afterDate;

        return $this;
    }

    /**
     * @return null|Alaress_Date_Format
     */
    public function getAfterDate()
    {
        return $this->afterDate;
    }

    /**
     * @param Collection|PastoralType\Model[] $types
     *
     * @return self
     */
    public function setTypes(Collection $types)
    {
        $this->types = $types;

        return $this;
    }

    /**
     * @return Collection|PastoralType\Model[]
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param Collection|PastoralSubType\Model[] $subTypes
     *
     * @return self
     */
    public function setSubTypes(Collection $subTypes) : self
    {
        $this->subTypes = $subTypes;

        return $this;
    }

    /**
     * @return null|Collection|PastoralSubType\Model[]
     */
    public function getSubTypes() : ?Collection
    {
        return $this->subTypes;
    }

    /**
     * @param Collection|House[] $houses
     * @return $this
     */
    public function setHouses(Collection $houses) : self
    {
        $this->houses = $houses;
        return $this;
    }

    /**
     * @return null|Collection|House[]
     */
    public function getHouses() : ?Collection
    {
        return $this->houses;
    }

    /**
     * @param Collection|User[] $authors
     *
     * @return self
     */
    public function setAuthors(Collection $authors)
    {
        $this->authors = $authors;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getAuthors()
    {
        return $this->authors;
    }

    /**
     * @param Collection|User[] $students
     *
     * @return self
     */
    public function setStudents(Collection $students)
    {
        $this->students = $students;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getStudents()
    {
        return $this->students;
    }

    /**
     * @param Collection|PastoralTag\Model[] $tags
     *
     * @return self
     */
    public function setTags(Collection $tags)
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return Collection|PastoralTag\Model[]
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param Collection $order
     *
     * @return self
     */
    public function setOrder(Collection $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param bool $involved
     *
     * @return self
     */
    public function setInvolved($involved)
    {
        $this->involved = $involved;

        return $this;
    }

    /**
     * @return bool
     */
    public function getInvolved()
    {
        return $this->involved;
    }

    /**
     * @param Collection $years
     *
     * @return self
     */
    public function setYears(Collection $years)
    {
        $this->years = $years;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getYears()
    {
        return $this->years;
    }

    /**
     * @param Collection|PastoralGroup\Model[] $groups
     *
     * @return self
     */
    public function setGroups(Collection $groups)
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * @return Collection|PastoralGroup\Model[]
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * @param bool $dashboard
     *
     * @return self
     */
    public function setDashboard($dashboard)
    {
        $this->dashboard = $dashboard;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDashboard()
    {
        return $this->dashboard;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function hasAccess(User $user)
    {
        if ($this->getOwner()->getId() == $user->getId()) {
            return true;
        }

        foreach ($this->getGroups() as $group) {
            foreach ($group->getMembers() as $member) {
                if ($member->getUser()->getId() == $user->getId()) {
                    return true;
                }
            }
        }

        return false;
    }
}
