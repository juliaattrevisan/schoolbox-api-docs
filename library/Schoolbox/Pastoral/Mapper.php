<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Tags\Mapper as PastoralTagsMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralTypeModel;
use Schoolbox\Pastoral\Category\Mapper as PastoralCategoryMapper;
use Schoolbox\Pastoral\Category\Model as PastoralCategoryModel;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverityModel;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachmentModel;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Permission\Access\Access as AccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;

class Mapper extends BaseMapper
{
    /**
     * Mapper constructor.
     */
    public function __construct()
    {
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

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral');
        $this->hasModel(PastoralModel::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_id', self::TYPE_INTEGER);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('private', self::TYPE_BOOLEAN);
        $this->uses('points', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->joins('parent_id', self::class, 'id', null, null, 'parent', 'parent');
        $this->joins('type_id', PastoralTypeMapper::class, 'id', null, null, 'type', 'type');
        $this->joins('category_id', PastoralCategoryMapper::class, 'id', null, null, 'category', 'category');
        $this->joins('severity_id', PastoralSeverityMapper::class, 'id', null, null, 'severity', 'severity');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->hasSoftDeleteDateField('deleted_at');

        // Anyone got a shorthand version of these?

        $this->hasJoin(
            'access',
            array(
                'mapper' => '\\Schoolbox\\Pastoral\\Access\\Mapper',
                'other_key' => 'pastoral_id',
                'this_key' => 'id',
                'multiple' => true,
            )
        );

        $this->hasJoin(
            'tags',
            array(
                'mapper' => '\\Schoolbox\\Pastoral\\Tags\\Mapper',
                'other_key' => 'pastoral_id',
                'this_key' => 'id',
                'multiple' => true,
            )
        );

        $this->hasJoin(
            'comments',
            array(
                'mapper' => '\\Schoolbox\\Pastoral\\Comment\\Mapper',
                'other_key' => 'pastoral_id',
                'this_key' => 'id',
                'multiple' => true,
            )
        );

        $this->hasJoin(
            'attachments',
            array(
                'mapper' => '\\Schoolbox\\Pastoral\\Attachment\\Mapper',
                'other_key' => 'pastoral_id',
                'this_key' => 'id',
                'multiple' => true,
            )
        );
    }

    /**
     * Find all pastoral records by student ID number
     * @param $id
     * @return Collection
     */
    public function findByStudentId($id)
    {
        $query = "
            SELECT
                {$this->getColumnString('r')},
                {$this->getJoinColumnString('student', 's')},
                {$this->getJoinColumnString('severity', 'sv')},
				{$this->getJoinColumnString('owner', 'o')},
				{$this->getJoinColumnString('type', 't')},
				{$this->getJoinColumnString('category', 'c')}
            FROM {$this->getTable()} `r`
            LEFT JOIN {$this->getJoinSql('student', 'r', 's')}
            LEFT JOIN {$this->getJoinSql('severity', 'r', 'sv')}
            LEFT JOIN {$this->getJoinSql('owner', 'r', 'o')}
            LEFT JOIN {$this->getJoinSql('type', 'r', 't')}
            LEFT JOIN {$this->getJoinSql('category', 'r', 'c')}
            WHERE s.id = ?
            AND r.deleted_at IS NULL
            ORDER BY r.created_at DESC
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array($id));

        $records = new Collection();

        foreach ($statement as $row) {
            /** @var Pastoral\Model $record */
            $record = $this->hydrate($row, 'r');

            // Check permissions

            if ($record->getPrivate() && !$this->hasPrivatePermission($record)) {

                // The record is private and the user does not have access, remove attachments and
                // override the body

                $record->setAttachmentList(null);
                $record->setShowPrivate(false);
                $record->setBody("The contents of this record have been marked as private.");
            } else {
                $record->setShowPrivate(true);
            }
            $records->add($this->hydrateRecord($record, $row));
        }

        return $records;
    }

    /**
     * Find pastoral record by ID number
     * @var int $id
     * @return Pastoral\Model
     * @throws MapperException
     */
    public function findById($id)
    {
        $query = "
            SELECT
                {$this->getColumnString('r')},
                {$this->getJoinColumnString('student', 's')},
                {$this->getJoinColumnString('severity', 'sv')},
				{$this->getJoinColumnString('owner', 'o')},
				{$this->getJoinColumnString('type', 't')},
				{$this->getJoinColumnString('category', 'c')}
            FROM {$this->getTable()} `r`
            LEFT JOIN {$this->getJoinSql('student', 'r', 's')}
            LEFT JOIN {$this->getJoinSql('severity', 'r', 'sv')}
            LEFT JOIN {$this->getJoinSql('owner', 'r', 'o')}
            LEFT JOIN {$this->getJoinSql('type', 'r', 't')}
            LEFT JOIN {$this->getJoinSql('category', 'r', 'c')}
            WHERE r.id = ?
            AND r.deleted_at IS NULL
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array($id));

        foreach ($statement as $row) {
            /** @var Pastoral\Model $record */
            $record = $this->hydrate($row, 'r');

            // Check permissions

            $record = $this->hydrateRecord($record, $row);

            if ($record->getPrivate() && !$this->hasPrivatePermission($record)) {
                $record->setBody("The contents of this record have been marked as private.");
                $record->setShowPrivate(false);
            } else {
                $record->setShowPrivate(true);
            }

            return $record;
        }

        throw new MapperException('Instance of object not found');
    }

