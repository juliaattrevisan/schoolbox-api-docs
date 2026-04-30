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
 * @method GenericCollection<Model> findAll($options = array())
 */
class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('rubric_result');
        $this->hasModel(Model::class);

        // This is NOT an identifier
        /* This ^^^ cryptic message was trying to explain that resultSetId doesn't correspond to a primary key in any of
         * the established rubric schema. It's an identifier that relates a set of descriptors, ordered by value, which
         * ends up being used in a shortcut to mass-produce descriptors from a predefined set, saving users manually
         * crafting them. It's an identifier in the sense that descriptors in a set are united by this resultSetId, but
         * it's not an autoincrement id like you might have naturally assumed.
         */
        $this->uses('resultset_id', self::TYPE_INTEGER, null, 'resultSetId');

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
    protected function raiseNotification($object, $verb, $previous = null): bool
    {
        return true;
    }
}
