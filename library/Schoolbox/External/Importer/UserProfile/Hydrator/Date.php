<?php
/**
 * A Date variant of Field
 *
 * Specifically built to deal with null dates being mysteriously
 * transformed into current date value after hydration
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer\UserProfile\Hydrator;

use Schoolbox\External\UserProfile as UserProfileModel;
use Schoolbox\External\Importer\User as UserImporter;
use Alaress_Date_Format;
use Alaress_Date_NullDate;

/**
 * Sets a field in the user based on the value of a key in the imported row,
 * if that key exists in the row.
 *
 * @package Schoolbox\External\Importer\UserProfile\Hydrator
 */
class Date implements HydratorInterface
{
    /**
     * The key in $row to set the value in the user to.
     *
     * @var string
     */
    protected $fieldname;

    /**
     * The set method of UserProfileModel to call in order to set the field.
     *
     * @var string
     */
    protected $setMethod;

    protected $userImporter;


    /**
     * Constructor
     *
     * @param string $fieldname
     * @param string $setMethod
     */
    public function __construct($fieldname, $setMethod, UserImporter $userImporter)
    {
        $this->fieldname = $fieldname;
        $this->setMethod = $setMethod;
        $this->userImpoter = $userImporter;

    }

    /**
     * @inheritdoc
     */
    public function hydrate($row, UserProfileModel $user)
    {
        /* If $this->fieldname has no length
         * Assume null data
         */
        if (!strlen(trim($row[$this->fieldname]))) {
            return call_user_func([$user, $this->setMethod], new Alaress_Date_NullDate());
        }

        /* Date has been provided
         */

        if ($this->setMethod === 'setDob') {

            $date = null;
            try {
                $date = new Alaress_Date_Format(
                    $row[$this->fieldname],
                    [Alaress_Date_Format::DATABASE_DATE_FORMAT]
                );

            } catch (\InvalidArgumentException $e) {

            }
            if (!$date || !$date->isValid()) {
                throw new \UnexpectedValueException('"Date of Birth" must be a date in YYYY-MM-DD format: skipping the value');
            }
            return call_user_func([$user, 'setDob'], $date);

        }

        return call_user_func([$user, $this->setMethod], $row[$this->fieldname]);
    }

}
