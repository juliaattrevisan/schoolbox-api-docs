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
use Schoolbox\GenericCollection;

/**
 * @method Result[]|GenericCollection findAll($options = array())
 */
class ResultMapper extends Schoolbox\Mapper {

    protected function mapping()
    {
        $this->hasTable('rubric_result');
        $this->hasModel(Result::class);
        $this->uses('resultset_id', self::TYPE_INTEGER, null, 'resultSetId'); // This is NOT an identifier
        $this->uses('value', self::TYPE_INTEGER);
        $this->uses('label', self::TYPE_STRING);
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }

}
