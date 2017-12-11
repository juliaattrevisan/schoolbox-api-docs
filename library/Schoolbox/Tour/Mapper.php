<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('tour');
        $this->hasModel(Model::class);
        $this->uses('uuid', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->joins('author_user_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('active', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('author', 'a')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('author', '', 'a', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            if ($model instanceof Model) {
                // Hydrate author

                /** @var UserMapper $authorUserMapper */
                $authorUserMapper = $this->getJoinMapper('author');

                /** @var User $author */
                $author = $authorUserMapper->hydrate($row, 'a');
                $model->setAuthor($author);

                // Add hydrated record to results

                $collection->add($model);
            }
        }

        return $collection;
    }

    /**
     * Find all tours relevant to a particular user
     *
     * @param User $user
     *
     * @return GenericCollection
     */
    public function findByUser(User $user): GenericCollection
    {
        $userQuery = $this->getUserFilterQuery($user);

        $statement = $this->getDatabase()->prepare("{$this->getSelectString()} WHERE {$userQuery['sql']}");
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
                                        ->isNull('tour.deleted_at')
                                        ->getWhere()[0];

        // --------------------------------------------------------------------------------------------------------
        // Tours where user is in a selected role
        // --------------------------------------------------------------------------------------------------------

        $audiencedRoleClause = "
            `tour`.`uuid` IN 
            (
                SELECT  `tar`.`tour_uuid` 
                FROM    `tour_audience_role` `tar`
                WHERE   `tar`.`role_id` = (:roleId) AND `tar`.`deleted_at` IS NULL
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
}