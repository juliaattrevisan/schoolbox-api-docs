<?php
/**
 * Mapper for rubric results.  Rubric results are a defined set of results that can be used to prepopulate a
 * rubrics descriptors.  Results come in sets defined by the resultset_id.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBox;

class ResultMapper extends Schoolbox\Mapper {

    protected function mapping() {
        $this->hasTable('rubric_result');
        $this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\Rubric\\Result');
        $this->hasMap(array(
                'column'     => 'resultset_id',
                'property'   => 'resultSetId',
                'type'       => self::TYPE_INTEGER,
            ));
        $this->hasMap(array(
                'column'     => 'value',
                'property'   => 'value',
                'type'       => self::TYPE_INTEGER,
            ));
        $this->hasMap(array(
                'column'     => 'label',
                'property'   => 'label',
                'type'       => self::TYPE_STRING,
            ));
    }
}
