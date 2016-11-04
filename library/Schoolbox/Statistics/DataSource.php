<?php
/**
 * The source of all homepage statistics
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Statistics;

use Schoolbox\Collection;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Log\Mapper AS FolderLogMapper;
use Schoolbox\Resource\Folder\Visits\Mapper AS VisitsMapper;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Permission\Access\UserAccessMapper;

class DataSource
{
    /**
     * Data source for users
     *
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Data source for folder logs
     *
     * @var FolderLogMapper
     */
    protected $folderLogMapper;

    /**
     * Data source for homepage visit counts
     *
     * @var VisitsMapper
     */
    protected $visitsMapper;

    /**
     * Data source for all homepage access, write, etc logs
     *
     * @var LogMapper
     */
    protected $logMapper;

    /**
     * Data source for user access to folders
     *
     * @var LogMapper
     */
    protected $userAccessMapper;

    /**
     * Object types for filtering the log
     */
    const FILTER_FILE = 'file';
    const FILTER_LINK = 'link';


    /**
     * Constructor
     *
     * @param UserMapper $userMapper
     * @param FolderLogMapper $folderLogMapper
     * @param VisitsMapper $visitsMapper
     * @param LogMapper $logMapper
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(
        UserMapper $userMapper,
        FolderLogMapper $folderLogMapper,
        VisitsMapper $visitsMapper,
        LogMapper $logMapper,
        UserAccessMapper $userAccessMapper
    ) {
        $this->userMapper = $userMapper;
        $this->folderLogMapper = $folderLogMapper;
        $this->visitsMapper = $visitsMapper;
        $this->logMapper = $logMapper;
        $this->userAccessMapper = $userAccessMapper;
    }


    /**
     * Get homepage hits split into roles
     *
     * @param int $folderId
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param string $role
     * @return Collection
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
            LEFT JOIN   {$this->visitsMapper->getJoinSql('role', 'hv', 'r')}
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
     * @param int $folderId
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param string $role
     * @param string $orderBy
     * @param int $limit
     * @return Collection
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
            WHERE       hl.cid = :folderId AND
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
            $record->getUser()->setRole($this->folderLogMapper->hydrateJoin($row, 'user.role', 'ur'));
            $records->add($record);
        }

        return $records;
    }


    /**
     * Get a total visitor count for a homepage
     *
     * @param int $folderId
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param string $role
     * @return int
     */
    public function getVisitorCount($folderId, $startDate, $endDate, $role = null)
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
            SELECT      COUNT(user)
            FROM        `{$this->folderLogMapper->getTable()}` AS `hl`
            LEFT JOIN   {$this->folderLogMapper->getJoinSql('user', 'hl', 'u')}
            LEFT JOIN   {$this->folderLogMapper->getJoinSql('user.role', 'u', 'ur')}
            WHERE       cid = :folderId AND
                        datetime BETWEEN :startDate AND :endDate
                        {$roleClause}
        ";

        $statement = $this->folderLogMapper->getDatabase()->prepare($query);
        $statement->execute($queryParams);

        foreach ($statement as $row) {
            return $row['COUNT(user)'];
        }
    }


    /**
     * Get top files downloaded for the homepage
     *
     * @param int $folderId
     * @param string $object
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param string $role
     * @param int $limit
     * @param bool $hydrate
     * @return Collection
     */
    public function getTopAccessed($folderId, $object, $startDate, $endDate, $role = null, $limit = null, $hydrate = false)
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
            $fileJoins = "
                LEFT JOIN storage ON f.hash = storage.hash
                LEFT JOIN allowedFileExtension as afe ON storage.ext = afe.extension
                LEFT JOIN allowedFileTypes as aft ON afe.fileTypeID = aft.fileTypeID
            ";
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

        /*
         *
         */

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
        usort($records, function($a, $b) {
            return $b['accessCount'] - $a['accessCount'];
        });

        return $records;
    }


    /**
     * Get a list of members that have not visited the homepage
     *
     * @param int $folderId
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param string $role
     * @param int $limit
     * @return Collection
     */
    public function getYetToVisit($folderId, $startDate, $endDate, $role = null, $limit = null)
    {
        $queryParams = [
            'folderId' => $folderId,
            'startDate' => $startDate->getDatabase(),
            'endDate' => $endDate->getDatabase()
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

        $query = "
            SELECT          {$this->userAccessMapper->getColumnString('ua')},
                            {$this->userAccessMapper->getJoinColumnString('user', 'u')},
                            {$this->userAccessMapper->getJoinColumnString('user.role', 'ur')}
            FROM            `{$this->userAccessMapper->getTable()}` AS ua
            LEFT JOIN       {$this->userAccessMapper->getJoinSql('user', 'ua', 'u')}
            LEFT JOIN       {$this->userAccessMapper->getJoinSql('user.role', 'u', 'ur')}
            RIGHT JOIN      userGroups ug ON ug.cid = ua.sid
            LEFT JOIN       groups g ON u.group_id = g.id
            WHERE           ua.sid = :folderId
                            AND ua.service = '1'
                            AND u.deleted_at IS NULL
                            {$roleFilter}
                            AND u.id NOT IN (
                                SELECT `user`
                                FROM homepage_log
                                WHERE cid = :folderId
                                AND datetime BETWEEN :startDate AND :endDate
                                GROUP BY `user`
                            )
            {$queryLimit}
        ";

        $statement = $this->userAccessMapper->getDatabase()->prepare($query);
        $statement->execute($queryParams);

        $records = new Collection();

        foreach ($statement as $row) {
            /** @var \Schoolbox\Permission\Access\UserAccess $record */
            $record = $this->userAccessMapper->hydrate($row, 'ua');
            $record->setUser($this->userAccessMapper->hydrateJoin($row, 'user', 'u'));
            $record->getUser()->setRole($this->userAccessMapper->hydrateJoin($row, 'user.role', 'ur'));
            $records->add($record);
        }

        return $records;
    }


    /**
     * Get count of visitors that have not visited a homepage
     *
     * @param int $folderId
     * @param \Alaress_Date_Format $startDate
     * @param \Alaress_Date_Format $endDate
     * @param string $role
     * @return int
     */
    public function getYetToVisitCount($folderId, $startDate, $endDate, $role = null)
    {
        $queryParams = [
            'folderId' => $folderId,
            'startDate' => $startDate->getDatabase(),
            'endDate' => $endDate->getDatabase()
        ];

        $roleFilter = '';
        if ($role !== null) {
            $roleFilter = "AND ur.group_type = :role";
            $queryParams['role'] = $role;
        }

        $query = "
            SELECT          COUNT(uid)
            FROM            `{$this->userAccessMapper->getTable()}` AS ua
            LEFT JOIN       {$this->userAccessMapper->getJoinSql('user', 'ua', 'u')}
            LEFT JOIN       {$this->userAccessMapper->getJoinSql('user.role', 'u', 'ur')}
            RIGHT JOIN      userGroups ug ON ug.cid = ua.sid
            LEFT JOIN       groups g ON u.group_id = g.id
            WHERE           ua.sid = :folderId
                            AND ua.service = '1'
                            AND u.deleted_at IS NULL
                            {$roleFilter}
                            AND u.id NOT IN (
                                SELECT    `user`
                                FROM      homepage_log
                                WHERE     cid = :folderId
                                          AND datetime BETWEEN :startDate AND :endDate
                                GROUP BY  `user`
                            )
        ";

        $statement = $this->userAccessMapper->getDatabase()->prepare($query);
        $statement->execute($queryParams);

        foreach ($statement as $row) {
            return $row['COUNT(uid)'];
        }
    }

}