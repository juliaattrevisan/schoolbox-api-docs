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
use Schoolbox\Permission\Access\UserAccessMapper;

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

    protected function mapping()
    {

        $this->hasTable('user');
        $this->hasModel('\Schoolbox\User');

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
                'type' => Mapper::TYPE_BOOLEAN,
            )
        );
        $this->hasMap(
            array(
                'column' => 'webmail',
                'property' => 'webmail',
                'type' => Mapper::TYPE_INTEGER,
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
            WHERE u.synergy_id = ?
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

                /** @var UserAccessMapper $uaMapper */
                $uaMapper = $this->getJoinMapper('userAccess');
                $user->setAccessList($uaMapper->findAllForUser($user));
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
}
