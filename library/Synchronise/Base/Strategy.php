<?php
/**
 * Base sync for all users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

abstract class Synchronise_Base_Strategy
{

    const DEFAULT_ADMIN_GROUP   = 1;
    const DEFAULT_STAFF_GROUP   = 2;
    const DEFAULT_STUDENT_GROUP = 3;
    const DEFAULT_PARENT_GROUP  = 4;

    /**
    * Are updates performed? If not, then a plan is output instead.
    *
    * @var bool
    */
    protected $perform_update = false;

    /**
    * @var User
    */
    protected $user_handler;

    /**
    * @var Group
    */
    protected $group_handler;

    /**
    * @var External_User
    */
    protected $external_user_handler;

    /**
    * List of users to enter into Schoolbox.
    *
    * @var array
    */
    protected $list = array();

    /**
    * List of hydrators to apply for converting details from external to
    * Schoolbox database.
    *
    * @var Synchronise_Base_IHydrator[]
    */
    protected $hydratorList = array();

    /**
    * @var Synchronise_Base_Verbose
    */
    protected $verbose_filter;

    /**
    * The title for the synchronisation, output when the sync is run.
    *
    * @var string
    */
    protected $title = "Update Schoolbox Parent Details Synchronisation";

    /**
    * Constructor
    * Instantiate Handlers and things
    */
    public function __construct()
    {
     //
     // Set up stupid handlers
        $this->user_handler          = new User();
        $this->group_handler         = new Group();
        $this->external_user_handler = new External_User();
     //
     // Set up a base verbose filter, which you can override in setup()
        $this->verbose_filter = new Synchronise_Base_Verbose();
        $this->setup();
    }

    /**
    * Setup hook for this synchroniser, after __construct is called.
    * By default does nothing.
    */
    protected function setup()
    {
    }

    /**
    * Adds a hydrator for converting data from external database to data for
    * this school.
    *
    * @param  Synchronise_Base_IHydrator $hydrator
    * @return $this
    */
    protected function addHydrator(Synchronise_Base_IHydrator $hydrator)
    {
        $this->hydratorList[] = $hydrator;
        return $this;
    }

    /**
    * Clears all hydrators added in addHydrator().
    *
    * @return $this
    */
    protected function clearHydratorList()
    {
        $this->hydratorList = array();
        return $this;
    }

    /**
    * Does this strategy have any hydrators set?
    *
    * @return bool
    */
    protected function hasHydrator()
    {
        return !empty($this->hydratorList);
    }

    /**
    * @return Synchronise_Base_IHydrator[]
    */
    protected function getHydratorList()
    {
        return $this->hydratorList;
    }

    /**
    * If the users exist in Synergy but not in Schoolbox, create an account for
    * them
    *
    * @return void
    */
    public function createIfExists()
    {
        $this->verbose_filter->intro();
        foreach ($this->list as $user) {
            $new_user = $this->setupUserDetails($user);
            if (empty($new_user)) {
                continue;
            }
            $user_id  = $this->executeCreateUpdate($new_user);
            if ($user_id > 0) {
                $new_user['id'] = $user_id;
                $this->executePostCreateUpdate($new_user, $user);
            }
        }
        $this->verbose_filter->summary();
    }

    /**
    * Identical algorithm used to create/update a user
    *
    * @param  array $newUser The record that is to be added
    * @return int            The user ID if the create/update was performed, or
    *                        zero otherwise.
    */
    protected function executeCreateUpdate($newUser)
    {
        $this->verbose_filter->setNewUser($newUser);

        // Does this person have a username? If not, skip them
        if ($this->hasUsername($newUser)) {
            $existing = $this->userExists($newUser);
            if ($existing) {
                // If the current user exists in Schoolbox, update them
                if ($this->perform_update) {
                    $newUser['id'] = $existing['uid'];
                    if ($this->user_handler->update($newUser)) {
                        $this->verbose_filter->performUpdate();
                        $this->verbose_filter->incrementUpdated();
                    } else {
                        $this->verbose_filter->skip(
                            'because user exists with different username.'
                        );
                        $this->verbose_filter->incrementSkipped();
                    }
                    return $existing['uid'];
                } else {
                    $this->verbose_filter->planUpdate();
                    $this->verbose_filter->incrementUpdated();
                }
            } else {
                // Perform action; report what happened
                if ($this->perform_update) {
                    $user_id = $this->user_handler->create($newUser);
                    if ($user_id) {
                        $this->verbose_filter->performCreation();
                        $this->verbose_filter->incrementCreated();
                    } else {
                        // User wasn't created - output a fail message
                        $this->verbose_filter->skip(
                            'because user either already exists or campus is not set.'
                        );
                        $this->verbose_filter->incrementSkipped();
                    }
                    return $user_id;
                } else {
                    $this->verbose_filter->planCreation();
                    $this->verbose_filter->incrementCreated();
                }
            }
        } else {
            $this->verbose_filter->skip();
            $this->verbose_filter->incrementSkipped();
        }
        return 0;
    }

    /**
    * Called after a user is added or created, or planned to be added or
    * created.
    *
    * @param array $newUser      The record that was created or updated
    * @param array $externalUser The external record that was read from
    *                            whatever the external source was
    */
    protected function executePostCreateUpdate($newUser, $externalUser)
    {
        return;
    }

    /**
     * Handle users who exist in Schoolbox but not Synergetic
     * Blank code block, allowing customisation per site
     */
    public function removeIfNotExists()
    {
    }

    /**
    * Determine if this person has a username
    *
    * @param  $currentUser
    * @return bool
    */
    protected function hasUsername($currentUser)
    {
        if (isset($currentUser['username'])) {
            return strlen(trim($currentUser['username'])) > 0;
        }

        return false;
    }

    /**
    * Retrieves the user that exists in Schoolbox for this user account, or
    * an empty value if they don't.
    *
    * @param  array $user
    *
    * @return array
    */
    protected function userExists($user)
    {
        return $this->user_handler->getBySynergyAndGroup($user['synergy_id'], $user['group_id']);
    }

    /**
    * @param  \Synchronise_Base_Verbose $verbose_filter
    * @return $this
    */
    public function setVerboseFilter($verbose_filter)
    {
        $this->verbose_filter = $verbose_filter;
        return $this;
    }

    /**
    * @return \Synchronise_Base_Verbose
    */
    public function getVerboseFilter()
    {
        return $this->verbose_filter;
    }

    /**
    * @param  bool  $perform_update
    * @return $this
    */
    public function setPerformUpdate($perform_update)
    {
        $this->perform_update = $perform_update;
        return $this;
    }

    /**
    * @return bool
    */
    public function getPerformUpdate()
    {
        return $this->perform_update;
    }

    /**
    * Print a nice heading before the rest of the confirmation messages
    *
    * @return void
    */
    public function generateTitle()
    {
        echo "\n------------------------------------------------------------\n";
        echo $this->title;
        echo "\n------------------------------------------------------------\n";
    }


    /**
    * Methods for retrieving Schoolbox fields from an external data set.
    * These may be overridden on a per-sync basis.
    */

    /**
    * Gets Schoolbox username from the external data set.
    *
    * @param  array $currentUser
    *
    * @return string
    */
    public function getUsernameFromUser($currentUser)
    {
        return $currentUser['NetworkLogin'];
    }

    /**
    * Gets Schoolbox user's title (Mr., Ms., etc.) from the external data set.
    *
    * @param  array $currentUser
    *
    * @return string
    */
    public function getTitleFromUser($currentUser)
    {
        return '';
    }

    /**
    * Gets Schoolbox user's first name from the external data set.
    *
    * @param  array $currentUser
    *
    * @return string
    */
    public function getFirstNameFromUser($currentUser)
    {
        return '';
    }

    /**
    * Gets Schoolbox user's last name from the external data set.
    *
    * @param  array $currentUser
    *
    * @return string
    */
    public function getLastNameFromUser($currentUser)
    {
        return '';
    }

    /**
    * Gets Schoolbox user's external database ID from the external data set.
    *
    * @param  array $currentUser
    *
    * @return string
    */
    public function getExternalIdFromUser($currentUser)
    {
        return '';
    }

    /**
    * Gets Schoolbox user's alternate email address from the external data set.
    *
    * @param  array $currentUser
    *
    * @return string
    */
    public function getAltEmailFromUser($currentUser)
    {
        return null;
    }

    /**
    * Gets Schoolbox user's local email setting from the external data set.
    *
    * For most schools, this will be a school-wide setting instead of a
    * per-user setting.
    *
    * @param  array $currentUser
    *
    * @return int
    */
    public function getLocalEmailFromUser($currentUser)
    {
        return 0;
    }

    /**
    * Gets Schoolbox default campus from the external data set.
    *
    * @param  array $currentUser
    *
    * @return int
    */
    public function getDefaultCampusFromUser($currentUser)
    {
        return 1;
    }

    /**
    * Gets Schoolbox campus list from the external data set.
    *
    * @param  array $currentUser
    *
    * @return array[int]
    */
    public function getCampusListFromUser($currentUser)
    {
        return array($this->getDefaultCampusFromUser($currentUser));
    }

    /**
    * Gets student's year from the external data set.
    *
    * @param  array $currentUser
    *
    * @return int
    */
    public function getYearFromUser($currentUser)
    {
        return null;
    }

    /**
    * Gets Schoolbox group from the external data set.
    *
    * @param  array $currentUser
    *
    * @return int
    */
    public function getGroupIDFromUser($currentUser)
    {
        return 1;
    }

    /**
    * Gets whether user is a superuser from the external data set.
    *
    * @param  array $currentUser
    *
    * @return bool
    */
    public function getIsSuperuserFromUser($currentUser)
    {
        return null;
    }

    /**
    * Gets user's house ID from the external data set.
    *
    * @param  array $currentUser
    *
    * @return int
    */
    public function getHouseFromUser($currentUser)
    {
        return 0;
    }

    /**
    * Gets user's residential house ID from the external data set.
    *
    * @param  array $currentUser
    *
    * @return int
    */
    public function getResidentialHouseFromUser($currentUser)
    {
        return 0;
    }

    /**
    * Gets user's eportfolio status from the external data set.
    *
    * @param  array $currentUser
    *
    * @return bool
    */
    public function getEportfolioEnabledFromUser($currentUser)
    {
        return null;
    }

    /**
    * Gets user's webmail status from external data set
    *
    * @param $currentUser
    * @return int
    */
    public function getWebmailFromUser($currentUser)
    {
        return 0;
    }

    /**
    * Gets user's external mail handling status from external data set
    *
    * This is known as mail_handler and corresponds to the 'Use External Mail'
    * checkbox when editing a user
    *
    * @param $currentUser
    * @return int
    */
    public function getExternalMailFromUser($currentUser)
    {
        return 0;
    }

    /**
    * Gets whether user is enabled from external data set.
    *
    * @param  array $currentUser
    * @return bool  Currently returns null so its value isn't overridden.
    */
    public function getAccountEnabledFromUser($currentUser)
    {
        return null;
    }

    /**
    * Creates an array for insertion into the Schoolbox users table.
    *
    * @param  array $external_user
    *
    * @return array
    */
    public function setupUserDetails($external_user)
    {
        $newUser = array();
        if ($this->hasHydrator()) {
            foreach ($this->getHydratorList() as $hydrator) {
                try {
                    $newUser = $hydrator->hydrate($external_user, $newUser);
                } catch (Exception $e) {
                    // Skip user as something is not good.
                    // Raise a skip message, though.
                    $this->verbose_filter->skip('user because: ' .$e->getMessage() ?: 'exception thrown');
                    $this->verbose_filter->incrementSkipped();
                    return false;
                }
            }
            return $newUser;
        }
        $newUser['username']             = $this->getUsernameFromUser($external_user);
        $newUser['title']                = $this->getTitleFromUser($external_user);
        $newUser['firstname']            = $this->getFirstNameFromUser($external_user);
        $newUser['lastname']             = $this->getLastNameFromUser($external_user);
        $newUser['synergy_id']           = $this->getExternalIdFromUser($external_user);
        $newUser['alt_email']            = $this->getAltEmailFromUser($external_user);
        $newUser['local_email']          = $this->getLocalEmailFromUser($external_user);
        $newUser['year']                 = $this->getYearFromUser($external_user);
        $newUser['defaultCampus']        = $this->getDefaultCampusFromUser($external_user);
        $newUser['campus']               = $this->getCampusListFromUser($external_user);
        $newUser['group_id']             = $this->getGroupIDFromUser($external_user);
        $newUser['su']                   = $this->getIsSuperuserFromUser($external_user);
        $newUser['house']                = $this->getHouseFromUser($external_user);
        $newUser['residential_house_id'] = $this->getResidentialHouseFromUser($external_user);
        $newUser['eportfolio_enabled']   = $this->getEportfolioEnabledFromUser($external_user);
        $newUser['webmail']				 = $this->getWebmailFromUser($external_user);
        $newUser['mail_handler']		 = $this->getExternalMailFromUser($external_user);
        $newUser['enabled']              = $this->getAccountEnabledFromUser($external_user);
        return $this->clearFields($newUser);
    }

    /**
    * Filters fields with a null value out of user details.
    *
    * @param  array $new_user
    *
    * @return array
    */
    protected function clearFields($new_user)
    {
           return array_filter($new_user, function($v) { return isset($v);
           });
    }

    /**
    * Abstract methods
    */

    /**
    * Generates the list of users for sync, and jams them into $this->list.
    *
    * @return void
    */
    abstract public function generateList();
}
