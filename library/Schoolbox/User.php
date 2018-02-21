<?php

/**
 * Generic user object for Schoolbox
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;

use House;
use HouseResidential;
use JsonSerializable;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\External\Subject;
use Schoolbox\External\SubjectCollection;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use DomainException;

class User implements JsonSerializable, ISoftDeletable
{
    use DeletedAtDate;

    /** @var int */
    private $id;

    /** @var string */
    private $title;

    /** @var string */
    private $firstname;

    /** @var string */
    private $lastname;

    /** @var string */
    private $externalId;

    /** @var string */
    private $altEmail;

    /** @var string */
    private $positionTitle;

    /** @var bool */
    private $webmail;

    /** @var string */
    private $username;

    /** @var string */
    private $password;

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

    /** @var string */
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
    protected $parent_collection;

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
    private $calendarView;

    /**
     * A Collection of User objects who should be receiving email
     * notifications about this User. These recipients are usually,
     * but not necessarily, a parent of this User instance.
     *
     * @var Collection|self[]
     */
    private $emailRecipients;

    /**
     * Student's boarding status (only used if boarder leave module is enabled)
     *
     * @var string
     */
    protected $boardingStatus;

    /**
     * @var IConfigLoader
     */
    protected $config_loader;

    /** @var Collection */
    protected $teacherCollection;

    /**
     * @var GenericCollection|User[]
     */
    private $children;

    /**
     * Build default empty objects so things don't explode
     */
    public function __construct()
    {
        $this->campusCollection = new GenericCollection(Campus::class);
        $this->subject_collection = new GenericCollection(Subject::class);
        $this->setConfigLoader(new ConfigLoader());
        $this->setLinkedUser(new Collection(array()));
        $this->setRole(new Role());
        $this->children = new GenericCollection(self::class);
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
     * @return GenericCollection|Campus[]
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
     * @return string
     */
    public function getEmail()
    {
        if ($this->localEmail == true) {
            return $this->username . '@' . $this->config_loader->get('mail_address');
        }
        return $this->altEmail;
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
        return $this->getRole()->getName();
//        $metadata = '';
//        $year     = $this->getYear();
//        $campus   = $this->getDefaultCampus()->getName();
//        $role     = $this->getRole()->getName();
//        if (!empty($year)) {
//            $metadata .= 'Year ' . $year;
//        }
//        // If $campus isn't in $role, output campus as well as role
//        if (stristr($role, $campus) === false) {
//            $metadata .= ' ' . $campus;
//        }
//        $metadata .= ' ' . $role;
//        return trim($metadata);
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
    public function getPreferredTitle(){
        if($this->hasPositionTitle()) {
            return $this->getPositionTitle();
        }
        else if($this->getRole() instanceof Role) {
            return $this->getRole()->getName();
        }
        else {
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
     * @param string $externalId
     * @return self
     */
    public function setExternalId($externalId)
    {
        $this->externalId = $externalId;
        return $this;
    }


    /**
     * @return string
     */
    public function getExternalId()
    {
        return $this->externalId;
    }


    /**
     * @param string$firstname
     * @return self
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
        return $this;
    }


    /**
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
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
     * @return int|string
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
     * @param string $lastname
     * @return self
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
        return $this;
    }


    /**
     * @return string
     */
    public function getLastname()
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
     * @param string $password
     * @return self
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }


    /**
     * @return string
     */
    public function getPassword()
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
     * @param string $year
     * @return self
     */
    public function setYear($year)
    {
        $this->year = $year;
        return $this;
    }


    /**
     * @return string
     */
    public function getYear()
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
        $this->campusCollection->replaceWith($campusCollection);
        return $this;
    }


    /**
     * Set boarding status (only if boarder leave is enabled)
     *
     * @param  string $boardingStatus
     *
     * @return $this
     */
    public function setBoardingStatus($boardingStatus)
    {
        $this->boardingStatus = $boardingStatus;
        return $this;
    }

    /**
     * Get boarding status
     *
     * @return string
     */
    public function getBoardingStatus()
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
     * @param  Collection|UserAccess[] $accessList
     *
     * @return $this
     */
    public function setAccessList($accessList)
    {
        $this->accessList = $accessList;
        return $this;
    }

    /**
     * @return Collection|UserAccess[]
     */
    public function getAccessList()
    {
        /** @var UserAccessMapper $uaMapper */
        if(!isset($this->accessList)) {
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
        foreach ($child->getParents() as $parent) {
            if ($parent->getId() == $this->getId()) {
                return true;
            }
        }
        foreach ($this->children as $currentChild) {
            if ($currentChild->getId() == $child->getId()) {
                return true;
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
    public function isChildOf(User $parent)
    {
        foreach ($this->getParents() as $childParent) {
            if ($childParent->getId() == $parent->getId()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Load teachers into object from external db
     * @return $this
     */
    public function loadTeachers()
    {
        $teacherCollection = new Collection();

        // TODO remove handler dependency
        $externalUser = new \External_User();
        $externalClass = new \External_Class();

        // Get all of the students subjects

        $found = [];
        $subjects = $externalUser->getSubjectList($this);

        // Iterate and find teachers associated with each subject

        if (is_array($subjects))
        {
            foreach ($subjects as $subject)
            {
                $teachers = $externalClass->getTeacherName($subject['code']);

                foreach ($teachers as $teacher)
                {
                    // If we haven't already, add this teacher to their collection

                    if (!isset($found[$teacher]))
                    {
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
        return $this;
    }

    /**
     * Load parents into object from external db
     * @return User
     */
    public function loadParents()
    {
        $this->parent_collection = new Collection();
        $DEPRECATED_EXTERNAL_USER = new \External_User();
        $allParents               = $DEPRECATED_EXTERNAL_USER->getStudentContactList($this->externalId);

        if (is_array($allParents)) {
            foreach ($allParents as $currentParent) {
                /* Add parent
                 */
                if (!empty($currentParent['StudentContactID'])) {
                    $parentCollection = UserMapper::getInstance()->findByExternalId($currentParent['StudentContactID'], Role::PARENT);
                    foreach ($parentCollection as $parent) {
                        $this->parent_collection->add($parent);
                    }
                }
                /* Add speece
                 */
                if (!empty($currentParent['StudentContactSpouseID'])) {
                    $spouseCollection = UserMapper::getInstance()->findByExternalId($currentParent['StudentContactSpouseID'], Role::PARENT);
                    if ($spouseCollection->count()) {
                        $this->parent_collection->add($spouseCollection->first());
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Get the parents associated with this user
     *
     * @return Collection|self[]
     */
    public function getParents()
    {
        return $this->parent_collection;
    }

    /**
     * TODO this logic needs to go in the mapper. Or somewhere. Just not here!
     *
     * Find the email recipients for this user who should be notified whenever
     * a comment is made, or when some other email-worthy action takes place
     *
     * At the moment it's private because nobody else is using this yet, but I
     * think there are other places which do a similar thing - when those are
     * ready to utilise findEmailRecipients, make this public or use the
     * loadEmailRecipients() approach
     *
     * Migrated from Conversation.php
     *
     * @param self $user
     *
     * @return Collection|self[]
     */
    private function findEmailRecipients($user)
    {
        $parentRoles = RoleMapper::getInstance()->findByColumn(
            'group_type',
            Role::PARENT
        )->getColumnValues('id');
        $allRecipients            = new Collection();
        $DEPRECATED_EXTERNAL_USER = new \External_User();
        $emailArray               = $DEPRECATED_EXTERNAL_USER->getParentEmails(array($user->getExternalId()));
        if (is_array($emailArray) && count($emailArray)) {
            foreach ($emailArray as $synergyId => $emailAddress) {
                $thisRecipient = new Collection();
                // if a synergy ID is available, and a valid user can be obtained from it, use that
                if ($synergyId) {
                    $thisRecipient = UserMapper::getInstance()->findByColumnList(
                        array(
                            'synergy_id' => $synergyId,
                            'group_id'   => $parentRoles
                        )
                    );
                }
                if ($thisRecipient->count()) {
                    /* This person has a Schoolbox account, so just use that
                     */
                    $recipient = $thisRecipient->first();
                } else {
                    /* Generate a dummy user object (which will still have a correct email address)
                     *
                     * This is used when someone is specified to receive emails, but doesn't
                     * actually have a Schoolbox account. This is actually possible.
                     */
                    $recipient = new User();
                    $recipient->setId(-1);
                }
                // override any email retrieved from schoolbox with the one from synergy
                $recipient->setAltEmail($emailAddress);
                /* Add him to the stack
                 */
                $allRecipients->add($recipient);
            }
        }
        return $allRecipients;
    }

    /**
     * Public alias for the Report Card approach
     *
     * @return void
     */
    public function loadEmailRecipients()
    {
        $this->setEmailRecipients($this->findEmailRecipients($this));
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
     * Set recipients
     *
     * @param Collection|self[] $emailRecipients
     *
     * @return self
     */
    private function setEmailRecipients($emailRecipients)
    {
        $this->emailRecipients = $emailRecipients;
        return $this;
    }

    /**
     * Get recipients
     *
     * @return Collection|self[]
     */
    public function getEmailRecipients()
    {
        return $this->emailRecipients;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return array(
            'id'        => $this->getId(),
            'title'     => $this->getTitle(),
            'firstname' => $this->getFirstname(),
            'lastname'  => $this->getLastname(),
        );
    }

    /**
     * @param Collection|self[] $linkedUser
     */
    public function setLinkedUser($linkedUser)
    {
        $this->linkedUser = $linkedUser;
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
     * @return boolean
     */
    public function getHideNotificationsTray()
    {
        return $this->hideNotificationsTray;
    }

    /**
     * Set hide notifications tray
     *
     * @param boolean $hideNotificationsTray
     */
    public function setHideNotificationsTray($hideNotificationsTray)
    {
        $this->hideNotificationsTray = $hideNotificationsTray;
    }

    /**
     * `Schoolbox\Config\Loader` got removed in 16.0.0, but it got serialized.
     *
     * Put a new config loader in.
     */
    public function __wakeup()
    {
        if ($this->getConfigLoader() instanceof \__PHP_Incomplete_Class) {
            $this->setConfigLoader(new ConfigLoader());
        }
    }

    /**
     * @return Collection
     */
    public function getTeacherCollection() {
        return $this->teacherCollection;
    }

    /**
     * @param Collection $teacherCollection
     * @return $this
     */
    public function setTeacherCollection($teacherCollection) {
        $this->teacherCollection = $teacherCollection;
        return $this;
    }


    /**
     * @return GenericCollection|User[]
     */
    public function getChildren()
    {
        return $this->children;
    }


    /**
     * @param Collection|User[] $children
     * @return $this
     * @throws DomainException If collection has wrong member-item type
     */
    public function setChildren(Collection $children): self
    {
        $this->children->replaceWith($children);
        return $this;
    }
}
