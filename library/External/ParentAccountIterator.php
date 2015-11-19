<?php
/**
 * Iterator over a `External_UserInterface->getParentUserAccountIterator()` -
 * maps each result to a `Schoolbox\External\Normalise\User\Guardian` object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\Normalise\User\Common;
use Schoolbox\External\Normalise\User\Guardian;
use Schoolbox\External\Normalise\User\Student;

/**
 * @method SeekableIterator getInnerIterator()
 */
class External_ParentAccountIterator extends IteratorIterator implements SeekableIterator
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
     * @return array
     */
    public function current()
    {
        // Build a normalized object out of the current item
        // (copypasted from Adapter->getParentUserAccountInfo)
        $currentResult = $this->getInnerIterator()->current();
        /* Year (common to all Guardians)
         */
        $student = new Student();
        $student
            ->getYear()
            ->setLevel($currentResult['StudentYearLevel'])
        ;

        /* Guardian 1
         */
        $guardian1 = new Guardian();
        $guardian1
            ->setId($currentResult['StudentContactID'])
            ->setTitle($currentResult['StudentContactTitle'])
            ->setPreferredName($currentResult['StudentContactPreferred'])
            ->setLastname($currentResult['StudentContactSurname'])
            ->setGender($currentResult['StudentGender'])
        ;

        /* Email
         */
        $email = new Common\Email();
        $email
            ->setAddress($currentResult['StudentContactDefaultEmail'])
        ;

        $guardian1
            ->addEmail($email);

        /* Account
         */
        $guardian1
            ->getAccount()
            ->setLogin($currentResult['StudentContactNetworkLogin'])
        ;

        /* Campus
         */
        $campus = new Common\Campus();
        $campus
            ->setName($currentResult['StudentCampus'])
        ;

        $guardian1
            ->getCampus()
            ->add($campus)
        ;

        /* Guardian 2
         */
        $guardian2 = new Guardian();
        $guardian2
            ->setId($currentResult['StudentContactSpouseID'])
            ->setTitle($currentResult['StudentContactSpouseTitle'])
            ->setPreferredName($currentResult['StudentContactSpousePreferred'])
            ->setLastname($currentResult['StudentContactSpouseSurname'])
        ;

        /* Email
         */
        $email = new Common\Email();
        $email
            ->setAddress($currentResult['StudentContactSpouseDefaultEmail'])
        ;

        $guardian2
            ->addEmail($email);

        /* Account
         */
        $guardian2
            ->getAccount()
            ->setLogin($currentResult['StudentContactSpouseNetworkLogin'])
        ;

        /* Campus
         */
        $campus = new Common\Campus();
        $campus
            ->setName($currentResult['StudentCampus'])
        ;

        $guardian2
            ->getCampus()
            ->add($campus)
        ;

        /* Stack it up
         */
        foreach (array($guardian1, $guardian2) as $currentGuardian) {
            /** @var Guardian $currentGuardian */
            if (strlen($currentGuardian->getPreferredName())) {

                /* Add guardian to the registry
                 */
                if (!isset($allGuardians[$currentGuardian->getId()])) {
                    $allGuardians[$currentGuardian->getId()] = $currentGuardian;
                }
                elseif (!($allGuardians[$currentGuardian->getId()] instanceof Guardian)) {
                    $allGuardians[$currentGuardian->getId()] = $currentGuardian;
                }
            }
        }
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