    /**
     * Find pastoral record by parent ID number
     * @param $parentId
     * @return Collection
     */
    public function findByParentId($parentId)
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
            WHERE r.parent_id = ?
            AND r.deleted_at IS NULL
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array($parentId));

        $collection = new Collection;

        foreach ($statement as $row) {
            /** @var Pastoral\Model $record */
            $record = $this->hydrate($row, 'r');
            $collection->add($this->hydrateRecord($record, $row));
        }

        return $collection;
    }

    /**
     * Gets DB records matching report criteria
     * @param \Schoolbox\User $user
     * @param $before_date
     * @param $after_date
     * @param $severity
     * @param $types
     * @param $authors
     * @param $students
     * @param $tags
     * @param $order
     * @param $involved
     * @param $years
     * @return Collection
     */
    public function findAllReport(
        $user,
        $before_date,
        $after_date,
        $severity,
        $types,
        $authors,
        $students,
        $tags,
        $order,
        $involved,
        $years
    ) {
        // Create filter SQL

        $criteria = [];

        if ($before_date != '') {
            $before_date = \Alaress_Date_Format::newLocal($before_date . " 12:00AM");
            $criteria[] = "created_at <= '" . $before_date->toIso8601String() . "'";
        }

        if ($after_date != '') {
            $after_date = \Alaress_Date_Format::newLocal($after_date . " 11:59PM");
            $criteria[] = "created_at >= '" . $after_date->toIso8601String() . "'";
        }

        if ($severity != '') {
            $criteria[] = "sv.order >= {$severity}";
        }
        if ($students != '') {
            $criteria[] = "student_id IN ({$students})";
        }
        if ($authors != '') {
            $criteria[] = "owner_id IN ({$authors})";
        }
        if ($types != '') {
            $criteria[] = "type_id IN ({$types})";
        }

        $array_years = explode(",", $years);

        $years_sql = '';

        if ($years != '') {

            $years_sql .= "";

            foreach ($array_years as $year) {
                if ($years_sql != "") {
                    $years_sql .= ",";
                }
                $years_sql .= "'" . str_replace("'", "''", $year) . "'";
            }

            $years_sql = "`s`.year IN ({$years_sql})";
        }

        $criteria[] = $years_sql;

        $criteria_sql = "";

        foreach ($criteria as $criterion) {
            if ($criteria_sql != "") {
                $criteria_sql .= " AND ";
            } else {
                $criteria_sql = "WHERE ";
            }
            $criteria_sql .= $criterion;
        }

        $order_sql = "";
        $order_array = explode(",", $order);

        foreach ($order_array as $order_current) {
            if ($order_sql != "") {
                $order_sql .= ", ";
            }

            switch ($order_current) {
                case 'student':
                    $order_sql .= "`s`.lastname, `s`.firstname";
                    break;
                case 'severity':
                    $order_sql .= "`sv`.order";
                    break;
                case 'date':
                    $order_sql .= "`r`.created_at";
                    break;
                case 'category':
                    $order_sql .= "`c`.name";
                    break;
                case 'author':
                    $order_sql .= "`o`.lastname, `o`.firstname";
                    break;
            }
        }

        if ($involved == 'true' ||
            (
                !$this->getSessionUser()->getSuperuser() ||
                !$this->getSessionUser()->getAccessList()->filterBy('service',
                    UserAccess::SERVICE_PASTORAL)->count() > 0
            )
        ) {

            // Only show records the logged in user is involved with via audience/role/ownership

            $role_id = $user->getRole()->getId();
            $user_id = $user->getId();

            $involved_sql = "
                AND (
                    (SELECT COUNT(*) FROM pastoral_access WHERE role_id = {$role_id} AND pastoral_id = `r`.id) > 0 OR
                    (SELECT COUNT(*) FROM pastoral_access WHERE user_id = {$user_id} AND pastoral_id = `r`.id) > 0 OR
                    `r`.owner_id = {$user_id}
                )
            ";

        } else {
            $involved_sql = '';
        }

        // Execute SQL

        $query = "
            SELECT
                {$this->getColumnString('r')},
                {$this->getJoinColumnString('student', 's')},
                {$this->getJoinColumnString('severity', 'sv')},
				{$this->getJoinColumnString('owner', 'o')},
				{$this->getJoinColumnString('type', 't')},
				{$this->getJoinColumnString('category', 'c')}
            FROM {$this->getTable()} `r`
            LEFT JOIN {$this->getJoinSql('student', 'r', 's')}
            LEFT JOIN {$this->getJoinSql('severity', 'r', 'sv')}
            LEFT JOIN {$this->getJoinSql('owner', 'r', 'o')}
            LEFT JOIN {$this->getJoinSql('type', 'r', 't')}
            LEFT JOIN {$this->getJoinSql('category', 'r', 'c')}
            {$criteria_sql}
            {$involved_sql}
            ORDER BY {$order_sql}
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        // Hydrate records for return

        $records = new Collection();

        foreach ($statement as $row) {
            /** @var Pastoral\Model $record */
            $record = $this->hydrate($row, 'r');

            // Skip if the record has been deleted

            if ($record->isDeleted()) {
                continue;
            }

            // Check tags

            if (trim($tags) != '') {

                $tagsArray = explode(",", strtolower(trim($tags)));
                $tagsList = $this->getPastoralTagsMapper()->findByColumn('pastoral_id', $record->getId());

                $found = false;

                foreach ($tagsArray as $tagCurrent) {
                    foreach ($tagsList as $tag) {
                        if (strtolower($tag->getName()) == $tagCurrent) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found) {
                        break;
                    }
                }

                if (!$found) {
                    continue;
                }
            }

            // Check permission

            if ($record->getPrivate() && !$this->hasPrivatePermission($record)) {
                $record->setBody("The contents of this record have been marked as private.");
                $record->setShowPrivate(false);
            } else {
                $record->setShowPrivate(true);
            }
            $records->add($this->hydrateRecord($record, $row));
        }

        return $records;
    }

    /**
     * Checks if the logged in user has permission to view private records
     * @var Pastoral\Model $record
     * @return bool
     */
    public function hasPrivatePermission(Pastoral\Model $record)
    {
        // Hack for notifier

        if (!isset($_SESSION)) {
            return true;
        }

        $user = $this->getSessionUser();

        if ($user instanceof User) {

            // To access a private record you must be a staff member

            /** @var \Schoolbox\Role $role */
            $role = $user->getRole();

            if (!$role->isStaff()) {
                return false;
            }

            // Pastoral moderators can always access private records

            if ($user->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0) {
                return true;
            }

            // Give owners permission to their own records regardless of other permissions

            if ($record->getOwner()->getId() == $user->getId()) {
                return true;
            }

            // If we get through to here we need to check the audience of item

            $accessRecords = $this->getPastoralAccessMapper()->findByColumn("pastoral_id", $record->getId());

            foreach ($accessRecords as $accessCurrent) {
                // Only specifically audienced individuals can access private records, so we are only interested
                // in records with a numeric `user_id`

                if (is_numeric($accessCurrent->getUser()->getId())) {
                    if ($accessCurrent->getUser()->getId() == $user->getId()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Since this hydration is common to all of the methods, shoved it down here
     * @param Model $record
     * @param $row
     * @return Model
     * @throws MapperException
     */
    protected function hydrateRecord(Pastoral\Model $record, $row)
    {
        // Hydrate everything on the record

        /** @var User $student */
        $student = $this->hydrateJoin($row, 'student', 's');
        /** @var User $owner */
        $owner = $this->hydrateJoin($row, 'owner', 'o');
        /** @var PastoralSeverityModel $severity */
        $severity = $this->hydrateJoin($row, 'severity', 'sv');
        /** @var PastoralTypeModel $type */
        $type = $this->hydrateJoin($row, 'type', 't');
        /** @var PastoralCategoryModel $category */
        $category = $this->hydrateJoin($row, 'category', 'c');

        $record->setStudent($student);
        $record->setOwner($owner);
        $record->setSeverity($severity);
        $record->setType($type);
        $record->setCategory($category);

        // Don't know if this is right for the one-to-many joins, but it works

        $record->setTagsList($this->getPastoralTagsMapper()->findByColumn("pastoral_id", $record->getId()));
        $record->setCommentList($this->getPastoralCommentMapper()->findByColumn("pastoral_id", $record->getId()));
        $record->setAttachmentList($this->getPastoralAttachmentMapper()->findByColumn("pastoral_id", $record->getId()));

        // Grab users and roles

        $record->setAccessList($this->getPastoralAccessMapper()->findByColumn("pastoral_id", $record->getId()));

        foreach ($record->getAccessList() as $access) {
            if ($access->getRole()->getId() != '') {
                try {
                    /** @var \Schoolbox\Role $role */
                    $role = $this->getRoleMapper()->findById($access->getRole()->getId());
                    $access->setRole($role);
                } catch (MapperException $e) {
                    throw new MapperException('Instance of object not found');
                }
            } else {
                if ($access->getUser()->getId() != '') {
                    try {
                        $access->setUser($this->getUserMapper()->findById($access->getUser()->getId()));
                    } catch (MapperException $e) {
                        throw new MapperException('Instance of object not found');
                    }
                }
            }
        }

        // Grab comment authors

        foreach ($record->getCommentList() as $comment) {
            try {
                $comment->setOwner($this->getUserMapper()->findById($comment->getOwner()->getId()));
            } catch (MapperException $e) {
                $comment->setOwner($this->getUserMapper()->getUnknownUser());
            }
        }

        // Grab attachment details

        $attachments = $record->getAttachmentList();

        /** @var PastoralAttachmentModel $attachment */
        foreach ($attachments as $attachment) {

            // Get file icon using mime type

            try {
                $file = $this->getStorageMapper()->findByHash($attachment->getHash());
                $attachment->setIcon($file->getMimetype()->getIconFile());
                $attachment->setFilename($file->getFilename());
            } catch (MapperException $e) {
                throw new MapperException('Instance of attached file not found');
            }
        }

        return $record;
    }

    /**
     * Here be the getters/setters
     */

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

    /**
     * @param $userAccessMapper
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }

    /**
     * @param $accessMapper
     */
    public function setAccessMapper($accessMapper)
    {
        $this->accessMapper = $accessMapper;
    }

    /**
     * @return AccessMapper
     */
    public function getAccessMapper()
    {
        return $this->accessMapper;
    }

    /**
     * @param $roleMapper
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper()
    {
        return $this->roleMapper;
    }

    /**
     * @param $userMapper
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param $storageMapper
     */
    public function setStorageMapper($storageMapper)
    {
        $this->storageMapper = $storageMapper;
    }

    /**
     * @return StorageMapper
     */
    public function getStorageMapper()
    {
        return $this->storageMapper;
    }

    /**
     * @param $pastoralCommentMapper
     */
    public function setPastoralCommentMapper($pastoralCommentMapper)
    {
        $this->pastoralCommentMapper = $pastoralCommentMapper;
    }

    /**
     * @return PastoralCommentMapper
     */
    public function getPastoralCommentMapper()
    {
        return $this->pastoralCommentMapper;
    }

    /**
     * @param $pastoralTagsMapper
     */
    public function setPastoralTagsMapper($pastoralTagsMapper)
    {
        $this->pastoralTagsMapper = $pastoralTagsMapper;
    }

    /**
     * @return PastoralTagsMapper
     */
    public function getPastoralTagsMapper()
    {
        return $this->pastoralTagsMapper;
    }

    /**
     * @param $pastoralAttachmentMapper
     */
    public function setPastoralAttachmentMapper($pastoralAttachmentMapper)
    {
        $this->pastoralAttachmentMapper = $pastoralAttachmentMapper;
    }

    /**
     * @return PastoralAttachmentMapper
     */
    public function getPastoralAttachmentMapper()
    {
        return $this->pastoralAttachmentMapper;
    }

    /**
     * @param $pastoralAccessMapper
     */
    public function setPastoralAccessMapper($pastoralAccessMapper)
    {
        $this->pastoralAccessMapper = $pastoralAccessMapper;
    }

    /**
     * @return PastoralAccessMapper
     */
    public function getPastoralAccessMapper()
    {
        return $this->pastoralAccessMapper;
    }
}
