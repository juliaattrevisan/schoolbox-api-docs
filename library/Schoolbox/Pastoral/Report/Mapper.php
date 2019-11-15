<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Report;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_report');
        $this->hasModel(Model::class);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('severity_id', PastoralSeverityMapper::class, 'id', null, null, 'severity', 'severity');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('uses_relative_date', self::TYPE_BOOLEAN);
        // TODO getting column not found error on relative_date_string
        $this->uses('relative_date_string', self::TYPE_STRING, self::COLUMN_ENUM, Model::getAllRelativeDateStrings());
        $this->uses('before_date', self::TYPE_DATETIME);
        $this->uses('after_date', self::TYPE_DATETIME);
        $this->uses('involved', self::TYPE_BOOLEAN);

        // Filter criteria CSV strings

        $this->uses('types', self::TYPE_STRING, null, 'typesCsv');
        $this->uses('sub_types', self::TYPE_STRING, null, 'subTypesCsv');
        $this->uses('authors', self::TYPE_STRING, null, 'authorsCsv');
        $this->uses('students', self::TYPE_STRING, null, 'studentsCsv');
        $this->uses('tags', self::TYPE_STRING, null, 'tagsCsv');
        $this->uses('order', self::TYPE_STRING, null, 'orderCsv');
        $this->uses('years', self::TYPE_STRING, null, 'yearsCsv');
        $this->uses('houses', self::TYPE_STRING, null, 'housesCsv');

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(Mapper::CALLBACK_POSTSAVE, [$this, 'saveGroups']);
    }

    /**
     * Update group access to the pastoral report
     *
     * @param Model $model
     */
    public function saveGroups(Model $model)
    {
        $groupIds = [];

        if ($model->getGroups()->count() > 0) {
            foreach ($model->getGroups() as $group) {
                $groupIds[] = $group->getId();
            }

            $bindings = PredicateBuilder::getArrayBindings($groupIds, 'group');

            // Delete any existing pastoral groups from the report that are no longer to be given access

            $sql = "
      
                UPDATE      `pastoral_report_groups`
                SET         `deleted_at` = NOW() 
                WHERE       `report_id` = :reportId AND 
                            `group_id` NOT IN ({$bindings['placeholders']}) AND 
                            `deleted_at` IS NULL
            ";

            $statement = $this->getDatabase()->prepare($sql);
            $statement->execute(array_merge($bindings['bindings'], [
                'reportId' => $model->getId(),
            ]));
        } else {
            $statement = $this->getDatabase()->prepare("
                UPDATE      `pastoral_report_groups`
                SET         `deleted_at` = NOW() 
                WHERE       `report_id` = :reportId AND 
                            `deleted_at` IS NULL            
            ");

            $statement->execute(['reportId' => $model->getId()]);
        }

        // Insert any new groups

        foreach ($groupIds as $groupId) {
            $sql = "
                INSERT INTO `pastoral_report_groups` (
                    report_id, 
                    group_id,
                    created_at
                ) 
                SELECT :reportId, :groupId, NOW() 
                WHERE (
                  SELECT    COUNT(*) 
                  FROM      `pastoral_report_groups` 
                  WHERE     `report_id` = :reportIdCount AND 
                            `group_id` = :groupIdCount AND 
                            `deleted_at` IS NULL   
                ) = 0          
            ";

            $statement = $this->getDatabase()->prepare($sql);
            $statement->execute([
                'reportId'      => $model->getId(),
                'reportIdCount' => $model->getId(),
                'groupId'       => $groupId,
                'groupIdCount'  => $groupId,
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            if (empty($row['id'])) {
                continue;
            }

            $model = $this->hydrate($row);

            if ($this->getConfigLoader()->get('pastoral_severity')) {
                if (!empty($row['severity_id'])) {
                    /** @var null|PastoralSeverity $severity */
                    try {
                        $severity = $this->getJoinMapper('severity')->findById($row['severity_id']);
                    } catch (MapperException\Deleted $e) {
                        $severity = null;
                    }
                    $model->setSeverity($severity);
                }
            }

            if (!empty($row['dashboard_id'])) {
                $model->setDashboard(true);
            }

            $model->setInvolved($row['involved'] == 1);

            // Should probably be storing report criteria as a bunch of pivot tables, but this will do for now.

            // This takes the report criteria which are stored in the database as CSV strings and turns them into collections
            // (of hydrated objects where applicable)

            $model->setOrder(new Collection(explode(',', $row['order'])));
            $model->setYears(new Collection(explode(',', $row['years'])));

            $model->setTypes($this->getCollection(PastoralTypeMapper::getInstance(), $row['types']));
            $model->setSubTypes($this->getCollection(PastoralSubTypeMapper::getInstance(), $row['sub_types']));
            $model->setAuthors($this->getCollection(UserMapper::getInstance(), $row['authors']));
            $model->setStudents($this->getCollection(UserMapper::getInstance(), $row['students']));
            $model->setTags($this->getCollection(PastoralTagMapper::getInstance(), $row['tags']));
            $model->setGroups($this->getCollection(PastoralGroupMapper::getInstance(), $row['groups']));
            $model->setHouses($this->getCollection(\HouseMapper::getInstance(), $row['houses']));

            $collection->add($model);
        }

        return $collection;
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "

            SELECT      {$this->getColumnString()},
                        GROUP_CONCAT(`pastoral_report_groups`.`group_id`) as `groups`
            FROM        {$this->getTable()}
            LEFT JOIN   `pastoral_report_groups` ON 
                            `pastoral_report_groups`.`report_id` = `pastoral_report`.`id` AND 
                            `pastoral_report_groups`.`deleted_at` IS NULL           
        ";
    }

    /**
     * Retreive all report definitions the user has access to
     *
     * @param User $user
     *
     * @return Collection|\Schoolbox\GenericCollection
     */
    public function findForUser(User $user)
    {
        $userId = $this->getDatabase()->quote($user->getId());

        $groupSql         = '';
        $groupsMembership = PastoralGroupMemberMapper::getInstance()->findByColumn('user_id', $user->getId());

        $groupIds = [];

        /** @var PastoralGroupMember $groupMember */
        foreach ($groupsMembership as $groupMember) {
            $groupIds[] = $groupMember->getGroup()->getId();
        }

        $bindings = PredicateBuilder::getArrayBindings($groupIds, 'group');

        if (!empty($bindings['placeholders'])) {
            $groupSql = "`group_id` IN ({$bindings['placeholders']}) AND";
        }

        $sql = "

            SELECT      {$this->getColumnString()},
                        GROUP_CONCAT(`pastoral_report_groups`.`group_id`) as `groups`,
                        `pastoral_report_dashboard`.`id` as `dashboard_id`
            FROM        {$this->getTable()}
            LEFT JOIN   `pastoral_report_groups` ON 
                            `pastoral_report_groups`.`report_id` = `pastoral_report`.`id` AND 
                            `pastoral_report_groups`.`deleted_at` IS NULL           
            LEFT JOIN   `pastoral_report_dashboard` ON
                            `pastoral_report_dashboard`.`report_id` = `pastoral_report`.`id` AND
                            `pastoral_report_dashboard`.`user_id` = {$userId} AND
                            `pastoral_report_dashboard`.`deleted_at` IS NULL
            WHERE       (`pastoral_report`.`owner_id` = :userId OR (
                            `pastoral_report`.`id` IN (
                                SELECT    `report_id` 
                                FROM      `pastoral_report_groups` 
                                WHERE     {$groupSql}
                                          `deleted_at` IS NULL
                            )
                        )) AND
                        `pastoral_report`.`deleted_at` IS NULL
            GROUP BY    `pastoral_report`.`id`
        ";

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute(array_merge($bindings['bindings'], [
            'userId' => $user->getId(),
        ]));

        return $this->hydrateSelect($statement);
    }

    /**
     * Retreive all report definitions that should display on users dashboard
     *
     * @param User $user
     *
     * @return Collection|\Schoolbox\GenericCollection
     */
    public function findForUserDashboard(User $user)
    {
        $userId = $this->getDatabase()->quote($user->getId());

        $groupSql         = '';
        $groupsMembership = PastoralGroupMemberMapper::getInstance()->findByColumn('user_id', $user->getId());

        $groupIds = [];

        /** @var PastoralGroupMember $groupMember */
        foreach ($groupsMembership as $groupMember) {
            $groupIds[] = $groupMember->getGroup()->getId();
        }

        $bindings = PredicateBuilder::getArrayBindings($groupIds, 'group');

        if (!empty($bindings['placeholders'])) {
            $groupSql = "`group_id` IN ({$bindings['placeholders']}) AND";
        }

        $sql = "

            SELECT      {$this->getColumnString()},
                        GROUP_CONCAT(`pastoral_report_groups`.`group_id`) as `groups`
            FROM        {$this->getTable()}
            LEFT JOIN   `pastoral_report_groups` ON 
                            `pastoral_report_groups`.`report_id` = `pastoral_report`.`id` AND 
                            `pastoral_report_groups`.`deleted_at` IS NULL           
            JOIN        `pastoral_report_dashboard` ON
                            `pastoral_report_dashboard`.`report_id` = `pastoral_report`.`id` AND
                            `pastoral_report_dashboard`.`user_id` = {$userId} AND
                            `pastoral_report_dashboard`.`deleted_at` IS NULL
            WHERE       `pastoral_report`.`deleted_at` IS NULL AND
            (`pastoral_report`.`owner_id` = :userId OR (
                            `pastoral_report`.`id` IN (
                                SELECT    `report_id` 
                                FROM      `pastoral_report_groups` 
                                WHERE     {$groupSql}
                                          `deleted_at` IS NULL
                            )
                        ))
            GROUP BY    `pastoral_report`.`id`
        ";

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute(array_merge($bindings['bindings'], [
            'userId' => $user->getId(),
        ]));

        return $this->hydrateSelect($statement);
    }

    /**
     * Helper function to convert CSV filters to a collection of objects
     *
     * @param BaseMapper $mapper
     * @param string     $ids
     *
     * @return Collection
     */
    private function getCollection($mapper, $ids)
    {
        $collection = $mapper->createCollection();

        $ids = trim($ids);

        if (!empty($ids)) {
            $array = explode(",", $ids);

            foreach ($array as $id) {
                try {
                    if (is_numeric($id)) {
                        $object = $mapper->findById($id);
                    } else {
                        $object = $mapper->findOneByColumn('name', $id);
                    }
                    $collection->add($object);
                } catch (MapperException $e) {
                }
            }
        }

        return $collection;
    }
}
