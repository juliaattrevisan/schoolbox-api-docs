<?php
/**
 * Iterator over a `External_UserInterface->getStudentUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Student` object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\Normalise\User\Common;
use Schoolbox\External\Normalise\User\Student;

/**
 * @method SeekableIterator getInnerIterator()
 */
class External_StudentAccountIterator extends IteratorIterator implements SeekableIterator
{
    /**
     * @var resource
     */
    protected $result;

    /**
     * @var array
     */
    protected $currentRow = [];

    /**
     * @var int
     */
    protected $rowNum = 0;

    /**
     * Constructor
     *
     * @param SeekableIterator $iterator
     */
    public function __construct(SeekableIterator $iterator)
    {
        parent::__construct($iterator);
    }

    /**
     * Return the current element
     *
     * @return Student
     */
    public function current()
    {
        // Build a normalized object out of the current item
        // (copypasted from Adapter->getStudentUserAccountInfo)
        $currentResult = $this->getInnerIterator()->current();
        $student       = new Student();
        $student
            ->setId($currentResult['StudentID'])
            ->setTitle($currentResult['StudentTitle'])
            ->setPreferredName($currentResult['StudentPreferred'])
            ->setLastname($currentResult['StudentSurname'])
            ->setGender($currentResult['StudentGender'])
        ;

        $student
            ->getYear()
            ->setLevel($currentResult['StudentYearLevel'])
        ;

        $student
            ->getHouse()
            ->setName($currentResult['StudentHouse'])
        ;

        $student
            ->getAccount()
            ->setLogin($currentResult['NetworkLogin'])
        ;

        /* Assign emails
         */
        $email1 = new Common\Email();
        $email1
            ->setAddress($currentResult['StudentEmail']);

        $email2 = new Common\Email();
        $email2
            ->setAddress($currentResult['StudentOccupEmail']);

        foreach (array($email1, $email2) as $currentEmail) {
            /** @var Common\Email $currentEmail */
            if (strlen($currentEmail->getAddress())) {
                $student->addEmail($currentEmail);
            }
        }

        if ($currentResult['StudentBoarder'] == 'Y') {
            $boardingHouse = new Common\BoardingHouse();
            $boardingHouse
                ->setName($currentResult['StudentBoardingHouse'])
            ;

            $student
                ->setBoardingHouse($boardingHouse)
            ;
        }

        /* Campus
         */
        $campus = new Common\Campus();
        $campus
            ->setName($currentResult['StudentCampus'])
        ;

        $student
            ->getCampus()
            ->add($campus)
        ;
        return $student;
    }

    /**
     * Seeks to a position
     *
     * @var int $position
     */
    public function seek($position)
    {
        $this->getInnerIterator()->seek($position);
    }
}
