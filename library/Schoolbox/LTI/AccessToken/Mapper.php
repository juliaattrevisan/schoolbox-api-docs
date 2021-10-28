<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use Schoolbox\GenericCollection;
use Schoolbox\LTI\Provider\Mapper as ToolMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * Mapper for oAuth access tokens for LTI.
 *
 * Whilst this currently covers LTI only, I can see a future where oAuth access
 * tokens are made available for all.
 *
 * @method Model findById($identifierValue)
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] createCollection()
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lti_access_token');
        $this->uses('identifier', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->joins('lti_provider_id', ToolMapper::class, 'id', null, null, 'ltiTool', 'ltiTool');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('expires_at', self::TYPE_DATETIME);
        $this->uses('scopes', self::TYPE_JSON);
    }
}
