<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Pastoral;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\Pastoral\Pastoral;
use Schoolbox\Pastoral\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Tags\Mapper as PastoralTagsMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Permission\Access\Access as AccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

class Mapper extends BaseMapper
{
    /**
     * @var ErrorMapper $errorMapper
     */
    protected $errorMapper;

    /**
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * @var AccessMapper
     */
    protected $accessMapper;

    /**
     * @var StorageMapper
     */
    protected $storageMapper;

    /**
     * @var PastoralTagsMapper
     */
    protected $pastoralTagsMapper;

    /**
     * @var PastoralCommentMapper
     */
    protected $pastoralCommentMapper;

    /**
     * @var PastoralAccessMapper
     */
    protected $pastoralAccessMapper;

    /**
     * @var PastoralAttachmentMapper
     */
    protected $pastoralAttachmentMapper;

    public function __construct()
    {
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setUserAccessMapper(UserAccessMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setAccessMapper(AccessMapper::getInstance());
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setStorageMapper(StorageMapper::getInstance());
        $this->setPastoralTagsMapper(PastoralTagsMapper::getInstance());
        $this->setPastoralCommentMapper(PastoralCommentMapper::getInstance());
        $this->setPastoralAttachmentMapper(PastoralAttachmentMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());

        parent::__construct();
    }

    protected function mapping()
    {
        $this->hasTable('pastoral');
        $this->hasPrototype(new PastoralModel);

        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => Mapper::TYPE_INTEGER,
                'identifier' => true
            )
        );

