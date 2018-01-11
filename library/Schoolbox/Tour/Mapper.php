<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Tour\Step\Mapper as TourStepMapper;
use Schoolbox\Tour\Trigger\Mapper as TriggerMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($id = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findOneByColumnList($columnList, $order = [], $options = [])
 * @package Schoolbox\Tour
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     *
     * @throws BaseMapper\DefinitionException
     */
    protected function mapping()
    {
        $this->hasTable('tour');
        $this->hasModel(Model::class);

        $this->uses('uuid', self::TYPE_STRING, self::COLUMN_IDENTIFIER_UUID);
        $this->joins('author_user_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->joins('tour_step_uuid', TourStepMapper::class, 'uuid', null, null, 'tourStep', 'tourStep');
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('active', self::TYPE_BOOLEAN);

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins('author_user_id', UserMapper::class, 'id', null, null, 'author', 'author');

        $this->hasJoin('trigger', [
            'mapper' => TriggerMapper::class,
            'this_key' => 'uuid',
            'other_key' => 'tour_uuid',
        ]);

        $this->registerCallback(self::CALLBACK_PRESAVE, function (Model $model) {
            return $this->preSave($model);
        });
    }

    /**
     * @param Model $model
     * @return bool
     */
    private function preSave(Model $model)
    {
        if ($model->getUuid()) {
            // already got an ID; do nothing

            // TODO consider validating that it's *actually* a UUID
            return true;
        }

        // TODO find a way to do this without the pre-query
        $model->setUuid($this->database->query('SELECT UUID()')->fetchColumn());

        return true;
    }

    /**
     * @inheritdoc
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('author', 'a')},
                        {$this->getJoinColumnString('tourStep', 's')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('author', '', 'a', true)}
            LEFT JOIN   {$this->getJoinSql('tourStep', '', 's')}
        ";
    }

    /**
     * @inheritdoc
     *
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws BaseMapper\DefinitionException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        /** @var UserMapper $authorUserMapper */
        $authorUserMapper = $this->getJoinMapper('author');

        /** @var TourStepMapper $tourStepMapper */
        $tourStepMapper = $this->getJoinMapper('tourStep');

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            if ($model instanceof Model) {
                $model->setAuthor($authorUserMapper->hydrate($row, 'a'));
                $model->setTourStep($tourStepMapper->hydrate($row, 's'));
                $collection->add($model);
            }
        }

        return $collection;
    }

    /**
     * Find all tours relevant to a particular user
     *
     * @param User $user
     * @return GenericCollection|Model[]
     * @throws BaseMapper\DefinitionException
     */
    public function findByUser(User $user): GenericCollection
    {
        $userQuery = $this->getUserFilterQuery($user);

        $statement = $this->getDatabase()->prepare("{$this->getSelectString()} WHERE {$userQuery['query']}");
        $statement->execute($userQuery['bindings']);

        return $this->hydrateSelect($statement);
    }


    /**
     * Find all tours relevant to a particular user and the supplied URL
     *
     * @param User   $user The user for whom we are retrieving the tours
     * @param string $url  The URL to be used to filter the returned tours
     * @param boolean $repeat Determines whether we should retrieve tours that have already started
     * @return GenericCollection
     * @throws BaseMapper\DefinitionException
     */
    public function findByUserForUrl(User $user, string $url, bool $repeat = false): GenericCollection
    {
        $userQuery = $this->getUserFilterQuery($user);

        // We have the basic audience query, onto this we need to append the regular expression URL filter
        $userQuery['bindings']['url'] = $url;

        $sql = " 
            {$this->getSelectString()} 
            INNER JOIN  {$this->getJoinSql('trigger', '', 't')}
            WHERE       {$userQuery['query']} AND 
                        :url REGEXP `t`.`url_regex` AND
                        `{$this->getTable()}`.`active` = 1
        ";

        if (!$repeat) {
            /** @var User $user */
            $user = $_SESSION['user'];
            $userQuery['bindings']['user_id'] = $user->getId();

            $sql = $sql. "AND
                            tour.uuid NOT IN (
                            SELECT tour_uuid
                            FROM tour_step ts
                            LEFT JOIN tour_status tst ON ts.uuid = tst.current_step
                            WHERE tst.user_id = :user_id)";
        }

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute($userQuery['bindings']);

        return $this->hydrateSelect($statement);
    }

    /**
     * Find all tours relevant to a particular user
     *
     * @param User $user
     *
     * @return array
     */
    private function getUserFilterQuery(User $user): array
    {
        $predicates = [];
        $bindings   = [];

        // --------------------------------------------------------------------------------------------------------
        // Only want un-deleted tours
        // --------------------------------------------------------------------------------------------------------

        $tourNotDeletedClause = $this->getPredicateBuilder()
                                     ->isNull($this->getTable() . '.deleted_at')
                                     ->getWhere()[0];

        // --------------------------------------------------------------------------------------------------------
        // Tours where user is in a selected role
        // --------------------------------------------------------------------------------------------------------

        $audiencedRoleClause = "
            {$this->getTable()}.`uuid` IN 
            (
                SELECT  `tar`.`tour_uuid` 
                FROM    `tour_audience_role` `tar`
                WHERE   `tar`.`role_id` = (:roleId)
            )
        ";

        $predicates[]       = $audiencedRoleClause;
        $bindings['roleId'] = $user->getRole()->getId();

        // --------------------------------------------------------------------------------------------------------
        // Add additional audience predicates outside of MVP below
        // --------------------------------------------------------------------------------------------------------

        // ...

        // --------------------------------------------------------------------------------------------------------
        // Concatenate all of the predicates into an OR query and return the SQL in an array along with bindings
        // --------------------------------------------------------------------------------------------------------

        $query = "({$tourNotDeletedClause} AND (" . implode(" OR ", $predicates) . "))";

        return [
            'query'    => $query,
            'bindings' => $bindings,
        ];
    }

    /**
     * Update an array of tours to active, and all others to inactive
     *
     * @param array $uuids
     */
    public function updateActiveTours(array $uuids)
    {
        if (\count($uuids)) {
            $predicate = PredicateBuilder::getArrayBindings($uuids, 'uuid');
            $query = "
                UPDATE  {$this->getTable()}
                SET     `active` = CASE WHEN `uuid` IN ({$predicate['placeholders']}) THEN 1 ELSE 0 END;
            ";
            $bindings = $predicate['bindings'];
        } else {
            // Nothing in the uuids array - set all tours to inactive
            $query = "
                UPDATE  {$this->getTable()}
                SET     `active` = 0;
            ";
            $bindings = null;
        }

        $statement = $this->database->prepare($query);
        $statement->execute($bindings);
    }
}
