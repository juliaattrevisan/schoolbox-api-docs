<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment;

use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * LTI assessment mapper.
 *
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lti_assessment');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(
            'assessment_id',
            AssessmentMapper::class,
            'id',
            null,
            null,
            'assessmentModel',
            'assessmentModel',
        );
        $this->uses('lti_assessment_url', self::TYPE_STRING);
    }

    /**
     * This mapper takes its soft-deletable status from the linked assessment
     * mapper.
     *
     * @inheritdoc
     */
    public function isSoftDeletable(): bool
    {
        return $this->getJoinMapper('assessmentModel')->isSoftDeletable();
    }
}
