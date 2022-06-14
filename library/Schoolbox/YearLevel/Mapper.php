<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\YearLevel;

use InvalidArgumentException;
use PDO;
use Schoolbox;
use Schoolbox\Mapper\Exception\CallbackException;

use function array_map;
use function implode;

/**
 * See Model's class docblock for entity semantics.
 * @see \Schoolbox\YearLevel\Model
 *
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Schoolbox\GenericCollection|Model[] findAll($options = [])
 */
class Mapper extends Schoolbox\Mapper
{
    public const TABLE_NAME                    = 'lu_year_level';
    public const COL_ID                        = 'id';
    public const COL_NAME                      = 'name';
    public const COL_OVERALL_GRADE             = 'overall_grade';
    public const COL_SHOW_OVERALL_GRADE        = 'show_overall_grade';
    public const COL_DISPLAY_GRADE_PAGE        = 'display_grade_page';
    public const COL_DISPLAY_ASSESSMENT_GRADE  = 'display_assessment_grade';
    public const COL_DISPLAY_RUBRIC_GRADE      = 'display_rubric_grade';
    public const COL_DISPLAY_CAPABILITY_GRADE  = 'display_capability_grade';
    public const COL_DISPLAY_PROGRESSION_GRADE = 'display_progression_grade';
    public const COL_SHOW_RUBRIC_POINTS        = 'show_rubric_points';
    public const COL_LMS_COHORT_COMPARISON     = 'lms_cohort_comparison';
    public const COL_LEARNING_MOMENTS_ENABLED  = 'learning_moments_enabled';
    public const COL_QRCODE_ENABLED            = 'qrcode_enabled';
    public const COL_SEQUENCE                  = 'sequence';
    public const COL_CREATED_AT                = 'created_at';
    public const COL_UPDATED_AT                = 'updated_at';
    public const COL_DELETED_AT                = 'deleted_at';


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
        $this->uses(self::COL_OVERALL_GRADE, self::TYPE_INTEGER);
        $this->uses(self::COL_SHOW_OVERALL_GRADE, self::TYPE_BOOLEAN);
        $this->uses(self::COL_DISPLAY_GRADE_PAGE, self::TYPE_BOOLEAN);
        $this->uses(self::COL_DISPLAY_ASSESSMENT_GRADE, self::TYPE_BOOLEAN);
        $this->uses(self::COL_DISPLAY_RUBRIC_GRADE, self::TYPE_BOOLEAN);
        $this->uses(self::COL_DISPLAY_CAPABILITY_GRADE, self::TYPE_BOOLEAN);
        $this->uses(self::COL_DISPLAY_PROGRESSION_GRADE, self::TYPE_BOOLEAN);
        $this->uses(self::COL_SHOW_RUBRIC_POINTS, self::TYPE_BOOLEAN);
        $this->uses(self::COL_LMS_COHORT_COMPARISON, self::TYPE_BOOLEAN);
        $this->uses(self::COL_LEARNING_MOMENTS_ENABLED, self::TYPE_BOOLEAN);
        $this->uses(self::COL_QRCODE_ENABLED, self::TYPE_BOOLEAN);
        $this->uses(self::COL_SEQUENCE, self::TYPE_INTEGER);
        $this->uses(self::COL_CREATED_AT, self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses(self::COL_UPDATED_AT, self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses(self::COL_DELETED_AT, self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateName']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'setSequenceIfUnset']);
    }


    /**
     * Validates that the name is not empty and is unique
     *
     * @param Model $model
     * @return bool
     * @throws CallbackException
     */
    protected function validateName(Model $model): bool
    {
        if (trim($model->getName() ?? '') === '') {
            throw new CallbackException("YearLevel name cannot be empty");
        }
        if (!$this->isUnique(['name' => $model->getName()], $model->getId())) {
            throw new CallbackException(
                sprintf(
                    'A YearLevel with the name "%s" already exists, you must have a unique name',
                    $model->getName()
                )
            );
        }

        return true;
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
        return [self::COL_SEQUENCE => 'ASC'];
    }
}
