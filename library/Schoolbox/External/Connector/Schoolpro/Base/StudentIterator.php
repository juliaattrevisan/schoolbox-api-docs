<?php

/**
 * StudentIterator
 *
 * Custom implementation for chub
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use IteratorIterator;
use Schoolbox\External\Normalise;
use Schoolbox\External\Normalise\User;

class StudentIterator extends IteratorIterator
{

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
     * @inheritdoc
     */
    public function next()
    {
        $this->getInnerIterator()->next();
        $this->loadCurrent();
    }


    /**
     * @inheritdoc
     */
    public function rewind()
    {
        parent::rewind();
        $this->loadCurrent();
    }


    /**
     * Performs the transformation of database row to Student object
     * @return void
     */
    public function loadCurrent()
    {
        /**
         * @var    array   $currentResult
         * @var    int     $currentResult['ID']        eg 1234
         * @var    string  $currentResult['UserName']  eg "16sdryburgh"
         * @var    string  $currentResult['Title']     eg NULL
         * @var    string  $currentResult['FirstName'] eg "Shania"
         * @var    string  $currentResult['Surname']   eg "Dryburgh"
         * @var    string  $currentResult['email']     eg "16sdryburgh@cedars.nsw.edu.au"
         * @var    int     $currentResult['LevelID']   eg 112
         * @var    int     $currentResult['CampusID']  eg 1
         * @var    int     $currentResult['HouseID']   eg 2
         */
        $currentResult = $this->getInnerIterator()->current();

        if (!is_array($currentResult)) {
            $this->setCurrentItem(null);
            return;
        }


        $student       = new Normalise\User\Student();
        $student
            ->setId($currentResult['ID'])
            ->setTitle($currentResult['Title'])
            ->setPreferredName($currentResult['FirstName'])
            ->setLastname($currentResult['Surname'])
        ;

        $student
            ->getYear()
            ->setLevel($currentResult['LevelID'])
        ;

        $student
            ->getHouse()
            ->setCode($currentResult['HouseID'])
        ;

        $student
            ->getAccount()
            ->setLogin($currentResult['UserName'])
        ;

        /* Assign emails
         */
        if (strlen($currentResult['email'])) {
            $email = new Normalise\User\Common\Email();
            $email
                ->setAddress($currentResult['email']);

            $student->addEmail($email);
        }

        /* Boarding House should be set if it has a value
         * You can't actually rely on StudentBoarder field, because that
         * is not strictly yes/no; sometimes it contains B, W, D, etc.
         * It's really a Student Boarder Type, more than anything
         */
        if (strlen($currentResult['student_boarding_house'] ?? '')) {
            $boardingHouse = new Normalise\User\Common\BoardingHouse();
            $boardingHouse
                ->setCode($currentResult['student_boarding_house'])
            ;

            $student
                ->setBoardingHouse($boardingHouse)
            ;
        }

        /* Campus
         */
        $campus = new Normalise\User\Common\Campus();
        $campus
            ->setCode($currentResult['CampusID'])
        ;

        $student
            ->getCampus()
            ->add($campus)
        ;

        $this->setCurrentItem($student);
    }


    /**
     * @param User\Student|null $currentItem
     */
    protected function setCurrentItem(User\Student $currentItem = null)
    {
        $this->currentItem = $currentItem;
    }
}
