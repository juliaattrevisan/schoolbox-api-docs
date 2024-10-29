<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress\Database\Predicate\Builder;
use DomainException;
use House;
use HouseMapper;
use HouseResidentialMapper;
use PDOStatement;
use Predis\ClientInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox;
use Schoolbox\Calendar\Util\ViewNameMapping;
use Schoolbox\External\Connector\ICommunityPortal;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Resource\Storage;
use UnexpectedValueException;

/**
 * Class UserMapper
 *
 * Mapper between user objects and database rows.
 *
 * @package Schoolbox
 * @method User findById($id)
 * @method User findByIdWithFallback($id)
 * @method User findOneByColumn($columnName, $columnValue, array $options = [])
 * @method User findOneByColumnList($columnList, array $options = [])
 * @method User hydrate(array $row, $alias = '', $callback = null)
 * @method User createModel($idValue = null)
 * @method GenericCollection|User[] createCollection($contents = [])
 * @method GenericCollection|User[] findByColumn($columnName, $columnValue, $options = [])
 * @method GenericCollection|User[] findByPredicates(Builder $pb, array $options = [])
 * @method GenericCollection|User[] hydrateCollection($collection, $identifier = null)
 */
class UserMapper extends Mapper
{
    /**
     * The username of the special 'Alaress Admin' user used by Schoolbox staff
     */
    public const USERNAME_ALARESS_ADMIN = 'alaress';

    /**
     * The minimum amount of characters required when searching for a user
     */
    public const MIN_CHARACTER_LIMIT = 2;

    /**
     * If in use (ie not null), this should mirror all non-softDeleted users in DB.
     * If we save, delete, soft-delete, or restore a user, we must update the
     * cache via callbacks to ensure integrity.
     *
     * Keys are all lowercase, hence 'UserName' === 'username' to mirror MySQL case-insensitivity
     *
     * @var array|null $usernameCache Maps 'username' => 'id'.
     */
    protected $usernameCache;


