<?php

/**
 * Generic user object for Schoolbox
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress\Image\Transformation\Portrait;
use Alaress\Json\Linkable;
use Alaress\Json\SerializeTrait;
use Alaress\Json\Serializable;
use House;
use HouseResidential;
use Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\SubjectCollection;
use Schoolbox\Json\Api\SitePageLink;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\User\Pronoun\Model;
use SensitiveParameter;

class User implements Serializable, Linkable, ISoftDeletable, IModel
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use SerializeTrait;
    use CreateCollection;

    // Username of the accounts used by Schoolbox staff
    public const USERNAME_SCHOOLBOX_ADMIN = 'alaress';

    /** @var null|int */
    private $id;

    /** @var string */
    private $title;

    /**
     * The user's legal first name.
     *
     * As of the introduction of `$this->preferredName`: where this is set and
     * `$preferredName` is not, this is both the legal and preferred name.
     *
     * It's expected but not mandatory that it is not null.
     *
     * @var string|null
     */
    private ?string $firstname = null;

    /**
     * The user's preferred name, as distinct from their legal first name.
     *
     * @var string|null
     */
    private ?string $preferredName = null;

    /**
     * The user's last name (a.k.a. surname).
     *
     * It's expected but not mandatory that it is not null.
     */
    private ?string $lastname = null;

    /** @var null|string */
    private $externalId;

    /** @var string */
    private $altEmail;

    /**
     * User's position title (optional field).
     */
    private ?string $positionTitle = null;

    /** @var string */
    private $username;

    /**
     * The user's encoded password: may be null if they have no password for
     * logging in.
     */
    private ?string $password = null;

    /**
     * Retrievable version of the user's password.
     *
     * Intentionally not mapped - I don't want this field to be passed around.
     *
     * @deprecated This field exists for compatibility with pre-16 password
     *             retrieval methods, and for setting the hash and key.
     *
     * @var string
     */
    private $retrievablePassword;

    /** @var YearLevel\Model|null */
    private $year;

    /** @var null|House */
    private $schoolHouse;

    /** @var null|HouseResidential */
    private $residentialHouse;

    /**
     * Note this variable should have a plural name, since it will contain a collection of linked users.
     * The name cannot be fixed without causing serialized user objects to become incompatible with the class definition.
     * So if the name is to be fixed to $linkedUsers, that problem must also be dealt with somehow.
     * Note we can't set the initial value to null without dealing with this extra problem for the same reason.
     * @var Collection|self[]
     */
    private $linkedUser;

    /**
     * The hash value is actually a digest key and it will change
     * with the username and password and should not be exposed
     * publically as it is an actual security token for the purposes
     * of webdav auth.
     *
     * @var string
     */
    private $hash = '';

    /**
     * Portrait images are stored in storage now
     *
     * @var Storage\Model|null
     */
    private $portrait;

    /**
     * Publicly available unique identifier, used in export functions
     *
     * @var string
     */
    private $key = '';

    /**
     * Users role within the system
     *
     * @var Role
     */
    private $role;

    /**
     * Collection of campuses
     *
     * @var GenericCollection|Campus[]
     */
    private $campusCollection;

    /** @var bool */
    private $superuser;

    /** @var bool */
    private $enabled;

    /** @var bool */
    private $hideContacts;

    /** @var bool */
    private $hideTimetable;

    /**
     * Hide side navigation
     *
     * @var bool
     */
    private $hideSidenav;

    /**
     * Hide notifications tray
     *
     * @var bool
     */
    private $hideNotificationsTray;

    /**
     * Is the grade graph displayed for this user?
     *
     * @var bool
     */
    private $displayGradeGraph;

    /**
     * This user's collection of subjects.
     *
     * Not guaranteed to have been loaded: thus, this may be null.
     *
     * @var SubjectCollection|null
     */
    protected $subject_collection;


    /** @var Collection|UserAccess[] */
    protected $accessList;

    /**
     * Controls if user has local domain email username@domain
     *
     * @var bool
     */
    private $localEmail;

    /**
     * Controls if user has eportfolio
     *
     * @var bool
     */
    private $eportfolioEnable;

    /**
     * Controls mailto (false) or internal schoolbox (true) mail sending
     *
     * @var bool
     */
    private $useInternalMail;

    /**
     * Controls default calendar view
     *
     * @var string
     */
    private $calendarView = 'month';

    /**
     * Student's boarding status (only used if boarder leave module is enabled)
     *
     * @deprecated In 23.0, can be removed after that release
     * @var null|string
     */
    private $boardingStatus;

    /**
     * Collection of parents, used for identifying relationships the user has.
     *
     * If this is null you will need to hydrate with the return of \Schoolbox\External\User::loadParents
     * @var Collection|User[]|null
     */
    protected $parents;

    /**
     * Collection of teachers, used for identifying relationships the user has.
     *
     * If this is null you will need to hydrate with the return of \Schoolbox\External\User::loadTeachers
     * @var Collection|User[]|null
     */
    protected $teacherCollection = null;

    /**
     * Collection of children, used for identifying relationships the user has.
     *
     * If this is null you will need to hydrate with the return of \Schoolbox\External\User::loadChildren
     * @var GenericCollection|User[]|null
     */
    private $children = null;

    /** @var array<int>|null */
    private ?array $pastoralGroupIds = null;

    /** If set, it'll be used to build email address, instead of Config option */
    private ?string $emailDomain = null;

    /** If set, it'll be used to format name, instead of Config option */
    private ?string $nameFormat = null;

    private ?Model $pronoun = null;

    public function __construct()
    {
        $this->role = new Role();
    }


    /**
     * Clones shouldn't share campusCollection
     */
    public function __clone()
    {
        if ($this->campusCollection !== null) {
            $this->campusCollection = clone $this->campusCollection;
        }
        if ($this->accessList instanceof GenericCollection) {
            $this->accessList = clone $this->accessList;
        }
    }

    /**
     * Test if user is in provided campus
     *
     * @param Campus $campus
     *
     * @return boolean
     */
    public function isInCampus(Campus $campus)
    {
        return $this->getCampusCollection()->getBy('id', $campus->getId()) instanceof Campus;
    }

    /**
     * Returns a list of collections the user belongs in
     *
     * @return GenericCollection|Campus[]|null
     */
    public function getCampusCollection()
    {
        return $this->campusCollection;
    }


    /**
     * @return bool
     */
    public function isSuperuser()
    {
        return $this->superuser;
    }

    /**
     * Returns users role, previously known as groups
     *
     * @return Role
     */
    public function getRole()
    {
        return $this->role;
    }


    /**
     * @param Role $role
     * @return self
     */
    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getEmail()
    {
        if ($this->localEmail == true) {
            return sprintf(
                "%s@%s",
                $this->username,
                $this->emailDomain ?: Config::get(ConfigMapper::NAME_MAIL_ADDRESS)
            );
        }
        return $this->altEmail;
    }


    /**
     * Explicitly sets domain name for email address
     * This overrides mail_address config
     * @param string $emailDomain
     * @return static
     */
    public function setEmailDomain(string $emailDomain): self
    {
        $this->emailDomain = $emailDomain;
        return $this;
    }


    /**
     * Decide if this user has an email address
     *
     * @return bool
     */
    public function hasEmail(): bool
    {
        return '' !== (string)$this->getEmail();
    }


    /**
     * The user's given name is preferentially their preferred name, otherwise
     * their first name.
     *
     * @return string|null
     */
    public function getGivenName()
    {
        return (($this->preferredName ?? '') !== '')
            ? $this->preferredName
            : $this->firstname;
    }


    /**
     * @return string
     */
    public function getFullName()
    {
        return $this->formatName();
    }


    /**
     * Format a name from a user object
     *
     * @param string $format
     *
     * @return string
     */
    public function formatName($format = null)
    {
        // Check for custom format
        $format = $format ?: $this->nameFormat;
        $formatter = empty($format)
            ? (new User\Formatter\Factory())->buildFromNothing()
            : new User\Formatter\Formatter($format);
        return $formatter->formatObject($this);
    }


    /**
     * Explicitly sets name format for full name
     * This overrides name_format config
     * @param string $nameFormat
     * @return static
     */
    public function setNameFormat(string $nameFormat): self
    {
        $this->nameFormat = $nameFormat;
        return $this;
    }


   /**
     * @param string $altEmail
     * @return self
     */
    public function setAltEmail($altEmail)
    {
        $this->altEmail = $altEmail;
        return $this;
    }

    /**
     * @param string|null $positionTitle
     * @return self
     */
    public function setPositionTitle(?string $positionTitle): self
    {
        $this->positionTitle = $positionTitle;
        return $this;
    }

    /**
     * @return string
     */
    public function getAltEmail()
    {
        return $this->altEmail;
    }

    /**
     * @return string|null
     */
    public function getPositionTitle(): ?string
    {
        return $this->positionTitle;
    }

    /**
     * @return bool
     */
    public function hasPositionTitle()
    {
        $positionTitle = trim($this->positionTitle ?? '');
        return !empty($positionTitle);
    }

    /**
     * This function will get the users position title, or fall back to their role if no title exists
     * This should be used on user cards to show their role in the school
     * @return string
     */
    public function getPreferredTitle()
    {
        if ($this->hasPositionTitle()) {
            return $this->getPositionTitle();
        } else if ($this->getRole() instanceof Role) {
            return $this->getRole()->getName();
        } else {
            return '';
        }
    }


    /**
     * @param string $calendarView
     * @return self
     */
    public function setCalendarView($calendarView)
    {
        $this->calendarView = $calendarView;
        return $this;
    }


    /**
     * @return string
     */
    public function getCalendarView()
    {
        return $this->calendarView;
    }


    /**
     * @param bool $eportfolioEnable
     * @return self
     */
    public function setEportfolioEnable($eportfolioEnable)
    {
        $this->eportfolioEnable = $eportfolioEnable;
        return $this;
    }


    /**
     * @return bool
     */
    public function getEportfolioEnable()
    {
        return $this->eportfolioEnable;
    }


    /**
     * @param null|string $externalId
     * @return self
     */
    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }


    /**
     * @param string|null $firstname
     * @return self
     */
    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    /**
     * @return string|null
     */
    public function getPreferredName(): ?string
    {
        return $this->preferredName;
    }


    /**
     * @param string|null $preferredName
     * @return $this
     */
    public function setPreferredName(?string $preferredName): self
    {
        $this->preferredName = $preferredName;
        return $this;
    }


    /**
     * @param string $hash
     * @return self
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
        return $this;
    }


    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }


    /**
     * @return Storage\Model|null
     */
    public function getPortrait(): ?Storage\Model
    {
        return $this->portrait;
    }


    /**
     * @param Storage\Model|null $portrait
     * @return self
     */
    public function setPortrait(?Storage\Model $portrait): User
    {
        $this->portrait = $portrait;
        return $this;
    }


    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @todo We should decide what type we want and enforce it.
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param string $key
     * @return self
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }


    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }


    /**
     * @param string|null $lastname
     * @return self
     */
    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getLastname(): ?string
    {
        return $this->lastname;
    }


    /**
     * @param bool $localEmail
     * @return self
     */
    public function setLocalEmail($localEmail)
    {
        $this->localEmail = $localEmail;
        return $this;
    }


    /**
     * @return bool
     */
    public function getLocalEmail()
    {
        return $this->localEmail;
    }


    /**
     * @param string|null $password
     * @return self
     */
    public function setPassword(#[SensitiveParameter] ?string $password): self
    {
        $this->password = $password;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @deprecated
     * @return string
     */
    public function getRetrievablePassword()
    {
        return $this->retrievablePassword;
    }

    /**
     * @deprecated
     * @param  string $retrievablePassword
     * @return $this
     */
    public function setRetrievablePassword($retrievablePassword)
    {
        $this->retrievablePassword = $retrievablePassword;
        return $this;
    }

    /**
     * @param HouseResidential $residentialHouse
     * @return self
     */
    public function setResidentialHouse($residentialHouse)
    {
        $this->residentialHouse = $residentialHouse;
        return $this;
    }


    /**
     * @return HouseResidential
     */
    public function getResidentialHouse()
    {
        return $this->residentialHouse;
    }


    /**
     * @param null|House $schoolHouse
     * @return self
     */
    public function setSchoolHouse($schoolHouse)
    {
        $this->schoolHouse = $schoolHouse;
        return $this;
    }


    /**
     * @return null|House
     */
    public function getSchoolHouse()
    {
        return $this->schoolHouse;
    }


    /**
     * @param bool $superuser
     * @return self
     */
    public function setSuperuser($superuser)
    {
        $this->superuser = $superuser;
        return $this;
    }


    /**
     * @return bool
     */
    public function getSuperuser()
    {
        return $this->superuser;
    }


    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }


    /**
     * @param bool $useInternalMail
     * @return self
     */
    public function setUseInternalMail($useInternalMail)
    {
        $this->useInternalMail = $useInternalMail;
        return $this;
    }


    /**.
     * @return bool
     */
    public function getUseInternalMail()
    {
        return $this->useInternalMail;
    }


    /**
     * @param string $username
     * @return self
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }


    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }


    /**
     * @param YearLevel\Model|null $year
     * @return self
     */
    public function setYear(?YearLevel\Model $year)
    {
        $this->year = $year;
        return $this;
    }


    /**
     * @return YearLevel\Model|null
     */
    public function getYear(): ?YearLevel\Model
    {
        return $this->year;
    }


    /**
     * @param bool $enabled
     * @return self
     */
    public function setEnabled($enabled)
    {
        // explicit cast
        $this->enabled = $enabled ? true : false;
        return $this;
    }

    /**
     * @param bool $hideContacts
     * @return self
     */
    public function setHideContacts($hideContacts)
    {
        // explicit cast
        $this->hideContacts = $hideContacts ? true : false;
        return $this;
    }

    /**
     * @param bool $hideTimetable
     * @return self
     */
    public function setHideTimetable($hideTimetable)
    {
        // explicit cast
        $this->hideTimetable = $hideTimetable ? true : false;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnabled()
    {
        // explicit cast
        return $this->enabled ? true : false;
    }

    /**
     * @return bool
     */
    public function getHideContacts()
    {
        // explicit cast
        return $this->hideContacts ? true : false;
    }

    /**
     * @return bool
     */
    public function getHideTimetable()
    {
        // explicit cast
        return $this->hideTimetable ? true : false;
    }


    /**
     * I got so sick of being confused by not hiding a timetable
     * in code so I made this inverted alias instead. Ideally we'd
     * invert the db schema as well, but this gets us close enough
     * for the most part
     *
     * @return bool
     */
    public function isShowTimetable(): bool
    {
        return !$this->getHideTimetable();
    }


    /**
     * @param GenericCollection|Campus[] $campusCollection
     * @return self
     */
    public function setCampusCollection(GenericCollection $campusCollection)
    {
        $campusCollection->assertCollectionOf(Campus::class);
        $this->campusCollection = $campusCollection;
        return $this;
    }


    /**
     * Set boarding status (only if boarder leave is enabled)
     *
     * @param null|string $boardingStatus
     * @deprecated In 23.0, can be removed after that release
     * @return $this
     */
    public function setBoardingStatus(?string $boardingStatus): self
    {
        $this->boardingStatus = $boardingStatus;
        return $this;
    }

    /**
     * Get boarding status
     *
     * @deprecated In 23.0, can be removed after that release
     * @return null|string
     */
    public function getBoardingStatus(): ?string
    {
        return $this->boardingStatus;
    }

    /**
     * Get abbreviated form of boarding status (first letter)
     *
     * @return string
     */
    public function getAbbreviatedBoardingStatus()
    {
        return substr($this->getBoardingStatus(), 0, 1);
    }

    /**
     * @param SubjectCollection $subject_collection
     *
     * @return self
     */
    public function setSubjectCollection($subject_collection)
    {
        $this->subject_collection = $subject_collection;
        return $this;
    }

    /**
     * @return SubjectCollection|null
     */
    public function getSubjectCollection()
    {
        return $this->subject_collection;
    }

    /**
     * @param  GenericCollection|UserAccess[] $accessList
     *
     * @return $this
     */
    public function setAccessList(GenericCollection $accessList): self
    {
        $this->accessList = $accessList;
        return $this;
    }

    /**
     * @return GenericCollection|UserAccess[]
     */
    public function getAccessList(): GenericCollection
    {
        if (!isset($this->accessList)) {
            $uaMapper = UserAccessMapper::getInstance();
            $this->setAccessList($uaMapper->findAllForUser($this));
        }

        return $this->accessList;
    }

    /**
     * Is this user a parent of $child?
     *
     * Requires that either:
     * - `loadParents()` has been called on $child, or
     * - this user is the current session user (and hence their children were loaded during session creation)
     *
     * @param  User $child
     * @return bool
     */
    public function isParentOf(User $child): bool
    {
        /* Parents were never initialised for $child
         * Their ancestry is inconclusive; continue
         */
        if (null !== $child->getParents()) {
            foreach ($child->getParents() as $parent) {
                if ($parent->getId() == $this->getId()) {
                    return true;
                }
            }
        }

        /* $this->children was never initialised for this user
         * Their descendents are inconclusive; continue
         */
        if (null !== $this->children) {
            foreach ($this->children as $currentChild) {
                if ($currentChild->getId() == $child->getId()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Is this user a child of $parent?
     *
     * Requires that `loadParents()` has been called on this user.
     *
     * @param  User $parent
     * @return bool
     */
    public function isChildOf(User $parent): bool
    {
        return $parent->isParentOf($this);
    }

    /**
     * @param GenericCollection<User> $parents
     * @return User
     */
    public function setParents(GenericCollection $parents): self
    {
        $parents->assertCollectionOf(self::class);
        $this->parents = $parents;
        return $this;
    }


    /**
     * Get the parents associated with this user
     *
     * If this is null you will need to hydrate with the return of \Schoolbox\External\User::loadParents
     *
     * @return Collection|self[]|null
     */
    public function getParents()
    {
        return $this->parents;
    }

    /**
     * Hide the sidenav
     */
    public function hideSidenav()
    {
        $this->setHideSidenav(true);
    }

    /**
     * Show the sidenav
     */
    public function showSidenav()
    {
        $this->setHideSidenav(false);
    }

    /**
     * Hide notifications tray
     */
    public function hideNotificationsTray()
    {
        $this->setHideNotificationsTray(true);
    }

    /**
     * Show notifications tray
     */
    public function showNotificationsTray()
    {
        $this->setHideNotificationsTray(false);
    }

    /**
     * @return bool
     */
    public function getDisplayGradeGraph()
    {
        return $this->displayGradeGraph;
    }

    /**
     * @param bool $displayGradeGraph
     * @return static
     */
    public function setDisplayGradeGraph($displayGradeGraph)
    {
        $this->displayGradeGraph = $displayGradeGraph;
        return $this;
    }

    /* Getters/Setters
     */

    /**
     * @param Collection|self[] $linkedUsers
     * @return $this
     */
    public function setLinkedUsers(Collection $linkedUsers): self
    {
        $this->linkedUser = $linkedUsers; // Note the class variable name should be plural (see comment above variable declaration).
        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getLinkedUsers(): ?Collection
    {
        return $this->linkedUser; // Note the class variable name should be plural (see comment above variable declaration).
    }

    /**
     * Get hide notifications tray
     *
     * @return boolean|null
     */
    public function getHideNotificationsTray(): ?bool
    {
        return $this->hideNotificationsTray;
    }

    /**
     * Set hide notifications tray
     *
     * @param boolean $hideNotificationsTray
     */
    public function setHideNotificationsTray($hideNotificationsTray): self
    {
        $this->hideNotificationsTray = $hideNotificationsTray;
        return $this;
    }


    /**
     * Get hide side navigation setting
     *
     * @return null|bool
     */
    public function getHideSidenav(): ?bool
    {
        return $this->hideSidenav;
    }


    /**
     * Set hide side navigation setting
     *
     * @param null|bool $hideSidenav
     * @return $this
     */
    public function setHideSidenav(?bool $hideSidenav): self
    {
        $this->hideSidenav = $hideSidenav;
        return $this;
    }

    /**
     * Collection of teachers, used for identifying relationships the user has.
     *
     * If this is null you will need to hydrate with the return of \Schoolbox\External\User::loadTeachers
     * @return Collection<User>|null
     */
    public function getTeacherCollection()
    {
        return $this->teacherCollection;
    }

    /**
     * @param Collection<User> $teacherCollection
     * @return $this
     */
    public function setTeacherCollection($teacherCollection)
    {
        $this->teacherCollection = $teacherCollection;
        return $this;
    }


    /**
     * Collection of children, used for identifying relationships the user has.
     *
     * If this is null you will need to hydrate with the return of \Schoolbox\External\User::loadChildren
     * @return GenericCollection<User>|null
     */
    public function getChildren()
    {
        return $this->children;
    }


    /**
     * @param GenericCollection<User> $children
     * @return $this
     */
    public function setChildren(GenericCollection $children): self
    {
        $children->assertCollectionOf(self::class);
        $this->children = $children;
        return $this;
    }


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return [
            'id',
            'externalId',
            'title',
            'firstname',
            'preferredName',
            'givenName',
            'lastname',
            'fullName',
        ];
    }


    /**
     * @return array
     */
    public function getJsonApiLinks(): array
    {
        $links = [];
        if (!$this->isDeleted()) {
            $links['profile'] = new SitePageLink("/search/user/{$this->id}");
            $links['avatar']  = new SitePageLink(
                sprintf(
                    '/portrait.php?id=%s&size=%s',
                    urlencode($this->id),
                    urlencode(\Alaress\Image\Transformation\Storage::TRANSFORM_SQUARE64)
                )
            );
        } else {
            $links['avatar'] = new SitePageLink(
                \SitePage::getBrokenImagePath(
                    500,
                    [
                        'transform_type' => Portrait::getSlug(),
                        'transform'      => \Alaress\Image\Transformation\Storage::TRANSFORM_SQUARE64,
                    ]
                )
            );
        }
        return $links;
    }

    /**
     * Returns formatted email display e.g: "Alaress Admin" <alaress@alaress.com.au>
     * @return string
     */
    public function getFormattedEmailDisplay(): string
    {
        return '"' . $this->formatName() . '" <' . $this->getEmail() . '>';
    }

    /**
     * @return array<int>|null
     */
    public function getPastoralGroupIds(): ?array
    {
        return $this->pastoralGroupIds;
    }

    /**
     * @param array<int> $pastoralGroupIds
     * @return $this
     */
    public function setPastoralGroupIds(array $pastoralGroupIds): self
    {
        $this->pastoralGroupIds = $pastoralGroupIds;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLearningMomentsEnabled(): bool
    {
        // If year is not set, fall back to global config
        return (null === $this->getYear()) || $this->getYear()->getLearningMomentsEnabled();
    }

    /**
     * Checks basic pastoral access
     * @return bool
     */
    public function isStaffWithPastoralAccess(): bool
    {
        if (!$this->getRole()->isStaff()) {
            return false;
        }
        foreach ($this->getAccessList() as $access) {
            if ($access->getService() === UserAccess::SERVICE_PASTORAL && $access->getAccessLevel() >= UserAccess::ACCESS_READ) {
                return true;
            }
        }
        return $this->getRole()->getAccessPastoral();
    }

    /**
     * Checks group access
     * @param int $groupId
     * @return bool
     */
    public function isGroupMember(int $groupId): bool
    {
        foreach ($this->getAccessList() as $access) {
            if ($access->getService() === UserAccess::SERVICE_HOMEPAGE && $access->getServiceIdentifier() == $groupId) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Model|null $pronoun
     */
    public function setPronoun(?Model $pronoun): self
    {
        $this->pronoun = $pronoun;
        return $this;
    }

    /**
     * @return Model|null
     */
    public function getPronoun(): ?Model
    {
        return $this->pronoun;
    }
}
