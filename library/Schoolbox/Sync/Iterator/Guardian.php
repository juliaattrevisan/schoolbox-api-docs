<?php
/**
 * Iterator over a `External_UserInterface->getParentUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Guardian` object.
 *
 * Note: most implementations of `External_UserInterface->getParentUserAccountInfo()`
 * return a parent and their spouse in a single row. This class expects that
 * parents and speese will be in separate rows.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use IteratorIterator;
use Schoolbox\External\Normalise\User;

class Guardian extends IteratorIterator
{
    /**
     * @var int
     */
    protected $rowNum = 0;

    /**
     * @var array|Guardian[]
     */
    protected $stack = [];

    /**
     * Loads separate parent records from the iterator, and loads them into
     * the stack for this item.
     */
    protected function buildStack()
    {
        $id       = null;
        $preStack = [];
        while ($this->getInnerIterator()->valid() && $row = $this->getInnerIterator()->current()) {
            $thisId = $row['StudentContactID'];
            if (!isset($id)) {
                $id = $thisId;
            }

            // Is this the next parent? step back to previous and break
            if ($thisId != $id) {
                break;
            }
            $preStack[] = $row;
            $this->getInnerIterator()->next();
        }
        if (empty($preStack)) {
            $this->stack = [];
            return;
        }

        // Forge a single parent out of the disparate parts of the prestack
        // Only the following fields will vary:
        // * StudentYearLevel
        // * StudentCampus
        // * StudentGender
        $currentResult = current($preStack);
        $guardian      = new User\Guardian();
        $guardian
            ->setId($currentResult['StudentContactID'])
            ->setTitle($currentResult['StudentContactTitle'])
            ->setPreferredName($currentResult['StudentContactPreferred'])
            ->setLastname($currentResult['StudentContactSurname'])
            ->setGender($currentResult['StudentGender'])
        ;

        /* Email
         */
        $email = new User\Common\Email();
        $email->setAddress($currentResult['StudentContactDefaultEmail']);
        $guardian->addEmail($email);

        /* Account
         */
        $guardian
            ->getAccount()
            ->setLogin($currentResult['StudentContactNetworkLogin'])
        ;

        // Add details for each one of their students
        foreach ($preStack as $row) {
            // Students
            $student = new User\Student();
            $student
                ->setId($row['StudentId'])
                ->setPreferredName($row['StudentPreferred'])
                ->setLastname($row['StudentSurname'])
                ->setGender($row['StudentGender'])
            ;

            $student
                ->getYear()
                ->setLevel($row['StudentYearLevel'])
            ;

            /* Campus
             */
            $campus = new User\Common\Campus();
            $campus->setName($row['StudentCampus']);
            $student->getCampus()->add($campus);
            $guardian->addStudent($student);
        }
        $this->stack[] = $guardian;
    }

    /**
     * Checks if the iterator is valid
     *
     * @return bool
     */
    public function valid()
    {
        if (!empty($this->stack)) {
            return true;
        }
        $this->buildStack();
        return !empty($this->stack);
    }

    /**
     * Return the current element
     *
     * @return User\Guardian
     */
    public function current()
    {
        // Stack is managed by valid()
        return $this->stack[0];
    }

    /**
     * Move forward to next element
     */
    public function next()
    {
        array_shift($this->stack);
        $this->rowNum++;
    }

    /**
     * Get the key of the current element
     *
     * @return int
     */
    public function key()
    {
        return $this->rowNum;
    }
}
