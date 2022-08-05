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
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\External\Subject;
use Schoolbox\External\SubjectCollection;
use Schoolbox\Json\Api\SitePageLink;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Code\SubjectCodeMatchFactory;
use Schoolbox\Resource\Folder\Code\SubjectMatcherBase;
use Schoolbox\Resource\Storage;
use Schoolbox\Pastoral;

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

    /** @var string */
    private $positionTitle;

    /** @var bool */
    private $webmail;

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

    /** @var Collection|self[] */
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
     * @var SubjectCollection|Subject[]
     */
    protected $subject_collection;

    /** @var Collection */
    protected $parents;

    /**
     * Determines if parent collection has been loaded
     * @var bool
     */
    private $parentsLoaded = false;

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
     * @var null|string
     */
    private $boardingStatus;

    /**
     *
     * @var IConfigLoader
     * @deprecated Don't use this in new code, models should not have dependencies on services
     * @todo Remove this from this model
     */
    protected $config_loader;

    /**
     * @var Collection|User[]
     */
    protected $teacherCollection;

    /**
     * Determines if teacher collection has been loaded
     * @var bool
     */
    private $teachersLoaded = false;

    /**
     * @var GenericCollection|User[]
     */
    private $children;

    /**
     * Determines if children collection has been loaded
     * @var bool
     */
    private $childrenLoaded = false;

    /** @var GenericCollection|Pastoral\Group\Member\Model[] */
    private $pastoralGroupsMemberships;


    public function __construct()
    {
        $this->role = new Role();
        // TODO SA-8354: destroy this
        $this->config_loader = new ConfigLoader();
    }


    /**
     * Clones shouldn't share campusCollection
     */
    public function __clone()
    {
        if ($this->campusCollection !== null) {
            $this->campusCollection = clone $this->campusCollection;
        }
//        $this->accessList = clone $this->accessList;
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
            return $this->username . '@' . $this->config_loader->get('mail_address');
        }
        return $this->altEmail;
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
        if (empty($format)) {
            $format = $this->getConfigLoader()->get('name_format');
        }

        /* Replace tags */
        preg_match_all('|{\w+}|', $format, $tags);

        foreach ($tags[0] as $tag) {
            $key  = trim($tag, '{}');
            $func = 'get' . ucfirst($key);
            $value = '';
            if (method_exists($this, $func)) {
                if (is_string($this->$func())) {
                    $value = $this->$func();
                } else {
                    $value = '';
                }
            }
            $format = str_replace($tag, $value, $format);
        }

        if ($format = trim($format)) {
            return $format;
        } else {
            return 'Unknown User';
        }
    }

    /**
     * Outputs a metadata line for this student.
     *
     * This is the same line that shows in search results underneath a student's name.
     *
     * @todo allow schools to set the format as they can with `$this->formatName`
     *
     * @return string
     */
    public function formatMetadata()
    {
        // For now, just show role name
        if (!empty($this->positionTitle)) {
            return $this->positionTitle;
        }

        $metadata = '';
        $role     = $this->getRole()->getName();

        /* This made a massive assumption that everyone's year level
         * begins with "Year", which means anyone using "ELC" in lu_year_level.name
         * would get metadata like "Year ELC"
         *
         * See inside getLongName() which checks whether it's just an integer on its own
         * before trying to prepend something
         */
        if (!empty($this->year)) {
            $metadata .= $this->year->getLongName();
        }
        return "{$metadata} {$role}";
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
     * @param string $positionTitle
     * @return self
     */
    public function setPositionTitle($positionTitle)
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
     * @return string
     */
    public function getPositionTitle()
    {
        return $this->positionTitle;
    }

    /**
     * @return bool
     */
    public function hasPositionTitle()
    {
        $positionTitle = trim($this->positionTitle);
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
     * @param bool $webmail
     * @return self
     */
    public function setWebmail($webmail)
    {
        $this->webmail = $webmail;
        return $this;
    }

    /**
     * @return bool
     */
    public function getWebmail()
    {
        return $this->webmail;
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
    public function setPassword(?string $password): self
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
     *
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
     * @return SubjectCollection|Subject[]
     */
    public function getSubjectCollection()
    {
        return $this->subject_collection;
    }


    /**
     * @param IConfigLoader $config_loader
     *
     * @return self
     */
    public function setConfigLoader(IConfigLoader $config_loader)
    {
        $this->config_loader = $config_loader;
        return $this;
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
     * @return IConfigLoader
     */
    public function getConfigLoader()
    {
        return $this->config_loader;
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
     * Load teachers into object from external db
     * @return $this
     */
    public function loadTeachers()
    {
        if (!$this->teachersLoaded) {
            $codeMatcher       = (new SubjectCodeMatchFactory())->build($this->getConfigLoader()->get('external_type'));
            $teacherCollection = new Collection();

            // TODO remove handler dependency
            $externalUser = new \External_User();
            $externalClass = new \External_Class();

            // Get all of the students subjects

            $found = [];
            $subjects = $externalUser->getSubjectList($this);

            // Iterate and find teachers associated with each subject
            if (is_array($subjects)) {
                foreach ($subjects as $subject) {
                    $teachers = $externalClass->getTeachers($codeMatcher::generateFolderCode($subject['campusCode'], $subject['code']));

                    foreach ($teachers as $teacher) {
                        // If we haven't already, add this teacher to their collection

                        if (!isset($found[$teacher])) {
                            try {
                                $teacherCollection->add(
                                    UserMapper::getInstance()->findByExternalId($teacher, Role::STAFF)
                                );

                                $found[$teacher] = 1;
                            } catch (\Schoolbox\Mapper\Exception $e) {
                                // Schoolbox user not found, lets not try and notify
                            }
                        }
                    }
                }
            }

            $this->setTeacherCollection($teacherCollection);
            $this->teachersLoaded = true;
        }

        return $this;
    }

    /**
     * Load parents into object from external db
     * @return self
     */
    public function loadParents(): self
    {
        if ($this->parentsLoaded) {
            return $this;
        }
        $this->parents = new GenericCollection(self::class);
        if (null === $this->externalId) {
            return $this;
        }
        $DEPRECATED_EXTERNAL_USER = new \External_User();
        $allParents               = $DEPRECATED_EXTERNAL_USER->getStudentContactList([$this->externalId]);

        foreach ($allParents as $currentParent) {
            /* Add parent
             */
            if (!empty($currentParent['StudentContactID'])) {
                try {
                    $parent = UserMapper::getInstance()->findByExternalId($currentParent['StudentContactID'], Role::PARENT);
                    $this->parents->add($parent);
                } catch (Mapper\Exception $e) {
                    //noop
                }
            }
            /* Add speece
             */
            if (!empty($currentParent['StudentContactSpouseID'])) {
                try {
                    $spouse = UserMapper::getInstance()->findByExternalId($currentParent['StudentContactSpouseID'], Role::PARENT);
                    $this->parents->add($spouse);
                } catch (Mapper\Exception $e) {
                    //noop
                }
            }
        }

        $this->parentsLoaded = true;

        return $this;
    }

    /**
     * Load students / children into object from external db
     * @return self
     */
    public function loadStudents(): self
    {
        if ($this->childrenLoaded) {
            return $this;
        }
        $externalUser = new \External_User();
        $userMapper = UserMapper::getInstance();
        $this->children = new GenericCollection(self::class);
        if (null === $this->externalId) {
            return $this;
        }
        if ($this->getExternalId()) {
            $enrolments = $externalUser->getEnrolledStudents($this->getExternalId());
            foreach ($enrolments as $child) {
                try {
                    $this->children->add($userMapper->findByExternalId($child['RelatedID']));
                } catch (Mapper\Exception $e) {
                    // noop
                }
            }
        }
        $this->childrenLoaded = true;
        return $this;
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
     * @return Collection|self[]
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
     * @param Collection|self[] $linkedUser
     * @return $this
     */
    public function setLinkedUser($linkedUser): self
    {
        $this->linkedUser = $linkedUser;
        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getLinkedUser()
    {
        return $this->linkedUser;
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
     * @return Collection<User>
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
     * @return GenericCollection|Pastoral\Group\Member\Model[]|null
     */
    public function getPastoralGroupsMemberships(): ?GenericCollection
    {
        return $this->pastoralGroupsMemberships;
    }

    /**
     * @param Collection|Pastoral\Group\Member\Model[] $pastoralGroupsMemberships
     * @return $this
     */
    public function setPastoralGroupsMemberships(Collection $pastoralGroupsMemberships): self
    {
        if (!$this->pastoralGroupsMemberships) {
            $this->pastoralGroupsMemberships = Pastoral\Group\Member\Model::createCollection();
        }

        $this->pastoralGroupsMemberships->replaceWith($pastoralGroupsMemberships);
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
}
