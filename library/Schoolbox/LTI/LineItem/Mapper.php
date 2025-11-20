<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\LineItem;

use Schoolbox\LTI\Assessment\Mapper as LtiAssessmentMapper;
use Schoolbox\Mapper as BaseMapper;

/**
 * LTI line item mapper.
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
        $this->hasTable('lti_line_item');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        // Join to lti_assessment table
        $this->joins(
            'lti_assessment_id',
            LtiAssessmentMapper::class,
            'id',
            null,
            null,
            'ltiAssessment',
            'ltiAssessment',
        );

        // LTI assessment GUID: an identifier for the future LTI assessment.
        // Note this doesn't have self::COLUMN_UUID, as it should be managed
        // outside of the mapper's save method.
        $this->uses('lti_assessment_guid', self::TYPE_STRING);

        // Identifier URL: used on the tool's end to identify the line item
        $this->uses('identifier_url', self::TYPE_STRING);

        $this->uses('label', self::TYPE_STRING);
        $this->uses('score_maximum', self::TYPE_DECIMAL);
        $this->uses('resource_id', self::TYPE_STRING);
        $this->uses('resource_link_id', self::TYPE_STRING);
        $this->uses('tag', self::TYPE_STRING);
        $this->uses('start_date', self::TYPE_DATETIME);
        $this->uses('end_date', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->registerCallback(self::CALLBACK_PRESAVE, $this->associateWithLtiAssessment(...));
    }

    /**
     * Pre-save function: associate this line item with the LTI assessment whose
     * GUID matches the model's `lti_assessment_guid` field.
     *
     * If there's no such assessment: this method will raise an error.
     *
     * @param Model $model
     * @return void
     */
    public function associateWithLtiAssessment(Model $model): void
    {
        if ($model->getLtiAssessmentGuid()) {
            $ltiAssessment = $this->getJoinMapper('ltiAssessment')
                ->findOneByColumn('guid', $model->getLtiAssessmentGuid());
            $model->setLtiAssessment($ltiAssessment);
        }
    }
}
