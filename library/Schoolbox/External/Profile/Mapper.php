<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Profile;

use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage;

/**
 * Class Mapper
 *
 * @package Schoolbox\External\Profile
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
        $this->hasTable('sis_user_profile');
        $this->hasModel(Model::class);
        $this->uses('external_id', self::TYPE_STRING);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('firstname', self::TYPE_STRING);
        $this->uses('preferred_firstname', self::TYPE_STRING, null, 'firstnamePreferred');
        $this->uses('lastname', self::TYPE_STRING);
        $this->joins('portrait_hash', Storage\Mapper::class, 'hash', null, null, 'portrait', 'portrait');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }
}
