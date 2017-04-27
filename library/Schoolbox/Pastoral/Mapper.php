<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use External_Pastoral;
use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Pastoral\Access\Model as Access;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Pastoral\Type\Mapper as TypeMapper;
use Schoolbox\Pastoral\Type\Model as Type;
use Schoolbox\Pastoral\Category\Mapper as CategoryMapper;
use Schoolbox\Pastoral\Category\Model as Category;
use Schoolbox\Pastoral\Severity\Mapper as SeverityMapper;
use Schoolbox\Pastoral\Severity\Model as Severity;
use Schoolbox\Pastoral\Comment\Mapper as CommentMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Attachment\Mapper as AttachmentMapper;
use Schoolbox\Pastoral\Access\Mapper as AccessMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral');
        $this->hasModel(Model::class);
        $this->joins('parent_id', self::class, 'id', null, null, 'parent', 'parent');
        $this->joins('type_id', TypeMapper::class, 'id', null, null, 'type', 'type');
        $this->joins('category_id', CategoryMapper::class, 'id', null, null, 'category', 'category');
        $this->joins('severity_id', SeverityMapper::class, 'id', null, null, 'severity', 'severity');
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('modified_by_user_id', UserMapper::class, 'id', null, null, 'modifiedBy', 'modifiedBy');
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_id', self::TYPE_INTEGER);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('private', self::TYPE_BOOLEAN);
        $this->uses('points', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');

        // Anyone got a shorthand version of these?

        $this->hasJoin(
            'access',
            array(
                'mapper' => AccessMapper::class,
                'other_key' => 'pastoral_id',
                'this_key' => 'id',
                'multiple' => true,
            )
        );

        $this->hasJoin(
            'comments',
            array(
                'mapper' => CommentMapper::class,
                'other_key' => 'pastoral_id',
                'this_key' => 'id',
                'multiple' => true,
            )
        );

        $this->hasJoin(
            'attachments',
            array(
                'mapper' => AttachmentMapper::class,
                'other_key' => 'pastoral_id',
                'this_key' => 'id',
                'multiple' => true,
            )
        );

        $this->registerCallback(Mapper::CALLBACK_PRESAVE, [$this, 'saveRevision']);
        $this->registerCallback(Mapper::CALLBACK_PREDELETE, [$this, 'deleteReferences']);
    }

    /**
     * Prior to deleting, make sure we delete anything that is linked to this record
     *
     * @param Model $model
     */
    public function deleteReferences(Model $model) {

        // Delete comments from record

        $this->getJoinMapper('comments')->deleteByColumn('pastoral_id', $model->getId());
        $this->getJoinMapper('attachments')->deleteByColumn('pastoral_id', $model->getId());
        $this->getJoinMapper('access')->deleteByColumn('pastoral_id', $model->getId());

        // Delete related tags

        $pastoralTagMapper = PastoralTagMapper::getInstance();
        $pastoralTagMapper->deleteForRecord($model);
        $pastoralTagMapper->purgeUnassociated();

        // Delete record from external database

        $pastoralExternal = new External_Pastoral();
        $pastoralExternal->deletePastoral($model);
    }

    /**
     * Prior to saving changes to a record, we cover out butts and keep a backup in the revision table for posterity
     *
     * @param Model $model
     */
    public function saveRevision(Model $model)
    {
        // Only save the revision if we are updating an existing record

        if (!empty($model->getId())) {
            $pastoralRevisionMapper = PastoralRevisionMapper::getInstance();
            $pastoralRevision = $pastoralRevisionMapper->createRevision($model);
            $pastoralRevisionMapper->save($pastoralRevision);
        }
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return
        "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('student', 's')},
                        {$this->getJoinColumnString('severity', 'sv')},
                        {$this->getJoinColumnString('owner', 'o')},
                        {$this->getJoinColumnString('type', 't')},
                        {$this->getJoinColumnString('category', 'c')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('student', '', 's')}
            LEFT JOIN   {$this->getJoinSql('severity', '', 'sv')}
            LEFT JOIN   {$this->getJoinSql('owner', '', 'o')}
            LEFT JOIN   {$this->getJoinSql('type', '', 't')}
            LEFT JOIN   {$this->getJoinSql('category', '', 'c')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // This internal function exists to stop recursion when hydrating related records

        return $this->hydrateSelectInternal($statement);
    }

    /**
     * Hydrate the record. This exists to prevent recursion when hydrating related records.
     *
     * @param PDOStatement $statement
     * @param bool $hydrateRelatedRecords
     * @return Collection
     */
    protected function hydrateSelectInternal(PDOStatement $statement, $hydrateRelatedRecords = true)
    {
        // Return pastoral records fully hydrated by default

        $collection = new Collection();

        foreach ($statement as $row) {
            $model = $this->hydrateRow($row, $hydrateRelatedRecords);

            if ($model instanceof Model) {
                $collection->add($model);
            }
        }

        return $collection;
    }

    /**
     * This is also used by the PastoralRevision class to hydrate a record
     *
     * @param $row
     * @param $hydrateRelatedRecords
     * @return Model
     */
    protected function hydrateRow($row, $hydrateRelatedRecords) {

        // This method gets called before checking authorization- so if the session timed out this might be blank

        if (!$this->getSessionUser() instanceof User) {
            return false;
        }

        // Shortcut hydration if it is a private record and the user is a parent

        if ($row['private'] && $this->getSessionUser()->getRole()->isParent()) {
            return false;
        }

        /** @var Model $model */
        $model = $this->hydrate($row);

        /** @var User $owner */
        $owner = $this->hydrateJoin($row, 'owner', 'o');
        $model->setOwner($owner);

        if ($this->getConfigLoader()->get('pastoral_severity')) {
            /** @var Severity $severity */
            $severity = $this->hydrateJoin($row, 'severity', 'sv');
            $model->setSeverity($severity);
        }

        /** @var Type $type */
        $type = $this->hydrateJoin($row, 'type', 't');
        $model->setType($type);

        /** @var Category $category */
        $category = $this->hydrateJoin($row, 'category', 'c');
        $model->setCategory($category);

        /** @var User $student */
        $student = $this->hydrateJoin($row, 'student', 's');
        $student->loadParents();
        $student->loadTeachers();
        $model->setStudent($student);

        $model->setAccess($this->getJoinMapper('access')->findByColumn('pastoral_id', $model->getId()));

        // If the user is not in the audience- and is not the owner, they can't see the record

        $inAudience = false;

        /** @var Access $access */
        foreach ($model->getAccess() as $access) {
            if ($access->hasAccess($this->getSessionUser())) {
                $inAudience = true;
                break;
            }
        }


        if (!$inAudience && $model->getOwner()->getId() != $this->getSessionUser()->getId()) {
            return false;
        }

        // If the record is marked as private we may need to blank out its contents if the user does not have sufficent access

        $accessGranted = false;

        if ($model->getPrivate()) {

            if ($model->getOwner()->getId() == $this->getSessionUser()->getId()) {

                // Owners can see their own records

                $accessGranted = true;

            } else {

                // Otherwise, only individually audienced users, or members of audienced groups get access
                // to private records

                foreach ($model->getAccess() as $access) {
                    if ($access->hasAccess($this->getSessionUser(), false)) {
                        $accessGranted = true;
                        break;
                    }
                }

            }
        } else {
            $accessGranted = true;
        }

        if ($accessGranted) {
            $model->setComments($this->getJoinMapper('comments')->findByColumn('pastoral_id', $model->getId()));
            $model->setAttachments($this->getJoinMapper('attachments')->findByColumn('pastoral_id', $model->getId()));
        } else {
            $model->setBody($this->getConfigLoader()->get('pastoral_private_body'));
        }

        // Only hydrate related records if we're specifically told to

        if ($hydrateRelatedRecords) {
            $model->setRelatedRecords($this->getRelatedRecords($model));
        }

        // Reeturn hydrated model

        return $model;
    }

    /**
     * Return any records immediately related to the supplied pastoral record
     *
     * @param Model $record
     * @return Collection
     */
    public function getRelatedRecords(Model $record)
    {
        $query = $this->getSelectString();

        if ($record->getParent() instanceof Model) {

            // This is a child item, find its parent and its other siblings

            $query .= "                
                WHERE   `pastoral`.`id` = :parent_id_0 AND
                        `pastoral`.`parent_id` = :parent_id_1 AND
                        `pastoral`.`id` <> :id
            ";

            $bindings['id'] = $record->getId();
            $bindings['parent_id_0'] = $record->getParent()->getId();
            $bindings['parent_id_1'] = $record->getParent()->getId();

        } else {

            // This is a parent item, just find its children

            $query .= "WHERE `parent_id` = :parent_id";
            $bindings['parent_id'] = $record->getId();
        }

        $statement = $this->database->prepare($query);
        $statement->execute($bindings);

        // Return hydrated objects- but don't hydrate their children or we'll end up in a recursive loop

        return $this->hydrateSelectInternal($statement, false);
    }

    /**
     * Returns a collection of students who have pastoral records linked to this student
     *
     * @param User $student
     * @return array
     */
    public function getLinkedStudents($student)
    {
        $statement = $this->database->prepare("
            SELECT DISTINCT   `student_id`,
                              COUNT(*) AS `count`,
                              `user`.`lastname` AS `last_name`, 
                              `user`.`firstname` AS `first_name`
            FROM              `pastoral`
            JOIN              `user` ON `user`.`id` = `student_id`
            WHERE             `parent_id` IN (
                                  SELECT `id`
                                  FROM pastoral
                                  WHERE `student_id` = :student_id_0) OR `pastoral`.`id` IN (SELECT `parent_id`
                                                                                             FROM pastoral
                                                                                             WHERE `student_id` = :student_id_1)
            GROUP BY `student_id`
            ORDER BY `count` DESC, `user`.`lastname`, `user`.`firstname`
        ");

        $statement->execute([
            ':student_id_0' => $student->getId(),
            ':student_id_1' => $student->getId()
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Display the most recent records that are tagged for display on dashboard based on their severity
     *
     * @param int $count Number of recent records to display
     * @return Collection
     */
    public function findRecentDashboard($count = 5)
    {
        $records = $this->findByColumnList([
                'sv.dashboard' => 1
            ], [
            'limit' => $count,
            ['order' => ['created_at' => 'DESC']]
        ]);

        $displayed = new Collection();

        /** @var Model$record */
        foreach ($records as $record) {
            if ($record->getOwner()->getId() == $this->getSessionUser()->getId()) {
                $displayed->add($record);;
                continue;
            }

            /** @var Access $access */
            foreach ($record->getAccess() as $access) {
                if ($access->hasAccess($this->getSessionUser())) {
                    $displayed->add($record);
                    break;
                }
            }
        }

        return $displayed;
    }

    /**
     * Perform a search of pastoral records (currently searches the heading, body and comments)
     *
     * @param string $search
     * @return Collection
     */
    public function getSearchResults($search) {
        
        $search = trim($search);

        // Keep an audit trail of searches

        $pastoralAuditMapper = PastoralAuditMapper::getInstance();

        $auditRecord = $pastoralAuditMapper->createModel();

        $auditRecord->setUser($this->getSessionUser());
        $auditRecord->setSearch($search);

        if (strlen($search) >= 3) {

            $searchArray = explode(' ', $search);

            $sql = $this->getSelectString() . " WHERE (";

            // Search heading & body of pastoral record

            foreach ($searchArray as $index => $search) {
                if ($index > 0) {
                    $sql .= " OR ";
                }

                $sql .= "
                `pastoral`.`body` LIKE :body_{$index} OR 
                `pastoral`.`heading` LIKE :heading_{$index}
            ";
            }

            // Search comments

            $sql .= ") OR (`pastoral`.`id` IN (SELECT `pastoral_id` FROM `pastoral_comment` WHERE ";

            foreach ($searchArray as $index => $search) {
                if ($index > 0) {
                    $sql .= " OR ";
                }

                $sql .= "`body` LIKE :comment_{$index} ";
            }

            $sql .= "))";

            // Bind the parameters

            $statement = $this->database->prepare($sql);

            foreach ($searchArray as $index => $search) {
                $statement->bindValue("body_{$index}", "%{$search}%");
                $statement->bindValue("heading_{$index}", "%{$search}%");
                $statement->bindValue("comment_{$index}", "%{$search}%");
            }

            // Retrieve the hydrated records

            $statement->execute();

            $results = $this->hydrateSelect($statement);

            // Keep a record of all searches performed by users, and what results were displayed

            $searchResults = [];

            /** @var Model $record */
            foreach ($results as $result) {
                $searchResults[] = $result->getId();
            }

            // Record the search results that were returned by the search by imploding their IDs down into CSV

            if (count($searchResults) > 0) {
                $auditRecord->setSearchResult(implode(',', $searchResults));
            }

        } else {

            // Search was too short

            $results = new Collection();
        }

        // Save the audit record of the search

        $pastoralAuditMapper->save($auditRecord);

        return $results;
    }

    /**
     * @var int $placeholderIndex This is a static placeholder used to make sure parameters in report filters are given unique names
     */
    private static $placeholderIndex = 0;

    /**
     * Ugly helper function to create report SQL condition
     *
     * @param array &$filters
     * @param array &$bindings
     * @param Collection $collection
     * @param string $field
     */
    private function addFilter(&$filters, &$bindings, Collection $collection, $field)
    {
        $ids = [];

        foreach ($collection as $current) {
            if (is_object($current) && method_exists($current, 'getId')) {
                $ids[] = $current->getId();
            } else {
                $ids[] = $current;
            }
        }

        if (count($ids) > 0)
        {
            $pbArray = PredicateBuilder::getArrayBindings(
                $ids,
                str_replace(".", "", str_replace("`", "", str_replace("_", "", $field))) . self::$placeholderIndex
            );

            $bindings = array_merge($bindings, $pbArray['bindings']);
            $filters[] = "{$field} IN ({$pbArray['placeholders']})";
        }

        self::$placeholderIndex++;
    }

    /**
     * This is the function that gets the report results out of the database
     *
     * @param User $user
     * @param Alaress_Date_Format $before_date
     * @param Alaress_Date_Format $after_date
     * @param Severity $severity
     * @param Collection $types
     * @param Collection $authors
     * @param Collection $students
     * @param Collection $tags
     * @param Collection $order
     * @param bool $involved
     * @param Collection $years
     *
     * @return Collection
     */
    public function getReportData(
        User $user,
        Alaress_Date_Format $before_date,
        Alaress_Date_Format $after_date,
        Severity $severity,
        Collection $types,
        Collection $authors,
        Collection $students,
        Collection $tags,
        Collection $order,
        $involved,
        Collection $years
    ) {
        $filters = [];
        $bindings = [];
        $criteriaSql = '';
        $orderSql = '';

        if ($before_date instanceof Alaress_Date_Format && !$before_date instanceof Alaress_Date_NullDate) {
            $bindings[':beforeDate'] = $before_date->endOfDay()->toIso8601String();
            $filters[] = '`created_at` <= :beforeDate';
        }

        if ($after_date instanceof Alaress_Date_Format && !$after_date instanceof Alaress_Date_NullDate) {
            $bindings[':afterDate'] = $after_date->startOfDay()->toIso8601String();
            $filters[] = '`created_at` >= :afterDate';
        }

        if (!empty($severity) && $this->getConfigLoader()->get('pastoral_severity')) {
            $bindings[':severity'] = $severity->getId();
            $filters[] = '`sv`.`order` >= :severity';
        }

        $this->addFilter($filters, $bindings, $students, '`student_id`');
        $this->addFilter($filters, $bindings, $years, '`s`.`year`');
        $this->addFilter($filters, $bindings, $types, '`type_id`');
        $this->addFilter($filters, $bindings, $authors, '`owner_id`');

        foreach ($order as $orderCurrent)
        {
            if ($orderSql != "") {
                $orderSql .= ",";
            }

            switch ($orderCurrent) {
                case 'student':     $orderSql .= "`s`.lastname, `s`.firstname"; break;
                case 'severity':    $orderSql .= "`sv`.order"; break;
                case 'date':        $orderSql .= "`pastoral`.created_at"; break;
                case 'category':    $orderSql .= "`c`.name"; break;
                case 'author':      $orderSql .= "`o`.lastname, `o`.firstname"; break;
            }
        }

        // If filtering on involvement, only show records the logged in user is involved with via audience/role/ownership

        if ($involved || !$this->getSessionUser()->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0)
        {
            $filters[] = "
                (
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `role_id` = {$user->getRole()->getId()} AND `pastoral_id` = `pastoral`.`id`) > 0 OR
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `user_id` = {$user->getId()} AND `pastoral_id` = `pastoral`.`id`) > 0 OR
                    (
                        SELECT COUNT(*) FROM `pastoral_access` WHERE `group_id` IN (
                            SELECT `group_id` 
                            FROM `pastoral_group_member` 
                            WHERE `user_id` = {$user->getId()}
                        ) AND `pastoral_id` = `pastoral`.`id`
                    ) > 0 OR
                    `pastoral`.`owner_id` = {$user->getId()}
                )
            ";
        }

        foreach ($filters as $filter) {
            if ($criteriaSql != '') {
                $criteriaSql .= ' AND ';
            }
            $criteriaSql .= $filter;
        }

        if ($criteriaSql != '') {
            $criteriaSql = ' WHERE ' . $criteriaSql;
        }

        // Execute SQL

        $statement = $this->getDatabase()->prepare($this->getSelectString() . "{$criteriaSql} ORDER BY {$orderSql}");
        $statement->execute($bindings);

        // Hydrate records for return

        $results = $this->hydrateSelect($statement);

        // If there are no tags to filter, just return the results now

        if ($tags->count() == 0) {
            return $results;
        }

        // Otherwise, check each record has at least one of the tags we are searching for

        $pastoralTagMapper = PastoralTagMapper::getInstance();
        $resultsFiltered = new Collection();

        /** @var Model $result */
        foreach ($results as $result) {

            $found = false;
            $tagsRecord = $pastoralTagMapper->findForRecord($result);

            /** @var PastoralTag $tagFilter */
            foreach ($tags as $tagFilter) {

                /** @var PastoralTag $tagRecord */
                foreach ($tagsRecord as $tagRecord) {

                    if ($tagRecord->getId() == $tagFilter->getId()) {
                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    $resultsFiltered->add($result);
                    break;
                }
            }
        }

        return $resultsFiltered;
    }
}
