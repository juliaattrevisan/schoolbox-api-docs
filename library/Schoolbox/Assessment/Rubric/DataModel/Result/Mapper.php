<?php

/**
 * Mapper for rubric results.  Rubric results are a defined set of results that can be used to prepopulate a
 * rubrics descriptors.  Results come in sets defined by the resultset_id.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Result;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * @method Model[]|GenericCollection findAll($options = array())
 */
class Mapper extends BaseMapper
{

    protected function mapping()
    {
        $this->hasTable('rubric_result');
        $this->hasModel(Model::class);
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
