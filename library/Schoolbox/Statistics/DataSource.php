<?php

/**
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Statistics;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Log\Mapper as FolderLogMapper;
use Schoolbox\Resource\Folder\Visits\Mapper as VisitsMapper;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;

/**
 * Class DataSource
 *
 * The source of all homepage statistics
 *
 * @package Schoolbox\Statistics
 */
class DataSource
{

    /**
     * Object types for filtering the log
     */
    public const FILTER_FILE = 'file';
    public const FILTER_LINK = 'link';


    /**
     * Data source for users
     *
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Data source for folder logs
     *
     * @var FolderLogMapper
     */
    private $folderLogMapper;


    /**
     * Data source for homepage visit counts
     *
     * @var VisitsMapper
     */
    private $visitsMapper;


    /**
     * Data source for all homepage access, write, etc logs
     *
     * @var LogMapper
     */
    private $logMapper;


    /**
     * Data source for user access to folders
     *
     * @var UserAccessMapper
     */
    private $userAccessMapper;


    /**
     * @var Folder\Mapper
     */
    private $folderMapper;


    /**
     * DataSource constructor.
     * @param UserMapper       $userMapper
     * @param FolderLogMapper  $folderLogMapper
     * @param VisitsMapper     $visitsMapper
     * @param LogMapper        $logMapper
     * @param UserAccessMapper $userAccessMapper
     * @param Folder\Mapper    $folderMapper
     */
    public function __construct(
        UserMapper $userMapper,
        FolderLogMapper $folderLogMapper,
        VisitsMapper $visitsMapper,
        LogMapper $logMapper,
        UserAccessMapper $userAccessMapper,
        Folder\Mapper $folderMapper
    ) {
        $this->userMapper = $userMapper;
        $this->folderLogMapper = $folderLogMapper;
        $this->visitsMapper = $visitsMapper;
        $this->logMapper = $logMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->folderMapper = $folderMapper;
    }


