<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

namespace Schoolbox\Filter\Filters\AssessmentType;

use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\Assessment\Term;


/**
 * Converts between termFilter concrete keys and Term models
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * Given a concrete key (such as returned from a user's filter panel selections), return the corresponding item
     *
     * @param string $key
     * @return mixed|Term\Model
     * @throws \InvalidArgumentException
     */
    public function getItem($key)
    {
        // Simply returns the key, representing an AssessmentType
        return $key;
    }


    /**
     * Return a concrete key for the specified element
     *
     * @param string $element
     * @return string
     */
    public function getKey($element)
    {
        return $element;
    }

    /**
     * @inheritdoc
     */
    public function sanitiseKey($key)
    {
        $validKeys = [
            Definition::KEY_TASK,
            Definition::KEY_DUE_WORK,
            Definition::KEY_QUIZ,
            Definition::KEY_PROJECT,
        ];
        return in_array($key, $validKeys, true) ? $key : '';
    }
}
