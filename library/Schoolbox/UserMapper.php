<?php
/**
 * Mapper between user objects and database rows.
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;

use PDOStatement;
use Schoolbox;
use UnexpectedValueException;

/**
 * Class UserMapper
 *
 * @package Schoolbox
 * @method User findById($id)
 * @method User findOneByColumn($columnName, $columnValue, $order = [])
 * @method User findOneByColumnList($columnList, $order = [])
 * @method User hydrate($row, $alias = '', $callback = null)
 */
class UserMapper extends Mapper
{
    /**
     * If in use (ie not null), this should mirror all non-softDeleted users in DB.
     * If we save, delete, soft-delete, or restore a user, we must update the
     * cache via callbacks to ensure integrity.
     *
     * @var array|null $usernameCache Maps 'username' => 'id'.
     */
    protected $usernameCache;

    protected function mapping()
    {

        $this->hasTable('user');
        $this->hasModel(User::class);

        $this->hasMap(
            array(
                'column' => 'id',
                'property' => 'id',
                'type' => Mapper::TYPE_INTEGER,
                'identifier' => true
            )
        );

        $this->hasMap(
            array(
                'column' => 'username',
                'property' => 'username',
                'type' => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column' => 'password',
                'property' => 'password',
                'type' => Mapper::TYPE_STRING,
            )
        );


        $this->hasMap(
            array(
                'column' => 'title',
                'property' => 'title',
                'type' => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column' => 'firstname',
                'property' => 'firstname',
                'type' => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column' => 'lastname',
                'property' => 'lastname',
                'type' => Mapper::TYPE_STRING,
            )
        );


        $this->hasMap(
            array(
                'column' => 'synergy_id',
                'property' => 'externalId',
                'type' => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column' => 'year',
                'property' => 'year',
                'type' => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column' => 'house',
                'property' => 'schoolHouse',
                'type' => Mapper::TYPE_MODEL,
                'join' => 'house',
                'join_def' => array(
                    'mapper' => \HouseMapper::class,
                    'this_key' => 'house',
                    'other_key' => 'id'
                )
            )
        );

        $this->hasMap(
            array(
                'column' => 'residential_house_id',
                'property' => 'residentialHouse',
                'type' => Mapper::TYPE_MODEL,
                'join' => 'reshouse',
                'join_def' => array(
                    'mapper' => \HouseResidentialMapper::class,
                    'this_key' => 'residential_house_id',
                    'other_key' => 'id'
                )
            )
        );


        $this->hasMap(
            array(
                'column' => 'local_email',
                'property' => 'localEmail',
                'type' => Mapper::TYPE_BOOLEAN,
            )
        );

        $this->hasMap(
            array(
                'column' => 'alt_email',
                'property' => 'altEmail',
                'type' => Mapper::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column' => 'webmail',
                'property' => 'webmail',
                'type' => Mapper::TYPE_BOOLEAN,
            )
        );

        $this->hasMap(
            array(
                'column' => 'mail_handler',
                'property' => 'useInternalMail',
                'type' => Mapper::TYPE_BOOLEAN,
            )
        );

        $this->hasMap(
            array(
                'column' => 'hash',
                'property' => 'hash',
                'type' => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column' => 'key',
                'property' => 'key',
                'type' => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column' => 'eportfolio_enabled',
                'property' => 'eportfolioEnable',
                'type' => Mapper::TYPE_BOOLEAN,
            )
        );

        $this->hasMap(
            array(
                'column' => 'group_id',
                'property' => 'role',
                'type' => Mapper::TYPE_MODEL,
                'join' => 'role',
            )
        );

        $this->hasMap(
            array(
                'column' => 'enabled',
                'property' => 'enabled',
                'type' => Mapper::TYPE_BOOLEAN,
            )
        );

        $this->hasMap(
            array(
                'column' => 'hide_contacts',
                'property' => 'hideContacts',
                'type' => Mapper::TYPE_BOOLEAN,
            )
        );

        $this->hasMap(
            array(
                'column' => 'hide_timetable',
                'property' => 'hideTimetable',
                'type' => Mapper::TYPE_BOOLEAN,
            )
        );

        $this->hasMap(
            array(
                'column' => 'hide_notifications_tray',
                'property' => 'hideNotificationsTray',
                'type' => Mapper::TYPE_BOOLEAN,
            )
        );

        //
        // Boarder leave field is only there if the boarder leave module is
        // enabled.
        $this_object = $this;
        $this->hasMap(
            array(
                'column' => 'boarding_status',
                'property' => 'boardingStatus',
                'type' => self::TYPE_STRING,
                'enabled' => function () use ($this_object) {
                        return $this_object->getConfigLoader()->get('boarder_leave_enabled');
                    }
            )
        );
        unset($this_object);

        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasJoin(
            'role',
            array(
                'mapper' => '\\Schoolbox\\RoleMapper',
                'this_key' => 'group_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'userAccess',
            array(
                'mapper' => '\\Schoolbox\\Permission\\Access\\UserAccessMapper',
                'this_key' => 'id',
                'other_key' => 'uid',
                'multiple' => true,
            )
        );

        $this->joinsVia('user_link', UserMapper::class, 'id', 'linked_user_id', 'user_id', null, null, null, 'linkedUser');

        $this->joinsVia('userCampus', CampusMapper::class, 'id', 'campus', 'uid', 'id', null, null, 'campusCollection');

        $this->registerCallback(self::CALLBACK_PRESAVE, function ($model) {

            /** @var User $model */

            if (!$model->getKey()) {
                // ensure a key is set when persisting users
                $model->setKey(substr(md5(uniqid() . time()), 0, 25));
            }

            if ($model->getRetrievablePassword()) {
                $model->setHash(md5($model->getUsername() . ':' . $this->getConfigLoader()->get('site_location') . ':' . $model->getRetrievablePassword()));
            }

            return true;
        });

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyUniqueUsername']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidUsername']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidRole']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyHasCampus']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidFirstName']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidLocalEmail']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidWebmail']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidInternalMail']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidEportfolioEnabled']);

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateSuperuserStatus']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateDefaultCampus']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'addUsernameToCache']);

        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'removeUsernameFromCache']);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'removeUsernameFromCache']);

        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'addUsernameToCache']);
    }

    /**
     * Finds a user by their email address.
     *
     * Allows search on both `alt_email` field and `username`@`config.mail_address`.
     *
     * @param string $email
     * @return Collection|User[]
     */
    public function findByEmail($email)
    {
        $query  = $this->getSelectString() . ' WHERE (user.alt_email = :altEmail AND user.local_email IS FALSE)';
        $params = ['altEmail' => $email];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailParts = explode('@', $email, 2);
            if (count($emailParts) === 2) {
                // Compare this email's hostname against the actual hostname
                list($username, $domain) = $emailParts;
                $thisDomain = $this->getConfigLoader()->get('mail_address');
                if ($domain == $thisDomain) {
                    $query .= ' OR (user.username = :username AND user.local_email IS TRUE)';
                    $params['username'] = $username;
                }
            }
        }
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return $this->hydrateSelect($statement);
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString('user')}, IF(su.id, 1, 0) as `user.superuser`,
                    {$this->getJoinColumnString('role', 'g')},
                    {$this->getJoinColumnString('userCampus', 'c')}, v_c.defaultCampus as `uc.defaultCampus`,
                    {$this->getColumnString('lu')}
            FROM `user`
            LEFT JOIN superusers su ON su.id = user.id
            LEFT JOIN {$this->getJoinSql('role', 'user', 'g')}
            LEFT JOIN {$this->getJoinSql('userCampus', 'user', 'c')}
            LEFT JOIN {$this->getJoinSql('user_link', 'user', 'lu')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var User[] $users */
        $users = [];

        foreach ($statement as $row) {

            $userId = $row['user.id'];

            if (empty($users[$userId])) {
                $users[$userId] = $this->hydrate($row, 'user');
                $users[$userId]->setSuperUser($row['user.superuser'] == 1);
                $users[$userId]->setRole($this->hydrateJoin($row, 'role', 'g'));
            }

            $campus = $this->hydrateJoin($row, 'userCampus', 'c');

            /* If the campus object has no id, then it spawned a campus
             * object from nonexistent data (e.g. a user with no campus)
             * In this case, the campus object will be totally empty but
             * still occupy slots in the user's campus collection stack
             */
            if ($campus->getId()) {
                // Don't double add
                $users[$userId]->getCampusCollection()->setOffset($campus->getId(), $campus);

                if ($row['uc.defaultCampus'] == 1) {
                    $users[$userId]->setDefaultCampus($campus);
                }
            }

            $linkedUser = $this->hydrate($row, 'lu');

            if ($linkedUser->getId()) {
                // Don't double add
                $users[$userId]->getLinkedUser()->setOffset($linkedUser->getId(), $linkedUser);
            }
        }

        return $this->createCollection(array_values($users));
    }

    /**
     * All Permissions
     *
     * Get a list of all Role- and Group-level userAccess instances
     * Default behaviour, just as it always was
     *
     * @param User $user
     * @return mixed
     */
    protected function findAccessList(User $user)
    {
        return $this->getJoinMapper('userAccess')->findAllForUser($user);
    }

    /**
     * Find a user by their external id and come with roles, campus, etc
     *
     * @param int    $id
     * @param string $roleType If provided, restrict to this role type.
     *                         One of `Role::STAFF`, `Role::STUDENT`, `Role::PARENT`, `Role::GUEST`
     * @return User
     * @throws Mapper\Exception
     */
    public function findByExternalId($id, $roleType = null) {
        $user = false;
        $roleMapper = new RoleMapper;
        $campusMapper = new CampusMapper;
        $linkedUserList = array();

        $query = "
            SELECT
                {$this->getColumnString('u')}, IF(su.id, 1, 0) as `u.superuser`,
                {$roleMapper->getColumnString('g')},
                {$campusMapper->getColumnString('c')}, uc.defaultCampus as `uc.defaultCampus`,
                {$this->getColumnString('lu')}
            FROM `user` u
            LEFT JOIN `groups` g ON group_id = g.`id`
            LEFT JOIN userCampus uc ON uc.`uid`=u.id
            LEFT JOIN superusers su ON su.id = u.id
            LEFT JOIN luCampus c ON c.id = uc.`campus`
            LEFT JOIN {$this->getJoinSql('user_link', 'u', 'lu')}
            WHERE u.synergy_id = ? AND u.deleted_at is null
        ";
        $params = [$id];
        if (isset($roleType)) {
            $query .= ' AND g.group_type = ?';
            $params[] = $roleType;
        }

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        /** @var User $user */
        foreach ($statement as $row) {
            if (!($user instanceof User)) {
                $user = $this->hydrate($row, 'u');
                $user->setSuperUser($row['u.superuser'] == 1);
                $user->setRole($roleMapper->hydrate($row, 'g'));

                $accessList = $this->findAccessList($user);

                $user->setAccessList($accessList);
            }

            $campus = $campusMapper->hydrate($row, 'c');

            /* If the campus object has no id, then it spawned a campus
             * object from nonexistent data (e.g. a user with no campus)
             * In this case, the campus object will be totally empty but
             * still occupy slots in the user's campus collection stack
             */
            if ($campus->getId()) {
                $user->getCampusCollection()->add($campus);

                if ($row['uc.defaultCampus'] == 1) {
                    $user->setDefaultCampus($campus);
                }
            }

            $linkedUser = $this->hydrate($row,'lu');
            if ($linkedUser->getId()) {
                $linkedUserList[$linkedUser->getId()] = $linkedUser;
            }
        }

        if (count($linkedUserList)) {
            $linkedCollection = new Collection($linkedUserList);
            $user->setLinkedUser($linkedCollection);
        }

        if (!($user instanceof User)) {
            throw new Schoolbox\Mapper\Exception('Instance of object not found');
        }
        return $user;
    }

    /**
     * Fetches the user, and all of the user's subjects.
     *
     * @throws Schoolbox\Mapper\Exception if user wasn't found
     * @param  int $id
     * @return \Schoolbox\User
     */
    public function findWithSubjects($id)
    {
        $statement = $this->database->prepare(
            "SELECT {$this->getColumnString('u')} FROM {$this->table} AS u WHERE u.id=?"
        );
        $statement->execute(array($id));

        if ($row = $statement->fetch()) {
            $user = $this->hydrate($row, 'u');
            //
            // Set subject collection here.
            $subject_mapper = new External\SubjectMapper();
            $user->setSubjectCollection($subject_mapper->findAllForStudent($user));
            return $user;
        }
        throw new Schoolbox\Mapper\Exception();
    }

    /**
     * Find all the users which match $name and are in group_id array $permittedValues
     *
     * @param $name
     * @param $permittedValues
     * @return Schoolbox\Collection
     */
    public function findByNameAndGroup($name, $permittedValues)
    {
        $collection = new Schoolbox\Collection();
        $roleMapper = new RoleMapper();

        $permittedValuesSql = implode(',', $permittedValues);

        $query = "
            SELECT
                {$this->getColumnString('u')},
                {$roleMapper->getColumnString('g')}
            FROM `{$this->table}` u
            LEFT JOIN `groups` g ON group_id = g.`id`
            WHERE (firstname LIKE ? OR lastname LIKE ?)
            AND group_id IN ({$permittedValuesSql})
        ";

        $statement = $this->database->prepare($query);

        $statement->execute(array("%$name%", "%$name%"));

        foreach ($statement as $row) {
            $user = $this->hydrate($row, 'u');
            $user->setRole($roleMapper->hydrate($row, 'g'));

            $collection->add($user);
        }

        return $collection;
    }

    /**
     * Fetches a single user ID, for use by GizaUser_DataMapper.
     *
     * This is to be @deprecated when the whole User object is stored in the
     * session, but has been added for now because it's injection safe (unlike
     * the Giza mappers)
     *
     * @param string $username
     * @return int
     */
    public function getIdForUsername($username)
    {
        $query = "SELECT `id` FROM `{$this->getTable()}` WHERE `username` = :username";
        $statement = $this->database->prepare($query);
        $statement->bindValue('username', $username, \PDO::PARAM_STR);
        $statement->execute();
        return $statement->fetchColumn();
    }

    /**
     * Get a user object stub, for when a user does not exist
     *
     * @return User
     */
    public function getUnknownUser()
    {
        $user = new User;
        $user->setLastname('Unknown User');
        return $user;
    }

    /**
     * Retrieves all users who have the given level of access (or higher) to the
     * requested service.
     *
     * @param  int $service The service level. A UserAccess::SERVICE_*
     *                                constant.
     * @param  int $accessLevel The access level. A UserAccess::ACCESS_*
     *                                constant.
     * @param  int $serviceIdentifier If provided, the campus or homepage that
     *                                $service is for.
     * @return Collection|User[] A list of users who have this level of access
     *                           to the given service.
     */
    public function findByAccess($service, $accessLevel, $serviceIdentifier = null)
    {
        $query = "
            SELECT DISTINCT {$this->getColumnString('u')}
            FROM {$this->getTable()} u
            INNER JOIN {$this->getJoinSql('userAccess', 'u', 'ua')}
            WHERE ua.service=:service
            AND ua.type >=:accessLevel
        ";
        $params = array(
            'service' => $service,
            'accessLevel' => $accessLevel,
        );
        if (isset($serviceIdentifier)) {
            $query .= 'AND ua.sid=:serviceIdentifier';
            $params['serviceIdentifier'] = $serviceIdentifier;
        }
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return $this->hydrateList($statement, 'u');
    }

    /**
     * @deprecated Don't even ask what a password hashing method is doing in a mapper.
     *             (this will be removed in 16 and replaced with `password_hash`)
     *
     * @param  string $password
     * @return string
     */
    public function hashPassword($password)
    {
        $statement = $this->getDatabase()->prepare('SELECT PASSWORD(?)');
        $statement->execute([$password]);
        return $statement->fetchColumn();
    }

    /**
     * Pre-save callback for verifying whether a user's name is unique.
     *
     * For data-integrity, this method relies on a post-save/restore
     * callback to add any new users to the cache.
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if it isn't unique
     */
    public function verifyUniqueUsername(User $user)
    {
        // Load cache on first invocation
        if(!is_array($this->usernameCache)){
            $this->usernameCache = [];
            $query = $this->selectScalarByColumnList("{$this->getTable()}.id, {$this->getTable()}.username", []);
            $query->execute();
            while($row = $query->fetch(\PDO::FETCH_ASSOC)){
                $this->usernameCache[$row['username']] = $row['id'];
            }
        }

        // Check cache
        if(array_key_exists($user->getUsername(), $this->usernameCache)) {
            if($this->usernameCache[$user->getUsername()] != $user->getId()){
                throw new UnexpectedValueException(
                    sprintf('A user with username "%s" already exists.', $user->getUsername() )
                );
            }
        }

        // No duplicates
        return true;
    }

    /**
     * Post-save + Post-restore callback to ensure username cache is up-to-date
     *
     * @param User $user
     */
    public function addUsernameToCache(User $user){
        // Is cache in use?
        if(is_array($this->usernameCache)){
            $this->usernameCache[$user->getUsername()] = $user->getId();
        }
    }

    /**
     * Post-delete + Post-softDelete callback to ensure username cache is up-to-date
     *
     * @param User $user
     */
    public function removeUsernameFromCache(User $user){
        // Is cache in use?
        if(is_array($this->usernameCache)){
            // Is username in cache?
            if(isset($this->usernameCache[$user->getUsername()])){
                // Does cache record belong to this user?
                if($this->usernameCache[$user->getUsername()] == $user->getId()){
                    unset($this->usernameCache[$user->getUsername()]);
                }
            }
        }
    }

    /**
     * Pre-save callback for verifying whether a user's name is not null or zero-length
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if user's name is null or zero-length
     */
    public function verifyValidUsername(User $user)
    {
        if ($user->getUsername() === null || !strlen($user->getUsername())) {
            throw new UnexpectedValueException('Username cannot be null');
        }
        return true;
    }

    /**
     * Pre-save callback for verifying whether a user's role type is set.
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if role type is null
     */
    public function verifyValidRole(User $user)
    {
        if (!$user->getRole() instanceof Role || null === $user->getRole()->getId()) {
            throw new UnexpectedValueException('User role cannot be null');
        }
        return true;
    }

    /**
     * Pre-save callback for ensuring a user has at least one campus
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if user has no Campus
     */
    public function verifyHasCampus(User $user)
    {
        if ($user->getCampusCollection()->count() < 1 ) {
            throw new UnexpectedValueException('User must have at least one campus');
        }
        return true;
    }

    /**
     * Pre-save callback for ensuring a user's firstname is not null or zero-length
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if firstname is null or zero-length
     */
    public function verifyValidFirstName(User $user)
    {
        if ($user->getFirstname() === null || !strlen($user->getFirstname())) {
            throw new UnexpectedValueException('Firstname cannot be null');
        }
        return true;
    }

    /**
     * Pre-save callback for ensuring a user's Local email flag is not null
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if Local email flag is null
     */
    public function verifyValidLocalEmail(User $user)
    {
        if ($user->getLocalEmail() === null) {
            throw new UnexpectedValueException('Local email flag cannot be null');
        }
        return true;
    }

    /**
     * Pre-save callback for ensuring a user's webmail flag is not null
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if webmail flag is null
     */
    public function verifyValidWebmail(User $user)
    {
        if ($user->getWebmail() === null) {
            throw new UnexpectedValueException('Webmail flag cannot be null');
        }
        return true;
    }

    /**
     * Pre-save callback for ensuring a user's internal mail flag is not null
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if internal mail flag is null
     */
    public function verifyValidInternalMail(User $user)
    {
        if ($user->getUseInternalMail() === null) {
            // While the code refers to this as 'Internal Mail', it's displayed as 'External Mail Client'
            // eg see UPS Effects and user modify screens
            throw new UnexpectedValueException('Use External Mail Client flag cannot be null');
        }
        return true;
    }

    /**
     * Pre-save callback for ensuring a user's Eportfolio enabled flag is not null
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if Eportfolio enable flag is null
     */
    public function verifyValidEportfolioEnabled(User $user)
    {
        if ($user->getEportfolioEnable() === null) {
            throw new UnexpectedValueException('Eportfolio enabled flag cannot be null');
        }
        return true;
    }


    /**
     * Post-save callback for updating a user's superuser status.
     *
     * @param User $user
     */
    public function updateSuperuserStatus(User $user)
    {
        $superuserStatus = $user->getSuperuser();
        if (isset($superuserStatus)) {
            if ($superuserStatus) {
                $query = 'INSERT IGNORE INTO superusers (id) VALUES (?)';
            } else {
                $query = 'DELETE FROM superusers WHERE id = ?';
            }
            $statement = $this->getDatabase()->prepare($query);
            $statement->execute([$user->getId()]);
        }
    }

    /**
     * Post-save callback for updating a user's default campus.
     *
     * @param User $user
     */
    public function updateDefaultCampus(User $user)
    {
        $defaultCampus = $user->getDefaultCampus();
        if ($defaultCampus instanceof Campus) {
            $defaultCampusId = $defaultCampus->getId();
            if ($defaultCampusId) {
                $statement = $this->getDatabase()->prepare("
                    UPDATE userCampus
                    SET    defaultCampus = :isDefault
                    WHERE  uid    = :userId
                    AND    campus = :campusId
                ");
                foreach ($user->getCampusCollection() as $campus) {
                    $statement->execute([
                        'userId'    => $user->getId(),
                        'campusId'  => $campus->getId(),
                        'isDefault' => ($defaultCampusId == $campus->getId() ? 1 : 0),
                    ]);
                }
            }
        }
    }

    /**
     * Port of `\User::transferOwnership` - transfers ownership of content from
     * one user to another.
     *
     * Intended to be called as part of a user deletion process.
     *
     * @param int $fromId
     * @param int $toId
     */
    public function transferOwnership($fromId, $toId)
    {
        // Update all files
        $params = ['from' => $fromId, 'to' => $toId,];
        $query = "UPDATE file SET owner = :toId WHERE owner = :fromId";
        $this->database->query($query, $params);

        // Update all links
        $query = "UPDATE link SET owner = :toId WHERE owner = :fromId";
        $this->database->query($query, $params);

        // Update all news
        $query = "UPDATE news_items SET item_owner = :toId WHERE item_owner = :fromId";
        $this->database->query($query, $params);
        $query = "UPDATE subject_news SET owner = :toId WHERE owner = :fromId";
        $this->database->query($query, $params);

        // Update wikis
        $query = "UPDATE wikiRevision SET userID = :toId WHERE userID = :fromId";
        $this->database->query($query, $params);

        // Update all forums
        $query = "UPDATE forumPost SET userID = :toId WHERE userID = :fromId";
        $this->database->query($query, $params);
        $query = "UPDATE forumThread SET userID = :toId WHERE userID = :fromId";
        $this->database->query($query, $params);

        // Update events
        $query = "UPDATE event SET owner = :toId WHERE owner = :fromId";
        $this->database->query($query, $params);
    }
}
