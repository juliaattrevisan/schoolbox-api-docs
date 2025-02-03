<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty\Grant;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Oidc\Server\RelyingParty\Mapper as RelyingPartyMapper;
use Schoolbox\Oidc\Server\RelyingParty\Model as RelyingPartyModel;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Mapper for storing whether a user has granted permission to an OIDC relying
 * party.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method GenericCollection<Model> createCollection($contents = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('oidc_relying_party_grant');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(
            'oidc_relying_party_id',
            RelyingPartyMapper::class,
            'id',
            null,
            null,
            'relyingParty',
            'relyingParty',
        );
        $this->joins(
            'user_id',
            UserMapper::class,
            'id',
            null,
            null,
            'user',
            'user',
        );
        $this->joinsReverseMulti(
            'oidc_relying_party_grant_id',
            Scope\Mapper::class,
            'scopes',
            'scope',
        );

        $this->uses('expires_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->registerCallback(self::CALLBACK_PRESAVE, $this->assertScopeRelation(...));
        $this->registerCallback(self::CALLBACK_POSTSAVE, $this->persistScopes(...));
    }


    /**
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('relyingParty', 'relyingParty')},
                {$this->getJoinColumnString('user', 'user')}
        ";
    }

    /**
     * @return string
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->database->getQuoter()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('relyingParty', '', 'relyingParty', true)}
            LEFT JOIN {$this->getJoinSql('user', '', 'user', true)}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $items = $this->createCollection();
        foreach ($statement as $row) {
            $items[$row['id']] = $this->hydrate($row)
                ->setRelyingParty($this->hydrateJoin($row, 'relyingParty', 'relyingParty'))
                ->setUser($this->hydrateJoin($row, 'user', 'user'));
        }

        $groupedScopes = $this->getJoinMapper('scope')
            ->findByColumn('oidc_relying_party_grant_id', $items->getPropertyValues('id'))
            ->groupByCallback(fn (Scope\Model $model) => $model->getRelyingPartyGrant()->getId());
        foreach ($groupedScopes as $modelId => $scopes) {
            $items[$modelId]->setScopes($scopes);
        }
        return $items;
    }

    /**
     * Find all permissions that `$user` has granted.
     *
     * @param User $user
     * @return GenericCollection<Model>
     */
    public function findByUser(User $user): GenericCollection
    {
        return $this->findByColumn('user_id', $user->getId());
    }

    /**
     * Finds the user's granted permission to the relying party, if there is
     * one.
     *
     * @param RelyingPartyModel $relyingParty
     * @param User              $user
     * @return Model|null
     */
    public function findByRelyingPartyAndUser(RelyingPartyModel $relyingParty, User $user): ?Model
    {
        // Fetch the last one to expire: that should be the only canonical
        // granted permission.
        return $this->findByColumnList(
            [
                'oidc_relying_party_id' => $relyingParty->getId(),
                'user_id'               => $user->getId(),
            ],
            [
                'limit' => 1,
                'order' => ['expires_at' => self::SORT_ORDER_DESC],
            ],
        )->first();
    }

    /**
     * Pre-save callback: assert that the grant's scope objects are not
     * associated with another grant.
     *
     * @param Model $model
     * @return bool
     */
    public function assertScopeRelation(Model $model): bool
    {
        /** @var Scope\Mapper $scopeMapper */
        $scopeMapper = $this->getJoinMapper('scope');
        foreach ($model->getScopes() as $scope) {
            $scope->setRelyingPartyGrant($model);
            if ($scope->getId()) {
                $scopeMapper->assertJoinedModelNotChanged($scope, 'relyingPartyUser');
            }
        }
        return true;
    }

    /**
     * Post-save callback: persist scopes associated with the saved grant.
     *
     * This also deletes scopes which were a part of the previous grant, but are
     * not part of the new one.
     *
     * @param Model      $model
     * @param Model|null $previous
     * @return bool Always returns true
     * @throws BaseMapper\Exception
     */
    public function persistScopes(Model $model, ?Model $previous = null): bool
    {
        /** @var Scope\Mapper $scopeMapper */
        $scopeMapper = $this->getJoinMapper('scope');
        if (!$previous) {
            $scopeMapper->save($model->getScopes());
        } else {
            $scopeMapper->save($model->getScopes()->diffBy($previous->getScopes(), 'scope'));
            $scopeMapper->delete($previous->getScopes()->diffBy($model->getScopes(), 'scope'));
        }
        return true;
    }
}
