<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer\User\Hydrator;

use Schoolbox\User as UserModel;

/**
 * Sets a field value based on a lookup.
 *
 * @package Schoolbox\External\Importer\User\Hydrator
 */
class Lookup implements HydratorInterface
{
    /**
     * The key in $row to set the value in the user to.
     *
     * @var string
     */
    protected $fieldname;

    /**
     * The set method of UserModel to call in order to set the field.
     *
     * @var string
     */
    protected $setMethod;

    /**
     * @var array
     */
    protected $lookupValues;

    /**
     * Constructor
     *
     * @param string $fieldname
     * @param string $setMethod
     * @param array  $lookupValues
     */
    public function __construct($fieldname, $setMethod, $lookupValues)
    {
        $this->fieldname    = $fieldname;
        $this->setMethod    = $setMethod;
        $this->lookupValues = $lookupValues;
    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, UserModel $user)
    {
        // Does the field exist in $row?
        if (isset($row[$this->fieldname])) {
            $row[$this->fieldname] = trim($row[$this->fieldname]);

            /* If the row contains an empty string, set it to null
             */
            if ('' === $row[$this->fieldname]) {
                return call_user_func(
                    [$user, $this->setMethod],
                    null
                );
            }

            // Is the field value in the list of lookup values?
            if (isset($this->lookupValues[$row[$this->fieldname]])) {
                return call_user_func(
                    [$user, $this->setMethod],
                    $this->lookupValues[$row[$this->fieldname]]
                );
            }
        }
        return $user;
    }
}
