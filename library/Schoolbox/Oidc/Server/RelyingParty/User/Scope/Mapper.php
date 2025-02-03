<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\RelyingParty\User\Scope;

use Schoolbox\GenericCollection;
use Schoolbox\Oidc\Server\RelyingParty\User\Mapper as RelyingPartyUserMapper;
use Schoolbox\Pastoral\BaseMapper;

/**
 * Mapper for the scopes which a user has granted access to, as part of granting
 * access to a relying party.
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
        $this->hasTable('oidc_relying_party_user_scope');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(
            'oidc_relying_party_user_id',
            RelyingPartyUserMapper::class,
            'id',
            null,
            null,
            'relyingPartyUser',
            'relyingPartyUser',
        );
        $this->uses('scope', self::TYPE_STRING);
    }
}