        $this->hasMap(
            array(
                'column'     => 'parent_id',
                'property'   => 'parentId',
                'type'       => Mapper::TYPE_INTEGER,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'type_id',
                'property' => 'type',
                'type'     => self::TYPE_MODEL,
                'join'     => 'type',
                'multiple' => true,
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\Pastoral\\Type\\Mapper',
                    'other_key' => 'id',
                ),
            )
        );

        $this->hasMap(
            array(
                'column'   => 'severity_id',
                'property' => 'severity',
                'type'     => self::TYPE_MODEL,
                'join'     => 'severity',
                'multiple' => true,
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\Pastoral\\Severity\\Mapper',
                    'other_key' => 'id',
                ),
            )
        );

        $this->hasMap(
            array(
                'column'     => 'external_id',
                'property'   => 'externalId',
                'type'       => Mapper::TYPE_INTEGER,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'heading',
                'property' => 'heading',
                'type'     => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'private',
                'property' => 'private',
                'type'     => Mapper::TYPE_BOOLEAN,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'datetime_posted',
                'property' => 'postDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'datetime_modified',
                'property' => 'modifiedDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'datetime_deleted',
                'property' => 'deletedDate',
                'type'     => Mapper::TYPE_DATETIME,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'body',
                'property' => 'body',
                'type'     => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'owner_id',
                'property' => 'owner',
                'type'     => self::TYPE_MODEL,
                'join'     => 'owner',
                'multiple' => true,
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\UserMapper',
                    'other_key' => 'id',
                ),
            )
        );

        $this->hasMap(
            array(
                'column'   => 'student_id',
                'property' => 'student',
                'type'     => self::TYPE_MODEL,
                'join'     => 'student',
                'multiple' => true,
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\UserMapper',
                    'other_key' => 'id',
                ),
            )
        );

        $this->hasJoin(
            'access',
            array(
                'mapper'    => '\\Schoolbox\\Pastoral\\Access\\Mapper',
                'other_key' => 'pastoral_id',
                'this_key'  => 'id',
                'multiple'  => true,
            )
        );

        $this->hasJoin(
            'tags',
            array(
                'mapper'    => '\\Schoolbox\\Pastoral\\Tags\\Mapper',
                'other_key' => 'pastoral_id',
                'this_key'  => 'id',
                'multiple'  => true,
            )
        );

        $this->hasJoin(
            'comments',
            array(
                'mapper'    => '\\Schoolbox\\Pastoral\\Comment\\Mapper',
                'other_key' => 'pastoral_id',
                'this_key'  => 'id',
                'multiple'  => true,
            )
        );

        $this->hasJoin(
            'attachments',
            array(
                'mapper'    => '\\Schoolbox\\Pastoral\\Attachment\\Mapper',
                'other_key' => 'pastoral_id',
                'this_key'  => 'id',
                'multiple'  => true,
            )
        );

        $this->hasSoftDeleteDateField('datetime_deleted');
    }

    /**
     * Find all pastoral records by student ID number
     *
     * @var int $id
     *
     * @return Collection|Pastoral\Model[]
     * @throws BaseMapper\Exception
     */

    public function findByStudentId($id)
    {
        $query = "
            SELECT
                {$this->getColumnString('r')},
                {$this->getJoinColumnString('student', 's')},
                {$this->getJoinColumnString('severity', 'sv')},
				{$this->getJoinColumnString('owner', 'o')},
				{$this->getJoinColumnString('type', 't')}
            FROM {$this->getTable()} `r`
            LEFT JOIN {$this->getJoinSql('student', 'r', 's')}
            LEFT JOIN {$this->getJoinSql('severity', 'r', 'sv')}
            LEFT JOIN {$this->getJoinSql('owner', 'r', 'o')}
            LEFT JOIN {$this->getJoinSql('type', 'r', 't')}
            WHERE s.id = ?
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array($id));

        $records = new Collection();

        foreach ($statement as $row)
        {
            $record = $this->hydrate($row, 'r');

            // Skip if the record has been deleted

            if ($record->isDeleted())
                continue;

            // Check permissions

            if ($this->hasPermission($record))
            {
                // Hydrate and record the record we found

                $records->add($this->hydrateRecord($record, $row));
            }
        }

        return $records;
    }

    /**
     * Find pastoral record by ID number
     *
     * @var int $id
     *
     * @return Pastoral\Model
     * @throws BaseMapper\Exception
     */

    public function findById($id)
    {
        $query = "
            SELECT
                {$this->getColumnString('r')},
                {$this->getJoinColumnString('student', 's')},
                {$this->getJoinColumnString('severity', 'sv')},
				{$this->getJoinColumnString('owner', 'o')},
				{$this->getJoinColumnString('type', 't')}
            FROM {$this->getTable()} `r`
            LEFT JOIN {$this->getJoinSql('student', 'r', 's')}
            LEFT JOIN {$this->getJoinSql('severity', 'r', 'sv')}
            LEFT JOIN {$this->getJoinSql('owner', 'r', 'o')}
            LEFT JOIN {$this->getJoinSql('type', 'r', 't')}
            WHERE r.id = ?
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array($id));

        foreach ($statement as $row)
        {
            /** @var Pastoral\Model $record */
            $record = $this->hydrate($row, 'r');

            // Bomb out if the record has been deleted

            $this->throwIfDeleted($record);

            // Check permissions

            if ($this->hasPermission($record))
            {
                // Hydrate and record the record we found

                return $this->hydrateRecord($record, $row);
            }
        }

        throw new BaseMapper\Exception('Instance of object not found');
    }

    /**
     * Checks if the logged in user has permission to view the specified record
     *
     * @var \Alaress_Date_Format $beforeDate (dd/mm/yyyy date)
     * @var \Alaress_Date_Format $afterDate (dd/mm/yyyy date)
     * @var int $severity (ID of severity)
     * @var string $types (CSV list)
     * @var string $authors (CSV list)
     * @var string $students (CSV list)
     *
     * @return Collection|Pastoral\Model
     */

    public function findAllReport($before_date, $after_date, $severity, $types, $authors, $students)
    {
        // Create filter SQL

        $criteria = [];

        if ($before_date != '') {
            $before_date = date("Y-m-d", strtotime($before_date));
            $criteria[] = "datetime_posted <= '{$before_date}'";
        }

        if ($after_date != '') {
            $after_date = date("Y-m-d", strtotime($after_date));
            $criteria[] = "datetime_posted >= '{$after_date}'";
        }

        if ($severity != '') $criteria[] = "severity_id >= {$severity}";
        if ($students != '') $criteria[] = "student_id IN ({$students})";
        if ($authors != '') $criteria[] = "owner_id IN ({$authors})";
        if ($types != '') $criteria[] = "type_id IN ('{$types}')";

        $criteria_sql = "";

        foreach ($criteria as $criterion) {
            if ($criteria_sql != "") { $criteria_sql .= " AND "; } else {
                $criteria_sql = "WHERE ";
            }
            $criteria_sql .= $criterion;
        }

        // Execute SQL

        $query = "
            SELECT
                {$this->getColumnString('r')},
                {$this->getJoinColumnString('student', 's')},
                {$this->getJoinColumnString('severity', 'sv')},
				{$this->getJoinColumnString('owner', 'o')},
				{$this->getJoinColumnString('type', 't')}
            FROM {$this->getTable()} `r`
            LEFT JOIN {$this->getJoinSql('student', 'r', 's')}
            LEFT JOIN {$this->getJoinSql('severity', 'r', 'sv')}
            LEFT JOIN {$this->getJoinSql('owner', 'r', 'o')}
            LEFT JOIN {$this->getJoinSql('type', 'r', 't')}
            {$criteria_sql}
            ORDER BY `r`.datetime_posted
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        // Hydrate records for return

        $records = new Collection();

        foreach ($statement as $row)
        {
            /** @var Pastoral\Model $record */
            $record = $this->hydrate($row, 'r');

            // Skip if the record has been deleted

            if ($record->isDeleted())
                continue;

            // Check permission

            if ($this->hasPermission($record))
            {
                // Hydrate and add to the collection

                $records->add($this->hydrateRecord($record, $row));
            }
        }

        return $records;
    }

    /**
     * Checks if the logged in user has permission to view the specified record
     *
     * @var Pastoral\Model $record
     *
     * @return bool
     */

    public function hasPermission(Pastoral\Model $record)
    {
        /** @var \Schoolbox\User $user */
        $user = $_SESSION["user"];

        // Pastoral moderators can access everything

        if ($this->getUserAccessMapper()->findAllForUser($user)->filterBy("service", PASTORAL_SERVICE)->count() > 0)
            return true;

        // To access a record you at least be a staff member, or the parent of the student

        /** @var \Schoolbox\Role $role */
        $role = $user->getRole();

        if ($role->getType() != "staff")
        {
            try {
                $record->setStudent($this->getUserMapper()->findById($record->getStudent()->getId()));
            }
            catch (\Exception $e) {
                $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND, $e);
            }
            
            $record->getStudent()->loadParents();

            if (!$user->isParentOf($record->getStudent())) {
                return false;
            }
        }


        // If we get through to here we need to check the audience of item

        $record->setAccessList($this->getPastoralAccessMapper()->findByColumn("pastoral_id", $record->getId()));

        $accessRecords = $record->getAccessList();

        foreach ($accessRecords as $accessCurrent)
        {
            // Access records are either role based of user based.

            if (is_numeric($accessCurrent->getRole()->getId()))
            {
                // This access record is a role one, check if the user is in this role

                if ($role->getId() == $accessCurrent->getRole()->getId())
                    return true;
            }
            else
            {
                // This access record is a user based one, check if this is the user

                if ($accessCurrent->getUser()->getId() == $user->getId())
                    return true;
            }
        }

        // BZZZZZT! You're outta here.

        return false;
    }

    // Since this hydration is common to all of the methods, shoved it down here

    /**
     * @var Pastoral\Model $record
     * @var null $row
     *
     * @return Pastoral\Model
     */

    protected function hydrateRecord($record, $row)
    {
        // Hydrate everything on the record

        $record->setStudent($this->hydrateJoin($row, 'student', 's'));
        $record->setOwner($this->hydrateJoin($row, 'owner', 'o'));
        $record->setSeverity($this->hydrateJoin($row, 'severity', 'sv'));
        $record->setType($this->hydrateJoin($row, 'type', 't'));

        // Don't know if this is right for the one-to-many joins, but it works

        $record->setTagsList($this->getPastoralTagsMapper()->findByColumn("pastoral_id", $record->getId()));
        $record->setCommentList($this->getPastoralCommentMapper()->findByColumn("pastoral_id", $record->getId()));
        $record->setAttachmentList($this->getPastoralAttachmentMapper()->findByColumn("pastoral_id", $record->getId()));

        // Grab users and roles

        $record->setAccessList($this->getPastoralAccessMapper()->findByColumn("pastoral_id", $record->getId()));

        foreach ($record->getAccessList() as $access) {
            if ($access->getRole()->getId() != '') {
                try {
                    $access->setRole($this->getRoleMapper()->findById($access->getRole()->getId()));
                } catch (\Exception $e) {
                    $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND, $e);
                }
            } else if ($access->getUser()->getId() != '') {
                try {
                    $access->setUser($this->getUserMapper()->findById($access->getUser()->getId()));
                } catch (\Exception $e) {
                    $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND, $e);
                }
            }
        }

        // Grab comment authors

        foreach ($record->getCommentList() as $comment) {
            try {
                $comment->setOwner($this->getUserMapper()->findById($comment->getOwner()->getId()));
            } catch (\Exception $e) {
                $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND, $e);
            }
        }

        // Grab attachment details

        $attachments = $record->getAttachmentList();

        foreach ($attachments as $attachment) {

            // Get file icon using mime type

            $file = $this->getStorageMapper()->findByHash($attachment->getHash());
            $attachment->setIcon($file->getMimetype()->getIconFile());
            $attachment->setFilename($file->getFilename());
        }

        return $record;
    }

    /**
     * @param $errorMapper
     * @return $this
     */
    public function setErrorMapper($errorMapper) {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper() {
        return $this->errorMapper;
    }

    /**
     * @param $userAccessMapper
     */
    public function setUserAccessMapper($userAccessMapper) {
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper() {
        return $this->userAccessMapper;
    }

    /**
     * @param $accessMapper
     */
    public function setAccessMapper($accessMapper) {
        $this->accessMapper = $accessMapper;
    }

    /**
     * @return AccessMapper
     */
    public function getAccessMapper() {
        return $this->accessMapper;
    }
    /**
     * @param $roleMapper
     */
    public function setRoleMapper($roleMapper) {
        $this->roleMapper = $roleMapper;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper() {
        return $this->roleMapper;
    }

    /**
     * @param $userMapper
     */
    public function setUserMapper($userMapper) {
        $this->userMapper = $userMapper;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper() {
        return $this->userMapper;
    }

    /**
     * @param $storageMapper
     */
    public function setStorageMapper($storageMapper) {
        $this->storageMapper = $storageMapper;
    }

    /**
     * @return StorageMapper
     */
    public function getStorageMapper() {
        return $this->storageMapper;
    }

    /**
     * @param $pastoralCommentMapper
     */
    public function setPastoralCommentMapper($pastoralCommentMapper) {
        $this->pastoralCommentMapper = $pastoralCommentMapper;
    }

    /**
     * @return PastoralCommentMapper
     */
    public function getPastoralCommentMapper() {
        return $this->pastoralCommentMapper;
    }

    /**
     * @param $pastoralTagsMapper
     */
    public function setPastoralTagsMapper($pastoralTagsMapper) {
        $this->pastoralTagsMapper = $pastoralTagsMapper;
    }

    /**
     * @return PastoralTagsMapper
     */
    public function getPastoralTagsMapper() {
        return $this->pastoralTagsMapper;
    }

    /**
     * @param $pastoralAttachmentMapper
     */
    public function setPastoralAttachmentMapper($pastoralAttachmentMapper) {
        $this->pastoralAttachmentMapper = $pastoralAttachmentMapper;
    }

    /**
     * @return PastoralAttachmentMapper
     */
    public function getPastoralAttachmentMapper() {
        return $this->pastoralAttachmentMapper;
    }

    /**
     * @param $pastoralAccessMapper
     */
    public function setPastoralAccessMapper($pastoralAccessMapper) {
        $this->pastoralAccessMapper = $pastoralAccessMapper;
    }

    /**
     * @return PastoralAccessMapper
     */
    public function getPastoralAccessMapper() {
        return $this->pastoralAccessMapper;
    }
}
