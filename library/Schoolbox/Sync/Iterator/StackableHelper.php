<?php
/**
 * Helper trait that does some data manipulation common to all user types
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use Schoolbox\External\Normalise\User;

/**
 * Class StackableHelper
 *
 * @package Schoolbox\Sync\Iterator
 */
trait StackableHelper
{
    /**
     * This takes a database result (stored an an array of rows- which in turn is an array of columns), and shrinks it down to a single dimensional
     * array with just the values from the specified column
     *
     * @param array $rows
     * @param string $columnName
     * @return string[]
     */
    public function columnValuesAsArray($rows, $columnName)
    {
        $array = [];

        foreach ($rows as $row) {
            $array = array_merge(
                $array,
                preg_split('/[,;]/', $row[$columnName])
            );
        }

        // Make it unique and remove blanks before we return the array

        return array_filter(array_unique($array));
    }

    /**
     * Does the same as above, but changes the values in the returned array to instances of Schoolbox\External\Normalise\User\Common\Email
     *
     * @param array $rows
     * @param string $columnName
     * @return \Schoolbox\External\Normalise\User\Common\Email[]
     */
    public function columnValuesAsEmail($rows, $columnName)
    {
        $emails = $this->columnValuesAsArray($rows, $columnName);

        $returnEmails = [];

        foreach ($emails as $key => $email) {
            $currentEmail = new User\Common\Email();
            $currentEmail->setAddress($email);
            $returnEmails[] = $currentEmail;
        }

        return $returnEmails;
    }
}