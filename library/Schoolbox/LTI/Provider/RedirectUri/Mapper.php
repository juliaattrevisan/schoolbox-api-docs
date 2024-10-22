<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Schoolbox\LTI\Provider\RedirectUri;

use Schoolbox\LTI\Provider as LtiProvider;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for redirect URIs associated with a LTI provider.
 *
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lti_provider_redirect_uri');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(
            'lti_provider_id',
            LtiProvider\Mapper::class,
            'id',
            null,
            null,
            'ltiProvider',
            'ltiProvider'
        );
        $this->uses('redirect_uri', self::TYPE_STRING);
    }
}
