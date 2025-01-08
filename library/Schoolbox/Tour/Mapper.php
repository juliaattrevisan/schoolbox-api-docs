<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
 * @method Model findOneByColumnList($columnList, array $options = [])
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
        $this->joins('first_step_uuid', TourStepMapper::class, 'uuid', null, null, 'firstStep', 'firstStep');
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('active', self::TYPE_BOOLEAN);

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasJoin('trigger', [
            'mapper' => TriggerMapper::class,
            'this_key' => 'uuid',
            'other_key' => 'tour_uuid',
        ]);

        $this->registerCallback(self::CALLBACK_PRESAVE, function (Model $model) {
            return $this->preSave($model);
        });

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, function (Model $model) {
            return $this->postDelete($model);
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
                        {$this->getJoinColumnString('firstStep', 's')},
                        {$this->getJoinColumnString('trigger', 't')}
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('author', '', 'a', true)}
            INNER JOIN  {$this->getJoinSql('trigger', '', 't')}
            INNER JOIN  {$this->getJoinSql('firstStep', '', 's')}
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
        $tourStepMapper = $this->getJoinMapper('firstStep');

        /** @var TriggerMapper $triggerMapper */
        $triggerMapper = $this->getJoinMapper('trigger');

        foreach ($statement as $row) {
            $model = $this->hydrate($row);
            $model->setTriggerList(new GenericCollection(Trigger\Model::class));

            if ($model instanceof Model) {
                $model->setAuthor($authorUserMapper->hydrate($row, 'a'));
                $model->setFirstStep($tourStepMapper->hydrate($row, 's'));

                $trigger = $triggerMapper->hydrate($row, 't');
                if ($trigger) {
                    $model->getTriggerList()->setOffset($trigger->getUuid(), $trigger);
                }

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
            WHERE       {$userQuery['query']} AND
                        :url REGEXP `t`.`url_regex` AND
                        `{$this->getTable()}`.`active` = 1
        ";

        if (!$repeat) {
            $userQuery['bindings']['user_id'] = $user->getId();

            $sql = $sql . "AND
                            tour.uuid NOT IN (
                            SELECT tour_uuid
                            FROM tour_status
                            WHERE user_id = :user_id)";
        }

        $sql .= ' ORDER BY updated_at';

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
        // Concatenate all the predicates into an OR query and return the SQL in an array along with bindings
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

    /**
     * Deletes steps and triggers related to tour being deleted
     * @param Model $model
     * @return bool
     */
    private function postDelete(Model $model): bool
    {
        /** @var TourStepMapper $tourStepMapper */
        $tourStepMapper = $this->getJoinMapper('firstStep');

        /** @var TriggerMapper $triggerMapper */
        $triggerMapper = $this->getJoinMapper('trigger');

        $tourStepMapper->deleteByColumn('tour_uuid', $model->getUuid());
        $triggerMapper->deleteByColumn('tour_uuid', $model->getUuid());
        return true;
    }

    /**
     * Return the audience role names for the given tour UUID.
     *
     * @param string $tourUuid
     * @return array<string>
     */
    public function getAudienceRoleNames(string $tourUuid): array
    {
        return $this->database->executeQuery(
            'SELECT DISTINCT g.name
            FROM tour_audience_role tar
            JOIN groups g ON g.id = tar.role_id
            WHERE tar.tour_uuid = ?
            AND g.deleted_at IS NULL
            ORDER BY g.name ASC',
            [$tourUuid]
        )->fetchColumnArray('name');
    }

    /**
     * Return an array of arrays of audience role names, indexed by tour UUID.
     *
     * @param bool $includeDisabled Whether to include rows in which `active` is 0.
     * @return array<string, array<string>>
     */
    public function getAudienceRoleNamesByUuid(bool $includeDisabled): array
    {
        $tourUuids = $this->database->executeQuery(
            'SELECT uuid
            FROM tour
            WHERE deleted_at IS NULL' .
            ($includeDisabled ? '' : ' AND active = 1')
        )->fetchColumnArray('uuid');

        $roleNamesByUuid = [];

        foreach ($tourUuids as $tourUuid) {
            $roleNamesByUuid[$tourUuid] = $this->getAudienceRoleNames($tourUuid);
        }

        return $roleNamesByUuid;
    }
}
