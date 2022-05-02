<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Schoolbox\GenericCollection;

/**
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method GenericCollection findByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection findByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Code\Mapper
     */
    private \Schoolbox\Resource\Folder\Code\Mapper $folderCodeMapper;


    /**
     * Special usages of mappers due to an exotic join
     */
    public function __construct()
    {
        parent::__construct();

        $this->folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();
        $this->folderCodeMapper = \Schoolbox\Resource\Folder\Code\Mapper::getInstance();
    }


    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_event');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('detail', self::TYPE_STRING);
        $this->uses('location', self::TYPE_STRING);
        $this->uses('code', self::TYPE_STRING);
        $this->uses('day', self::TYPE_STRING);
        $this->uses('period', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->uses('finish_at', self::TYPE_DATETIME);
        $this->uses('allday', self::TYPE_INTEGER);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('classification', self::TYPE_STRING);
        $this->uses('guid', self::TYPE_STRING);
        $this->uses('source', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }


    /**
     * @inheritDoc
     *
     * @return string
     */
    protected function getSelectString()
    {
        /* I can't join to a folder via a code (code doesn't have an identifier property)
         */
        $folderColumnString = $this->folderMapper->getColumnString('f');
        $folderCodeColumnString = $this->folderCodeMapper->getColumnString('fc');

        return "
            SELECT
                {$this->getColumnString()},
                {$folderColumnString}, 
                {$folderCodeColumnString}
            FROM {$this->getTable()}
            LEFT JOIN {$this->folderCodeMapper->getTable()} fc ON {$this->getTable()}.code = fc.code AND fc.deleted_at IS NULL
            LEFT JOIN {$this->folderMapper->getTable()} f ON fc.folder_id = f.id AND f.deleted_at IS NULL
        ";
    }


    /**
     * @inheritDoc
     *
     * @param \PDOStatement $statement
     * @return \Schoolbox\GenericCollection
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $stack = $this->createCollection();

        foreach ($statement as $row) {
            $event = $this->hydrate($row);

            /* If a folder code x folder join can be found, shove it in
             */
            if ($row['f.id']) {
                /** @var \Schoolbox\Resource\Folder\Model|null $folder */
                $folder = $this->folderMapper->hydrate($row, 'f');

                /** @var \Schoolbox\Resource\Folder\Code\Model|null $folderCode */
                $folderCode = $this->folderCodeMapper->hydrate($row, 'fc');

                $folder->setCodes($this->folderCodeMapper->createCollection([$folderCode]));

                $event->setFolder($folder);
            }

            $stack->add($event);
        }

        return $stack;
    }


    /**
     * Find all the events which have ids in the $eventIds array,
     * returning a collection of Model instances which also have
     * their Staff array populated.
     *
     * The Staff array is a set of (user id, title + fullname) tuples
     * whose sole purpose was to show and link names in the calendar modal
     * e.g. (22, Mr Jack Coles), (24, Ms Rebecca White)
     *
     * @param array $eventIds
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function findWithCheapStaff(array $eventIds): GenericCollection
    {
        $stack = $this->createCollection();

        if (!\count($eventIds)) {
            return $stack;
        }

        /* I can't join to a folder via a code (code doesn't have an identifier property)
         */
        $folderColumnString = $this->folderMapper->getColumnString('f');
        $folderCodeColumnString = $this->folderCodeMapper->getColumnString('fc');

        /* Limit it to $eventIds
         */
        $pb = $this->getPredicateBuilder()
            ->in('id', $eventIds);

        $where = $pb->getWhere()[0];
        $params = $pb->getParams();

        /* An event can have an optional folder associated by event.code
         * An event can have an optional set of involved users associated via sis_class and sis_class_user
         * An event can have zero or more staff members in that set of associated users (these are the teachers)
         */
        $query = "
            SELECT
                {$this->getColumnString()},
                {$folderColumnString}, 
                {$folderCodeColumnString},
                u.id AS `u.id`, u.title AS `u.title`, u.firstname AS `u.firstname`, u.lastname AS `u.lastname`, 
                g.group_type as `g.group_type`
            FROM {$this->getTable()}
            LEFT JOIN {$this->folderCodeMapper->getTable()} fc ON {$this->getTable()}.code = fc.code AND fc.deleted_at IS NULL
            LEFT JOIN {$this->folderMapper->getTable()} f ON fc.folder_id = f.id AND f.deleted_at IS NULL
            LEFT JOIN sis_class sc ON sis_event.code = sc.code
            LEFT JOIN sis_class_user scu ON sc.id = scu.class_id
            LEFT JOIN `user` u ON scu.external_id = u.synergy_id
            LEFT JOIN `groups` g ON u.group_id = g.id
            WHERE {$where}
            AND (group_type = :studentRoleType OR group_type = :staffRoleType)
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array_merge([
            'studentRoleType' => \Schoolbox\Role::STUDENT,
            'staffRoleType' => \Schoolbox\Role::STAFF
        ], $params));

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /* If we haven't seen this event yet, spawn it and stash it in the stack
             */
            if (!$stack->getOffset($row['id'])) {
                $event = $this->hydrate($row);

                /* If a folder code x folder join can be found, shove it in
                 */
                if ($row['f.id']) {
                    /** @var \Schoolbox\Resource\Folder\Model|null $folder */
                    $folder = $this->folderMapper->hydrate($row, 'f');

                    /** @var \Schoolbox\Resource\Folder\Code\Model|null $folderCode */
                    $folderCode = $this->folderCodeMapper->hydrate($row, 'fc');

                    $folder->setCodes($this->folderCodeMapper->createCollection([ $folderCode ]));

                    $event->setFolder($folder);
                }

                /* Trickle staff data into a simple format, to save on object spawnage
                 */
                $stack->setOffset($event->getId(), $event);
            }

            /* Populate staff members in a cheap hydraty way
             * Note that some classes could potentially have students-only; if we use
             * WHERE group_type = 'staff' these students-only classes won't appear in
             * the result set
             */
            $id = (int)$row['u.id'];
            $groupType = (string)$row['g.group_type'];

            if (\Schoolbox\Role::STAFF === $groupType) {
                $name = implode(' ', array_filter([
                    $row['u.title'],
                    $row['u.firstname'],
                    $row['u.lastname']
                ], static function ($val) {
                    return '' !== trim((string)$val);
                }));

                /* Trickle it in
                 */
                $stack->getOffset($row['id'])->addStaff($id, $name);
            }
        }

        return $stack;
    }
}
