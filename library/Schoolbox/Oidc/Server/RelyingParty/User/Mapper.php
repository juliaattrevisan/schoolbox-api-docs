<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty\User;

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
        $this->hasTable('oidc_relying_party_user');
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

        $this->uses('expires_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
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
        return $items;
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
}
