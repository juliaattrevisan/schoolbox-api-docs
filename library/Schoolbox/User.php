<?php

/**
 * Generic user object for Schoolbox
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;

use JsonSerializable;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

class User implements JsonSerializable
{

    private $id;
    private $title;
    private $firstname;
    private $lastname;
    private $externalId;
    private $altEmail;
    private $webmail;
    private $username;
    private $password;
    private $year;
    private $schoolHouse;
    private $residentialHouse;
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

    private $role; //Users role within the system
    private $campusCollection; //Collection of campuses
    private $defaultCampus; //The users default campus
    private $superuser; //Bool
    private $enabled; //Bool

    /**
     * @var \Schoolbox\External\SubjectCollection
     */
    protected $subject_collection;

    /** @var Collection */
    protected $parent_collection;

    /** @var Collection|UserAccess[] */
    protected $accessList;

    //User config options
    private $localEmail; //Controls if user has local domain email username@domain
    private $eportfolioEnable; //Controls if user has eportfolio
    private $useInternalMail; //Controls mailto or internal schoolbox mail sending
    private $calendarView; //Controls default calendar view

    /**
     * A Collection of User objects who should be receiving email
     * notifications about this User. These recipients are usually,
     * but not necessarily, a parent of this User instance.
     *
     * @var null
     */
    private $emailRecipients = null;

    /**
     * Student's boarding status (only used if boarder leave module is enabled)
     */
    protected $boardingStatus;

    /**
     * @var IConfigLoader
     */
    protected $config_loader;

    public function __construct()
    {
        $this->setCampusCollection(new CampusCollection(array()));
        $this->setConfigLoader(new ConfigLoader());
        $this->setLinkedUser(new \Schoolbox\Collection(array()));
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
        return $this->getCampusCollection()->find($campus);
    }

    /**
     * Returns the users default campus
     *
     * @return Campus
     */
    public function getDefaultCampus()
    {
        return $this->defaultCampus;
    }

    /**
     * Returns a list of collections the user belongs in
     *
     * @return CampusCollection|Campus[]
     */
    public function getCampusCollection()
    {
        return $this->campusCollection;
    }


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

    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }

    public function getEmail()
    {
        if ($this->localEmail == true) {
            return $this->username . '@' . $this->config_loader->get('mail_address');
        }
        return $this->altEmail;
    }

    public function getFullName()
    {
        return $this->formatName();
    }


    /**
     * Format a name from a user object
     *
     * @param null $format
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

    public function setAltEmail($altEmail)
    {
        $this->altEmail = $altEmail;
        return $this;
    }

    public function getAltEmail()
    {
        return $this->altEmail;
    }

    /**
     * @param int $webmail
     *
     * @return self
     */
    public function setWebmail($webmail)
    {
        $this->webmail = $webmail;
        return $this;
    }

    /**
     * @return int
     */
    public function getWebmail()
    {
        return $this->webmail;
    }


    public function setCalendarView($calendarView)
    {
        $this->calendarView = $calendarView;
        return $this;
    }

    public function getCalendarView()
    {
        return $this->calendarView;
    }

    public function setEportfolioEnable($eportfolioEnable)
    {
        $this->eportfolioEnable = $eportfolioEnable;
        return $this;
    }

    public function getEportfolioEnable()
    {
        return $this->eportfolioEnable;
    }

    public function setExternalId($externalId)
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getExternalId()
    {
        return $this->externalId;
    }

    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
        return $this;
    }

    public function getFirstname()
    {
        return $this->firstname;
    }

    public function setHash($hash)
    {
        $this->hash = $hash;
        return $this;
    }

    public function getHash()
    {
        return $this->hash;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
        return $this;
    }

    public function getLastname()
    {
        return $this->lastname;
    }

    public function setLocalEmail($localEmail)
    {
        $this->localEmail = $localEmail;
        return $this;
    }

    public function getLocalEmail()
    {
        return $this->localEmail;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setResidentialHouse($residentialHouse)
    {
        $this->residentialHouse = $residentialHouse;
        return $this;
    }

    public function getResidentialHouse()
    {
        return $this->residentialHouse;
    }

    public function setSchoolHouse($schoolHouse)
    {
        $this->schoolHouse = $schoolHouse;
        return $this;
    }

    public function getSchoolHouse()
    {
        return $this->schoolHouse;
    }

    public function setSuperuser($superuser)
    {
        $this->superuser = $superuser;
        return $this;
    }

    public function getSuperuser()
    {
        return $this->superuser;
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setUseInternalMail($useInternalMail)
    {
        $this->useInternalMail = $useInternalMail;
        return $this;
    }

    public function getUseInternalMail()
    {
        return $this->useInternalMail;
    }

    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setYear($year)
    {
        $this->year = $year;
        return $this;
    }

    public function getYear()
    {
        return $this->year;
    }

    public function setEnabled($enabled)
    {
        // explicit cast
        $this->enabled = $enabled ? true : false;
        return $this;
    }

    public function getEnabled()
    {
        // explicit cast
        return $this->enabled ? true : false;
    }

    public function setCampusCollection($campusCollection)
    {
        $this->campusCollection = $campusCollection;
        return $this;
    }

    public function setDefaultCampus($defaultCampus)
    {
        $this->defaultCampus = $defaultCampus;
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
     * @param \Schoolbox\External\SubjectCollection $subject_collection
     *
     * @return self
     */
    public function setSubjectCollection($subject_collection)
    {
        $this->subject_collection = $subject_collection;
        return $this;
    }

    /**
     * @return \Schoolbox\External\SubjectCollection
     */
    public function getSubjectCollection()
    {
        return $this->subject_collection;
    }


    /**
     * @param \Schoolbox\IConfigLoader $config_loader
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
     * @return \Schoolbox\IConfigLoader
     */
    public function getConfigLoader()
    {
        return $this->config_loader;
    }

    /**
     * Is this user a parent of $child?
     *
     * Requires that `loadParents()` has been called on $child.
     *
     * @param  User $child
     * @return bool
     */
    public function isParentOf(User $child)
    {
        foreach ($child->getParents() as $parent) {
            if ($parent->getId() == $this->getId()) {
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
     * Load parents into object from external db
     */
    public function loadParents()
    {
        $this->parent_collection = new Collection();
        // TODO remove handler dependency
        $DEPRECATED_EXTERNAL_USER = new \External_User();
        $allParents               = $DEPRECATED_EXTERNAL_USER->getStudentContactList($this->externalId);
        $parentRoles              = RoleMapper::getInstance()->findByColumn(
            'group_type',
            Role::PARENT
        )->getColumnValues('id');
        if (is_array($allParents)) {
            foreach ($allParents as $currentParent) {
                /* Add parent
                 */
                if (!empty($currentParent['StudentContactID'])) {
                    $parentCollection = UserMapper::getInstance()->findByColumnList(
                        array(
                            'synergy_id' => $currentParent['StudentContactID'],
                            'group_id'   => $parentRoles
                        )
                    );
                    if ($parentCollection->count()) {
                        $this->parent_collection->add($parentCollection->first());
                    }
                }
                /* Add speece
                 */
                if (!empty($currentParent['StudentContactSpouseID'])) {
                    $spouseCollection = UserMapper::getInstance()->findByColumnList(
                        array(
                            'synergy_id' => $currentParent['StudentContactSpouseID'],
                            'group_id'   => $parentRoles
                        )
                    );
                    if ($spouseCollection->count()) {
                        $this->parent_collection->add($spouseCollection->first());
                    }
                }
            }
        }
    }

    /**
     * Get the parents associated with this user
     *
     * @return Collection
     */
    public function getParents()
    {
        return $this->parent_collection;
    }

    /**
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
     * @param $user
     *
     * @return \Schoolbox\Collection
     */
    private function findEmailRecipients($user)
    {
        $parentRoles = \Schoolbox\RoleMapper::getInstance()->findByColumn(
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
                    $thisRecipient = \Schoolbox\UserMapper::getInstance()->findByColumnList(
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
     */
    public function loadEmailRecipients()
    {
        $this->setEmailRecipients($this->findEmailRecipients($this));
    }

    /* Getters/Setters
     */

    /**
     * Set recipients
     *
     * @param \Schoolbox\Collection $emailRecipients
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
     * @return \Schoolbox\Collection
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
     * @param \Schoolbox\Collection $linkedUser
     */
    public function setLinkedUser($linkedUser)
    {
        $this->linkedUser = $linkedUser;
    }

    /**
     * @return \Schoolbox\Collection
     */
    public function getLinkedUser()
    {
        return $this->linkedUser;
    }


}
