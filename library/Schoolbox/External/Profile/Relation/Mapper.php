<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Profile\Relation;

use PDOStatement;
use Schoolbox\External\Profile;
use Schoolbox\GenericCollection;

/**
 * Class Mapper
 *
 * @package Schoolbox\External\Profile\Relation
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_user_profile_relation');
        $this->hasModel(Model::class);
        $this->uses('child_profile_id', self::TYPE_MODEL);
        $this->uses('user_profile_id', self::TYPE_MODEL);
        $this->uses('type', self::TYPE_STRING);
        $this->joins('child_profile_id', Profile\Mapper::class, 'id', null, null, 'childProfile', 'childProfile');
        $this->joins('user_profile_id', Profile\Mapper::class, 'id', null, null, 'userProfile', 'userProfile');
    }


    /**
     * {@inheritdoc}
     *
     * @return string|void
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('childProfile', 'cp')},
                {$this->getJoinColumnString('userProfile', 'up')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('childProfile', '', 'cp')}
            INNER JOIN {$this->getJoinSql('userProfile', '', 'up')}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $relation = $this->hydrate($row);

            /* Child profile
             */
            /** @var Profile\Model $childProfile */
            $childProfile = $this->hydrateJoin($row, 'childProfile', 'cp');

            /* User profile
             */
            /** @var Profile\Model $userProfile */
            $userProfile = $this->hydrateJoin($row, 'userProfile', 'up');

            /* Assign them (they should both exist due to FKs)
             */
            $relation->setChildProfile($childProfile)->setUserProfile($userProfile);

            /* Stack 'em
             */
            $collection->add($relation);
        }

        return $collection;
    }
}