    /**
     * Fetches the community portal out of DI on a need-to-know basis only.
     *
     * @return ICommunityPortal
     * @throws DomainException if it wasn't in the injector, or it was but wasn't an ICommunityPortal instance
     */
    protected function retrieveExternalCommunityPortal()
    {
        try {
            /*** Do not explode if the integrations is not configured ***/
            set_error_handler(function () {
                return true;
            }, E_USER_WARNING);
            $externalCommunityPortal = self::getDi()->get('externalCommunityPortal');
            restore_error_handler();
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            throw new DomainException('externalCommunityPortal service is not configured');
        }

        if ($externalCommunityPortal instanceof ICommunityPortal) {
            return $externalCommunityPortal;
        }
        throw new DomainException(
            'externalCommunityPortal service is not an \Schoolbox\External\Connector\ICommunityPortal instance'
        );
    }


    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('user');
        $this->hasModel(User::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('username', self::TYPE_STRING);
        $this->uses('password', self::TYPE_STRING);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('firstname', self::TYPE_STRING);
        $this->uses('preferred_name', self::TYPE_STRING);
        $this->uses('lastname', self::TYPE_STRING);
        $this->uses('local_email', self::TYPE_BOOLEAN);
        $this->uses('alt_email', self::TYPE_STRING);
        $this->uses('hash', self::TYPE_STRING);
        $this->uses('position_title', self::TYPE_STRING);
        $this->uses('key', self::TYPE_STRING);
        $this->uses('synergy_id', self::TYPE_STRING, null, 'externalId');
        $this->uses('mail_handler', self::TYPE_BOOLEAN, null, 'useInternalMail');
        $this->uses('eportfolio_enabled', self::TYPE_BOOLEAN, null, 'eportfolioEnable');
        $this->uses('calendar_view', self::TYPE_STRING);
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('hide_contacts', self::TYPE_BOOLEAN);
        $this->uses('hide_timetable', self::TYPE_BOOLEAN);
        $this->uses('hide_sidenav', self::TYPE_BOOLEAN);
        $this->uses('hide_notifications_tray', self::TYPE_BOOLEAN);
        $this->uses('display_grade_graph', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('portrait_hash', Storage\Mapper::class, 'hash', null, null, 'portrait', 'portrait');
        $this->joins('house', HouseMapper::class, 'id', null, null, 'schoolHouse', 'schoolHouse');
        $this->joins('residential_house_id', HouseResidentialMapper::class, 'id', null, null, 'residentialHouse', 'residentialHouse');
        $this->joins('pronoun', Schoolbox\User\Pronoun\Mapper::class, 'id', null, null, 'pronoun', 'pronoun');
        $this->joins('group_id', RoleMapper::class, 'id', null, null, 'role', 'role');
        $this->joins('year_id', YearLevel\Mapper::class, 'id', null, null, 'year', 'year');
        $this->joinsVia('user_link', UserMapper::class, 'id', 'linked_user_id', 'user_id', null, null, null, 'linkedUsers');
        $this->joinsVia('userCampus', CampusMapper::class, 'id', 'campus', 'uid', 'id', null, null, 'campusCollection');

        $this->hasJoin(
            'userAccess',
            array(
                'mapper' => '\\Schoolbox\\Permission\\Access\\UserAccessMapper',
                'this_key' => 'id',
                'other_key' => 'uid',
                'multiple' => true,
            )
        );

        // Setup callbacks

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'setDefaultDisplayGradeGraph']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidUsername']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyUniqueUsername']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidRole']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyHasCampus']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidFirstName']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidLocalEmail']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidInternalMail']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidEportfolioEnabled']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'updateKey']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'updateHash']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'undoAlaressUserModification']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyValidCalendarView']);

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateSuperuserStatus']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'addUsernameToCache']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateCommunityPortalPassword']);

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'blockAlaressUserDeletion']);

        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'removeUsernameFromCache']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'removeUsernameFromCache']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deletePastoralAudiences']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteNotificationQueue']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteToken']);

        $this->registerCallback(
            self::CALLBACK_POSTSOFTDELETE,
            function (User $user): void {
                // If user id is null, do not attempt to delete pastoral access
                if (!$user->getId()) {
                    return;
                }
                Pastoral\Access\Mapper::getInstance()->deleteByColumn('user_id', $user->getId());
            }
        );

        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'addUsernameToCache']);

        // Persist search index changes on changing of a user.
        $this->registerPersistSearchIndexCallbacks();
    }


    /**
     * Pre-save callback to ensure the Alaress Admin superuser account is not broken
     *
     * Undoes:
     * - marking as disabled
     * - removal of superuser perms
     * - change of username
     * - soft-deletion via an update
     *
     * @param User $user
     * @return bool
     * @throws UnexpectedValueException
     * @throws \PDOException
     */
    public function undoAlaressUserModification(User $user): bool
    {
        if ($user->getId()) {
            // Unfortunately pre-saves do not have access to previous state.
            $previous = $this->findColumnsByPredicates(
                ['username'],
                $this->getPredicateBuilder()->equals('id', $user->getId())
            );
            $previousUsername = $previous[0]['username'] ?? null;
            if ($previousUsername === self::USERNAME_ALARESS_ADMIN) {
                $user
                    ->setEnabled(true)
                    ->setDeletedAt(null)
                    ->setUsername(self::USERNAME_ALARESS_ADMIN)
                    ->setSuperuser(true);
            }
        }
        return true;
    }


    /**
     * Pre-soft-delete callback to ensure the Alaress Admin user is not deleted
     *
     * @param User $user
     * @return bool
     */
    public function blockAlaressUserDeletion(User $user): bool
    {
        if ($user->getUsername() === self::USERNAME_ALARESS_ADMIN) {
            throw new SchoolboxException(sprintf('The %s user may not be deleted', self::USERNAME_ALARESS_ADMIN));
        }
        return true;
    }


    /**
     * Pre-save callback: if the user's display grade average flag isn't set, set it to its default value.
     *
     * @param User $model
     * @return bool Always true
     */
    public function setDefaultDisplayGradeGraph(User $model)
    {
        if ($model->getDisplayGradeGraph() === null) {
            $model->setDisplayGradeGraph((bool)$this->getConfigLoader()->get('display_grade_graph'));
        }

        return true;
    }


    /**
     * Update password in community portal.
     *
     * @param User $model
     * @throws DomainException if community portal isn't set up at all
     */
    public function updateCommunityPortalPassword(User $model)
    {
        /* User must have an externalId for community portal changes
         * getExternalId can return nulls so check for that too
         */
        if ('' === (string)$model->getExternalId()) {
            return;
        }

        /* Parents can get their community portal password changed
         * using the externalId and password they supplied
         */
        if ($model->getRole()->isParent() && !empty($model->getRetrievablePassword())) {
            $this->retrieveExternalCommunityPortal()->changePassword(
                $model->getExternalId(),
                $model->getRetrievablePassword()
            );
        }
    }


    /**
     * Pre-save hook: Set key in user, if it's not set.
     *
     * @param User $model
     * @return bool Always true
     */
    public function updateKey(User $model)
    {
        if (!$model->getKey()) {
            // ensure a key is set when persisting users
            $model->setKey(substr(md5(uniqid() . time()), 0, 25));
        }
        return true;
    }


    /**
     * Pre-save hook: Set hash if password has changed.
     *
     * @param User $model
     * @return bool Always true
     */
    public function updateHash(User $model)
    {
        if ($model->getRetrievablePassword()) {
            $model->setHash(md5($model->getUsername() . ':' . $this->getConfigLoader()->get('site_school_code') . ':' . $model->getRetrievablePassword()));
        }
        return true;
    }


    /**
     * Finds a user by their email address.
     *
     * Allows search on both `alt_email` field and `username`@`config.mail_address`.
     *
     * @param string $email
     * @return Collection|User[]
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws \PDOException
     */
    public function findByEmail($email)
    {
        $query = 'user.alt_email = :altEmail AND user.local_email IS FALSE';
        $params = ['altEmail' => $email];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailParts = explode('@', $email, 2);
            if (count($emailParts) === 2) {
                // Compare this email's hostname against the actual hostname
                [$username, $domain] = $emailParts;
                $thisDomain = $this->getConfigLoader()->get('mail_address');
                if ($domain == $thisDomain) {
                    $query = '((' . $query . ') OR (user.username = :username AND user.local_email IS TRUE))';
                    $params['username'] = $username;
                }
            }
        }
        $query = $this->getSelectString() . ' WHERE ' . $query . ' AND user.deleted_at IS NULL';
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);
        return $this->hydrateSelect($statement);
    }


    /**
     * @return string
     * @throws Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()}, IF(su.id, 1, 0) as `superuser`,
                {$this->getJoinColumnString('role', 'g')},
                {$this->getJoinColumnString('userCampus', 'c')},
                {$this->getJoinColumnString('schoolHouse', 'h')},
                {$this->getJoinColumnString('residentialHouse', 'rh')},
                {$this->getColumnString('lu')},
                {$this->getJoinColumnString('role', 'lur')},
                {$this->getJoinColumnString('year', 'lyl')},
                {$this->getJoinColumnString('portrait', 'portrait')},
                {$this->getJoinColumnString('portrait.allowedFileExtension', 'afe')},
                {$this->getJoinColumnString('pronoun', 'p')}
            FROM `user`
            LEFT JOIN superusers su ON su.id = user.id
            JOIN {$this->getJoinSql('role', '', 'g', true)}
            LEFT JOIN {$this->getJoinSql('userCampus', '', 'c')}
            LEFT JOIN {$this->getJoinSql('user_link', '', 'lu')}
            LEFT JOIN {$this->getJoinSql('role', 'lu', 'lur', true)}
            LEFT JOIN {$this->getJoinSql('year', '', 'lyl', true)}
            LEFT JOIN {$this->getJoinSql('schoolHouse', '', 'h')}
            LEFT JOIN {$this->getJoinSql('residentialHouse', '', 'rh')}
            LEFT JOIN {$this->getJoinSql('portrait', '', 'portrait')}
            LEFT JOIN {$this->getJoinSql('portrait.allowedFileExtension', 'portrait', 'afe', true)}
            LEFT JOIN {$this->getJoinSql('pronoun', '', 'p')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement): Collection
    {
        /** @var User[] $users */
        $users = [];

        /** @var CampusMapper $campusMapper */
        $campusMapper = $this->getJoinMapper('userCampus');

        foreach ($statement as $row) {
            $userId = $row['id'];

            // Don't override previous hydrations of this user: add to it instead
            if (!isset($users[$userId])) {

                /** @var Role $role */
                $role = $this->hydrateJoin($row, 'role', 'g');

                /** @var YearLevel\Model $year */
                $year = $this->hydrateJoin($row, 'year', 'lyl');

                /** @var House $schoolHouse **/
                $schoolHouse = $this->hydrateJoin($row, 'schoolHouse', 'h');

                /** @var \HouseResidential $residentialHouse **/
                $residentialHouse = $this->hydrateJoin($row, 'residentialHouse', 'rh');

                /** @var User\Pronoun\Model|null $pronoun **/
                $pronoun = $this->hydrateJoin($row, 'pronoun', 'p');

                $users[$userId] = $this->hydrate($row)
                    ->setSuperuser($row['superuser'] == 1)
                    ->setRole($role)
                    ->setYear($year)
                    ->setSchoolHouse($schoolHouse)
                    ->setResidentialHouse($residentialHouse)
                    ->setLinkedUsers($this->createCollection())
                    ->setPronoun($pronoun)
                    ->setCampusCollection($campusMapper->createCollection());
            }

            $campus = $campusMapper->hydrate($row, 'c');

            /* If the campus object has no id, then it spawned a campus
             * object from nonexistent data (e.g. a user with no campus)
             * In this case, the campus object will be totally empty but
             * still occupy slots in the user's campus collection stack
             */
            if ($campus) {
                // Don't double add
                $users[$userId]->getCampusCollection()->setOffset($campus->getId(), $campus);
            }

            $linkedUser = $this->hydrate($row, 'lu');

            if ($linkedUser) {
                // Don't double add
                $linkedUser->setRole($this->hydrateJoin($row, 'role', 'lur'));

                $users[$userId]->getLinkedUsers()->setOffset($linkedUser->getId(), $linkedUser);
            }

            /* Portrait Image
             */

            /** @var Storage\Model $portrait */
            $portrait = $this->hydrateJoin($row, 'portrait', 'portrait');

            if ($portrait) {
                /** @var \Schoolbox\File\Mimetype $mime */
                $mime = $this->hydrateJoin($row, 'portrait.allowedFileExtension', 'afe');

                $portrait->setMimetype($mime);
                $users[$userId]->setPortrait($portrait);
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
     * @throws Mapper\DefinitionException
     */
    protected function findAccessList(User $user)
    {
        /** @var UserAccessMapper $userAccessMapper */
        $userAccessMapper = $this->getJoinMapper('userAccess');

        return $userAccessMapper->findAllForUser($user);
    }


    /**
     * Find a user by their external id and come with roles, campus, etc
     *
     * @param string $id
     * @param string $roleType If provided, restrict to this role type.
     *                         One of `Role::STAFF`, `Role::STUDENT`, `Role::PARENT`, `Role::GUEST`
     * @return User
     * @throws Mapper\Exception
     * @throws Mapper\Exception\NotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findByExternalId($id, $roleType = null)
    {

        if (\strlen($id) === 0) {
            throw new Schoolbox\Mapper\Exception('An ID must be provided');
        }

        $pb = $this->getPredicateBuilder();

        $pb->equals('synergy_id', $id);

        if ($roleType) {
            $pb->equals('g.group_type', $roleType);
        }

        $user = $this->findByPredicates($pb)->first();

        if (!$user instanceof User) {
            throw new Schoolbox\Mapper\Exception\NotFound();
        }

        return $user;
    }


    /**
     * Fetches the user, and all of the user's subjects.
     *
     * NOTE: spawns External\SubjectMapper to find these subjects
     *
     * @param  int $id
     * @return \Schoolbox\User
     *
     * @throws Schoolbox\Mapper\Exception if user wasn't found
     * @throws Schoolbox\Mapper\Exception\Deleted if user wasn't found
     */
    public function findWithSubjects($id)
    {
        $user = $this->findById($id);
        $subject_mapper = new External\SubjectMapper();
        $user->setSubjectCollection($subject_mapper->findAllForUser($user, $this->getSessionUser()));
        return $user;
    }


    /**
     * Find all the users which match $name and are in group_id array $permittedValues
     *
     * @param string $name
     * @param array $permittedValues
     * @return Schoolbox\Collection
     * @throws Mapper\Exception
     * @throws \PDOException
     */
    public function findByNameAndGroup($name, $permittedValues): Schoolbox\Collection
    {
        $collection = new Schoolbox\Collection();

        if (empty($permittedValues)) {
            return $collection;
        }

        $roleMapper = new RoleMapper();
        $yearLevelMapper = YearLevel\Mapper::getInstance();

        $pb = $this->getPredicateBuilder();
        $pb->any(
            $this->getPredicateBuilder()
                 ->like('u.firstname', "%$name%")
                 ->like('u.preferred_name', "%$name%")
                 ->like('u.lastname', "%$name%")
        );
        $pb->isNUll('u.deleted_at');
        $pb->in('u.group_id', $permittedValues);

        $query = "
            SELECT
                {$this->getColumnString('u')},
                {$roleMapper->getColumnString('g')},
                {$this->getJoinColumnString('year', 'lyl')}
            FROM `{$this->table}` u
            LEFT JOIN `groups` g ON group_id = g.`id` AND g.deleted_at IS NULL
            LEFT JOIN {$this->getJoinSql('year', 'u', 'lyl')}
            WHERE
        " . implode(' AND ', $pb->getWhere());

        $statement = $this->database->prepare($query);

        $statement->execute($pb->getParams());

        foreach ($statement as $row) {
            $user = $this->hydrate($row, 'u');
            $role = $roleMapper->hydrate($row, 'g');
            $year = $yearLevelMapper->hydrate($row, 'lyl');

            $user->setRole($role);
            $user->setYear($year);

            $collection->add($user);
        }

        return $collection;
    }


    /**
     * Get a user object stub, for when a user does not exist
     *
     * @return User
     */
    public function getUnknownUser()
    {
        $user = new User();
        $user->setLastname('Unknown User');
        return $user;
    }


    /**
     * Do a keyword search for students
     *
     * @param string $search
     * @return Collection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findStudents($search)
    {
        $search = trim($search);

        $students = new Collection();

        $foundValidKeyword = false;

        if (strlen($search) >= self::MIN_CHARACTER_LIMIT) {
            // Only search if there are X or more characters

            $keywordArray = explode(' ', $search);

            $pb = $this->getPredicateBuilder();

            foreach ($keywordArray as $keyword) {
                if (strlen($keyword) >= self::MIN_CHARACTER_LIMIT) {
                    // Each keyword must be long enough to search

                    $foundValidKeyword = true;

                    $searchLike = "%{$this->getDatabase()->getQuoter()->escapeForLike($keyword)}%";
                    $pb->like('firstname', $searchLike)
                       ->like('preferred_name', $searchLike)
                       ->like('lastname', $searchLike);
                }
            }

            // Had to have at least one valid keyword

            if ($foundValidKeyword) {
                $users = $this->findByPredicates($this->getPredicateBuilder()->any($pb));

                /** @var User $user */
                foreach ($users as $user) {
                    if ($user->getRole()->isStudent()) {
                        $students->add($user);
                    }
                }
            }
        }

        return $students;
    }


    /**
     * Retrieves all users who have the given level of access (or higher) to the
     * requested service.
     *
     * @param int   $service           The service level. A UserAccess::SERVICE_* constant
     * @param int   $accessLevel       The access level. A UserAccess::ACCESS_* constant
     * @param int   $serviceIdentifier If provided, the campus or homepage that $service is for
     * @param array $order             Sort order array
     * @param int   $limit             If provided, limit the amount of users to query
     * @return Collection|User[]       A list of users who have this level of access to the given service.
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws \PDOException
     */
    public function findByAccess($service, $accessLevel, $serviceIdentifier = null, $order = [], $limit = null)
    {

        $query = "
        SELECT DISTINCT {$this->getColumnString('u')}, {$this->getJoinColumnString('role', 'r')}, {$this->getJoinColumnString('pronoun', 'pronoun')}
            FROM {$this->getTable()} u
            INNER JOIN {$this->getJoinSql('userAccess', 'u', 'ua')}
            LEFT JOIN {$this->getJoinSql('pronoun', 'u', 'pronoun')}
            INNER JOIN {$this->getJoinSql('role', 'u', 'r')}
            WHERE ua.service=:service
            AND ua.type >=:accessLevel
            AND u.deleted_at is null
        ";
        $params = array(
            'service' => $service,
            'accessLevel' => $accessLevel,
        );

        if (isset($serviceIdentifier)) {
            $query .= 'AND ua.sid=:serviceIdentifier';
            $params['serviceIdentifier'] = $serviceIdentifier;
        }

        if (is_array($order) && !empty($order)) {
            $order_parts = array();
            foreach ($order as $column => $direction) {
                $order_parts[] = $this->database->getQuoter()->quoteIdentifier($column) . (strtoupper($direction) == 'DESC' ? ' DESC' : ' ASC');
            }
            $query .= ' ORDER BY ' . (implode(', ', $order_parts));
        }

        if ($limit !== null) {
            $query .= " LIMIT {$limit};";
        }

        $users = new Collection();
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        foreach ($statement as $row) {
            $user = $this->hydrate($row, 'u');

            /** @var Role $role */
            $role = $this->hydrateJoin($row, 'role', 'r');

            $user->setRole($role);
            $user->setPronoun($this->hydrateJoin($row, 'pronoun', 'pronoun'));
            $users->add($user);
        }

        return $users;
    }


    /**
     * Pre-save callback for verifying whether a user's name is unique.
     *
     * For data-integrity, this method relies on a post-save/restore
     * callback to add any new users to the cache.
     *
     * @param User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws UnexpectedValueException if it isn't unique
     * @throws \PDOException
     * @throws \UnderflowException
     */
    public function verifyUniqueUsername(User $user)
    {
        // Load cache on first invocation
        if (!\is_array($this->usernameCache)) {
            $this->usernameCache = [];
            $query = $this->selectScalarByColumnList("{$this->getTable()}.id, {$this->getTable()}.username", []);
            $query->execute();
            /** @noinspection PhpAssignmentInConditionInspection */
            while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                $this->usernameCache[\strtolower($row['username'])] = $row['id'];
            }
        }

        // Check cache
        $usernameLowerCase = \strtolower($user->getUsername());
        if (array_key_exists($usernameLowerCase, $this->usernameCache)) {
            if ($this->usernameCache[$usernameLowerCase] != $user->getId()) {
                throw new UnexpectedValueException(
                    sprintf(
                        'A user with username "%s" already exists. Existing user id: "%s"',
                        $user->getUsername(),
                        $this->usernameCache[$usernameLowerCase]
                    )
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
    public function addUsernameToCache(User $user)
    {
        // Is cache in use?
        if (\is_array($this->usernameCache)) {
            $this->usernameCache[strtolower($user->getUsername())] = $user->getId();
        }
    }


    /**
     * Post-delete + Post-softDelete callback to ensure username cache is up-to-date
     *
     * @param User $user
     */
    public function removeUsernameFromCache(User $user)
    {
        // Is cache in use?
        if (\is_array($this->usernameCache)) {
            // Is username in cache?
            $usernameLowerCase = \strtolower($user->getUsername());
            if (isset($this->usernameCache[$usernameLowerCase])) {
                // Does cache record belong to this user?
                if ($this->usernameCache[$usernameLowerCase] == $user->getId()) {
                    unset($this->usernameCache[$usernameLowerCase]);
                }
            }
        }
    }


    /**
     * Post-softDelete callback to delete redis queues for this user
     * @param User $user
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function deleteNotificationQueue(User $user)
    {
        /** @var ClientInterface $redis */
        $redis   = self::getDi()->get('redis.storage');
        $factory = new Schoolbox\Realtime\MessageList\ListFactory(INSTANCE_ID, $redis);
        $factory->listed($user->getId())->drain();
        $factory->unread($user->getId())->drain();
        $factory->digest($user->getId())->drain();
    }


    /**
     * Deletes any pastoral audiences for this user
     *
     * @param User $user
     * @return bool
     */
    protected function deletePastoralAudiences(User $user): bool
    {
        Pastoral\Audience\Mapper::getInstance()->deleteByColumn('user_id', $user->getId());
        return true;
    }

    /**
     * Deletes token
     * @param User $user
     * @return bool
     */
    protected function deleteToken(User $user): bool
    {
        Schoolbox\User\Token\Mapper::getInstance()->deleteByColumn('user_id', $user->getId());
        return true;
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
        // if no campus has been selected in the interface
        if (null === $user->getCampusCollection() || $user->getCampusCollection()->count() < 1) {
            throw new UnexpectedValueException('User must have at least one campus');
        }

        // verify that at least one valid campus id exists in the selected campuses.
        $campusList = CampusMapper::getInstance()->findAll()->indexBy('id')->keys();
        foreach ($user->getCampusCollection() as $selectedCampus) {
            if (in_array($selectedCampus->getId(), $campusList)) {
                return true;
            }
        }

        throw new UnexpectedValueException('User must have at least one valid campus');
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
            throw new UnexpectedValueException('Email Address from Username cannot be null');
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
     * @throws \PDOException
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
        $query = 'UPDATE file SET owner = :toId WHERE owner = :fromId';
        $this->database->query($query, $params);

        // Update all links
        $query = 'UPDATE link SET owner = :toId WHERE owner = :fromId';
        $this->database->query($query, $params);

        // Update wikis
        $query = 'UPDATE wikiRevision SET userID = :toId WHERE userID = :fromId';
        $this->database->query($query, $params);

        // Update all forums
        $query = 'UPDATE forumPost SET userID = :toId WHERE userID = :fromId';
        $this->database->query($query, $params);
        $query = 'UPDATE forumThread SET userID = :toId WHERE userID = :fromId';
        $this->database->query($query, $params);

        // Update events
        $query = 'UPDATE event SET owner = :toId WHERE owner = :fromId';
        $this->database->query($query, $params);
    }


    /**
     * Finds a user by username
     *
     * @param string $username
     * @param array  $options
     * @return null|User
     */
    public function findByUsername(string $username, array $options = []): ?User
    {
        return $this->findByColumn('username', $username, $options)->first();
    }

    /**
     * Gets calendar view for the user
     *
     * @param int $userId
     * @return string
     */
    public function getCalendarView(int $userId): string
    {
        $query = "SELECT calendar_view FROM {$this->table} WHERE id = :userId";
        return $this->database->query($query, ['userId' => $userId])->fetchColumn();
    }

    /**
     * Updates calendar view for the user
     *
     * @param int    $userId
     * @param string $calendarView
     * @return bool
     */
    public function updateCalendarView(int $userId, string $calendarView): bool
    {
        if (!\in_array($calendarView, ViewNameMapping::MAP_FC_TO_STORED)) {
            return false;
        }
        $query = "UPDATE {$this->table} SET calendar_view = :calendarView WHERE id = :userId";
        $this->database->query($query, ['userId' => $userId, 'calendarView' => $calendarView]);
        return true;
    }

    /**
     * The hash is a function of the password, we use this to store the password when the user logs in
     * This is dangerous and a security risk and will likely need to be removed in the future
     *
     * @param User   $user
     * @param string $hash
     * @return void
     */
    public function storeHash(User $user, string $hash): void
    {
        $query = "UPDATE {$this->table} SET hash = :hash WHERE id = :userId";
        $this->database->query($query, ['userId' => $user->getId(), 'hash' => $hash]);
    }

    /**
     * Pre-save callback for ensuring a user's calendar View is valid
     *
     * @param  User $user
     * @return bool Always true, as long as it doesn't throw
     * @throws SchoolboxException if calendarView is invalid
     */
    public function verifyValidCalendarView(User $user): bool
    {
        if (!\in_array($user->getCalendarView(), ViewNameMapping::MAP_FC_TO_STORED)) {
            throw new SchoolboxException("Calendar View '{$user->getCalendarView()}' is invalid.");
        }

        return true;
    }
}
