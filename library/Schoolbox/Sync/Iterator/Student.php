<?php
/**
 * Iterator over a `External_UserInterface->getStudentUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Student` object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Iterator;

use IteratorIterator;
use Schoolbox\External\Normalise\User;

class Student extends IteratorIterator
{
    use StackableHelper;

    /**
     * @var User\Student|null
     */
    private $currentItem;


    /**
     * @return bool
     */
    public function valid()
    {
        return $this->currentItem instanceof User\Student;
    }


    /**
     * Return the current element
     *
     * @return User\Student|null
     */
    public function current()
    {
        return $this->currentItem;
    }


    /**
     * @return void
     */
    public function next()
    {
        $this->getInnerIterator()->next();
        $this->loadCurrent();
    }


    /**
     * Performs the transformation of database row to Student object
     *
     * @return void
     */
    protected function loadCurrent()
    {
        /**
         * @var array  $currentResult
         * @var int    $currentResult['student_id']
         * @var string $currentResult['network_login']
         * @var string $currentResult['student_title']
         * @var string $currentResult['student_given_name']
         * @var string $currentResult['student_preferred']
         * @var string $currentResult['student_surname']
         * @var string $currentResult['student_gender']
         * @var string $currentResult['student_email']
         * @var string $currentResult['student_work_email']
         * @var string $currentResult['student_year_level']
         * @var string $currentResult['student_house']
         * @var string $currentResult['student_boarder']
         * @var string $currentResult['student_boarding_house']
         * @var string $currentResult['student_campus']
         */
        $currentResult = $this->getInnerIterator()->current();

        if(!is_array($currentResult)){
            $this->currentItem = null;
            return;
        }

        $student = (new User\Student())
            ->setId($currentResult['student_id'])
            ->setTitle($currentResult['student_title'])
            ->setGivenName($currentResult['student_given_name'])
            ->setPreferredName($currentResult['student_preferred'])
            ->setLastname($currentResult['student_surname'])
            ->setGender($currentResult['student_gender'])
        ;

        $student
            ->getYear()
            ->setLevel($currentResult['student_year_level'])
        ;

        $student
            ->getHouse()
            ->setCode($currentResult['student_house'])
        ;

        $student
            ->getAccount()
            ->setLogin($currentResult['network_login'])
        ;

        /* Emails - one for every item in stack
         */

        array_map([$student, "addEmail"], $this->columnValuesAsEmail([$currentResult], 'student_default_email'));
        array_map([$student, "addWorkEmail"], $this->columnValuesAsEmail([$currentResult], 'student_work_email'));
        array_map([$student, "addHomeEmail"], $this->columnValuesAsEmail([$currentResult], 'student_home_email'));

        /* Boarding House should be set if it has a value
         * You can't actually rely on StudentBoarder field, because that
         * is not strictly yes/no; sometimes it contains B, W, D, etc.
         * It's really a Student Boarder Type, more than anything
         */
        if (strlen($currentResult['student_boarding_house'])) {
            $boardingHouse = new User\Common\BoardingHouse();
            $boardingHouse
                ->setCode($currentResult['student_boarding_house'])
            ;

            $student
                ->setBoardingHouse($boardingHouse)
            ;
        }

        /* Campus
         */
        $campus = new User\Common\Campus();
        $campus
            ->setCode($currentResult['student_campus'])
        ;

        $student
            ->getCampus()
            ->add($campus)
        ;

        $this->currentItem = $student;
    }


    /**
     * @param User\Student|null $currentItem
     */
    protected function setCurrentItem(User\Student $currentItem = null)
    {
        $this->currentItem = $currentItem;
    }
}
