<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Mggs\Personalised\Participant;

use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * Data definition for a personalised participation for a student in an assessment.
 *
 * @method Model createModel($id=null)
 */
class Mapper extends BaseMapper
{
    /**
     * Mapping for this table
     */
    protected function mapping()
    {
        $this->hasTable('assessment_participant');

        $this->hasModel(Model::class);

        // Don't think this needs an identifier
        $this->joins('assessment_id', AssessmentMapper::class, 'id', null, null, 'assessment', 'assessment');
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('open_date', self::TYPE_DATETIME);
        $this->uses('due_date', self::TYPE_DATETIME);
    }
}
