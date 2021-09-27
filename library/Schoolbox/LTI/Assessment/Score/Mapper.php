<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment\Score;

use Schoolbox\LTI\Assessment\Mapper as LTIAssessmentMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * LTI assessment score mapper.
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lti_assessment_score');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins(
            'lti_assessment_id',
            LTIAssessmentMapper::class,
            'id',
            null,
            null,
            null,
            'lti_assessment',
        );
        $this->joins(
            'student_id',
            UserMapper::class,
            'id',
            null,
            null,
            null,
            'student'
        );
        $this->joins(
            'marking_user_id',
            UserMapper::class,
            'id',
            null,
            null,
            null,
            'marking_user'
        );
        $this->uses('score_given', self::TYPE_DECIMAL);
        $this->uses('score_maximum', self::TYPE_DECIMAL);
        $this->uses('comment', self::TYPE_STRING);
        $this->uses(
            'activity_progress',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            Model::ACTIVITY_PROGRESS_VALID_VALUES,
        );
        $this->uses(
            'grading_progress',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            Model::GRADING_PROGRESS_VALID_VALUES,
        );
        $this->uses('is_response', self::TYPE_BOOLEAN);
        $this->uses('is_feedback', self::TYPE_BOOLEAN);
    }
}
