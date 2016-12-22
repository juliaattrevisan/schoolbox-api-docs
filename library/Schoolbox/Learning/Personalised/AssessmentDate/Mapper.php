<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Personalised\AssessmentDate;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\UserMapper;

/**
 * Data definition for a personalised open/due date for a student in an assessment.
 */
class Mapper extends BaseMapper
{
    /**
     * Mapping for this table
     */
    protected function mapping()
    {
        // Eventually this may become the source of truth for whether a student is participating or not.
        $this->hasTable('assessment_participant');

        $this->hasModel(Model::class);

        // Don't think this table needs an identifier
        $this->joins('assessment_id', AssessmentMapper::class, 'id', null, null, 'assessment', 'assessment');
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('open_date', self::TYPE_DATETIME);
        $this->uses('due_date', self::TYPE_DATETIME);
    }
}
