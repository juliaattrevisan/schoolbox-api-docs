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

        // LTI assessment GUID: an identifier for the future LTI assessment
        $this->uses('lti_assessment_guid', self::TYPE_STRING, self::COLUMN_UUID);

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
    }
}
