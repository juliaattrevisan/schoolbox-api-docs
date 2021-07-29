<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

use RangeException;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Mapper for due work grade levels.
 *
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    private const MIN_VALUE = 0;
    private const MAX_VALUE = 100;

    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('submission_grade');
        $this->hasModel(Model::class);
        $this->uses('grade_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins(
            'submission_grade_group_id',
            Group\Mapper::class,
            'id',
            null,
            null,
            'group',
            'group'
        );
        $this->uses('lower_bound', self::TYPE_DECIMAL);
        $this->uses('raw_grade', self::TYPE_DECIMAL);
        $this->uses('caption', self::TYPE_STRING);
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        //apply createdBy() to new rows only
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'applyCreatedBy']);

        // Validation
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'assertValidCaption']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'assertValidLowerBound']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'assertValidRawGrade']);
    }

    /**
     * Pre-save validation for the caption: it must be set and not empty.
     *
     * @param Model $model
     * @return bool
     */
    public function assertValidCaption(Model $model): bool
    {
        if ($model->getCaption() === '') {
            throw new UnexpectedValueException('Caption must not be empty');
        }
        return true;
    }


    /**
     * Pre-save validation for the lower bound: it must be set and within 0-100.
     *
     * @param Model $model
     * @return bool
     */
    public function assertValidLowerBound(Model $model): bool
    {
        $this->assertBetween($model->getLowerBound(), 'Lower bound', self::MIN_VALUE, self::MAX_VALUE);
        return true;
    }


    /**
     * Pre-save validation for the raw grade: it must be set and within 0-100.
     *
     * @param Model $model
     * @return bool
     */
    public function assertValidRawGrade(Model $model): bool
    {
        $this->assertBetween($model->getRawGrade(), 'Raw grade', self::MIN_VALUE, self::MAX_VALUE);
        return true;
    }


    /**
     * Helper for asserting `$value` is between `$min` and `$max`: if it isn't,
     * raise a `RangeException` containing `$label`.
     *
     * @param float  $value
     * @param string $label
     * @param int    $min
     * @param int    $max
     * @throws RangeException if not between `$min` and `$max`
     */
    private function assertBetween(float $value, string $label, int $min, int $max): void
    {
        if ($value < $min) {
            throw new RangeException(sprintf('%s must be greater than %d', $label, self::MIN_VALUE));
        }
        if ($value > $max) {
            throw new RangeException(sprintf('%s must be less than %d', $label, self::MAX_VALUE));
        }
    }

    /**
     * @param Model $gradeLevel
     * @return bool
     */
    public function applyCreatedBy(Model $gradeLevel): bool
    {
        if ($gradeLevel->getId() == null) {
            $gradeLevel->setCreatedBy($gradeLevel->getUpdatedBy());
        }

        return true;
    }
}
