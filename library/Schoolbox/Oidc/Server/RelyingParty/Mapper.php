<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * Mapper for OIDC relying party configurations.
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
        $this->hasTable('oidc_relying_party');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('client_id', self::TYPE_STRING);
        $this->uses('client_secret_hash', self::TYPE_STRING);
        $this->uses('redirect_uri', self::TYPE_STRING);
        $this->uses('back_channel_logout_uri', self::TYPE_STRING);
        $this->joins(
            'client_credentials_user_id',
            UserMapper::class,
            'id',
            null,
            null,
            'clientCredentialsUser',
            'clientCredentialsUser',
        );
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->registerCallback(self::CALLBACK_PRESAVE, $this->assertUniqueName(...));
    }

    /**
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('clientCredentialsUser', 'clientCredentialsUser')}
        ";
    }

    /**
     * @return string
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->database->getQuoter()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('clientCredentialsUser', '', 'clientCredentialsUser', true)}
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
                ->setClientCredentialsUser($this->hydrateJoin($row, 'clientCredentialsUser', 'clientCredentialsUser'));
        }
        return $items;
    }


    /**
     * Find a single relying party by its client ID.
     *
     * @param string $clientId
     * @return Model|null The relying party model, or null if it doesn't exist.
     */
    public function findByClientId(string $clientId): ?Model
    {
        return $this->findByColumn('client_id', $clientId)->first();
    }

    /**
     * Assert whether this relying party's name is unique.
     *
     * @param Model|null $model
     * @return bool
     */
    public function assertUniqueName(?Model $model): bool
    {
        if ($model) {
            if (!$this->isUnique(['name' => $model->getName()], $model->getId())) {
                throw new BaseMapper\Exception\CallbackException(
                    sprintf(
                        'The name "%s" is already in use',
                        $model->getName()
                    )
                );
            }
        }
        return true;
    }
}
