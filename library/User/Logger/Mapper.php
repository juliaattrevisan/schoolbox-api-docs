<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace User\Logger;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper
{
    // Table definition
    public const TABLE_NAME       = 'user_assume_log';
    public const FIELD_ID         = 'id';
    public const FIELD_USER       = 'user';
    public const FIELD_ASSUMED    = 'assumed';
    public const FIELD_IP         = 'ip';
    public const FIELD_CREATED_AT = 'created_at';

    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);

        $this->uses(self::FIELD_ID, self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses(self::FIELD_USER, self::TYPE_STRING);
        $this->uses(self::FIELD_ASSUMED, self::TYPE_STRING);
        $this->uses(self::FIELD_IP, self::TYPE_STRING);
        $this->uses(self::FIELD_CREATED_AT, self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }
}
