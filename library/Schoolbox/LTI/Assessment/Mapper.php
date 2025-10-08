<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment;

use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\LTI\Provider\Mapper as ToolMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * LTI assessment mapper.
 *
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
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

        // LTI tool join.
        // The column is `lti_provider_id` (mirroring its name in the schema),
        // but references to it contain the name "tool"
        // (which is what providers were renamed to in 1.3)
        $this->joins(
            'lti_provider_id',
            ToolMapper::class,
            'id',
            null,
            null,
            'ltiTool',
            'ltiTool',
        );
        $this->hasJoin(
            'scores',
            [
                'mapper'    => Score\Mapper::class,
                'multiple'  => true,
                'this_key'  => 'id',
                'other_key' => 'lti_assessment_id',
            ],
        );
        $this->uses('lti_assessment_url', self::TYPE_STRING);
        $this->uses('lti_assessment_title', self::TYPE_STRING);
        $this->uses('lti_assessment_text', self::TYPE_STRING);
        $this->uses('custom_fields', self::TYPE_JSON);
        $this->uses('line_item_resource_id', self::TYPE_STRING);
        $this->uses('line_item_score_maximum', self::TYPE_DECIMAL);
        $this->uses('mark_visibility', self::TYPE_INTEGER);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'truncateFields']);
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

    /**
     * Pre-save callback: truncate title and text to fit within their column's
     * field limits.
     *
     * These fields aren't input directly by the user, and are presentational
     * only, so we shouldn't raise an error if they're too large: we should
     * truncate them silently instead.
     *
     * TODO consider making this a property of columns instead
     *
     * @param Model $model
     * @return true
     */
    public function truncateFields(Model $model): bool
    {
        $charset = 'UTF-8';
        if ($model->getLtiAssessmentTitle() !== null) {
            $model->setLtiAssessmentTitle(mb_substr($model->getLtiAssessmentTitle(), 0, 2048, $charset));
        }
        if ($model->getLtiAssessmentText() !== null) {
            $model->setLtiAssessmentText(mb_substr($model->getLtiAssessmentText(), 0, 2048, $charset));
        }
        return true;
    }
}
