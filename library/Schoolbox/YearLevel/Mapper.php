<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\YearLevel;

use InvalidArgumentException;
use Schoolbox;

/**
 * See Model's class docblock for entity semantics.
 * @see \Schoolbox\YearLevel\Model
 */
class Mapper extends Schoolbox\Mapper
{
    public const TABLE_NAME     = 'lu_year_level';
    public const COL_ID         = 'id';
    public const COL_NAME       = 'name';
    public const COL_SEQUENCE   = 'sequence';
    public const COL_CREATED_AT = 'created_at';
    public const COL_UPDATED_AT = 'updated_at';
    public const COL_DELETED_AT = 'deleted_at';


    /**
     * Defines the mapping of PHP properties to SQL columns
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);

        $this->uses(self::COL_ID, self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses(self::COL_NAME, self::TYPE_STRING);
        $this->uses(self::COL_SEQUENCE, self::TYPE_INTEGER);
        $this->uses(self::COL_CREATED_AT, self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses(self::COL_UPDATED_AT, self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses(self::COL_DELETED_AT, self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'setSequenceIfUnset']);
    }

    /**
     * Sets the model's sequence to the next available value if not already set
     *
     * @param Model $model
     * @return bool
     */
    protected function setSequenceIfUnset(Model $model): bool
    {
        if ($model->getSequence() === null) {
            $sql = sprintf(
                "SELECT MAX(%s) FROM %s WHERE %s IS NULL",
                self::COL_SEQUENCE,
                self::TABLE_NAME,
                self::COL_DELETED_AT
            );
            $maxSequence = $this->getDatabase()->query($sql)->fetchColumn();
            $nextSequence = $maxSequence !== null ? $maxSequence + 1 : $model::sequenceBase();
            $model->setSequence($nextSequence);
        }
        return true;
    }

    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }
}