    /**
     * Get homepage hits split into roles
     *
     * @param int                  $folderId
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param null                 $role
     * @return Collection
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws MapperException
     */
    public function getVisits($folderId, $startDate, $endDate, $role = null)
    {
        $queryParams = [
            'folderId' => $folderId,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        $roleClause = '';
        if ($role !== null) {
            $roleClause = "AND r.group_type = :role";
            $queryParams['role'] = $role;
        }

        $query = "
            SELECT      {$this->visitsMapper->getColumnString('hv')},
                        {$this->visitsMapper->getJoinColumnString('role', 'r')}
            FROM        `{$this->visitsMapper->getTable()}` AS hv
            JOIN        {$this->visitsMapper->getJoinSql('role', 'hv', 'r', true)}
            WHERE       hv.category_id = :folderId
                        AND hv.date BETWEEN :startDate AND :endDate
                        {$roleClause}
        ";

        $statement = $this->visitsMapper->getDatabase()->prepare($query);
        $statement->execute($queryParams);

        $records = new Collection();

        foreach ($statement as $row) {
            /** @var \Schoolbox\Resource\Folder\Visits\Model $record */
            $record = $this->visitsMapper->hydrate($row, 'hv');
            $record->setRole($this->visitsMapper->hydrateJoin($row, 'role', 'r'));
            $records->add($record);
        }

        return $records;
    }


    /**
     * Get a list of visitors and their hitcount for a homepage
     *
     * @param int                  $folderId
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param null                 $role
     * @param string               $orderBy
     * @param null                 $limit
     * @return Collection
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws MapperException
     */
    public function getVisitors($folderId, $startDate, $endDate, $role = null, $orderBy, $limit = null)
    {
        $queryParams = [
            'folderId' => $folderId,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        $roleClause = '';
        if ($role !== null) {
            $roleClause = "AND ur.group_type = :role";
            $queryParams['role'] = $role;
        }

        $queryLimit = '';
        if ($limit !== null) {
            $queryLimit = "LIMIT {$limit}";
        }

        $query = "
            SELECT      {$this->folderLogMapper->getColumnString('hl')},
                        {$this->folderLogMapper->getJoinColumnString('user', 'u')},
                        {$this->folderLogMapper->getJoinColumnString('user.role', 'ur')}
            FROM        `{$this->folderLogMapper->getTable()}` AS `hl`
            LEFT JOIN   {$this->folderLogMapper->getJoinSql('user', 'hl', 'u')}
            LEFT JOIN   {$this->folderLogMapper->getJoinSql('user.role', 'u', 'ur')}
            WHERE       hl.folder_id = :folderId AND
                        datetime BETWEEN :startDate AND :endDate
                        {$roleClause}
            ORDER BY    {$orderBy}
            {$queryLimit}
        ";

        $statement = $this->folderLogMapper->getDatabase()->prepare($query);
        $statement->execute($queryParams);

        $records = new Collection();

        foreach ($statement as $row) {
            /** @var \Schoolbox\Resource\Folder\Log\Model $record */
            $record = $this->folderLogMapper->hydrate($row, 'hl');
            $record->setUser($this->folderLogMapper->hydrateJoin($row, 'user', 'u'));
            $user = $record->getUser();
            if (! is_null($user)) {
                $record->getUser()->setRole($this->folderLogMapper->hydrateJoin($row, 'user.role', 'ur'));
                $records->add($record);
            }
        }

        return $records;
    }


    /**
     * Get a total visitor count for a homepage
     *
     * @param int                  $folderId
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param string|null          $role
     * @return int
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getVisitorCount(int $folderId, \Alaress_Date_Format $startDate, \Alaress_Date_Format $endDate, string $role = null): int
    {
        $queryParams = [
            'folderId' => $folderId,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        $roleClause = '';
        if ($role !== null) {
            $roleClause = "AND ur.group_type = :role";
            $queryParams['role'] = $role;
        }

        $query = "
            SELECT      COUNT(user_id)
            FROM        `{$this->folderLogMapper->getTable()}` AS `hl`
            LEFT JOIN   {$this->folderLogMapper->getJoinSql('user', 'hl', 'u')}
            LEFT JOIN   {$this->folderLogMapper->getJoinSql('user.role', 'u', 'ur')}
            WHERE       folder_id = :folderId AND
                        datetime BETWEEN :startDate AND :endDate
                        {$roleClause}
        ";

        $statement = $this->folderLogMapper->getDatabase()->prepare($query);
        $statement->execute($queryParams);
        return $statement->fetchColumn();
    }


    /**
     * Get top files downloaded for the homepage
     *
     * @param int                  $folderId
     * @param string               $object
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param string|null          $role
     * @param int|null             $limit
     * @param bool                 $hydrate
     * @return array
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getTopAccessed($folderId, $object, $startDate, $endDate, $role = null, $limit = null, $hydrate = false): array
    {
        $queryParams = [
            'folderId' => $folderId,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        $roleFilter = '';
        if ($role !== null) {
            $roleFilter = "AND ur.group_type = :role";
            $queryParams['role'] = $role;
        }

        $queryLimit = '';
        if ($limit !== null) {
            $queryLimit = "LIMIT {$limit}";
        }

        $fileJoins = '';
        $fileSelects = '';
        if ($object == self::FILTER_FILE) {
            $fileJoins = '
                LEFT JOIN storage ON f.hash = storage.hash
                LEFT JOIN allowedFileExtension as afe ON storage.ext = afe.extension
                LEFT JOIN allowedFileTypes as aft ON afe.fileTypeID = aft.fileTypeID
            ';
            $fileSelects = ',storage.filesize, aft.iconFile';
        }

        $query = "
            SELECT      COUNT(cl.id), objectId,
                        {$this->logMapper->getJoinColumnString($object, 'f')}
                        {$fileSelects}
            FROM        `{$this->logMapper->getTable()}` AS cl
            LEFT JOIN   {$this->logMapper->getJoinSql($object, 'cl', 'f')}
            LEFT JOIN   {$this->logMapper->getJoinSql('user', 'cl', 'u')}
            LEFT JOIN   {$this->logMapper->getJoinSql('user.role', 'u', 'ur')}
            {$fileJoins}
            WHERE       cl.object = '{$object}'
                        AND f.`category` = :folderId
                        AND time BETWEEN :startDate AND :endDate
                        {$roleFilter}
            GROUP BY    objectId
            {$queryLimit}
        ";

        $statement = $this->logMapper->getDatabase()->prepare($query);
        $statement->execute($queryParams);

        $records = [];

        foreach ($statement as $row) {
            $records[$row['objectId']]['accessCount'] = $row['COUNT(cl.id)'];

            if ($hydrate) {
                $records[$row['objectId']]['info'] = $this->logMapper->hydrateJoin($row, $object, 'f');

                if ($object == 'file') {
                    $records[$row['objectId']]['filesize'] = $row['filesize'];
                    $records[$row['objectId']]['iconFile'] = $row['iconFile'];
                }
            }
        }

        // Sort by most accessed first
        usort($records, function ($a, $b) {
            return $b['accessCount'] - $a['accessCount'];
        });

        return $records;
    }


    /**
     * Get a list of members that have not visited the homepage
     *
     * @param Folder\Model         $folder
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param string|null          $role
     * @return Collection
     * @throws MapperException
     */
    public function getYetToVisit(Folder\Model $folder, \Alaress_Date_Format $startDate, \Alaress_Date_Format $endDate, ?string $role = null): Collection
    {

        /* Find a list of users who are known to be in this class
         * These are indexed by id so we can unset them later
         */
        $expectedUsers = $this->userAccessMapper->findUsersInCategory($folder, $role);
        if ($expectedUsers->count() === 0) {
            return new Collection();
        }

        /* Find a list of users who have visited this class
         *
         * I could have used FolderLogMapper but I'd be using it to get a list of data to delete
         * That seems like completely wasted hydration efforts which I can shortcut here
         */
        $params = [
            'folderId' => $folder->getId(),
            'startDate' => $startDate->getDatabase(),
            'endDate' => $endDate->getDatabase()
        ];

        /* Optionals from the modal ui
         */
        $roleFilter = '';

        if ($role !== null) {
            $roleFilter = 'AND r.group_type = :role';
            $params['role'] = $role;
        }
        $bindings = Builder::getBindings($expectedUsers->getPropertyValues('id'), 'uid');

        /* Pile it in the way it used to work
         */
        $query = "
            SELECT `user_id`
            FROM homepage_log
            INNER JOIN `user` u ON user_id = u.id
            INNER JOIN `groups` r on u.group_id = r.id
            WHERE folder_id = :folderId
            AND datetime BETWEEN :startDate AND :endDate
            AND user_id IN ({$bindings->getPlaceholder()})
            {$roleFilter}
        ";

        $statement = $this->userAccessMapper->getDatabase()->prepare($query);
        $statement->execute(array_merge($params, $bindings->getParams()));

        /* Find the diff, which tells you known users who should be visiting this class
         * but haven't done so yet
         */
        foreach ($statement as $row) {
            $visitedUserId = $row['user_id'];

            /* This person has visited, so we don't need to list them
             * Everyone who remains are yet to visit
             */
            if ($expectedUsers->getOffset($visitedUserId)) {
                $expectedUsers->offsetUnset($visitedUserId);
            }
        }

        /* Return a collection of users
         */
        return $expectedUsers;
    }
}
