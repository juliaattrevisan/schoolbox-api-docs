<?php
/**
 * Mapper for the submission box grades.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('submission_grade');
        $this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\GradeLevel\\Model');
        $this->hasMap(
            array(
                'column'     => 'grade_id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'submission_grade_group_id',
                'property' => 'group',
                'type'     => self::TYPE_MODEL,
                'join'     => 'group',
                'join_def' => array(
                    'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\GradeLevel\\Group\\Mapper',
                    'other_key' => 'id',
                )
            )
        );
        $this->hasMap(
            array(
                'column'   => 'lower_bound',
                'property' => 'lowerBound',
                'type'     => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'raw_grade',
                'property' => 'rawGrade',
                'type'     => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'caption',
                'property' => 'caption',
                'type'     => self::TYPE_STRING,
            )
        );
    }
}
