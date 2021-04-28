<?php
/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Help\InstanceRegistry;

use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for the instance registry table.
 *
 * This table contains all information which this instance needs to know about
 * other Schoolbox instances.
 *
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 */
class Mapper extends BaseMapper
{
    public const TABLE = 'instance_registry';
    public const COL_ID = 'id';
    public const COL_INSTANCE_ID = 'instance_id';
    public const COL_SITE_SCHOOL_CODE = 'site_school_code';
    public const COL_DOMAIN_NAME = 'domain_name';
    public const COL_JWT_PUBLIC_KEY = 'jwt_public_key';
    public const COL_CREATED_AT = 'created_at';
    public const COL_UPDATED_AT = 'updated_at';
    public const COL_DELETED_AT = 'deleted_at';

    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE);
        $this->hasModel(Model::class);
        $this->uses(self::COL_ID, self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses(self::COL_INSTANCE_ID, self::TYPE_STRING);
        $this->uses(self::COL_SITE_SCHOOL_CODE, self::TYPE_STRING);
        $this->uses(self::COL_DOMAIN_NAME, self::TYPE_STRING);
        $this->uses(self::COL_JWT_PUBLIC_KEY, self::TYPE_STRING);
        $this->uses(self::COL_CREATED_AT, self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses(self::COL_UPDATED_AT, self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses(self::COL_DELETED_AT, self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Find a registration record by its instance ID.
     *
     * @param string $instanceId
     * @return Model
     * @throws BaseMapper\Exception if this registration wasn't found
     */
    public function findByInstanceId(string $instanceId): Model
    {
        return $this->findOneByColumn(self::COL_INSTANCE_ID, $instanceId);
    }
}
